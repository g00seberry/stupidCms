<?php

declare(strict_types=1);

namespace App\Support\TermHierarchy;

use App\Models\Term;
use App\Models\TermTree;

/**
 * Сервис для управления иерархией терминов через Closure Table.
 */
class TermHierarchyService
{
    /**
     * Установить родителя для термина.
     * Обновляет term_tree согласно Closure Table паттерну.
     * Корректно обрабатывает перенос терма с потомками, сохраняя связи с потомками.
     *
     * @param \App\Models\Term $term Терм, для которого устанавливается родитель
     * @param int|null $parentId ID нового родителя (null для удаления родителя)
     * @return void
     * @throws \InvalidArgumentException Если родитель не принадлежит той же таксономии или создаст цикл
     */
    public function setParent(Term $term, ?int $parentId): void
    {
        if ($parentId === null) {
            // Удаляем старые связи и добавляем только само-ссылку
            $this->removeFromTree($term);
            $this->addSelfReference($term);
            return;
        }

        $parent = Term::find($parentId);
        if (!$parent || $parent->taxonomy_id !== $term->taxonomy_id) {
            throw new \InvalidArgumentException('Parent term must belong to the same taxonomy');
        }

        // Проверка на циклическую зависимость (ПЕРЕД удалением старых связей!)
        if ($this->wouldCreateCycle($term, $parent)) {
            throw new \InvalidArgumentException('Cannot set parent: would create a cycle');
        }

        // Сохраняем связи с потомками (где term является предком)
        // Это нужно для корректного переноса поддерева
        $descendantLinks = TermTree::where('ancestor_id', $term->id)
            ->where('descendant_id', '!=', $term->id) // исключаем само-ссылку
            ->get()
            ->map(fn ($link) => [
                'descendant_id' => $link->descendant_id,
                'depth' => $link->depth,
            ])
            ->toArray();

        // Получаем список всех потомков (включая вложенных)
        $allDescendantIds = TermTree::where('ancestor_id', $term->id)
            ->where('descendant_id', '!=', $term->id)
            ->pluck('descendant_id')
            ->toArray();

        // Получаем список старых предков term (до удаления)
        $oldAncestorIds = TermTree::where('descendant_id', $term->id)
            ->where('ancestor_id', '!=', $term->id)
            ->pluck('ancestor_id')
            ->toArray();

        // Удаляем связи, где term является потомком (связи со старыми предками)
        TermTree::where('descendant_id', $term->id)
            ->where('ancestor_id', '!=', $term->id)
            ->delete();

        // Удаляем связи потомков со старыми предками term
        // Это необходимо, так как потомки больше не связаны со старыми предками через term
        if (!empty($allDescendantIds) && !empty($oldAncestorIds)) {
            TermTree::whereIn('ancestor_id', $oldAncestorIds)
                ->whereIn('descendant_id', $allDescendantIds)
                ->delete();
        }

        // Добавляем связи согласно Closure Table:
        // 1. Само-ссылка (ancestor = descendant = term, depth = 0)
        $this->addSelfReference($term);

        // 2. Связь с родителем (ancestor = parent, descendant = term, depth = 1)
        TermTree::create([
            'ancestor_id' => $parent->id,
            'descendant_id' => $term->id,
            'depth' => 1,
        ]);

        // 3. Все предки родителя становятся предками термина
        // Для каждого предка parent (ancestor) с depth = d:
        // добавляем (ancestor, term) с depth = d + 1
        $parentAncestors = TermTree::where('descendant_id', $parent->id)
            ->where('ancestor_id', '!=', $parent->id) // исключаем само-ссылку
            ->get();

        foreach ($parentAncestors as $ancestor) {
            TermTree::create([
                'ancestor_id' => $ancestor->ancestor_id,
                'descendant_id' => $term->id,
                'depth' => $ancestor->depth + 1,
            ]);
        }

        // 4. Восстанавливаем и обновляем связи с потомками
        // Для каждого потомка нужно:
        // - Восстановить связь с термом (ancestor = term, descendant = потомок)
        // - Добавить связи с новыми предками терма (предки нового родителя)
        foreach ($descendantLinks as $link) {
            $descendantId = $link['descendant_id'];
            $originalDepth = $link['depth'];

            // Восстанавливаем связь терма с потомком (используем firstOrCreate, так как связь могла быть удалена)
            TermTree::firstOrCreate(
                [
                    'ancestor_id' => $term->id,
                    'descendant_id' => $descendantId,
                ],
                [
                    'depth' => $originalDepth,
                ]
            );

            // Добавляем связи потомка с предками нового родителя
            // Для каждого предка нового родителя (ancestor) с depth = d:
            // добавляем (ancestor, потомок) с depth = d + 1 + originalDepth
            foreach ($parentAncestors as $ancestor) {
                TermTree::firstOrCreate(
                    [
                        'ancestor_id' => $ancestor->ancestor_id,
                        'descendant_id' => $descendantId,
                    ],
                    [
                        'depth' => $ancestor->depth + 1 + $originalDepth,
                    ]
                );
            }

            // Добавляем связь потомка с новым родителем терма
            TermTree::firstOrCreate(
                [
                    'ancestor_id' => $parent->id,
                    'descendant_id' => $descendantId,
                ],
                [
                    'depth' => 1 + $originalDepth,
                ]
            );
        }
    }

    /**
     * Удалить термин из дерева (удалить все связи).
     */
    public function removeFromTree(Term $term): void
    {
        TermTree::where('ancestor_id', $term->id)
            ->orWhere('descendant_id', $term->id)
            ->delete();
    }

    /**
     * Добавить само-ссылку (ancestor = descendant = term, depth = 0).
     */
    private function addSelfReference(Term $term): void
    {
        TermTree::firstOrCreate(
            [
                'ancestor_id' => $term->id,
                'descendant_id' => $term->id,
            ],
            [
                'depth' => 0,
            ]
        );
    }

    /**
     * Проверка на циклическую зависимость.
     * Нельзя сделать родителем потомка этого термина.
     */
    private function wouldCreateCycle(Term $term, Term $parent): bool
    {
        // Если parent является потомком term, то будет цикл
        return TermTree::where('ancestor_id', $term->id)
            ->where('descendant_id', $parent->id)
            ->where('depth', '>', 0)
            ->exists();
    }
}


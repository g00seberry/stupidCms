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

        // Удаляем старые связи
        $this->removeFromTree($term);

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


<?php

declare(strict_types=1);

namespace App\Domain\Search;

/**
 * Результат поиска (одна найденная запись).
 *
 * Представляет одну найденную запись с информацией о релевантности
 * и подсветкой совпадений в тексте.
 *
 * @package App\Domain\Search
 * @psalm-type HighlightMap = array<string, list<string>>
 */
final class SearchHit
{
    /**
     * @param string $id ID записи
     * @param string $postType Slug типа записи
     * @param string $slug Slug записи
     * @param string $title Заголовок записи
     * @param string|null $excerpt Краткое описание записи
     * @param float|null $score Релевантность (score от поискового движка)
     * @param array<string, list<string>> $highlight Подсветка совпадений по полям (title, excerpt, body_plain)
     */
    public function __construct(
        public readonly string $id,
        public readonly string $postType,
        public readonly string $slug,
        public readonly string $title,
        public readonly ?string $excerpt,
        public readonly ?float $score,
        public readonly array $highlight
    ) {
    }
}



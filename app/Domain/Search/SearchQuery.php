<?php

declare(strict_types=1);

namespace App\Domain\Search;

use App\Domain\Search\ValueObjects\SearchTermFilter;
use Carbon\CarbonImmutable;

/**
 * Value Object для поискового запроса.
 *
 * Инкапсулирует параметры поиска: текст запроса, фильтры по типам записей,
 * термам, датам, пагинацию.
 *
 * @package App\Domain\Search
 */
final class SearchQuery
{
    /**
     * @param string|null $query Текст поискового запроса
     * @param list<int|string> $postTypes Список ID типов записей для фильтрации (принимает как int, так и string для обратной совместимости)
     * @param list<\App\Domain\Search\ValueObjects\SearchTermFilter> $terms Список фильтров по термам
     * @param \Carbon\CarbonImmutable|null $from Начальная дата для фильтрации по дате публикации
     * @param \Carbon\CarbonImmutable|null $to Конечная дата для фильтрации по дате публикации
     * @param int $page Номер страницы (начиная с 1)
     * @param int $perPage Количество результатов на странице
     */
    public function __construct(
        private readonly ?string $query,
        private readonly array $postTypes,
        private readonly array $terms,
        private readonly ?CarbonImmutable $from,
        private readonly ?CarbonImmutable $to,
        private readonly int $page,
        private readonly int $perPage
    ) {
    }

    /**
     * Получить текст поискового запроса.
     *
     * @return string|null Текст запроса или null
     */
    public function query(): ?string
    {
        return $this->query;
    }

    /**
     * Получить список типов записей для фильтрации.
     *
     * @return list<int|string> Список ID типов записей (может содержать строки для обратной совместимости)
     */
    public function postTypes(): array
    {
        return $this->postTypes;
    }

    /**
     * Получить список фильтров по термам.
     *
     * @return list<\App\Domain\Search\ValueObjects\SearchTermFilter> Список фильтров термов
     */
    public function terms(): array
    {
        return $this->terms;
    }

    /**
     * Получить начальную дату для фильтрации.
     *
     * @return \Carbon\CarbonImmutable|null Начальная дата или null
     */
    public function from(): ?CarbonImmutable
    {
        return $this->from;
    }

    /**
     * Получить конечную дату для фильтрации.
     *
     * @return \Carbon\CarbonImmutable|null Конечная дата или null
     */
    public function to(): ?CarbonImmutable
    {
        return $this->to;
    }

    /**
     * Получить номер страницы.
     *
     * @return int Номер страницы (начиная с 1)
     */
    public function page(): int
    {
        return $this->page;
    }

    /**
     * Получить количество результатов на странице.
     *
     * @return int Количество результатов на странице
     */
    public function perPage(): int
    {
        return $this->perPage;
    }

    /**
     * Вычислить offset для пагинации.
     *
     * @return int Смещение для запроса (from в Elasticsearch)
     */
    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }

    /**
     * Проверить, является ли запрос пустым.
     *
     * @return bool true, если запрос пустой или null
     */
    public function isBlank(): bool
    {
        return $this->query === null || trim($this->query) === '';
    }
}



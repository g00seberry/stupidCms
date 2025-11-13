<?php

declare(strict_types=1);

namespace App\Domain\Search;

/**
 * Результаты поискового запроса.
 *
 * Инкапсулирует результаты поиска: список найденных записей, общее количество,
 * информацию о пагинации и время выполнения запроса.
 *
 * @package App\Domain\Search
 * @psalm-type HitList = list<SearchHit>
 */
final class SearchResult
{
    /**
     * @param list<\App\Domain\Search\SearchHit> $hits Список найденных записей
     * @param int $total Общее количество найденных записей
     * @param int $page Номер текущей страницы
     * @param int $perPage Количество результатов на странице
     * @param int $tookMs Время выполнения запроса в миллисекундах
     */
    public function __construct(
        private readonly array $hits,
        private readonly int $total,
        private readonly int $page,
        private readonly int $perPage,
        private readonly int $tookMs
    ) {
    }

    /**
     * Получить список найденных записей.
     *
     * @return list<\App\Domain\Search\SearchHit> Список результатов поиска
     */
    public function hits(): array
    {
        return $this->hits;
    }

    /**
     * Получить общее количество найденных записей.
     *
     * @return int Общее количество результатов
     */
    public function total(): int
    {
        return $this->total;
    }

    /**
     * Получить номер текущей страницы.
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
     * Получить время выполнения запроса.
     *
     * @return int Время выполнения в миллисекундах
     */
    public function tookMs(): int
    {
        return $this->tookMs;
    }

    /**
     * Создать пустой результат поиска.
     *
     * Используется когда поиск отключен или запрос пустой.
     *
     * @param int $page Номер страницы
     * @param int $perPage Количество результатов на странице
     * @return self Пустой результат поиска
     */
    public static function empty(int $page, int $perPage): self
    {
        return new self([], 0, $page, $perPage, 0);
    }
}



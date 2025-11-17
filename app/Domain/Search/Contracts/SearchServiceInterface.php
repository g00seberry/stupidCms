<?php

declare(strict_types=1);

namespace App\Domain\Search\Contracts;

use App\Domain\Search\SearchQuery;
use App\Domain\Search\SearchResult;

/**
 * Контракт для выполнения поисковых запросов.
 *
 * @package App\Domain\Search\Contracts
 */
interface SearchServiceInterface
{
    /**
     * Выполнить поисковый запрос.
     *
     * Если поиск отключен или запрос пустой, возвращает пустой результат.
     * При ошибке поискового движка выбрасывает HttpErrorException.
     *
     * @param \App\Domain\Search\SearchQuery $query Поисковый запрос
     * @return \App\Domain\Search\SearchResult Результаты поиска
     * @throws \App\Support\Errors\HttpErrorException Если поисковый движок недоступен
     */
    public function search(SearchQuery $query): SearchResult;
}


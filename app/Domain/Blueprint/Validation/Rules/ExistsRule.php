<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Validation\Rules;

/**
 * Доменное правило валидации: существование значения.
 *
 * Проверяет, что значение поля существует в указанной таблице/колонке.
 * Применяется к полям типа ref (ссылки на другие сущности).
 *
 * @package App\Domain\Blueprint\Validation\Rules
 */
final class ExistsRule implements Rule
{
    /**
     * @param string $table Таблица для проверки существования
     * @param string $column Колонка для проверки (по умолчанию 'id')
     * @param string|null $whereColumn Дополнительная колонка для WHERE условия
     * @param mixed $whereValue Значение для WHERE условия
     */
    public function __construct(
        private readonly string $table,
        private readonly string $column = 'id',
        private readonly ?string $whereColumn = null,
        private readonly mixed $whereValue = null
    ) {}

    /**
     * @inheritDoc
     */
    public function getType(): string
    {
        return 'exists';
    }

    /**
     * @inheritDoc
     */
    public function getParams(): array
    {
        $params = [
            'table' => $this->table,
            'column' => $this->column,
        ];

        if ($this->whereColumn !== null) {
            $params['where_column'] = $this->whereColumn;
            $params['where_value'] = $this->whereValue;
        }

        return $params;
    }

    /**
     * Получить таблицу для проверки.
     *
     * @return string
     */
    public function getTable(): string
    {
        return $this->table;
    }

    /**
     * Получить колонку для проверки.
     *
     * @return string
     */
    public function getColumn(): string
    {
        return $this->column;
    }

    /**
     * Получить колонку для WHERE условия.
     *
     * @return string|null
     */
    public function getWhereColumn(): ?string
    {
        return $this->whereColumn;
    }

    /**
     * Получить значение для WHERE условия.
     *
     * @return mixed
     */
    public function getWhereValue(): mixed
    {
        return $this->whereValue;
    }
}


<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Validation\Rules;

/**
 * Доменное правило валидации: уникальность значения.
 *
 * Проверяет, что значение поля уникально в указанной таблице/колонке.
 * Применяется к полям типа ref (ссылки на другие сущности) или string (для проверки уникальности в других таблицах).
 *
 * @package App\Domain\Blueprint\Validation\Rules
 */
final class UniqueRule implements Rule
{
    /**
     * @param string $table Таблица для проверки уникальности
     * @param string $column Колонка для проверки (по умолчанию 'id')
     * @param string|null $exceptColumn Колонка для исключения (например, при обновлении)
     * @param mixed $exceptValue Значение для исключения
     * @param string|null $whereColumn Дополнительная колонка для WHERE условия
     * @param mixed $whereValue Значение для WHERE условия
     */
    public function __construct(
        private readonly string $table,
        private readonly string $column = 'id',
        private readonly ?string $exceptColumn = null,
        private readonly mixed $exceptValue = null,
        private readonly ?string $whereColumn = null,
        private readonly mixed $whereValue = null
    ) {}

    /**
     * @inheritDoc
     */
    public function getType(): string
    {
        return 'unique';
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

        if ($this->exceptColumn !== null) {
            $params['except_column'] = $this->exceptColumn;
            $params['except_value'] = $this->exceptValue;
        }

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
     * Получить колонку для исключения.
     *
     * @return string|null
     */
    public function getExceptColumn(): ?string
    {
        return $this->exceptColumn;
    }

    /**
     * Получить значение для исключения.
     *
     * @return mixed
     */
    public function getExceptValue(): mixed
    {
        return $this->exceptValue;
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


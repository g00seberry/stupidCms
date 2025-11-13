<?php

declare(strict_types=1);

namespace App\Documentation\ValueObjects;

final readonly class ModelMeta
{
    /**
     * @param array<string> $fillable
     * @param array<string> $guarded
     * @param array<string, string> $casts
     * @param array<string, array<string, mixed>> $relations
     */
    public function __construct(
        public string $table,
        public array $fillable = [],
        public array $guarded = [],
        public array $casts = [],
        public array $relations = [],
        public ?string $factory = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'table' => $this->table,
            'fillable' => $this->fillable,
            'guarded' => $this->guarded,
            'casts' => $this->casts,
            'relations' => $this->relations,
            'factory' => $this->factory,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            table: $data['table'],
            fillable: $data['fillable'] ?? [],
            guarded: $data['guarded'] ?? [],
            casts: $data['casts'] ?? [],
            relations: $data['relations'] ?? [],
            factory: $data['factory'] ?? null,
        );
    }
}


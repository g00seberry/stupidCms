<?php

declare(strict_types=1);

namespace App\Documentation;

final readonly class DocEntity
{
    public function __construct(
        public string $id,
        public string $type,
        public string $name,
        public string $path,
        public string $summary,
        public ?string $details = null,
        public array $meta = [],
        public array $related = [],
        public array $tags = [],
    ) {
        $this->validate();
    }

    private function validate(): void
    {
        if (empty($this->id)) {
            throw new \InvalidArgumentException('DocEntity: id is required');
        }
        if (empty($this->type)) {
            throw new \InvalidArgumentException('DocEntity: type is required');
        }
        if (empty($this->name)) {
            throw new \InvalidArgumentException('DocEntity: name is required');
        }
        if (empty($this->path)) {
            throw new \InvalidArgumentException('DocEntity: path is required');
        }
        if (empty($this->summary)) {
            throw new \InvalidArgumentException('DocEntity: summary is required');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'name' => $this->name,
            'path' => $this->path,
            'summary' => $this->summary,
            'details' => $this->details,
            'meta' => $this->meta,
            'related' => $this->related,
            'tags' => $this->tags,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            type: $data['type'],
            name: $data['name'],
            path: $data['path'],
            summary: $data['summary'],
            details: $data['details'] ?? null,
            meta: $data['meta'] ?? [],
            related: $data['related'] ?? [],
            tags: $data['tags'] ?? [],
        );
    }
}


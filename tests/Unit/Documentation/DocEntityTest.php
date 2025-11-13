<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use App\Documentation\DocEntity;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class DocEntityTest extends TestCase
{
    public function test_creates_doc_entity_with_required_fields(): void
    {
        $entity = new DocEntity(
            id: 'model:App\\Models\\Entry',
            type: 'model',
            name: 'Entry',
            path: 'app/Models/Entry.php',
            summary: 'Entry model',
        );

        $this->assertSame('model:App\\Models\\Entry', $entity->id);
        $this->assertSame('model', $entity->type);
        $this->assertSame('Entry', $entity->name);
        $this->assertSame('Entry model', $entity->summary);
        $this->assertNull($entity->details);
        $this->assertSame([], $entity->meta);
        $this->assertSame([], $entity->related);
        $this->assertSame([], $entity->tags);
    }

    public function test_creates_doc_entity_with_all_fields(): void
    {
        $entity = new DocEntity(
            id: 'model:App\\Models\\Entry',
            type: 'model',
            name: 'Entry',
            path: 'app/Models/Entry.php',
            summary: 'Entry model',
            details: 'Detailed description',
            meta: ['table' => 'entries'],
            related: ['domain_service:Entries/PublishingService'],
            tags: ['entry', 'content'],
        );

        $this->assertSame('Detailed description', $entity->details);
        $this->assertSame(['table' => 'entries'], $entity->meta);
        $this->assertSame(['domain_service:Entries/PublishingService'], $entity->related);
        $this->assertSame(['entry', 'content'], $entity->tags);
    }

    public function test_throws_exception_when_id_is_empty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('DocEntity: id is required');

        new DocEntity(
            id: '',
            type: 'model',
            name: 'Entry',
            path: 'app/Models/Entry.php',
            summary: 'Entry model',
        );
    }

    public function test_throws_exception_when_type_is_empty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('DocEntity: type is required');

        new DocEntity(
            id: 'model:App\\Models\\Entry',
            type: '',
            name: 'Entry',
            path: 'app/Models/Entry.php',
            summary: 'Entry model',
        );
    }

    public function test_throws_exception_when_name_is_empty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('DocEntity: name is required');

        new DocEntity(
            id: 'model:App\\Models\\Entry',
            type: 'model',
            name: '',
            path: 'app/Models/Entry.php',
            summary: 'Entry model',
        );
    }

    public function test_throws_exception_when_path_is_empty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('DocEntity: path is required');

        new DocEntity(
            id: 'model:App\\Models\\Entry',
            type: 'model',
            name: 'Entry',
            path: '',
            summary: 'Entry model',
        );
    }

    public function test_throws_exception_when_summary_is_empty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('DocEntity: summary is required');

        new DocEntity(
            id: 'model:App\\Models\\Entry',
            type: 'model',
            name: 'Entry',
            path: 'app/Models/Entry.php',
            summary: '',
        );
    }

    public function test_converts_doc_entity_to_array(): void
    {
        $entity = new DocEntity(
            id: 'model:App\\Models\\Entry',
            type: 'model',
            name: 'Entry',
            path: 'app/Models/Entry.php',
            summary: 'Entry model',
            details: 'Details',
            meta: ['table' => 'entries'],
            related: ['related-id'],
            tags: ['tag1'],
        );

        $array = $entity->toArray();

        $this->assertSame([
            'id' => 'model:App\\Models\\Entry',
            'type' => 'model',
            'name' => 'Entry',
            'path' => 'app/Models/Entry.php',
            'summary' => 'Entry model',
            'details' => 'Details',
            'meta' => ['table' => 'entries'],
            'related' => ['related-id'],
            'tags' => ['tag1'],
        ], $array);
    }

    public function test_creates_doc_entity_from_array(): void
    {
        $data = [
            'id' => 'model:App\\Models\\Entry',
            'type' => 'model',
            'name' => 'Entry',
            'path' => 'app/Models/Entry.php',
            'summary' => 'Entry model',
            'details' => 'Details',
            'meta' => ['table' => 'entries'],
            'related' => ['related-id'],
            'tags' => ['tag1'],
        ];

        $entity = DocEntity::fromArray($data);

        $this->assertSame('model:App\\Models\\Entry', $entity->id);
        $this->assertSame('model', $entity->type);
        $this->assertSame('Entry', $entity->name);
        $this->assertSame('Entry model', $entity->summary);
        $this->assertSame('Details', $entity->details);
        $this->assertSame(['table' => 'entries'], $entity->meta);
        $this->assertSame(['related-id'], $entity->related);
        $this->assertSame(['tag1'], $entity->tags);
    }

    public function test_creates_doc_entity_from_array_with_optional_fields_missing(): void
    {
        $data = [
            'id' => 'model:App\\Models\\Entry',
            'type' => 'model',
            'name' => 'Entry',
            'path' => 'app/Models/Entry.php',
            'summary' => 'Entry model',
        ];

        $entity = DocEntity::fromArray($data);

        $this->assertNull($entity->details);
        $this->assertSame([], $entity->meta);
        $this->assertSame([], $entity->related);
        $this->assertSame([], $entity->tags);
    }
}


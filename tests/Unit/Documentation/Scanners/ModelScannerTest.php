<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation\Scanners;

use App\Documentation\Scanners\ModelScanner;
use App\Models\Entry;
use Tests\TestCase;

class ModelScannerTest extends TestCase
{
    private ModelScanner $scanner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scanner = new ModelScanner();
    }

    public function test_scans_models_successfully(): void
    {
        $entities = $this->scanner->scan();

        $this->assertNotEmpty($entities, 'Should find at least one model');

        // Проверяем, что Entry модель найдена
        $entryEntity = null;
        foreach ($entities as $entity) {
            if ($entity->name === 'Entry') {
                $entryEntity = $entity;
                break;
            }
        }

        $this->assertNotNull($entryEntity, 'Entry model should be found');
        $this->assertSame('model', $entryEntity->type);
        $this->assertStringContainsString('app/Models/Entry.php', $entryEntity->path);
        $this->assertNotEmpty($entryEntity->summary);
    }

    public function test_extracts_model_meta(): void
    {
        $entities = $this->scanner->scan();

        $entryEntity = null;
        foreach ($entities as $entity) {
            if ($entity->name === 'Entry') {
                $entryEntity = $entity;
                break;
            }
        }

        $this->assertNotNull($entryEntity);
        $this->assertArrayHasKey('table', $entryEntity->meta);
        $this->assertArrayHasKey('relations', $entryEntity->meta);
        $this->assertIsArray($entryEntity->meta['relations']);
    }

    public function test_extracts_relations(): void
    {
        $entities = $this->scanner->scan();

        $entryEntity = null;
        foreach ($entities as $entity) {
            if ($entity->name === 'Entry') {
                $entryEntity = $entity;
                break;
            }
        }

        $this->assertNotNull($entryEntity);
        $relations = $entryEntity->meta['relations'];

        // Entry должен иметь relations
        $this->assertNotEmpty($relations, 'Entry should have relations');

        // Проверяем наличие известных relations
        $relationNames = array_keys($relations);
        $this->assertContains('postType', $relationNames, 'Entry should have postType relation');
    }

    public function test_generates_correct_id(): void
    {
        $entities = $this->scanner->scan();

        $entryEntity = null;
        foreach ($entities as $entity) {
            if ($entity->name === 'Entry') {
                $entryEntity = $entity;
                break;
            }
        }

        $this->assertNotNull($entryEntity);
        $this->assertSame('model:App\\Models\\Entry', $entryEntity->id);
    }

    public function test_extracts_tags(): void
    {
        $entities = $this->scanner->scan();

        $entryEntity = null;
        foreach ($entities as $entity) {
            if ($entity->name === 'Entry') {
                $entryEntity = $entity;
                break;
            }
        }

        $this->assertNotNull($entryEntity);
        $this->assertIsArray($entryEntity->tags);
        $this->assertContains('entry', $entryEntity->tags);
    }
}


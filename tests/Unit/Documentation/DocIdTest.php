<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use App\Documentation\DocId;
use PHPUnit\Framework\TestCase;

class DocIdTest extends TestCase
{
    public function test_generates_model_id_correctly(): void
    {
        $this->assertSame('model:App\\Models\\Entry', DocId::forModel('App\\Models\\Entry'));
    }

    public function test_generates_domain_service_id_correctly(): void
    {
        $this->assertSame('domain_service:Entries/PublishingService', DocId::forDomainService('App\\Domain\\Entries\\PublishingService'));
        $this->assertSame('domain_service:Entries/PublishingService', DocId::forDomainService('Entries\\PublishingService'));
    }

    public function test_generates_blade_view_id_correctly(): void
    {
        $this->assertSame('blade_view:entry.blade.php', DocId::forBladeView('resources/views/entry.blade.php'));
        $this->assertSame('blade_view:entry.blade.php', DocId::forBladeView('entry.blade.php'));
        $this->assertSame('blade_view:pages/show.blade.php', DocId::forBladeView('resources/views/pages/show.blade.php'));
    }

    public function test_generates_config_area_id_correctly(): void
    {
        $this->assertSame('config_area:stupidcms', DocId::forConfigArea('config/stupidcms.php'));
        $this->assertSame('config_area:stupidcms', DocId::forConfigArea('stupidcms.php'));
    }

    public function test_generates_concept_id_correctly(): void
    {
        $this->assertSame('concept:postType:post', DocId::forConcept('postType', 'post'));
        $this->assertSame('concept:domain:Entries', DocId::forConcept('domain', 'Entries'));
    }

    public function test_generates_http_endpoint_id_correctly(): void
    {
        $this->assertSame('http_endpoint:GET:/api/entries/{id}', DocId::forHttpEndpoint('GET', '/api/entries/{id}'));
        $this->assertSame('http_endpoint:POST:/api/admin/entries', DocId::forHttpEndpoint('POST', '/api/admin/entries'));
    }

    public function test_parses_valid_id_correctly(): void
    {
        $parsed = DocId::parse('model:App\\Models\\Entry');
        $this->assertSame([
            'type' => 'model',
            'value' => 'App\\Models\\Entry',
        ], $parsed);

        $parsed = DocId::parse('domain_service:Entries/PublishingService');
        $this->assertSame([
            'type' => 'domain_service',
            'value' => 'Entries/PublishingService',
        ], $parsed);
    }

    public function test_returns_null_for_invalid_id_format(): void
    {
        $this->assertNull(DocId::parse('invalid'));
        $this->assertNull(DocId::parse(''));
    }
}


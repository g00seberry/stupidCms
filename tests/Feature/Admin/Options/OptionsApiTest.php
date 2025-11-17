<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Options;

use App\Models\Option;
use App\Models\User;
use App\Support\Errors\ErrorCode;
use Illuminate\Support\Str;
use Tests\Support\FeatureTestCase;

class OptionsApiTest extends FeatureTestCase
{

    public function test_it_upserts_option_value_object_and_reads_back_roundtrip(): void
    {
        $admin = $this->adminWithPermissions(['options.read', 'options.write']);

        $ulid = Str::ulid()->toBase32();
        $payload = [
            'value' => $ulid,
            'description' => 'Homepage entry reference',
        ];

        $createResponse = $this->putJsonAsAdmin(
            '/api/v1/admin/options/site/home_entry_id',
            $payload,
            $admin
        );

        $createResponse->assertStatus(201);
        $createResponse->assertHeader('Cache-Control', 'no-store, private');
        $createResponse->assertHeader('Vary', 'Cookie');
        $this->assertSame($ulid, $createResponse->json('data.value'));
        $this->assertSame('Homepage entry reference', $createResponse->json('data.description'));

        $this->assertDatabaseHas('options', [
            'namespace' => 'site',
            'key' => 'home_entry_id',
            'description' => 'Homepage entry reference',
        ]);

        $readResponse = $this->getJsonAsAdmin(
            '/api/v1/admin/options/site/home_entry_id',
            $admin
        );

        $readResponse->assertOk();
        $readResponse->assertHeader('Cache-Control', 'no-store, private');
        $readResponse->assertHeader('Vary', 'Cookie');
        $this->assertSame($ulid, $readResponse->json('data.value'));

        $option = Option::query()
            ->where('namespace', 'site')
            ->where('key', 'home_entry_id')
            ->firstOrFail();

        $this->assertSame($ulid, $option->value_json);
    }

    public function test_it_returns_200_on_subsequent_updates(): void
    {
        $admin = $this->adminWithPermissions(['options.read', 'options.write']);

        Option::factory()->create([
            'namespace' => 'site',
            'key' => 'title',
            'value_json' => 'My Site',
        ]);

        $response = $this->putJsonAsAdmin(
            '/api/v1/admin/options/site/title',
            ['value' => 'New Site Title'],
            $admin
        );

        $response->assertOk();
        $this->assertSame('New Site Title', $response->json('data.value'));
    }

    public function test_it_validates_json_value_size_limit(): void
    {
        $admin = $this->adminWithPermissions(['options.read', 'options.write']);

        $tooLarge = str_repeat('A', 70_000);

        $response = $this->putJsonAsAdmin(
            '/api/v1/admin/options/site/hero_html',
            ['value' => $tooLarge],
            $admin
        );

        $response->assertStatus(422);
        $this->assertErrorResponse($response, ErrorCode::INVALID_JSON_VALUE);
        $this->assertValidationErrors($response, ['value']);
    }

    public function test_it_lists_namespace_with_filters_and_pagination(): void
    {
        $admin = $this->adminWithPermissions(['options.read']);

        Option::factory()->create([
            'namespace' => 'site',
            'key' => 'title',
            'value_json' => 'Site Title',
            'description' => 'Primary site title',
        ]);

        Option::factory()->create([
            'namespace' => 'site',
            'key' => 'tagline',
            'value_json' => 'Tagline',
            'description' => 'Primary tagline',
        ]);

        Option::factory()->create([
            'namespace' => 'site',
            'key' => 'deprecated',
            'value_json' => 'Deprecated',
        ])->delete();

        $response = $this->getJsonAsAdmin(
            '/api/v1/admin/options/site?q=Primary&deleted=with&per_page=1&page=1',
            $admin
        );

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame('tagline', $response->json('data.0.key'));
        $response->assertJsonPath('meta.current_page', 1);
        $response->assertJsonPath('meta.from', 1);
        $response->assertJsonPath('meta.to', 1);
        $response->assertJsonPath('meta.last_page', 2);
        $response->assertJsonPath('meta.per_page', 1);
        $response->assertJsonPath('meta.total', 2);
    }

    public function test_it_soft_deletes_and_restores_option(): void
    {
        $admin = $this->adminWithPermissions(['options.read', 'options.delete', 'options.restore']);

        $option = Option::factory()->create([
            'namespace' => 'site',
            'key' => 'subtitle',
            'value_json' => 'Initial subtitle',
        ]);

        $deleteResponse = $this->deleteJsonAsAdmin(
            '/api/v1/admin/options/site/subtitle',
            [],
            $admin
        );

        $deleteResponse->assertNoContent();
        $this->assertSoftDeleted('options', ['id' => $option->id]);

        $showResponse = $this->getJsonAsAdmin(
            '/api/v1/admin/options/site/subtitle',
            $admin
        );
        $showResponse->assertStatus(404);

        $restoreResponse = $this->postJsonAsAdmin(
            '/api/v1/admin/options/site/subtitle/restore',
            [],
            $admin
        );

        $restoreResponse->assertOk();
        $this->assertNull(Option::query()->find($option->id)?->deleted_at);
    }

    public function test_it_rejects_malformed_namespace_or_key(): void
    {
        $admin = $this->adminWithPermissions(['options.read']);

        $response = $this->getJsonAsAdmin(
            '/api/v1/admin/options/INVALID_NS',
            $admin
        );

        $response->assertStatus(422);
        $this->assertSame('INVALID_OPTION_IDENTIFIER', $response->json('code'));
    }

    public function test_show_missing_option_renders_problem_exception(): void
    {
        $admin = $this->adminWithPermissions(['options.read']);

        $response = $this->getJsonAsAdmin(
            '/api/v1/admin/options/site/missing',
            $admin
        );

        $response->assertStatus(404);
        $response->assertHeader('Content-Type', 'application/problem+json');
        $this->assertErrorResponse($response, ErrorCode::NOT_FOUND, [
            'detail' => 'Option "site/missing" was not found.',
            'meta.namespace' => 'site',
            'meta.key' => 'missing',
        ]);
    }

    public function test_it_observes_permissions(): void
    {
        $user = $this->adminWithPermissions(['options.read']);

        $response = $this->putJsonAsAdmin(
            '/api/v1/admin/options/site/feature_flag_new_ui',
            ['value' => true],
            $user
        );

        $response->assertStatus(403);
    }

    private function typeUri(ErrorCode $code): string
    {
        return config('errors.types.' . $code->value . '.uri');
    }
}


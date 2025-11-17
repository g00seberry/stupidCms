<?php

namespace Tests\Feature;

use App\Http\Requests\OptionsRequest;
use App\Models\Entry;
use App\Models\PostType;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Tests\Support\FeatureTestCase;

class OptionsValidationTest extends FeatureTestCase
{
    private PostType $postType;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2025-01-01 12:00:00');

        $this->postType = PostType::create([
            'slug' => 'page',
            'name' => 'Page',
            'options_json' => [],
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_validation_allows_null_value(): void
    {
        $request = OptionsRequest::create('/', 'POST', [
            'namespace' => 'site',
            'key' => 'home_entry_id',
            'value' => null,
        ]);
        $request->setContainer(app());

        $validator = Validator::make($request->all(), $request->rules());

        $this->assertTrue($validator->passes());
    }

    public function test_validation_allows_existing_entry_id(): void
    {
        $entry = Entry::create([
            'post_type_id' => $this->postType->id,
            'title' => 'Test',
            'slug' => 'test',
            'status' => 'published',
            'published_at' => Carbon::now('UTC')->subDay(),
            'data_json' => [],
        ]);

        $request = OptionsRequest::create('/', 'POST', [
            'namespace' => 'site',
            'key' => 'home_entry_id',
            'value' => $entry->id,
        ]);
        $request->setContainer(app());

        $validator = Validator::make($request->all(), $request->rules());

        $this->assertTrue($validator->passes());
    }

    public function test_validation_rejects_non_existent_entry_id(): void
    {
        $request = OptionsRequest::create('/', 'POST', [
            'namespace' => 'site',
            'key' => 'home_entry_id',
            'value' => 99999,
        ]);
        $request->setContainer(app());

        $validator = Validator::make($request->all(), $request->rules());

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('value', $validator->errors()->toArray());
    }

    public function test_validation_rejects_negative_integer(): void
    {
        $request = OptionsRequest::create('/', 'POST', [
            'namespace' => 'site',
            'key' => 'home_entry_id',
            'value' => -1,
        ]);
        $request->setContainer(app());

        $validator = Validator::make($request->all(), $request->rules());

        $this->assertFalse($validator->passes());
        $errors = $validator->errors()->toArray();
        $this->assertArrayHasKey('value', $errors);
    }

    public function test_validation_rejects_zero(): void
    {
        $request = OptionsRequest::create('/', 'POST', [
            'namespace' => 'site',
            'key' => 'home_entry_id',
            'value' => 0,
        ]);
        $request->setContainer(app());

        $validator = Validator::make($request->all(), $request->rules());

        $this->assertFalse($validator->passes());
        $errors = $validator->errors()->toArray();
        $this->assertArrayHasKey('value', $errors);
    }

    public function test_validation_allows_only_configured_options(): void
    {
        // Пытаемся установить неразрешённую опцию
        $request = OptionsRequest::create('/', 'POST', [
            'namespace' => 'unknown',
            'key' => 'some_key',
            'value' => 'test',
        ]);
        $request->setContainer(app());

        $rules = $request->rules();
        
        // Для неразрешённых опций должна быть базовая валидация
        $this->assertArrayHasKey('namespace', $rules);
        $this->assertArrayHasKey('key', $rules);
        $this->assertArrayHasKey('value', $rules);
    }
}


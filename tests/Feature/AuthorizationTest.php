<?php

namespace Tests\Feature;

use App\Models\Entry;
use App\Models\Media;
use App\Models\PostType;
use App\Models\Term;
use App\Models\Taxonomy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $regularUser;
    private Entry $entry;
    private Term $term;
    private Media $media;
    private PostType $postType;
    private Taxonomy $taxonomy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->regularUser = User::factory()->create();

        $this->postType = PostType::create([
            'slug' => 'page',
            'name' => 'Page',
            'options_json' => [],
        ]);

        $this->taxonomy = Taxonomy::create([
            'name' => 'Category',
            'hierarchical' => false,
        ]);

        $this->entry = Entry::create([
            'post_type_id' => $this->postType->id,
            'title' => 'Test Entry',
            'slug' => 'test-entry',
            'status' => 'draft',
            'data_json' => [],
        ]);

        $this->term = Term::create([
            'taxonomy_id' => $this->taxonomy->id,
            'name' => 'Test Term',
            'meta_json' => [],
        ]);

        $this->media = Media::create([
            'disk' => 'media',
            'path' => 'test/test.jpg',
            'original_name' => 'test.jpg',
            'ext' => 'jpg',
            'mime' => 'image/jpeg',
            'size_bytes' => 1024,
            'width' => 100,
            'height' => 100,
            'checksum_sha256' => null,
        ]);
    }

    public function test_admin_has_full_access_to_entry_abilities(): void
    {
        $this->actingAs($this->admin);

        $this->assertTrue(Gate::allows('viewAny', Entry::class));
        $this->assertTrue(Gate::allows('view', $this->entry));
        $this->assertTrue(Gate::allows('create', Entry::class));
        $this->assertTrue(Gate::allows('update', $this->entry));
        $this->assertTrue(Gate::allows('delete', $this->entry));
        $this->assertTrue(Gate::allows('restore', $this->entry));
        $this->assertTrue(Gate::allows('forceDelete', $this->entry));
        $this->assertTrue(Gate::allows('publish', $this->entry));
        $this->assertTrue(Gate::allows('attachMedia', $this->entry));
        $this->assertTrue(Gate::allows('manageTerms', $this->entry));
    }

    public function test_admin_has_full_access_to_term_abilities(): void
    {
        $this->actingAs($this->admin);

        $this->assertTrue(Gate::allows('viewAny', Term::class));
        $this->assertTrue(Gate::allows('view', $this->term));
        $this->assertTrue(Gate::allows('create', Term::class));
        $this->assertTrue(Gate::allows('update', $this->term));
        $this->assertTrue(Gate::allows('delete', $this->term));
        $this->assertTrue(Gate::allows('restore', $this->term));
        $this->assertTrue(Gate::allows('forceDelete', $this->term));
        $this->assertTrue(Gate::allows('attachEntry', $this->term));
    }

    public function test_admin_has_full_access_to_media_abilities(): void
    {
        $this->actingAs($this->admin);

        $this->assertTrue(Gate::allows('viewAny', Media::class));
        $this->assertTrue(Gate::allows('view', $this->media));
        $this->assertTrue(Gate::allows('create', Media::class));
        $this->assertTrue(Gate::allows('update', $this->media));
        $this->assertTrue(Gate::allows('delete', $this->media));
        $this->assertTrue(Gate::allows('restore', $this->media));
        $this->assertTrue(Gate::allows('forceDelete', $this->media));
        $this->assertTrue(Gate::allows('upload', Media::class));
        $this->assertTrue(Gate::allows('reprocess', $this->media));
        $this->assertTrue(Gate::allows('move', $this->media));
    }

    public function test_regular_user_denied_entry_abilities(): void
    {
        $this->actingAs($this->regularUser);

        $this->assertFalse(Gate::allows('viewAny', Entry::class));
        $this->assertFalse(Gate::allows('view', $this->entry));
        $this->assertFalse(Gate::allows('create', Entry::class));
        $this->assertFalse(Gate::allows('update', $this->entry));
        $this->assertFalse(Gate::allows('delete', $this->entry));
        $this->assertFalse(Gate::allows('restore', $this->entry));
        $this->assertFalse(Gate::allows('forceDelete', $this->entry));
        $this->assertFalse(Gate::allows('publish', $this->entry));
        $this->assertFalse(Gate::allows('attachMedia', $this->entry));
        $this->assertFalse(Gate::allows('manageTerms', $this->entry));
    }

    public function test_regular_user_denied_term_abilities(): void
    {
        $this->actingAs($this->regularUser);

        $this->assertFalse(Gate::allows('viewAny', Term::class));
        $this->assertFalse(Gate::allows('view', $this->term));
        $this->assertFalse(Gate::allows('create', Term::class));
        $this->assertFalse(Gate::allows('update', $this->term));
        $this->assertFalse(Gate::allows('delete', $this->term));
        $this->assertFalse(Gate::allows('restore', $this->term));
        $this->assertFalse(Gate::allows('forceDelete', $this->term));
        $this->assertFalse(Gate::allows('attachEntry', $this->term));
    }

    public function test_regular_user_denied_media_abilities(): void
    {
        $this->actingAs($this->regularUser);

        $this->assertFalse(Gate::allows('viewAny', Media::class));
        $this->assertFalse(Gate::allows('view', $this->media));
        $this->assertFalse(Gate::allows('create', Media::class));
        $this->assertFalse(Gate::allows('update', $this->media));
        $this->assertFalse(Gate::allows('delete', $this->media));
        $this->assertFalse(Gate::allows('restore', $this->media));
        $this->assertFalse(Gate::allows('forceDelete', $this->media));
        $this->assertFalse(Gate::allows('upload', Media::class));
        $this->assertFalse(Gate::allows('reprocess', $this->media));
        $this->assertFalse(Gate::allows('move', $this->media));
    }

    public function test_guest_denied_all_abilities(): void
    {
        $this->assertFalse(Gate::allows('viewAny', Entry::class));
        $this->assertFalse(Gate::allows('view', $this->entry));
        $this->assertFalse(Gate::allows('create', Entry::class));
        $this->assertFalse(Gate::allows('update', $this->entry));
        $this->assertFalse(Gate::allows('delete', $this->entry));
    }

    public function test_user_factory_admin_state(): void
    {
        $admin = User::factory()->admin()->create();
        $this->assertTrue($admin->is_admin);

        $regular = User::factory()->create(['is_admin' => false]);
        $this->assertFalse($regular->is_admin);
    }

    public function test_user_model_is_admin_attribute(): void
    {
        $user = User::factory()->create(['is_admin' => true]);
        $this->assertTrue($user->is_admin);

        $user = User::factory()->create(['is_admin' => false]);
        $this->assertFalse($user->is_admin);
    }

    public function test_policies_are_registered(): void
    {
        // Проверяем, что политики зарегистрированы через проверку abilities
        $this->actingAs($this->admin);
        
        // Если политики зарегистрированы, то abilities должны работать
        $this->assertTrue(Gate::allows('viewAny', Entry::class));
        $this->assertTrue(Gate::allows('viewAny', Term::class));
        $this->assertTrue(Gate::allows('viewAny', Media::class));
    }

    public function test_guest_receives_403_on_protected_route(): void
    {
        // Гость (неаутентифицированный) получает 403 на защищённом маршруте
        $response = $this->get('/test/admin/entries');
        $response->assertForbidden(); // 403
    }

    public function test_regular_user_receives_403_on_protected_route(): void
    {
        // Обычный пользователь (без прав) получает 403 на защищённом маршруте
        $this->actingAs($this->regularUser);
        
        $response = $this->get('/test/admin/entries');
        $response->assertForbidden(); // 403
    }

    public function test_admin_receives_200_on_protected_route(): void
    {
        // Администратор получает доступ к защищённому маршруту
        $this->actingAs($this->admin);
        
        $response = $this->get('/test/admin/entries');
        $response->assertOk(); // 200
        $response->assertJson(['message' => 'ok']);
    }
}


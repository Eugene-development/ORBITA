<?php
namespace Tests\Feature;
use App\Modules\Auth\Employee;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AuthMediaTest extends TestCase
{
    public function test_login_logout_and_me_hide_passwords(): void {
        Employee::query()->create(['name' => 'Editor', 'email' => 'editor@example.test', 'password' => 'SecurePassword123', 'role' => 'editor']);
        $this->getJson('/auth/csrf')->assertOk()->assertJsonStructure(['csrf_token']);
        $this->postJson('/auth/login', ['email' => 'editor@example.test', 'password' => 'wrong'])->assertUnprocessable();
        $this->postJson('/auth/login', ['email' => 'editor@example.test', 'password' => 'SecurePassword123'])->assertOk()->assertJsonPath('user.role', 'editor')->assertJsonMissingPath('user.password');
        $this->getJson('/auth/me')->assertJsonPath('capabilities.catalogCrud', true);
        $this->postJson('/auth/logout')->assertOk(); $this->getJson('/auth/me')->assertJsonPath('user', null);
    }
    public function test_inactive_employee_cannot_log_in_or_mutate(): void {
        $user = Employee::query()->create(['name' => 'Disabled', 'email' => 'disabled@example.test', 'password' => 'SecurePassword123', 'role' => 'admin', 'is_active' => false]);
        $this->postJson('/auth/login', ['email' => $user->email, 'password' => 'SecurePassword123'])->assertUnprocessable();
        $this->actingAs($user)->postJson('/graphql', ['query' => '{ adminProducts { data { id } } }'])->assertJsonStructure(['errors']);
    }
    public function test_auth_and_graphql_posts_enforce_csrf_outside_test_bypass(): void {
        $this->app['env'] = 'local';
        $this->postJson('/auth/login', ['email' => 'user@example.test', 'password' => 'x'])->assertStatus(419);
        $this->postJson('/graphql', ['query' => '{ catalog { id } }'])->assertStatus(419);
    }
    public function test_guest_graphql_get_reads_work_without_csrf_and_get_mutations_fail(): void {
        $this->fixtureProduct(); $this->approveSyntheticCatalog();
        $user = Employee::query()->create(['name' => 'Editor', 'email' => 'get@example.test', 'password' => 'SecurePassword123', 'role' => 'editor']);
        $this->app['env'] = 'local';
        $this->getJson('/graphql?'.http_build_query(['query' => '{ catalog { id } }']))->assertOk()->assertJsonPath('data.catalog.0.id', '1');
        $this->actingAs($user)->getJson('/graphql?'.http_build_query(['query' => 'mutation { archiveProduct(id:"1") { id } }']))->assertStatus(405)->assertJsonStructure(['errors']);
        $this->assertDatabaseHas('product', ['id' => 1, 'is_active' => 1]);
        $this->assertDatabaseCount('orbita_audit', 0);
    }
    public function test_media_upload_is_authenticated_and_validated(): void {
        Storage::fake('local'); config(['orbita.media_disk' => 'local', 'orbita.media_uploads_enabled' => true, 'orbita.media_base_url' => 'https://images.example.test/images']);
        $this->postJson('/api/media')->assertUnauthorized();
        $user = Employee::query()->create(['name' => 'Editor', 'email' => 'upload@example.test', 'password' => 'SecurePassword123', 'role' => 'editor']);
        $this->actingAs($user)->post('/api/media', ['image' => UploadedFile::fake()->create('unsafe.svg', 1, 'image/svg+xml')], ['Accept' => 'application/json'])->assertUnprocessable();
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jZAAAAABJRU5ErkJggg==');
        $file = UploadedFile::fake()->createWithContent('tiny.png', $png);
        $response = $this->post('/api/media', ['image' => $file], ['Accept' => 'application/json'])->assertCreated()->assertJsonStructure(['hash', 'url']);
        $this->assertSame(rtrim(config('app.url'), '/').'/media/'.$response->json('hash'), $response->json('url'));
        Storage::disk('local')->assertExists('images/'.$response->json('hash'));
        $this->assertDatabaseCount('orbita_media', 1);
        \Illuminate\Support\Facades\Auth::logout();
        $asset = $this->get('/media/'.$response->json('hash'))->assertOk()->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Content-Type-Options', 'nosniff')->assertCookieMissing('orbita_session');
        $this->assertSame($png, $asset->streamedContent());
        $this->get('/media/00000000-0000-0000-0000-000000000000.png')->assertNotFound();
        $this->get('/media/%2e%2e%2Fsecret.png')->assertNotFound();
    }
    public function test_password_reset_link_is_generic_and_password_is_hashed(): void {
        $user = Employee::query()->create(['name' => 'Editor', 'email' => 'reset@example.test', 'password' => 'SecurePassword123', 'role' => 'editor']);
        $this->assertTrue(Hash::check('SecurePassword123', $user->password));
        $a = $this->postJson('/auth/password/forgot', ['email' => $user->email])->assertOk();
        $b = $this->postJson('/auth/password/forgot', ['email' => 'missing@example.test'])->assertOk();
        $this->assertSame($a->json(), $b->json());
        $this->assertDatabaseCount('orbita_password_reset_tokens', 1);
    }
    public function test_graphql_batches_and_ambiguous_get_operations_are_rejected(): void {
        $this->postJson('/graphql', [['query' => '{ catalog { id } }'], ['query' => '{ catalog { id } }']])->assertBadRequest();
        $this->getJson('/graphql?'.http_build_query(['query' => 'query Read { catalog { id } } mutation Write { archiveProduct(id:"1") { id } }']))->assertBadRequest();
        $this->getJson('/graphql?'.http_build_query(['query' => 'query Read { catalog { id } } mutation Write { archiveProduct(id:"1") { id } }', 'operationName' => 'Write']))->assertStatus(405);
        $this->getJson('/graphql?'.http_build_query(['query' => 'query Read { catalog { id } } mutation Write { archiveProduct(id:"1") { id } }', 'operationName' => 'Read']))->assertOk()->assertJsonPath('data.catalog.0.id', '1');
    }
    public function test_health_checks_service_schema_without_starting_a_browser_session(): void {
        $this->getJson('/health')->assertOk()->assertJson(['status' => 'ok'])->assertCookieMissing('orbita_session');
        \Illuminate\Support\Facades\Schema::drop('orbita_schema_approvals');
        $this->getJson('/health')->assertStatus(503)->assertExactJson(['status' => 'unavailable']);
    }
    public function test_image_url_additive_field_uses_legacy_base_and_known_local_metadata(): void {
        $this->fixtureProduct();
        $hash = '00000000-0000-0000-0000-000000000001.png';
        config(['orbita.media_base_url' => 'https://images.example.test/images']);
        \Illuminate\Support\Facades\DB::table('image')->insert(['id' => 1, 'key' => '1', 'hash' => 'фото товара.png', 'parentable_type' => 'product', 'parentable_id' => 1]);
        $this->postJson('/graphql', ['query' => '{product_one(slug:"product-1"){image{hash url}}}'])->assertJsonPath('data.product_one.image.url', 'https://images.example.test/images/'.rawurlencode('фото товара.png'));
        \Illuminate\Support\Facades\DB::table('orbita_media')->insert(['hash' => $hash, 'disk' => 'local', 'path' => 'images/'.$hash, 'mime' => 'image/png', 'bytes' => 10, 'uploaded_by' => 1, 'created_at' => now(), 'updated_at' => now()]);
        \Illuminate\Support\Facades\DB::table('image')->where('id', 1)->update(['hash' => $hash]);
        $this->postJson('/graphql', ['query' => '{product_one(slug:"product-1"){image{hash url}}}'])->assertJsonPath('data.product_one.image.url', rtrim(config('app.url'), '/').'/media/'.$hash);
    }
}

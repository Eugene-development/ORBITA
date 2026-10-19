<?php
namespace Tests\Feature;
use App\Modules\Auth\Employee;
use App\Modules\Catalog\CatalogService;
use App\Modules\Catalog\ProductAction;
use App\Modules\Schema\CatalogWriteGate;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    private function employee(string $role = 'editor'): Employee {
        return Employee::query()->create(['name' => 'Employee', 'email' => $role.'@example.test', 'password' => 'SafePassword123', 'role' => $role]);
    }
    private function input(): array {
        return ['value' => 'Новый товар', 'slug' => 'new-product', 'categoryId' => '12', 'price' => '101.25', 'unit' => 'шт', 'texts' => [['key' => '1', 'value' => 'Описание']], 'properties' => [['key' => '1', 'value' => 'Характеристика']]];
    }
    public function test_public_catalog_excludes_other_projects_and_archived_products(): void {
        $this->fixtureProduct(1); $this->fixtureProduct(2, key: '2'); $this->fixtureProduct(3, active: false);
        $this->postJson('/graphql', ['query' => '{ products { data { id value price { value } parentable { ... on Category { id } } } paginatorInfo { total } } }'])
            ->assertOk()->assertJsonPath('data.products.paginatorInfo.total', 1)->assertJsonPath('data.products.data.0.price.value', '100.00');
        $this->postJson('/graphql', ['query' => '{ productIds(ids:["1","2","3"]) { id } }'])->assertJsonCount(1, 'data.productIds');
        $this->postJson('/graphql', ['query' => '{ product_one(slug:"product-2") { id } }'])->assertJsonPath('data.product_one', null);
        $this->postJson('/graphql', ['query' => '{ catalog(key:"2") { id } }'])->assertJsonStructure(['errors']);
    }
    public function test_nested_relations_are_project_scoped(): void {
        $this->fixtureProduct();
        DB::table('image')->insert(['id' => 77, 'key' => '2', 'hash' => 'secret.webp', 'parentable_type' => 'product', 'parentable_id' => 1]);
        $this->postJson('/graphql', ['query' => '{ product_one(slug:"product-1") { image { hash } } }'])->assertJsonPath('data.product_one.image', null);
    }
    public function test_admin_queries_and_mutations_require_assigned_employee(): void {
        $this->postJson('/graphql', ['query' => '{ adminProducts { data { id } } }'])->assertJsonStructure(['errors']);
        $this->actingAs($this->employee())->postJson('/graphql', ['query' => '{ referenceData { categories { id } catalogWritesEnabled } }'])
            ->assertJsonPath('data.referenceData.catalogWritesEnabled', false)->assertJsonCount(1, 'data.referenceData.categories');
        $this->postJson('/graphql', ['query' => 'mutation { createProduct(input:{value:"Test",slug:"test",categoryId:"12",price:"10.00",unit:"шт"}) { id } }'])
            ->assertJsonStructure(['errors']);
        $this->assertDatabaseCount('product', 0);
    }
    public function test_create_allocates_global_ids_and_preserves_independent_price_id(): void {
        $this->fixtureProduct(2983, priceId: 3089); $this->fixtureProduct(4000, key: '2', priceId: 5000);
        $this->approveSyntheticCatalog(); $this->actingAs($this->employee());
        $product = app(ProductAction::class)->save($this->input());
        $this->assertSame('4001', (string) $product->id); $this->assertSame('5001', (string) $product->price->id);
        $this->assertSame('101.25', $product->price->value); $this->assertSame('product', $product->price->parentable_type);
        $this->assertSame('4001', (string) $product->price->parentable_id);
        $this->assertDatabaseHas('product', ['id' => 4000, 'key' => '2']);
        $this->assertDatabaseCount('orbita_audit', 1);
    }
    public function test_update_preserves_existing_related_ids_and_archive_is_reversible(): void {
        $this->fixtureProduct(1, priceId: 3090); $this->approveSyntheticCatalog(); $this->actingAs($this->employee());
        $product = app(ProductAction::class)->save($this->input(), '1');
        $this->assertSame('3090', (string) $product->price->id); $this->assertSame('2001', (string) $product->unit->id);
        app(ProductAction::class)->setActive('1', false);
        $this->assertSame([], app(CatalogService::class)->productIds(['1']));
        app(ProductAction::class)->setActive('1', true); $this->assertCount(1, app(CatalogService::class)->productIds(['1']));
        $this->assertDatabaseCount('product', 1);
    }
    public function test_entire_graph_and_sequence_roll_back_on_invalid_nested_key(): void {
        $this->approveSyntheticCatalog(); $this->actingAs($this->employee());
        $before = DB::table('orbita_legacy_sequences')->pluck('next_id', 'table_name')->all();
        $input = $this->input(); $input['texts'][0]['key'] = '2';
        try { app(ProductAction::class)->save($input); $this->fail('Expected validation failure.'); } catch (ValidationException) {}
        foreach (['product', 'price', 'unit', 'text', 'property'] as $table) { $this->assertDatabaseCount($table, 0); }
        $this->assertSame($before, DB::table('orbita_legacy_sequences')->pluck('next_id', 'table_name')->all());
    }
    public function test_schema_drift_closes_previously_approved_gate(): void {
        $this->approveSyntheticCatalog(); $this->assertTrue(app(CatalogWriteGate::class)->isOpen());
        Schema::table('product', fn (Blueprint $table) => $table->string('unexpected')->nullable());
        $this->assertFalse(app(CatalogWriteGate::class)->isOpen());
    }
    public function test_cross_project_category_and_product_writes_cannot_change_data(): void {
        $this->fixtureProduct(8, key: '2'); $this->approveSyntheticCatalog(); $this->actingAs($this->employee());
        $input = $this->input(); $input['categoryId'] = '13';
        try { app(ProductAction::class)->save($input); $this->fail('Expected category denial.'); } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {}
        try { app(ProductAction::class)->setActive('8', false); $this->fail('Expected product denial.'); } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {}
        $this->assertDatabaseHas('product', ['id' => 8, 'key' => '2', 'is_active' => 1]);
    }
    public function test_authoritative_quote_rejects_ambiguous_locked_prices(): void {
        $this->fixtureProduct();
        DB::table('price')->insert(['id' => 5000, 'key' => '1', 'value' => '900.00', 'parentable_type' => 'product', 'parentable_id' => 1]);
        $this->expectException(ValidationException::class);
        DB::transaction(fn () => app(CatalogService::class)->quoteProducts(['1'], true));
    }
    public function test_duplicate_morph_one_prevents_schema_approval(): void {
        $this->fixtureProduct();
        DB::table('price')->insert(['id' => 5000, 'key' => '1', 'value' => '900.00', 'parentable_type' => 'product', 'parentable_id' => 1]);
        $report = app(\App\Modules\Schema\SchemaInspector::class)->inspect();
        $this->assertContains('Ambiguous morphOne records in price', $report['issues']);
        $this->artisan('orbita:catalog-approve', ['fingerprint' => $report['fingerprint'], '--writers-coordinated' => true])->assertExitCode(1);
        $this->assertDatabaseCount('orbita_schema_approvals', 0);
    }
    public function test_removing_property_with_nested_price_cannot_create_an_orphan(): void {
        $this->fixtureProduct();
        DB::table('property')->insert(['id' => 81, 'key' => '1', 'value' => 'Legacy priced property', 'parentable_type' => 'product', 'parentable_id' => 1]);
        DB::table('price')->insert(['id' => 82, 'key' => '1', 'value' => '20.00', 'parentable_type' => 'property', 'parentable_id' => 81]);
        $this->approveSyntheticCatalog(); $this->actingAs($this->employee());
        $input = $this->input(); $input['properties'] = [];
        try { app(ProductAction::class)->save($input, '1'); $this->fail('Expected linked-property deletion denial.'); } catch (ValidationException) {}
        $this->assertDatabaseHas('property', ['id' => 81, 'parentable_id' => 1]);
        $this->assertDatabaseHas('price', ['id' => 82, 'parentable_id' => 81]);
        $this->assertDatabaseHas('price', ['id' => 1001, 'value' => '100.00']);
    }
    public function test_content_input_assigns_project_key_server_side_and_uses_slug_for_label(): void {
        $this->approveSyntheticCatalog(); $this->actingAs($this->employee());
        $input = $this->input(); $input['texts'] = [['value' => 'Описание', 'slug' => 'description']];
        $input['properties'] = [['value' => '10 кг', 'slug' => 'weight']];
        $product = app(ProductAction::class)->save($input);
        $this->assertSame('1', $product->text[0]->key); $this->assertSame('description', $product->text[0]->slug);
        $this->assertSame('1', $product->property[0]->key); $this->assertSame('weight', $product->property[0]->slug);
        $this->postJson('/graphql', ['query' => 'mutation { updateProduct(id:"'.$product->id.'",input:{value:"Test",slug:"new-product",categoryId:"12",price:"10.00",unit:"шт",texts:[{key:"2",value:"Attack"}]}) { id } }'])->assertJsonStructure(['errors']);
        $this->assertDatabaseHas('text', ['key' => '1', 'value' => 'Описание']);
    }
    public function test_foreign_nested_id_rolls_back_earlier_graph_updates_and_counters(): void {
        $this->fixtureProduct(1); $this->fixtureProduct(2);
        DB::table('text')->insert(['id' => 9, 'key' => '1', 'value' => 'Other product text', 'parentable_type' => 'product', 'parentable_id' => 2]);
        $this->approveSyntheticCatalog(); $this->actingAs($this->employee());
        $before = DB::table('orbita_legacy_sequences')->pluck('next_id', 'table_name')->all();
        $input = $this->input(); $input['texts'] = [['id' => '9', 'value' => 'Cross-product overwrite']];
        try { app(ProductAction::class)->save($input, '1'); $this->fail('Expected nested ID denial.'); } catch (ValidationException) {}
        $this->assertDatabaseHas('price', ['id' => 1001, 'value' => '100.00']);
        $this->assertDatabaseHas('text', ['id' => 9, 'value' => 'Other product text', 'parentable_id' => 2]);
        $this->assertSame($before, DB::table('orbita_legacy_sequences')->pluck('next_id', 'table_name')->all());
    }
    public function test_unmapped_relationship_tables_views_and_triggers_close_approval(): void {
        $this->approveSyntheticCatalog();
        Schema::create('product_tag', function (Blueprint $table): void { $table->unsignedBigInteger('product_id'); $table->unsignedBigInteger('tag_id'); });
        DB::statement('CREATE VIEW legacy_catalog_view AS SELECT id FROM product');
        if (DB::connection()->getDriverName() === 'sqlite') { DB::statement("CREATE TRIGGER legacy_catalog_trigger AFTER UPDATE ON product BEGIN UPDATE product SET slug = NEW.slug WHERE id = NEW.id; END"); }
        else { DB::statement('CREATE TRIGGER legacy_catalog_trigger BEFORE UPDATE ON product FOR EACH ROW SET NEW.slug = NEW.slug'); }
        $report = app(\App\Modules\Schema\SchemaInspector::class)->inspect();
        $this->assertContains('Unmapped legacy relationship table: product_tag', $report['issues']);
        $this->assertContains('Unreviewed catalog view: legacy_catalog_view', $report['issues']);
        $this->assertContains('Unreviewed catalog trigger: legacy_catalog_trigger', $report['issues']);
        $this->assertNotEmpty($report['server_version']); $this->assertSame(['1'], $report['observed']['catalog']['keys']);
        $this->assertFalse(app(CatalogWriteGate::class)->isOpen());
        DB::statement('DROP TRIGGER legacy_catalog_trigger'); DB::statement('DROP VIEW legacy_catalog_view'); Schema::drop('product_tag');
    }
    public function test_existing_unicode_spaces_and_encoded_image_basenames_are_preserved_exactly(): void {
        $this->fixtureProduct();
        DB::table('image')->insert(['id' => 31, 'key' => '1', 'hash' => 'temporary.png', 'alt' => 'Existing alt', 'parentable_type' => 'product', 'parentable_id' => 1]);
        $this->approveSyntheticCatalog(); $this->actingAs($this->employee());
        foreach ([' Рулон Техно №1.png ', rawurlencode('Фасад белый.png')] as $hash) {
            DB::table('image')->where('id', 31)->update(['hash' => $hash]);
            $input = $this->input(); $input['imageHash'] = $hash;
            $input['texts'] = []; $input['properties'] = [];
            $this->postJson('/graphql', ['query' => 'mutation Edit($input:ProductInput!){ updateProduct(id:"1",input:$input){ image {hash alt} } }', 'variables' => ['input' => $input]])
                ->assertJsonPath('data.updateProduct.image.hash', $hash)->assertJsonPath('data.updateProduct.image.alt', 'Existing alt');
            $this->assertDatabaseHas('image', ['id' => 31, 'hash' => $hash]);
        }
    }
    public function test_image_hash_unsafe_forms_and_other_project_hashes_are_rejected(): void {
        $this->fixtureProduct();
        DB::table('image')->insert(['id' => 31, 'key' => '1', 'hash' => 'allowed.png', 'parentable_type' => 'product', 'parentable_id' => 1]);
        DB::table('image')->insert(['id' => 32, 'key' => '2', 'hash' => 'чужая картинка.png', 'parentable_type' => 'product', 'parentable_id' => 9]);
        $this->approveSyntheticCatalog(); $this->actingAs($this->employee());
        foreach (['/x.png', 'folder\\x.png', '..', '%2e%2e', '%2f../x.png', '%252fx.png', '%00x.png', 'https%3Aimage.png', 'неизвестная.png', 'чужая картинка.png', 'ALLOWED.png'] as $hash) {
            $input = $this->input(); $input['imageHash'] = $hash;
            try { app(ProductAction::class)->save($input, '1'); $this->fail('Expected hash denial: '.$hash); } catch (ValidationException) {}
        }
        $this->assertDatabaseHas('image', ['id' => 31, 'hash' => 'allowed.png']);
        $input = $this->input(); $input['price'] = str_repeat('9', 10000);
        try { app(ProductAction::class)->save($input, '1'); $this->fail('Expected long decimal denial.'); } catch (ValidationException) {}
        $this->assertDatabaseHas('price', ['id' => 1001, 'value' => '100.00']);
    }
}

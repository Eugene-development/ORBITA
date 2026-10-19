<?php
namespace App\Modules\Catalog;
final class ProductMutation {
    public function create(mixed $root, array $args): mixed { return app(ProductAction::class)->save($args['input']); }
    public function update(mixed $root, array $args): mixed { return app(ProductAction::class)->save($args['input'], (string) $args['id']); }
    public function archive(mixed $root, array $args): mixed { return app(ProductAction::class)->setActive((string) $args['id'], false); }
    public function restore(mixed $root, array $args): mixed { return app(ProductAction::class)->setActive((string) $args['id'], true); }
}

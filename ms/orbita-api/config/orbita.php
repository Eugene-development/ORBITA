<?php
return [
    'project_key' => '1', 'catalog_writes_enabled' => (bool) env('ORBITA_CATALOG_WRITES_ENABLED', env('CATALOG_WRITES_ENABLED', false)),
    'writer_coordination_confirmed' => (bool) env('ORBITA_WRITERS_COORDINATED', env('CATALOG_WRITERS_COORDINATED', false)),
    'approved_catalog_fingerprint' => env('ORBITA_SCHEMA_FINGERPRINT', env('CATALOG_APPROVED_FINGERPRINT', '')),
    'legacy_tables' => ['catalog', 'rubric', 'category', 'product', 'property', 'price', 'unit', 'text', 'image', 'seotitle', 'seodescription', 'menu', 'project', 'resource', 'seoquery', 'position'],
    'write_tables' => ['product', 'property', 'price', 'unit', 'text', 'image'],
    'service_tables' => ['orbita_migrations', 'orbita_users', 'orbita_password_reset_tokens', 'orbita_legacy_sequences', 'orbita_schema_approvals',
        'orbita_media', 'orbita_audit', 'orbita_orders', 'orbita_order_items', 'orbita_payments', 'orbita_outbox', 'orbita_subscriptions'],
    // These lists must be changed in reviewed code after the full export is mapped.
    'reviewed_non_catalog_tables' => [], 'reviewed_catalog_triggers' => [], 'reviewed_catalog_views' => [],
    'media_prefix' => 'images', 'media_base_url' => env('ORBITA_IMAGE_BASE_URL', env('MEDIA_BASE_URL', '')),
    'media_disk' => env('ORBITA_MEDIA_DISK', 's3'),
    'media_uploads_enabled' => (bool) env('MEDIA_UPLOADS_ENABLED', false),
];

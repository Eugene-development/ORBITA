<?php
return ['default' => env('QUEUE_CONNECTION', 'redis'), 'connections' => [
    'sync' => ['driver' => 'sync'],
    'redis' => ['driver' => 'redis', 'connection' => 'default', 'queue' => 'orbita', 'retry_after' => 120, 'block_for' => 5, 'after_commit' => true],
], 'failed' => ['driver' => 'null']];

<?php
return ['catalog_writes_enabled' => (bool) env('ORBITA_CATALOG_WRITES_ENABLED', env('CATALOG_WRITES_ENABLED', false))];

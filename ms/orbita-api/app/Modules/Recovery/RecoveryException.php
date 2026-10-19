<?php

declare(strict_types=1);

namespace App\Modules\Recovery;

use RuntimeException;

/** Messages must never contain CSV values, SQL bindings or credentials. */
final class RecoveryException extends RuntimeException {}

<?php

declare(strict_types=1);

namespace App\Modules\Payments;

use RuntimeException;

/** Only an internal classification is kept; provider messages may contain personal data. */
final class PaymentFailure extends RuntimeException {}

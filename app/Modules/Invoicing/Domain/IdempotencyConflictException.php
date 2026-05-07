<?php

declare(strict_types=1);

namespace Modules\Invoicing\Domain;

use RuntimeException;

/**
 * Thrown when the same idempotency key is replayed with a different payload
 * fingerprint. The HTTP layer maps this to 409 Conflict (TR-6.5.3).
 */
final class IdempotencyConflictException extends RuntimeException {}

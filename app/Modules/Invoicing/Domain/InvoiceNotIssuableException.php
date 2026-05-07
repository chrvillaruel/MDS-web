<?php

declare(strict_types=1);

namespace Modules\Invoicing\Domain;

use RuntimeException;

/**
 * Thrown when IssueInvoiceAction is asked to issue an invoice that is not
 * in DRAFT status, or that is missing required data (PRD §3.1, FR-6.2.1).
 */
final class InvoiceNotIssuableException extends RuntimeException {}

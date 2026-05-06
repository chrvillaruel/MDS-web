<?php

declare(strict_types=1);

namespace Modules\Invoicing\Domain;

/**
 * Builds the canonical EIS-format JSON we transmit to BIR and store
 * forever (PRD §3.2 C-3.2.1).
 *
 * Versioned via DI binding: when BIR publishes the official ATG schema, swap
 * the V1 implementation for V2; existing invoices retain their v1
 * payload_schema_version stamp (Q-1 fallback path).
 */
interface EisPayloadBuilder
{
    public function version(): string;

    /**
     * @return array<string, mixed> the canonical payload (must be JSON-serializable)
     */
    public function build(EisBuildContext $context): array;
}

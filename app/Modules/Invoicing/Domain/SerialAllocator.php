<?php

declare(strict_types=1);

namespace Modules\Invoicing\Domain;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Allocates the next BIR serial number for a branch.
 *
 * **Regulatory invariant (PRD §3.4 C-3.4.1):** no two ISSUED invoices on the
 * same (seller_id, branch_id) ever share (serial_number, reset_counter).
 *
 * Implementation: pessimistic row lock on `branches` via `SELECT FOR UPDATE`,
 * inside the caller's transaction. SQLite has no FOR UPDATE; on that driver
 * we fall back to a plain SELECT — production runs on Postgres (see ADR-004).
 *
 * Allocations are NOT freed on rollback — gaps allowed, reuse never (§3.4
 * C-3.4.4). The caller's transaction commits or aborts; either way the next
 * allocation moves forward.
 */
final class SerialAllocator
{
    public function __construct(private ?Connection $connection = null) {}

    public function next(int $branchId): SerialAllocation
    {
        /** @var Connection $conn */
        $conn = $this->connection ?? DB::connection();
        $supportsForUpdate = in_array($conn->getDriverName(), ['pgsql', 'mysql', 'mariadb'], true);

        $sql = $supportsForUpdate
            ? 'SELECT current_serial, reset_counter, max_serial FROM branches WHERE id = ? FOR UPDATE'
            : 'SELECT current_serial, reset_counter, max_serial FROM branches WHERE id = ?';

        $row = $conn->selectOne($sql, [$branchId]);

        if ($row === null) {
            throw new RuntimeException("Branch {$branchId} not found.");
        }

        $current = (int) $row->current_serial;
        $resetCounter = (int) $row->reset_counter;
        $max = (int) $row->max_serial;

        if ($current >= $max) {
            $next = 1;
            $resetCounter++;
        } else {
            $next = $current + 1;
        }

        $conn->update(
            'UPDATE branches SET current_serial = ?, reset_counter = ?, updated_at = ? WHERE id = ?',
            [$next, $resetCounter, now(), $branchId],
        );

        return new SerialAllocation($next, $resetCounter);
    }
}

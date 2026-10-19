<?php

namespace App\Contracts;

use App\Models\Concerns\Audited;

/**
 * A record whose changes go into the audit trail. Most models get this from the
 * {@see Audited} trait.
 */
interface Auditable
{
    /** A stable, short name for the kind of record ("category", "brand", "user"). */
    public function auditRecordType(): string;

    /** How the record reads to a person ("Summer Collection"), kept as it was at the time. */
    public function auditLabel(): string;

    /**
     * The values worth recording, never secrets.
     *
     * @return array<string, mixed>
     */
    public function auditValues(): array;

    /**
     * @return mixed the record's key
     */
    public function getKey();
}

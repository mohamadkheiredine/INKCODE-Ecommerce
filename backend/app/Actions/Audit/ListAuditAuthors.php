<?php

namespace App\Actions\Audit;

use App\Models\AuditEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * The audit log's User filter: every User who has changed something, by name.
 */
final class ListAuditAuthors
{
    /**
     * @return Collection<int, User>
     */
    public function handle(): Collection
    {
        return User::query()
            ->whereIn('id', AuditEntry::query()->select('user_id')->whereNotNull('user_id'))
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'name']);
    }
}

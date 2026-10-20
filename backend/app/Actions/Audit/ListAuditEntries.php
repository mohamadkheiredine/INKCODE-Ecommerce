<?php

namespace App\Actions\Audit;

use App\Models\AuditEntry;
use App\Support\ListParameters;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

/**
 * A page of the audit log with each entry's User. The date range covers whole days,
 * both included.
 */
final class ListAuditEntries
{
    private const array COLUMN_FILTERS = [
        'user' => 'user_id',
        'action' => 'action',
        'record_type' => 'record_type',
        'record' => 'record_id',
    ];

    /**
     * @return LengthAwarePaginator<int, AuditEntry>
     */
    public function handle(ListParameters $parameters): LengthAwarePaginator
    {
        $query = AuditEntry::query()->with('user:id,name');

        $parameters->search($query, ['record_label']);

        foreach (self::COLUMN_FILTERS as $filter => $column) {
            if ($parameters->hasFilter($filter)) {
                $query->where($column, $parameters->filter($filter));
            }
        }
        if ($parameters->hasFilter('from')) {
            $query->where('created_at', '>=', Carbon::parse((string) $parameters->filter('from'))->startOfDay());
        }
        if ($parameters->hasFilter('to')) {
            $query->where('created_at', '<=', Carbon::parse((string) $parameters->filter('to'))->endOfDay());
        }

        return $parameters->paginate($query);
    }
}

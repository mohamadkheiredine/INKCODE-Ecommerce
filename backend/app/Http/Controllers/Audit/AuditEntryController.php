<?php

namespace App\Http\Controllers\Audit;

use App\Actions\Audit\ListAuditEntries;
use App\Http\Controllers\Controller;
use App\Http\Requests\Audit\ListAuditEntriesRequest;
use App\Http\Resources\AuditEntryResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The audit log is read-only: entries are written by App\Services\AuditTrail alone.
 */
class AuditEntryController extends Controller
{
    public function index(ListAuditEntriesRequest $request, ListAuditEntries $listAuditEntries): AnonymousResourceCollection
    {
        return AuditEntryResource::collection($listAuditEntries->handle($request->listParameters()));
    }
}

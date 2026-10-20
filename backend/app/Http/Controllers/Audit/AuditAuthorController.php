<?php

namespace App\Http\Controllers\Audit;

use App\Actions\Audit\ListAuditAuthors;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class AuditAuthorController extends Controller
{
    /**
     * Authorized by the route (audit.view).
     */
    public function index(ListAuditAuthors $listAuditAuthors): JsonResponse
    {
        return response()->json([
            'data' => $listAuditAuthors->handle()->map(fn (User $user): array => ['id' => $user->id, 'name' => $user->name]),
        ]);
    }
}

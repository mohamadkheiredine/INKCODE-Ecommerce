<?php

namespace App\Http\Controllers\Dashboard;

use App\Actions\Dashboard\ShowDashboard;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\ShowDashboardRequest;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    /**
     * The executive dashboard for `from`–`to`: KPIs from Counted orders compared with the
     * previous period, daily Revenue, the Order-status breakdown, top Products, recent
     * Orders and alerts.
     */
    public function show(ShowDashboardRequest $request, ShowDashboard $showDashboard): JsonResponse
    {
        return response()->json(['data' => $showDashboard->handle($request->from(), $request->to())]);
    }
}

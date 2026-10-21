<?php

namespace App\Http\Controllers\Reports;

use App\Actions\Reports\BuildReport;
use App\Enums\ReportType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\ShowReportRequest;
use Illuminate\Http\JsonResponse;

class ReportController extends Controller
{
    /**
     * One of the six reports (sales, products, categories, customers, returns, inventory)
     * for `from`–`to`: its columns, rows, and a summary compared with the previous period.
     */
    public function show(ShowReportRequest $request, ReportType $report, BuildReport $buildReport): JsonResponse
    {
        return response()->json(['data' => $buildReport->handle(
            $report,
            $request->from(),
            $request->to(),
            $request->salesInterval(),
            $request->rank(),
        )]);
    }
}

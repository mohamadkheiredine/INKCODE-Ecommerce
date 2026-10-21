<?php

namespace App\Http\Controllers\Reports;

use App\Actions\Reports\BuildReport;
use App\Actions\Reports\ExportReport;
use App\Enums\ReportType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\ExportReportRequest;
use Symfony\Component\HttpFoundation\Response;

class ReportExportController extends Controller
{
    /**
     * Downloads a report as CSV or Excel (.xlsx): the same rows as the screen, for the
     * same filters. Needs reports.export as well as reports.view.
     */
    public function show(ExportReportRequest $request, ReportType $report, BuildReport $buildReport, ExportReport $exportReport): Response
    {
        return $exportReport->handle(
            $buildReport->handle($report, $request->from(), $request->to(), $request->salesInterval(), $request->rank()),
            $request->exportFormat(),
        );
    }
}

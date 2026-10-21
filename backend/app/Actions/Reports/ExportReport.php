<?php

namespace App\Actions\Reports;

use App\Support\XlsxWriter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A built report ({@see BuildReport}) as a download: the same rows as the screen, headers
 * and stored values (reasons, Customer types) in the request's language, and numbers left
 * as plain numbers so a spreadsheet can add them up. The file is named after the report
 * and its dates, e.g. `sales-report-2026-09-21-to-2026-10-20.xlsx`.
 */
final class ExportReport
{
    /**
     * @param  array{report: string, range: array<string, string>, columns: list<array{key: string, type: string}>, rows: list<array<string, string|int|null>>}  $report
     */
    public function handle(array $report, string $format): Response
    {
        $name = "{$report['report']}-report-{$report['range']['from']}-to-{$report['range']['to']}.$format";
        $headers = array_map(fn (array $column): string => __("reports.columns.{$column['key']}"), $report['columns']);
        $rows = array_map(fn (array $row): array => array_map(
            fn (array $column): string|int|null => $column['type'] === 'enum' && $row[$column['key']] !== null
                ? __("reports.values.{$row[$column['key']]}")
                : $row[$column['key']],
            $report['columns'],
        ), $report['rows']);

        $numeric = array_map(fn (array $column): bool => in_array($column['type'], ['integer', 'money', 'percent'], true), $report['columns']);

        if ($format === 'xlsx') {
            $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'report-'.bin2hex(random_bytes(16)).'.xlsx';
            XlsxWriter::write($path, __("reports.names.{$report['report']}"), $headers, $rows, $numeric);

            return (new BinaryFileResponse($path, 200, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]))->setContentDisposition('attachment', $name)->deleteFileAfterSend();
        }

        // Text a spreadsheet would run as a formula (=, +, -, @) is kept as text.
        $rows = array_map(fn (array $row): array => array_map(
            fn (string|int|null $value, bool $isNumber): string|int|null => ! $isNumber && is_string($value) && preg_match('/^[=+\-@\t\r]/', $value) === 1 ? "'".$value : $value,
            $row,
            $numeric,
        ), $rows);

        return new StreamedResponse(function () use ($headers, $rows): void {
            $output = fopen('php://output', 'w');
            if ($output === false) {
                return;
            }
            // A byte-order mark so Excel opens the UTF-8 (Arabic) text correctly.
            fwrite($output, "\u{FEFF}");
            fputcsv($output, $headers, escape: '');
            foreach ($rows as $row) {
                fputcsv($output, $row, escape: '');
            }
            fclose($output);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"$name\"",
        ]);
    }
}

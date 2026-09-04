<?php
/**
 * CSV export helper.
 *
 * Streams straight to the client instead of buffering, so a large export does
 * not have to fit in memory.
 */

class Csv
{
    /**
     * Send rows as a CSV download and terminate the request.
     *
     * @param string        $filename Suggested download name
     * @param list<string>  $headers  Column headings
     * @param iterable<array<int, string|int|float|null>> $rows
     */
    public static function download(string $filename, array $headers, iterable $rows): void
    {
        // Strip anything that could break out of the header value.
        $safeName = preg_replace('/[^A-Za-z0-9._-]/', '', $filename) ?: 'export.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header("Content-Disposition: attachment; filename=\"{$safeName}\"");
        header('Cache-Control: no-store');

        $out = fopen('php://output', 'w');

        // BOM so Excel opens UTF-8 accented names correctly instead of mojibake.
        fwrite($out, "\xEF\xBB\xBF");

        fputcsv($out, $headers);
        foreach ($rows as $row) {
            fputcsv($out, array_map([self::class, 'defuse'], $row));
        }

        fclose($out);
        exit;
    }

    /**
     * Neutralize spreadsheet formula injection.
     *
     * A value beginning with =, +, -, or @ is executed as a formula by Excel
     * and Sheets, which turns an adorer-supplied name into code execution on
     * the admin's machine. Prefixing a tab keeps the text readable while
     * making it inert.
     */
    private static function defuse(string|int|float|null $value): string
    {
        if ($value === null) {
            return '';
        }

        $text = (string) $value;
        if ($text !== '' && str_contains('=+-@', $text[0])) {
            return "\t" . $text;
        }
        return $text;
    }
}

<?php

namespace App\Modules\Analytics\Clarity\Heatmaps\Services;

use App\Modules\Analytics\Clarity\Heatmaps\Exceptions\ClarityHeatmapException;
use DateTime;

/**
 * Validates that an uploaded CSV is genuinely a Microsoft Clarity heatmap
 * export, not a random spreadsheet, an image, or an unrelated CSV.
 *
 * Two layers of checks:
 *   1. File-level — non-empty, looks like text, within size budget.
 *   2. Content signature — must contain the "Date range" row in Clarity's
 *      m/d/Y g:i A format AND at least N other Clarity metadata markers
 *      (URL / Heatmap type / Device type / Filters / Visitors / Pageviews).
 *
 * The filename pattern Clarity_<Project>_<HeatmapType>_<Device>_<date>.csv is
 * a useful hint but NOT enforced — users frequently rename downloads, and we
 * don't want to reject legitimate exports over cosmetic changes.
 */
class ClarityHeatmapValidator
{
    /**
     * Header rows we expect to see in the metadata block at the top of a
     * Clarity heatmap CSV. We accept either casing variant since we've seen
     * Clarity emit both "Heatmap type" and "Heatmap Type" historically.
     */
    private const METADATA_MARKERS = [
        'URL',
        'Url',
        'Heatmap type', 'Heatmap Type',
        'Device type', 'Device Type', 'Device',
        'Filters',
        'Visitors',
        'Pageviews', 'Page views',
        'Project', 'Project name', 'Project Name',
    ];

    /**
     * Minimum number of distinct markers (not counting "Date range") required
     * to call this a Clarity heatmap. Two is enough to rule out arbitrary
     * one-marker CSVs while staying tolerant of Clarity changing column names.
     */
    private const MIN_MARKERS_REQUIRED = 2;

    /** Max bytes from the head of the file we'll scan for metadata markers. */
    private const HEAD_SCAN_BYTES = 16 * 1024;

    /** Max bytes we'll accept overall (matches the Livewire validate rule). */
    private const MAX_BYTES = 2 * 1024 * 1024;

    private const DATE_FORMAT = 'm/d/Y g:i A';

    /**
     * Throws ClarityHeatmapException with a stable `reason` code if the
     * content does not look like a Clarity heatmap. Returns the parsed
     * start date (Y-m-d) on success — saves the caller a second pass.
     */
    public function validate(string $filename, string $content): string
    {
        if ($content === '' || trim($content) === '') {
            throw new ClarityHeatmapException(ClarityHeatmapException::REASON_EMPTY);
        }

        if (strlen($content) > self::MAX_BYTES) {
            throw new ClarityHeatmapException(ClarityHeatmapException::REASON_TOO_LARGE, [
                'limit_kb' => self::MAX_BYTES / 1024,
            ]);
        }

        // Strip UTF-8 BOM up front so str_getcsv keys aren't polluted with it.
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

        // Scan the first chunk only — the metadata block lives at the top of
        // every Clarity export, and we don't need to look at the click rows.
        $head = substr($content, 0, self::HEAD_SCAN_BYTES);

        if (!$this->looksLikeText($head)) {
            throw new ClarityHeatmapException(ClarityHeatmapException::REASON_BINARY);
        }

        $lines = preg_split('/\r\n|\r|\n/', $head, 60);

        $markerHits = [];
        $dateRow = null;

        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }

            $row = str_getcsv($line);
            $key = trim($row[0] ?? '', " \t\"");

            if ($key === 'Date range' || $key === 'Date Range') {
                $dateRow = $row[1] ?? null;
                continue;
            }

            if (in_array($key, self::METADATA_MARKERS, true)) {
                $markerHits[strtolower($key)] = true;
            }
        }

        if ($dateRow === null) {
            throw new ClarityHeatmapException(ClarityHeatmapException::REASON_NO_DATE_RANGE);
        }

        $startDateStr = trim(explode(' - ', trim($dateRow, " \t\""))[0] ?? '');
        $parsed = DateTime::createFromFormat(self::DATE_FORMAT, $startDateStr);

        if ($parsed === false) {
            throw new ClarityHeatmapException(ClarityHeatmapException::REASON_INVALID_DATE, [
                'value' => $startDateStr,
            ]);
        }

        if (count($markerHits) < self::MIN_MARKERS_REQUIRED) {
            throw new ClarityHeatmapException(ClarityHeatmapException::REASON_SIGNATURE_MISMATCH, [
                'found_markers' => array_keys($markerHits),
                'required' => self::MIN_MARKERS_REQUIRED,
            ]);
        }

        return $parsed->format('Y-m-d');
    }

    /**
     * NUL bytes in the head almost always indicate an actual binary file
     * (PNG, PDF, XLSX). Real CSVs won't contain them.
     */
    private function looksLikeText(string $head): bool
    {
        return strpos($head, "\0") === false;
    }
}

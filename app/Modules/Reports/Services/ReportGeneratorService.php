<?php

namespace App\Modules\Reports\Services;

use App\Modules\Ai\Agents\PageAnalyst;
use App\Modules\Ai\Agents\ReportAnalyst;
use App\Modules\Ai\Contexts\Enums\AiContextType;
use App\Modules\Ai\Contexts\Enums\ContextTag;
use App\Modules\Ai\Contexts\Models\AiContext;
use App\Modules\Analytics\Clarity\Models\ClarityInsight;
use App\Modules\Analytics\Clarity\Heatmaps\Models\Heatmap;
use App\Modules\Analytics\GoogleAnalytics\Exceptions\GoogleAnalyticsException;
use App\Modules\Analytics\GoogleAnalytics\Queries\DateRange;
use App\Modules\Analytics\GoogleAnalytics\Services\GoogleAnalyticsQueryService;
use App\Modules\Reports\Enums\ReportStatusEnum;
use App\Modules\Reports\Models\Report;

class ReportGeneratorService
{
    public function generate(Report $report): Report
    {
        $report->update(['status' => ReportStatusEnum::GENERATING]);

        try {
            $provider       = config('ai.default', 'openai');
            $preset         = $this->loadPreset($report, $provider);
            $flavor         = $this->resolveFlavor($report, $preset);
            $agent          = $this->resolveAgent($flavor, $report->project);
            $prompt         = $this->buildPrompt($report, $flavor, $preset, $provider);

            $response = $agent->prompt($prompt, provider: $provider);

            $report->update([
                'content' => (string) $response,
                'ai_model_name' => $provider,
                'status' => ReportStatusEnum::COMPLETED,
            ]);
        } catch (\Throwable $e) {
            $report->update([
                'content' => 'Error: ' . $e->getMessage(),
                'status' => ReportStatusEnum::FAILED,
            ]);
        }

        return $report->refresh();
    }

    /**
     * Load the preset row once. The same row is used to pick a flavor (via its
     * tags) and to render its content into the prompt — fetching it twice was
     * wasteful.
     */
    protected function loadPreset(Report $report, string $provider): ?AiContext
    {
        if (! $report->preset) {
            return null;
        }

        return AiContext::active()
            ->ofType(AiContextType::PRESET)
            ->forModel($provider)
            ->where('slug', $report->preset)
            ->first();
    }

    /**
     * Flavor split so the agent + prompt builder stay in sync:
     *   page    — specific URL analysis (PageAnalyst)
     *   ga      — GA4-driven report (ReportAnalyst with GA system instructions)
     *   clarity — Clarity-driven report (ReportAnalyst, default)
     *
     * Keyed off preset tags, not a column on Report — adding a flavor needs
     * only a new tag, not a migration.
     */
    protected function resolveFlavor(Report $report, ?AiContext $preset): string
    {
        if ($report->page_url) {
            return 'page';
        }

        if ($preset && in_array(ContextTag::GA->value, $preset->tags ?? [], true)) {
            return 'ga';
        }

        return 'clarity';
    }

    protected function resolveAgent(string $flavor, $project)
    {
        return match ($flavor) {
            'page'  => PageAnalyst::make(),
            'ga'    => new ReportAnalyst($project, instructionsSlug: 'ga-analyst-instructions'),
            default => new ReportAnalyst($project),
        };
    }

    protected function buildPrompt(Report $report, string $flavor, ?AiContext $preset, string $provider): string
    {
        return match ($flavor) {
            'page'  => $this->buildPagePrompt($report, $preset, $provider),
            'ga'    => $this->buildGaPrompt($report, $preset, $provider),
            default => $this->buildClarityPrompt($report, $preset, $provider),
        };
    }

    // ── Page Analysis prompt ───────────────────────────────────────

    protected function buildPagePrompt(Report $report, ?AiContext $preset, string $provider): string
    {
        $parts = [];

        if ($preset) {
            $parts[] = $preset->context;
        }

        // The target URL + fetched page content.
        $parts[] = "\n## Target Page\nAnalyse the following URL: `{$report->page_url}`";

        $html = app(HtmlFetcherService::class)->fetch($report->page_url);

        if ($html) {
            $parts[] = "\n## Page Content\nBelow is the fetched content of the page:\n\n" . $html;
        } else {
            $parts[] = "\n## Note\nThe page could not be fetched. Provide recommendations based on the URL and the preset context.";
        }

        // Custom prompt
        if ($report->custom_prompt) {
            $parts[] = "\n## Additional Instructions\n" . $report->custom_prompt;
        }

        // Language instruction
        $this->appendLanguageInstruction($parts, $report);

        // Output format (shared between clarity & page)
        $this->appendOutputFormat($parts, $provider);

        return implode("\n", $parts);
    }

    // ── Clarity Report prompt ──────────────────────────────────────

    protected function buildClarityPrompt(Report $report, ?AiContext $preset, string $provider): string
    {
        $parts = [];

        if ($preset) {
            $parts[] = $preset->context;
        }

        // Custom prompt
        if ($report->custom_prompt) {
            $parts[] = "\n## Additional Instructions\n" . $report->custom_prompt;
        }

        // Clarity data for the date range
        $clarityData = ClarityInsight::where('project_id', $report->project_id)
            ->when($report->aspect_date_from, fn($q) => $q->where('date_from', '>=', $report->aspect_date_from))
            ->when($report->aspect_date_to, fn($q) => $q->where('date_to', '<=', $report->aspect_date_to))
            ->get();

        if ($clarityData->isNotEmpty()) {
            $parts[] = "\n## Clarity Data\n";
            foreach ($clarityData as $insight) {
                $parts[] = "### {$insight->metric_name}" .
                    ($insight->dimension1 ? " (Dimension: {$insight->dimension1})" : '') .
                    "\nDate range: {$insight->date_from->format('Y-m-d')} to {$insight->date_to->format('Y-m-d')}\n" .
                    "```json\n" . json_encode($insight->data, JSON_PRETTY_PRINT) . "\n```\n";
            }
        } else {
            $parts[] = "\n## Note\nNo Clarity data available for the selected date range ({$report->aspect_date_from?->format('Y-m-d')} to {$report->aspect_date_to?->format('Y-m-d')}). " .
                "Please provide a general analysis framework and recommendations based on the preset context.";
        }

        // Google Analytics data (only when toggled on)
        if ($report->include_ga && $report->project && $report->project->hasGoogleAnalytics()) {
            $gaContext = $this->renderGaContext($report);
            if ($gaContext !== null) {
                $parts[] = $gaContext;
            }
        }

        // Heatmap data (only when toggled on)
        if ($report->include_heatmaps) {
            $heatmaps = Heatmap::where('project_id', $report->project_id)
                ->when($report->aspect_date_from, fn($q) => $q->where('date', '>=', $report->aspect_date_from))
                ->when($report->aspect_date_to, fn($q) => $q->where('date', '<=', $report->aspect_date_to))
                ->get();

            if ($heatmaps->isNotEmpty()) {
                $heatmapContext = AiContext::active()
                    ->ofType(AiContextType::INSTRUCTION)
                    ->forModel($provider)
                    ->where('slug', 'heatmap-analysis')
                    ->first();

                if ($heatmapContext) {
                    $parts[] = "\n" . $heatmapContext->context;
                }

                foreach ($heatmaps as $heatmap) {
                    $parts[] = "### Heatmap: {$heatmap->filename} (Date: {$heatmap->date->format('Y-m-d')})\n" .
                        "```csv\n" . $heatmap->heatmap . "\n```\n";
                }
            }
        }

        // Language instruction
        $this->appendLanguageInstruction($parts, $report);

        // Output format
        $this->appendOutputFormat($parts, $provider);

        return implode("\n", $parts);
    }

    // ── Google Analytics report prompt ─────────────────────────────

    /**
     * GA-flavored report: no Clarity data, GA block is always included
     * (not gated on include_ga), no heatmaps. The GA-specific system context
     * is supplied by ReportAnalyst via its instructionsSlug.
     */
    protected function buildGaPrompt(Report $report, ?AiContext $preset, string $provider): string
    {
        $parts = [];

        if ($preset) {
            $parts[] = $preset->context;
        }

        if ($report->custom_prompt) {
            $parts[] = "\n## Additional Instructions\n" . $report->custom_prompt;
        }

        if ($report->project && $report->project->hasGoogleAnalytics()) {
            $gaContext = $this->renderGaContext($report);
            if ($gaContext !== null) {
                $parts[] = $gaContext;
            }
        } else {
            $parts[] = "\n## Note\nNo Google Analytics property is configured for this project. " .
                "Provide a general analytical framework based on the preset context.";
        }

        $this->appendLanguageInstruction($parts, $report);
        $this->appendOutputFormat($parts, $provider);

        return implode("\n", $parts);
    }

    // ── Google Analytics context ──────────────────────────────────

    protected function renderGaContext(Report $report): ?string
    {
        $range = $this->resolveGaRange($report);
        $project = $report->project;

        try {
            $svc = app(GoogleAnalyticsQueryService::class);

            $overview    = $svc->getTrafficOverview($project, $range);
            $previous    = $range->previousPeriod();
            $compare     = $svc->comparePeriod($project, $range, $previous, [
                'sessions', 'totalUsers', 'engagedSessions', 'screenPageViews',
                'engagementRate', 'bounceRate',
            ]);
            $topPages    = $svc->getTopPages($project, $range, 10);
            $acquisition = $svc->getAcquisitionBreakdown($project, $range);
            $devices     = $svc->getDeviceBreakdown($project, $range);
        } catch (GoogleAnalyticsException $e) {
            return "\n## Google Analytics\nGA data could not be loaded: {$e->getMessage()}\n";
        }

        $section = "\n## Google Analytics ({$range->signature()})\n";
        $section .= "\n### Traffic overview\n```json\n" . json_encode($overview->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n```\n";
        $section .= "\n### Period comparison (vs. {$previous->signature()})\n```json\n" . json_encode($compare->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n```\n";
        $section .= "\n### Top 10 pages\n```json\n" . json_encode($topPages->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n```\n";
        $section .= "\n### Acquisition by channel\n```json\n" . json_encode($acquisition->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n```\n";
        $section .= "\n### Device breakdown\n```json\n" . json_encode($devices->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n```\n";

        return $section;
    }

    private function resolveGaRange(Report $report): DateRange
    {
        if ($report->aspect_date_from && $report->aspect_date_to) {
            return DateRange::between($report->aspect_date_from, $report->aspect_date_to);
        }
        return DateRange::last7Days();
    }

    // ── Shared helpers ─────────────────────────────────────────────

    protected function appendLanguageInstruction(array &$parts, Report $report): void
    {
        $languageNames = ['en' => 'English', 'hu' => 'Hungarian'];
        $langName = $languageNames[$report->language ?? 'en'] ?? 'English';

        if (($report->language ?? 'en') !== 'en') {
            $parts[] = "\n## Language Instruction\nYou MUST write the entire report in {$langName}. All headings, analysis, recommendations, and conclusions must be in {$langName}.";
        }
    }

    protected function appendOutputFormat(array &$parts, string $provider): void
    {
        $outputFormat = AiContext::active()
            ->ofType(AiContextType::INSTRUCTION)
            ->forModel($provider)
            ->where('slug', 'output-format')
            ->first();

        if ($outputFormat) {
            $parts[] = "\n" . $outputFormat->context;
        }
    }
}

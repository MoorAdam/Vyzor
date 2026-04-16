<?php

namespace App\Services;

use App\Ai\Agents\PageAnalyst;
use App\Ai\Agents\ReportAnalyst;
use App\AiContextType;
use App\Models\AiContext;
use App\Models\ClarityInsight;
use App\Models\Heatmap;
use App\Models\Report;
use App\ReportStatusEnum;

class ReportGeneratorService
{
    public function generate(Report $report): Report
    {
        $report->update(['status' => ReportStatusEnum::GENERATING]);

        try {
            $prompt = $this->buildPrompt($report);
            $provider = config('ai.default', 'openai');

            // Use PageAnalyst for page URL reports, ReportAnalyst for Clarity reports.
            $agent = $report->page_url
                ? PageAnalyst::make()
                : ReportAnalyst::make();

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

    protected function buildPrompt(Report $report): string
    {
        // Branch into two distinct flows based on report type.
        return $report->page_url
            ? $this->buildPagePrompt($report)
            : $this->buildClarityPrompt($report);
    }

    // ── Page Analysis prompt ───────────────────────────────────────

    protected function buildPagePrompt(Report $report): string
    {
        $provider = config('ai.default', 'openai');
        $parts = [];

        // Preset context (page_analyser-tagged)
        if ($report->preset) {
            $preset = AiContext::active()
                ->ofType(AiContextType::PRESET)
                ->forModel($provider)
                ->where('slug', $report->preset)
                ->first();

            if ($preset) {
                $parts[] = $preset->context;
            }
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

    protected function buildClarityPrompt(Report $report): string
    {
        $provider = config('ai.default', 'openai');
        $parts = [];

        // Preset context (clarity-tagged)
        if ($report->preset) {
            $preset = AiContext::active()
                ->ofType(AiContextType::PRESET)
                ->forModel($provider)
                ->where('slug', $report->preset)
                ->first();

            if ($preset) {
                $parts[] = $preset->context;
            }
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

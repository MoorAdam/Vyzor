<?php

namespace Database\Seeders;

use App\AiContextType;
use App\ContextTag;
use App\Models\AiContext;
use Illuminate\Database\Seeder;

class AiContextSeeder extends Seeder
{
    public function run(): void
    {
        $contexts = [
            // System instructions
            [
                'name' => 'Report Analyst Instructions',
                'slug' => 'report-analyst-instructions',
                'type' => AiContextType::SYSTEM,
                'models' => ['all'],
                'tags' => [ContextTag::CLARITY->value],
                'icon' => 'robot',
                'label_color' => '#8b5cf6',
                'description' => 'Core system instructions for the AI report analyst agent.',
                'sort_order' => 0,
                'context' => file_get_contents(resource_path('ai-prompts/report-analyst-instructions.md')),
            ],

            // Instruction contexts
            [
                'name' => 'Heatmap Analysis',
                'slug' => 'heatmap-analysis',
                'type' => AiContextType::INSTRUCTION,
                'models' => ['all'],
                'tags' => [ContextTag::CLARITY->value],
                'icon' => 'fire',
                'label_color' => '#f97316',
                'description' => 'Instructions for analysing click/tap heatmap data from Microsoft Clarity.',
                'sort_order' => 0,
                'context' => file_get_contents(resource_path('ai-prompts/heatmap-analysis.md')),
            ],
            [
                'name' => 'Output Format',
                'slug' => 'output-format',
                'type' => AiContextType::INSTRUCTION,
                'models' => ['all'],
                'tags' => [ContextTag::CLARITY->value, ContextTag::PAGE_ANALYSER->value],
                'icon' => 'file-text',
                'label_color' => '#6b7280',
                'description' => 'Standard output format instructions for AI-generated reports.',
                'sort_order' => 1,
                'context' => file_get_contents(resource_path('ai-prompts/output-format.md')),
            ],

            // Preset contexts
            [
                'name' => 'Traffic Overview',
                'slug' => 'traffic-overview',
                'type' => AiContextType::PRESET,
                'models' => ['all'],
                'tags' => [ContextTag::CLARITY->value],
                'icon' => 'chart-line-up',
                'label_color' => '#3b82f6',
                'description' => 'Comprehensive overview of website traffic patterns, sessions, and user trends.',
                'sort_order' => 1,
                'context' => file_get_contents(resource_path('ai-prompts/presets/traffic-overview.md')),
            ],
            [
                'name' => 'UX Issues',
                'slug' => 'ux-issues',
                'type' => AiContextType::PRESET,
                'models' => ['all'],
                'tags' => [ContextTag::CLARITY->value],
                'icon' => 'warning',
                'label_color' => '#f43f5e',
                'description' => 'Analysis of UX signals like dead clicks, rage clicks, and quick backs.',
                'sort_order' => 2,
                'context' => file_get_contents(resource_path('ai-prompts/presets/ux-issues.md')),
            ],
            [
                'name' => 'Engagement Analysis',
                'slug' => 'engagement-analysis',
                'type' => AiContextType::PRESET,
                'models' => ['all'],
                'tags' => [ContextTag::CLARITY->value],
                'icon' => 'chart-bar',
                'label_color' => '#8b5cf6',
                'description' => 'Deep dive into user engagement metrics and content consumption patterns.',
                'sort_order' => 3,
                'context' => file_get_contents(resource_path('ai-prompts/presets/engagement-analysis.md')),
            ],
            [
                'name' => 'Device & Browser Analysis',
                'slug' => 'device-browser-analysis',
                'type' => AiContextType::PRESET,
                'models' => ['all'],
                'tags' => [ContextTag::CLARITY->value],
                'icon' => 'device-mobile',
                'label_color' => '#06b6d4',
                'description' => 'Breakdown of device, browser, and OS usage with compatibility insights.',
                'sort_order' => 4,
                'context' => file_get_contents(resource_path('ai-prompts/presets/device-browser-analysis.md')),
            ],
            [
                'name' => 'Content Performance',
                'slug' => 'content-performance',
                'type' => AiContextType::PRESET,
                'models' => ['all'],
                'tags' => [ContextTag::CLARITY->value],
                'icon' => 'article',
                'label_color' => '#f59e0b',
                'description' => 'Page-level performance analysis including top pages and engagement.',
                'sort_order' => 5,
                'context' => file_get_contents(resource_path('ai-prompts/presets/content-performance.md')),
            ],
            [
                'name' => 'Weekly Summary',
                'slug' => 'weekly-summary',
                'type' => AiContextType::PRESET,
                'models' => ['all'],
                'tags' => [ContextTag::CLARITY->value],
                'icon' => 'calendar-check',
                'label_color' => '#22c55e',
                'description' => 'Executive-style weekly summary covering all major performance aspects.',
                'sort_order' => 6,
                'context' => file_get_contents(resource_path('ai-prompts/presets/weekly-summary.md')),
            ],

            // ── Page Analyser ──────────────────────────────────────────

            // System instructions for page analysis
            [
                'name' => 'Page Analyst Instructions',
                'slug' => 'page-analyst-instructions',
                'type' => AiContextType::SYSTEM,
                'models' => ['all'],
                'tags' => [ContextTag::PAGE_ANALYSER->value],
                'icon' => 'browser',
                'label_color' => '#10b981',
                'description' => 'Core system instructions for the AI page analyst agent.',
                'sort_order' => 0,
                'context' => file_get_contents(resource_path('ai-prompts/page-analyst-instructions.md')),
            ],

            // Page analyser presets
            [
                'name' => 'SEO Audit',
                'slug' => 'seo-audit',
                'type' => AiContextType::PRESET,
                'models' => ['all'],
                'tags' => [ContextTag::PAGE_ANALYSER->value],
                'icon' => 'magnifying-glass',
                'label_color' => '#3b82f6',
                'description' => 'Search engine optimisation audit covering meta tags, headings, links, and structured data.',
                'sort_order' => 10,
                'context' => file_get_contents(resource_path('ai-prompts/presets/seo-audit.md')),
            ],
            [
                'name' => 'Accessibility Review',
                'slug' => 'accessibility-review',
                'type' => AiContextType::PRESET,
                'models' => ['all'],
                'tags' => [ContextTag::PAGE_ANALYSER->value],
                'icon' => 'wheelchair',
                'label_color' => '#8b5cf6',
                'description' => 'WCAG 2.1 AA compliance check with screen reader and keyboard navigation analysis.',
                'sort_order' => 11,
                'context' => file_get_contents(resource_path('ai-prompts/presets/accessibility-review.md')),
            ],
            [
                'name' => 'Page Performance',
                'slug' => 'page-performance',
                'type' => AiContextType::PRESET,
                'models' => ['all'],
                'tags' => [ContextTag::PAGE_ANALYSER->value],
                'icon' => 'lightning',
                'label_color' => '#f59e0b',
                'description' => 'Technical performance analysis including page weight, render-blocking resources, and optimisation.',
                'sort_order' => 12,
                'context' => file_get_contents(resource_path('ai-prompts/presets/page-performance.md')),
            ],
            [
                'name' => 'Content Quality',
                'slug' => 'content-quality',
                'type' => AiContextType::PRESET,
                'models' => ['all'],
                'tags' => [ContextTag::PAGE_ANALYSER->value],
                'icon' => 'text-aa',
                'label_color' => '#06b6d4',
                'description' => 'Content readability, messaging clarity, and structure assessment.',
                'sort_order' => 13,
                'context' => file_get_contents(resource_path('ai-prompts/presets/content-quality.md')),
            ],
            [
                'name' => 'Conversion Optimisation',
                'slug' => 'conversion-optimisation',
                'type' => AiContextType::PRESET,
                'models' => ['all'],
                'tags' => [ContextTag::PAGE_ANALYSER->value],
                'icon' => 'target',
                'label_color' => '#22c55e',
                'description' => 'CTA effectiveness, trust signals, and conversion funnel analysis with A/B test ideas.',
                'sort_order' => 14,
                'context' => file_get_contents(resource_path('ai-prompts/presets/conversion-optimisation.md')),
            ],
        ];

        foreach ($contexts as $context) {
            AiContext::updateOrCreate(
                ['slug' => $context['slug']],
                $context,
            );
        }
    }
}

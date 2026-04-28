<?php

namespace Database\Seeders;

use App\Modules\Ai\Contexts\Enums\AiContextType;
use App\Modules\Ai\Contexts\Enums\ContextTag;
use App\Modules\Ai\Contexts\Models\AiContext;
use Illuminate\Database\Seeder;

class AiContextSeeder extends Seeder
{
    public function run(): void
    {
        $contexts = [
            // System instructions
            [
                'name' => 'Riport elemző utasítások',
                'slug' => 'report-analyst-instructions',
                'type' => AiContextType::SYSTEM,
                'models' => ['all'],
                'tags' => [ContextTag::CLARITY->value],
                'icon' => 'robot',
                'label_color' => '#8b5cf6',
                'description' => 'Az AI riport elemző ágens alapvető rendszerutasításai.',
                'sort_order' => 0,
                'context' => file_get_contents(resource_path('ai-prompts/report-analyst-instructions.md')),
            ],

            // Instruction contexts
            [
                'name' => 'Hőtérkép elemzés',
                'slug' => 'heatmap-analysis',
                'type' => AiContextType::INSTRUCTION,
                'models' => ['all'],
                'tags' => [ContextTag::CLARITY->value],
                'icon' => 'fire',
                'label_color' => '#f97316',
                'description' => 'Utasítások a Microsoft Clarity kattintás/érintés hőtérkép adatainak elemzéséhez.',
                'sort_order' => 0,
                'context' => file_get_contents(resource_path('ai-prompts/heatmap-analysis.md')),
            ],
            [
                'name' => 'Kimeneti formátum',
                'slug' => 'output-format',
                'type' => AiContextType::INSTRUCTION,
                'models' => ['all'],
                'tags' => [ContextTag::CLARITY->value, ContextTag::PAGE_ANALYSER->value],
                'icon' => 'file-text',
                'label_color' => '#6b7280',
                'description' => 'Szabványos kimeneti formátum utasítások az AI által generált riportokhoz.',
                'sort_order' => 1,
                'context' => file_get_contents(resource_path('ai-prompts/output-format.md')),
            ],

            // Preset contexts
            [
                'name' => 'Forgalmi áttekintés',
                'slug' => 'traffic-overview',
                'type' => AiContextType::PRESET,
                'models' => ['all'],
                'tags' => [ContextTag::CLARITY->value],
                'icon' => 'chart-line-up',
                'label_color' => '#3b82f6',
                'description' => 'Átfogó áttekintés a weboldal forgalmi mintáiról, munkameneteiről és felhasználói trendjeiről.',
                'sort_order' => 1,
                'context' => file_get_contents(resource_path('ai-prompts/presets/traffic-overview.md')),
            ],
            [
                'name' => 'UX problémák',
                'slug' => 'ux-issues',
                'type' => AiContextType::PRESET,
                'models' => ['all'],
                'tags' => [ContextTag::CLARITY->value],
                'icon' => 'warning',
                'label_color' => '#f43f5e',
                'description' => 'UX jelek elemzése, mint halott kattintások, dühös kattintások és gyors visszalépések.',
                'sort_order' => 2,
                'context' => file_get_contents(resource_path('ai-prompts/presets/ux-issues.md')),
            ],
            [
                'name' => 'Elköteleződés elemzés',
                'slug' => 'engagement-analysis',
                'type' => AiContextType::PRESET,
                'models' => ['all'],
                'tags' => [ContextTag::CLARITY->value],
                'icon' => 'chart-bar',
                'label_color' => '#8b5cf6',
                'description' => 'Mélyreható elemzés a felhasználói elköteleződési mutatókról és tartalomfogyasztási mintákról.',
                'sort_order' => 3,
                'context' => file_get_contents(resource_path('ai-prompts/presets/engagement-analysis.md')),
            ],
            [
                'name' => 'Eszköz és böngésző elemzés',
                'slug' => 'device-browser-analysis',
                'type' => AiContextType::PRESET,
                'models' => ['all'],
                'tags' => [ContextTag::CLARITY->value],
                'icon' => 'device-mobile',
                'label_color' => '#06b6d4',
                'description' => 'Eszköz-, böngésző- és operációs rendszer használat bontása kompatibilitási betekintésekkel.',
                'sort_order' => 4,
                'context' => file_get_contents(resource_path('ai-prompts/presets/device-browser-analysis.md')),
            ],
            [
                'name' => 'Tartalom teljesítmény',
                'slug' => 'content-performance',
                'type' => AiContextType::PRESET,
                'models' => ['all'],
                'tags' => [ContextTag::CLARITY->value],
                'icon' => 'article',
                'label_color' => '#f59e0b',
                'description' => 'Oldalszintű teljesítményelemzés, beleértve a legnépszerűbb oldalakat és elköteleződést.',
                'sort_order' => 5,
                'context' => file_get_contents(resource_path('ai-prompts/presets/content-performance.md')),
            ],
            [
                'name' => 'Heti összefoglaló',
                'slug' => 'weekly-summary',
                'type' => AiContextType::PRESET,
                'models' => ['all'],
                'tags' => [ContextTag::CLARITY->value],
                'icon' => 'calendar-check',
                'label_color' => '#22c55e',
                'description' => 'Vezetői szintű heti összefoglaló az összes fő teljesítménymutató áttekintésével.',
                'sort_order' => 6,
                'context' => file_get_contents(resource_path('ai-prompts/presets/weekly-summary.md')),
            ],

            // ── Page Analyser ──────────────────────────────────────────

            // System instructions for page analysis
            [
                'name' => 'Oldalelemző utasítások',
                'slug' => 'page-analyst-instructions',
                'type' => AiContextType::SYSTEM,
                'models' => ['all'],
                'tags' => [ContextTag::PAGE_ANALYSER->value],
                'icon' => 'browser',
                'label_color' => '#10b981',
                'description' => 'Az AI oldalelemző ágens alapvető rendszerutasításai.',
                'sort_order' => 0,
                'context' => file_get_contents(resource_path('ai-prompts/page-analyst-instructions.md')),
            ],

            // Page analyser presets
            [
                'name' => 'SEO audit',
                'slug' => 'seo-audit',
                'type' => AiContextType::PRESET,
                'models' => ['all'],
                'tags' => [ContextTag::PAGE_ANALYSER->value],
                'icon' => 'magnifying-glass',
                'label_color' => '#3b82f6',
                'description' => 'Keresőoptimalizálási audit, amely kiterjed a meta címkékre, címsorokra, linkekre és strukturált adatokra.',
                'sort_order' => 10,
                'context' => file_get_contents(resource_path('ai-prompts/presets/seo-audit.md')),
            ],
            [
                'name' => 'Akadálymentességi felülvizsgálat',
                'slug' => 'accessibility-review',
                'type' => AiContextType::PRESET,
                'models' => ['all'],
                'tags' => [ContextTag::PAGE_ANALYSER->value],
                'icon' => 'wheelchair',
                'label_color' => '#8b5cf6',
                'description' => 'WCAG 2.1 AA megfelelőségi ellenőrzés képernyőolvasó és billentyűzetes navigáció elemzéssel.',
                'sort_order' => 11,
                'context' => file_get_contents(resource_path('ai-prompts/presets/accessibility-review.md')),
            ],
            [
                'name' => 'Oldal teljesítmény',
                'slug' => 'page-performance',
                'type' => AiContextType::PRESET,
                'models' => ['all'],
                'tags' => [ContextTag::PAGE_ANALYSER->value],
                'icon' => 'lightning',
                'label_color' => '#f59e0b',
                'description' => 'Technikai teljesítményelemzés, beleértve az oldal méretét, renderelést blokkoló erőforrásokat és optimalizálást.',
                'sort_order' => 12,
                'context' => file_get_contents(resource_path('ai-prompts/presets/page-performance.md')),
            ],
            [
                'name' => 'Tartalom minőség',
                'slug' => 'content-quality',
                'type' => AiContextType::PRESET,
                'models' => ['all'],
                'tags' => [ContextTag::PAGE_ANALYSER->value],
                'icon' => 'text-aa',
                'label_color' => '#06b6d4',
                'description' => 'Tartalom olvashatóság, üzenet érthetőség és struktúra értékelés.',
                'sort_order' => 13,
                'context' => file_get_contents(resource_path('ai-prompts/presets/content-quality.md')),
            ],
            [
                'name' => 'Konverzió optimalizálás',
                'slug' => 'conversion-optimisation',
                'type' => AiContextType::PRESET,
                'models' => ['all'],
                'tags' => [ContextTag::PAGE_ANALYSER->value],
                'icon' => 'target',
                'label_color' => '#22c55e',
                'description' => 'CTA hatékonyság, bizalmi jelek és konverziós tölcsér elemzés A/B teszt ötletekkel.',
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

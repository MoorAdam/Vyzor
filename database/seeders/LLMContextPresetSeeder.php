<?php

namespace Database\Seeders;

use App\Modules\Ai\Contexts\Models\LLMContextPreset;
use Illuminate\Database\Seeder;

class LLMContextPresetSeeder extends Seeder
{
    public function run(): void
    {
        $presets = [
            [
                'name' => 'Forgalmi áttekintés',
                'slug' => 'traffic-overview',
                'description' => 'Átfogó áttekintés a weboldal forgalmi mintáiról, munkameneteiről és felhasználói trendjeiről.',
                'label_color' => '#3b82f6',
                'icon' => 'chart-line-up',
                'sort_order' => 1,
                'context' => file_get_contents(resource_path('ai-prompts/presets/traffic-overview.md')),
            ],
            [
                'name' => 'UX problémák',
                'slug' => 'ux-issues',
                'description' => 'UX jelek elemzése, mint halott kattintások, dühös kattintások és gyors visszalépések.',
                'label_color' => '#f43f5e',
                'icon' => 'warning',
                'sort_order' => 2,
                'context' => file_get_contents(resource_path('ai-prompts/presets/ux-issues.md')),
            ],
            [
                'name' => 'Elköteleződés elemzés',
                'slug' => 'engagement-analysis',
                'description' => 'Mélyreható elemzés a felhasználói elköteleződési mutatókról és tartalomfogyasztási mintákról.',
                'label_color' => '#8b5cf6',
                'icon' => 'chart-bar',
                'sort_order' => 3,
                'context' => file_get_contents(resource_path('ai-prompts/presets/engagement-analysis.md')),
            ],
            [
                'name' => 'Eszköz és böngésző elemzés',
                'slug' => 'device-browser-analysis',
                'description' => 'Eszköz-, böngésző- és operációs rendszer használat bontása kompatibilitási betekintésekkel.',
                'label_color' => '#06b6d4',
                'icon' => 'device-mobile',
                'sort_order' => 4,
                'context' => file_get_contents(resource_path('ai-prompts/presets/device-browser-analysis.md')),
            ],
            [
                'name' => 'Tartalom teljesítmény',
                'slug' => 'content-performance',
                'description' => 'Oldalszintű teljesítményelemzés, beleértve a legnépszerűbb oldalakat és elköteleződést.',
                'label_color' => '#f59e0b',
                'icon' => 'article',
                'sort_order' => 5,
                'context' => file_get_contents(resource_path('ai-prompts/presets/content-performance.md')),
            ],
            [
                'name' => 'Heti összefoglaló',
                'slug' => 'weekly-summary',
                'description' => 'Vezetői szintű heti összefoglaló az összes fő teljesítménymutató áttekintésével.',
                'label_color' => '#22c55e',
                'icon' => 'calendar-check',
                'sort_order' => 6,
                'context' => file_get_contents(resource_path('ai-prompts/presets/weekly-summary.md')),
            ],
        ];

        foreach ($presets as $preset) {
            LLMContextPreset::updateOrCreate(
                ['slug' => $preset['slug']],
                $preset,
            );
        }
    }
}

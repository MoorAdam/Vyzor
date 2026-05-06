<?php

namespace App\Modules\Ai\Agents;

use App\Modules\Ai\Contexts\Enums\AiContextType;
use App\Modules\Ai\Contexts\Models\AiContext;
use App\Modules\Analytics\GoogleAnalytics\Services\GoogleAnalyticsQueryService;
use App\Modules\Analytics\GoogleAnalytics\Tools\GoogleAnalyticsTool;
use App\Modules\Projects\Models\Project;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Stringable;

#[Timeout(300)]
class ReportAnalyst implements Agent, HasTools
{
    use Promptable;

    public function __construct(
        public readonly ?Project $project = null,
        public readonly string $instructionsSlug = 'report-analyst-instructions',
    ) {}

    public function instructions(): Stringable|string
    {
        $context = AiContext::active()
            ->ofType(AiContextType::SYSTEM)
            ->forModel()
            ->where('slug', $this->instructionsSlug)
            ->first();

        return $context?->context ?? '';
    }

    public function tools(): iterable
    {
        // Return a plain array, not a Generator — the OpenAI gateway's
        // generateText() type-hints `array $tools` even though the HasTools
        // contract says `iterable`, so a `yield` here blows up at runtime.
        if ($this->project && $this->project->hasGoogleAnalytics()) {
            return [new GoogleAnalyticsTool(
                project: $this->project,
                query:   app(GoogleAnalyticsQueryService::class),
            )];
        }

        return [];
    }
}

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
    ) {}

    public function instructions(): Stringable|string
    {
        $context = AiContext::active()
            ->ofType(AiContextType::SYSTEM)
            ->forModel()
            ->where('slug', 'report-analyst-instructions')
            ->first();

        return $context?->context ?? '';
    }

    public function tools(): iterable
    {
        if ($this->project && $this->project->hasGoogleAnalytics()) {
            yield new GoogleAnalyticsTool(
                project: $this->project,
                query:   app(GoogleAnalyticsQueryService::class),
            );
        }
    }
}

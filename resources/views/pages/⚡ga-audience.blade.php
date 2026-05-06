<?php

use App\Modules\Analytics\GoogleAnalytics\Enums\GaDimension;
use App\Modules\Analytics\GoogleAnalytics\Enums\GaMetric;
use App\Modules\Analytics\GoogleAnalytics\Exceptions\GoogleAnalyticsException;
use App\Modules\Analytics\GoogleAnalytics\Queries\DateRange;
use App\Modules\Analytics\GoogleAnalytics\Queries\Filter;
use App\Modules\Analytics\GoogleAnalytics\Queries\ReportRequest;
use App\Modules\Analytics\GoogleAnalytics\Services\GoogleAnalyticsCache;
use App\Modules\Analytics\GoogleAnalytics\Services\GoogleAnalyticsQueryService;
use App\Modules\Projects\Models\Project;
use App\Modules\Users\Enums\PermissionEnum;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

new #[Layout('layouts.app')] class extends Component {
    public string $rangePreset = 'last_30';
    public string $dateFrom = '';
    public string $dateTo = '';

    /** Selected country for region/city drill-down — null = pick the top country automatically. */
    public ?string $selectedCountry = null;

    /**
     * Gates the GA fetch in with(). Initial render keeps this false so the
     * page paints instantly with a skeleton; wire:init flips it on the first
     * AJAX round-trip, after which every subsequent render fetches normally.
     */
    public bool $loaded = false;

    public function loadData(): void
    {
        $this->loaded = true;
    }

    public function mount(): void
    {
        abort_unless(
            auth()->user()->can('permission', [PermissionEnum::VIEW_GOOGLE_ANALYTICS, Project::current()]),
            403,
        );

        // Seed custom-mode inputs only — preset modes resolve in with().
        $today = now();
        $this->dateFrom = $today->copy()->subDays(29)->format('Y-m-d');
        $this->dateTo   = $today->format('Y-m-d');
    }

    #[On('current-project-changed')]
    public function onProjectChanged(): void
    {
        $this->selectedCountry = null;
    }

    public function selectCountry(string $country): void
    {
        $this->selectedCountry = $country;
    }

    public function forceRefresh(): void
    {
        $project = Project::current();
        if ($project && $project->hasGoogleAnalytics()) {
            app(GoogleAnalyticsCache::class)->forgetForProperty($project->gaPropertyResource());
        }
    }

    /**
     * Resolve the active range every render. See ga-overview.blade.php for the
     * detailed reasoning — computing in updated*() hooks races with with()
     * and can serve stale data on the first paint after a preset change.
     */
    private function resolveRange(): DateRange
    {
        return match ($this->rangePreset) {
            'today'    => DateRange::today(),
            'last_7'   => DateRange::lastNDays(7),
            'last_28'  => DateRange::lastNDays(28),
            'last_30'  => DateRange::lastNDays(30),
            'custom'   => DateRange::between($this->dateFrom, $this->dateTo),
            default    => DateRange::lastNDays(30),
        };
    }

    public function with(): array
    {
        $project = Project::current();
        $base = [
            'project'    => $project,
            'configured' => $project?->hasGoogleAnalytics() ?? false,
            'error'      => null,
            ...$this->viewHelpers(),
        ];

        if (!$project || !$project->hasGoogleAnalytics()) {
            return $base;
        }

        // Skeleton-first: skip the heavy 2-batch GA call on the initial paint.
        // wire:init triggers loadData() right after hydration, which flips
        // $loaded=true and brings us back here for the real fetch.
        if (!$this->loaded) {
            return $base;
        }

        try {
            $range = $this->resolveRange();
            $svc   = app(GoogleAnalyticsQueryService::class);

            // First batch — 6 reports that don't depend on the selected country.
            // GA caps each batch at 5 reports, so this becomes 2 chunks (5+1)
            // automatically inside runBatch().
            $first = $svc->runBatch($project, [
                'age' => new ReportRequest(
                    dimensions: [GaDimension::UserAgeBracket],
                    metrics:    [GaMetric::TotalUsers, GaMetric::Sessions, GaMetric::EngagementRate],
                    dateRange:  $range,
                    orderBy:    [['type' => 'dimension', 'name' => 'userAgeBracket', 'desc' => false]],
                    limit:      10,
                ),
                'gender' => new ReportRequest(
                    dimensions: [GaDimension::UserGender],
                    metrics:    [GaMetric::TotalUsers, GaMetric::Sessions, GaMetric::EngagementRate],
                    dateRange:  $range,
                    orderBy:    [['type' => 'metric', 'name' => 'totalUsers', 'desc' => true]],
                    limit:      5,
                ),
                'device' => new ReportRequest(
                    dimensions: [GaDimension::DeviceCategory],
                    metrics:    [GaMetric::Sessions, GaMetric::TotalUsers, GaMetric::EngagementRate, GaMetric::BounceRate],
                    dateRange:  $range,
                    orderBy:    [['type' => 'metric', 'name' => 'sessions', 'desc' => true]],
                    limit:      10,
                ),
                'resolution' => new ReportRequest(
                    dimensions: [GaDimension::ScreenResolution, GaDimension::DeviceCategory],
                    metrics:    [GaMetric::Sessions, GaMetric::TotalUsers, GaMetric::EngagementRate],
                    dateRange:  $range,
                    orderBy:    [['type' => 'metric', 'name' => 'sessions', 'desc' => true]],
                    limit:      10,
                ),
                'browser' => new ReportRequest(
                    dimensions: [GaDimension::Browser, GaDimension::OperatingSystem],
                    metrics:    [GaMetric::Sessions, GaMetric::EngagementRate],
                    dateRange:  $range,
                    orderBy:    [['type' => 'metric', 'name' => 'sessions', 'desc' => true]],
                    limit:      10,
                ),
                'geo' => new ReportRequest(
                    dimensions: [GaDimension::Country],
                    metrics:    [GaMetric::Sessions, GaMetric::TotalUsers, GaMetric::EngagedSessions],
                    dateRange:  $range,
                    orderBy:    [['type' => 'metric', 'name' => 'sessions', 'desc' => true]],
                    limit:      10,
                ),
            ]);

            $age       = $first['age'];
            $gender    = $first['gender'];
            $devices   = $first['device'];
            $res       = $first['resolution'];
            $browsers  = $first['browser'];
            $countries = $first['geo'];

            // Drill-down: if no country picked yet, default to the top one.
            $country = $this->selectedCountry
                ?? $countries->rows()->first()?->dimension('country');

            // Second batch — region + city, both filtered by the resolved country.
            // Done as a separate batch because the filter depends on the geo result.
            $regions = $cities = null;
            if ($country) {
                $countryFilter = Filter::equals('country', $country);
                $second = $svc->runBatch($project, [
                    'region:' . $country => new ReportRequest(
                        dimensions: [GaDimension::Region, GaDimension::Country],
                        metrics:    [GaMetric::Sessions, GaMetric::TotalUsers, GaMetric::EngagedSessions],
                        dateRange:  $range,
                        orderBy:    [['type' => 'metric', 'name' => 'sessions', 'desc' => true]],
                        limit:      10,
                        dimensionFilter: $countryFilter,
                    ),
                    'city:' . $country => new ReportRequest(
                        dimensions: [GaDimension::City, GaDimension::Country],
                        metrics:    [GaMetric::Sessions, GaMetric::TotalUsers, GaMetric::EngagedSessions],
                        dateRange:  $range,
                        orderBy:    [['type' => 'metric', 'name' => 'sessions', 'desc' => true]],
                        limit:      10,
                        dimensionFilter: $countryFilter,
                    ),
                ]);
                $regions = $second['region:' . $country];
                $cities  = $second['city:' . $country];
            }

            // Demographics health hint: GA4 hides demographic data without
            // Google Signals; if "unknown" dominates, surface that to the user.
            $ageTotal = $age->rows()->sum(fn ($r) => (int) $r->metric('totalUsers'));
            $ageUnknown = (int) $age->rows()->firstWhere(fn ($r) => $r->dimension('userAgeBracket') === 'unknown')?->metric('totalUsers');
            $demoLowConfidence = $ageTotal > 0 && ($ageUnknown / $ageTotal) > 0.5;

            return [
                ...$base,
                'range'              => $range,
                'rangeFrom'          => $range->startString(),
                'rangeTo'            => $range->endString(),
                'age'                => $age,
                'gender'             => $gender,
                'devices'            => $devices,
                'resolutions'        => $res,
                'browsers'           => $browsers,
                'countries'          => $countries,
                'regions'            => $regions,
                'cities'             => $cities,
                'country'            => $country,
                'demoLowConfidence'  => $demoLowConfidence,
            ];
        } catch (GoogleAnalyticsException $e) {
            return [...$base, 'error' => $e->getMessage()];
        }
    }

    /**
     * Closures exposed to the Blade view. Defined here so the Livewire single-file
     * compiler doesn't trip on top-level code after the class.
     */
    private function viewHelpers(): array
    {
        return [
            'formatNumber'  => fn ($n) => number_format((float) $n, 0, '.', ' '),
            'formatPercent' => fn (float $v) => number_format($v * 100, 1) . '%',

            // Compute the percentage of a metric value vs. the whole — used
            // for bar widths and text labels next to each row.
            'pctOfTotal' => function ($value, $total) {
                $t = (float) $total;
                if ($t <= 0) return 0.0;
                return ((float) $value) / $t * 100;
            },

            // Pretty-print "(not set)" / "unknown" buckets so the UI doesn't
            // look like raw API output.
            'cleanBucket' => function (?string $v): string {
                if ($v === null || $v === '') return '(unknown)';
                return match (strtolower($v)) {
                    '(not set)', 'unknown' => '(unknown)',
                    default => $v,
                };
            },
        ];
    }
};
?>

<div class="p-6 space-y-6" wire:init="loadData">
    <div>
        <x-ui.heading level="h1" size="xl">{{ __('Google Analytics — Audience') }}</x-ui.heading>
        <x-ui.description class="mt-1">
            {{ __('Who your visitors are: demographics, devices, resolutions, and where they come from.') }}
        </x-ui.description>
    </div>

    @php
        // Fallback closures — populated for empty-state branches that bypass with()'s viewHelpers().
        $formatNumber  ??= fn ($n) => number_format((float) $n, 0, '.', ' ');
        $formatPercent ??= fn (float $v) => number_format($v * 100, 1) . '%';
        $pctOfTotal    ??= fn ($v, $t) => ((float) $t) > 0 ? ((float) $v) / ((float) $t) * 100 : 0.0;
        $cleanBucket   ??= fn (?string $v) => $v ?: '(unknown)';
    @endphp

    @if (!$project)
        <x-ui.card>
            <x-ui.empty>
                <x-ui.empty.contents>
                    <x-ui.icon name="folder-open" class="size-10 text-neutral-300 dark:text-neutral-600" />
                    <x-ui.text>{{ __('Select a project from the header to view analytics.') }}</x-ui.text>
                </x-ui.empty.contents>
            </x-ui.empty>
        </x-ui.card>
    @elseif (!$configured)
        <x-ui.card>
            <x-ui.empty>
                <x-ui.empty.contents>
                    <x-ui.icon name="plug" class="size-10 text-neutral-300 dark:text-neutral-600" />
                    <x-ui.text>{{ __('No Google Analytics property configured for this project.') }}</x-ui.text>
                    <x-ui.button :href="route('project.edit', $project)" variant="outline" icon="gear" class="mt-2">
                        {{ __('Configure in project settings') }}
                    </x-ui.button>
                </x-ui.empty.contents>
            </x-ui.empty>
        </x-ui.card>
    @else
        {{-- Date range picker + refresh --}}
        <div class="flex items-center justify-between gap-4 flex-wrap">
            <x-ui.radio.group wire:model.live="rangePreset" direction="horizontal" variant="segmented">
                <x-ui.radio.item value="today" :label="__('Today')" />
                <x-ui.radio.item value="last_7" :label="__('Last 7 days')" />
                <x-ui.radio.item value="last_28" :label="__('Last 28 days')" />
                <x-ui.radio.item value="last_30" :label="__('Last 30 days')" />
                <x-ui.radio.item value="custom" :label="__('Custom')" />
            </x-ui.radio.group>

            <div class="flex items-center gap-3 flex-wrap">
                @if ($rangePreset === 'custom')
                    <div class="flex items-center gap-2">
                        <x-ui.label>{{ __('From') }}</x-ui.label>
                        <x-ui.input type="date" wire:model.live="dateFrom" class="w-44" />
                    </div>
                    <div class="flex items-center gap-2">
                        <x-ui.label>{{ __('To') }}</x-ui.label>
                        <x-ui.input type="date" wire:model.live="dateTo" class="w-44" />
                    </div>
                @else
                    <div class="text-xs text-neutral-500 dark:text-neutral-400 tabular-nums">
                        {{ $rangeFrom ?? $dateFrom }} → {{ $rangeTo ?? $dateTo }}
                    </div>
                @endif

                <span wire:loading.delay.short class="inline-flex items-center gap-1.5 text-xs text-neutral-500 dark:text-neutral-400">
                    <x-ui.icon name="circle-notch" class="size-3.5 animate-spin" />
                    {{ __('Refreshing...') }}
                </span>

                <x-ui.button type="button" wire:click="forceRefresh" wire:loading.attr="disabled"
                    variant="outline" color="neutral" size="sm" icon="arrow-clockwise">
                    {{ __('Refresh') }}
                </x-ui.button>
            </div>
        </div>

        @if ($error)
            <x-ui.card>
                <x-ui.error :messages="[$error]" />
            </x-ui.card>
        @elseif (!$loaded)
            {{-- Skeleton: mirrors the loaded layout (demographics row, devices row,
                 geography drill-down). wire:init triggers loadData() right after
                 hydration; on the next render this branch is replaced with data. --}}
            <div class="animate-pulse space-y-6" aria-busy="true" aria-label="{{ __('Loading analytics') }}">
                {{-- Demographics row --}}
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    @for ($c = 0; $c < 2; $c++)
                        <x-ui.card size="full">
                            <div class="h-5 w-32 bg-neutral-200 dark:bg-neutral-700 rounded mb-4"></div>
                            <div class="space-y-3">
                                @for ($r = 0; $r < 5; $r++)
                                    <div>
                                        <div class="flex items-center justify-between mb-1">
                                            <div class="h-3 w-24 bg-neutral-200 dark:bg-neutral-700 rounded"></div>
                                            <div class="h-3 w-16 bg-neutral-200 dark:bg-neutral-700 rounded"></div>
                                        </div>
                                        <div class="h-1.5 bg-neutral-100 dark:bg-neutral-800 rounded overflow-hidden">
                                            <div class="h-full bg-neutral-200 dark:bg-neutral-700 rounded" style="width: {{ rand(20, 90) }}%"></div>
                                        </div>
                                    </div>
                                @endfor
                            </div>
                        </x-ui.card>
                    @endfor
                </div>

                {{-- Devices + browsers row --}}
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <x-ui.card size="full">
                        <div class="h-5 w-44 bg-neutral-200 dark:bg-neutral-700 rounded mb-4"></div>
                        <div class="space-y-3">
                            @for ($r = 0; $r < 5; $r++)
                                <div>
                                    <div class="flex items-center justify-between mb-1">
                                        <div class="h-3 w-28 bg-neutral-200 dark:bg-neutral-700 rounded"></div>
                                        <div class="h-3 w-20 bg-neutral-200 dark:bg-neutral-700 rounded"></div>
                                    </div>
                                    <div class="h-1.5 bg-neutral-100 dark:bg-neutral-800 rounded overflow-hidden">
                                        <div class="h-full bg-neutral-200 dark:bg-neutral-700 rounded" style="width: {{ rand(20, 90) }}%"></div>
                                    </div>
                                </div>
                            @endfor
                        </div>
                    </x-ui.card>

                    <x-ui.card size="full">
                        <div class="h-5 w-48 bg-neutral-200 dark:bg-neutral-700 rounded mb-4"></div>
                        <div class="space-y-3">
                            @for ($r = 0; $r < 6; $r++)
                                <div class="flex items-center justify-between gap-3">
                                    <div class="flex gap-3">
                                        <div class="h-3 w-20 bg-neutral-200 dark:bg-neutral-700 rounded"></div>
                                        <div class="h-3 w-20 bg-neutral-200 dark:bg-neutral-700 rounded"></div>
                                    </div>
                                    <div class="flex gap-3">
                                        <div class="h-3 w-12 bg-neutral-200 dark:bg-neutral-700 rounded"></div>
                                        <div class="h-3 w-12 bg-neutral-200 dark:bg-neutral-700 rounded"></div>
                                    </div>
                                </div>
                            @endfor
                        </div>
                    </x-ui.card>
                </div>

                {{-- Geography card --}}
                <x-ui.card size="full">
                    <div class="h-5 w-32 bg-neutral-200 dark:bg-neutral-700 rounded mb-4"></div>
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        @for ($col = 0; $col < 3; $col++)
                            <div>
                                <div class="h-3 w-20 bg-neutral-200 dark:bg-neutral-700 rounded mb-3"></div>
                                <div class="space-y-2">
                                    @for ($r = 0; $r < 6; $r++)
                                        <div>
                                            <div class="flex items-center justify-between mb-1">
                                                <div class="h-3 w-24 bg-neutral-200 dark:bg-neutral-700 rounded"></div>
                                                <div class="h-3 w-12 bg-neutral-200 dark:bg-neutral-700 rounded"></div>
                                            </div>
                                            <div class="h-1 bg-neutral-100 dark:bg-neutral-800 rounded overflow-hidden">
                                                <div class="h-full bg-neutral-200 dark:bg-neutral-700 rounded" style="width: {{ rand(15, 85) }}%"></div>
                                            </div>
                                        </div>
                                    @endfor
                                </div>
                            </div>
                        @endfor
                    </div>
                </x-ui.card>
            </div>
        @else
            {{-- Demographics row: age + gender side by side --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                {{-- Age brackets --}}
                <x-ui.card size="full">
                    <x-ui.heading level="h2" size="md" class="mb-3">
                        <x-ui.icon name="users-three" class="size-4 inline mr-1 text-neutral-400" />
                        {{ __('Age brackets') }}
                    </x-ui.heading>
                    @if ($demoLowConfidence)
                        <div class="mb-3 flex items-start gap-2 text-xs text-amber-700 dark:text-amber-400 bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-900/50 rounded p-2">
                            <x-ui.icon name="info" class="size-4 shrink-0 mt-0.5" />
                            <span>{{ __('Most users have no demographic data — check that Google Signals is enabled in this GA4 property to get age/gender breakdowns.') }}</span>
                        </div>
                    @endif
                    @if ($age->isEmpty())
                        <x-ui.text class="text-neutral-500">{{ __('No demographic data.') }}</x-ui.text>
                    @else
                        @php $ageTotal = $age->rows()->sum(fn ($r) => (int) $r->metric('totalUsers')); @endphp
                        <div class="space-y-2">
                            @foreach ($age->rows() as $row)
                                @php
                                    $users = (int) $row->metric('totalUsers');
                                    $pct   = $pctOfTotal($users, $ageTotal);
                                @endphp
                                <div class="text-sm">
                                    <div class="flex items-baseline justify-between mb-1">
                                        <span class="font-medium text-neutral-700 dark:text-neutral-300">
                                            {{ $cleanBucket($row->dimension('userAgeBracket')) }}
                                        </span>
                                        <span class="tabular-nums text-xs text-neutral-500 dark:text-neutral-400">
                                            {{ $formatNumber($users) }} <span class="text-neutral-400 dark:text-neutral-500">({{ number_format($pct, 1) }}%)</span>
                                        </span>
                                    </div>
                                    <div class="h-1.5 bg-neutral-100 dark:bg-neutral-800 rounded overflow-hidden">
                                        <div class="h-full bg-blue-500 dark:bg-blue-600 rounded" style="width: {{ min(100, $pct) }}%"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </x-ui.card>

                {{-- Gender --}}
                <x-ui.card size="full">
                    <x-ui.heading level="h2" size="md" class="mb-3">
                        <x-ui.icon name="user" class="size-4 inline mr-1 text-neutral-400" />
                        {{ __('Gender') }}
                    </x-ui.heading>
                    @if ($gender->isEmpty())
                        <x-ui.text class="text-neutral-500">{{ __('No gender data.') }}</x-ui.text>
                    @else
                        @php $genderTotal = $gender->rows()->sum(fn ($r) => (int) $r->metric('totalUsers')); @endphp
                        <div class="space-y-2">
                            @foreach ($gender->rows() as $row)
                                @php
                                    $users = (int) $row->metric('totalUsers');
                                    $pct   = $pctOfTotal($users, $genderTotal);
                                    $label = strtolower($row->dimension('userGender') ?? '');
                                    $color = match ($label) {
                                        'male'   => 'bg-sky-500 dark:bg-sky-600',
                                        'female' => 'bg-pink-500 dark:bg-pink-600',
                                        default  => 'bg-neutral-400 dark:bg-neutral-600',
                                    };
                                @endphp
                                <div class="text-sm">
                                    <div class="flex items-baseline justify-between mb-1">
                                        <span class="font-medium text-neutral-700 dark:text-neutral-300 capitalize">
                                            {{ $cleanBucket($row->dimension('userGender')) }}
                                        </span>
                                        <span class="tabular-nums text-xs text-neutral-500 dark:text-neutral-400">
                                            {{ $formatNumber($users) }} <span class="text-neutral-400 dark:text-neutral-500">({{ number_format($pct, 1) }}%)</span>
                                        </span>
                                    </div>
                                    <div class="h-1.5 bg-neutral-100 dark:bg-neutral-800 rounded overflow-hidden">
                                        <div class="h-full {{ $color }} rounded" style="width: {{ min(100, $pct) }}%"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </x-ui.card>
            </div>

            {{-- Devices + resolutions row --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                {{-- Top resolutions --}}
                <x-ui.card size="full">
                    <x-ui.heading level="h2" size="md" class="mb-3">
                        <x-ui.icon name="frame-corners" class="size-4 inline mr-1 text-neutral-400" />
                        {{ __('Top screen resolutions') }}
                    </x-ui.heading>
                    @if ($resolutions->isEmpty())
                        <x-ui.text class="text-neutral-500">{{ __('No resolution data.') }}</x-ui.text>
                    @else
                        @php $resTotal = $resolutions->rows()->sum(fn ($r) => (int) $r->metric('sessions')); @endphp
                        <div class="space-y-2">
                            @foreach ($resolutions->rows() as $row)
                                @php
                                    $sessions = (int) $row->metric('sessions');
                                    $pct      = $pctOfTotal($sessions, $resTotal);
                                @endphp
                                <div class="text-sm">
                                    <div class="flex items-baseline justify-between mb-1">
                                        <span class="font-mono text-xs text-neutral-700 dark:text-neutral-300">
                                            {{ $row->dimension('screenResolution') ?: '(unknown)' }}
                                        </span>
                                        <span class="text-xs text-neutral-500 dark:text-neutral-400 capitalize tabular-nums">
                                            {{ $row->dimension('deviceCategory') ?: '—' }}
                                            <span class="text-neutral-300 dark:text-neutral-600 mx-1">·</span>
                                            {{ $formatNumber($sessions) }}
                                            <span class="text-neutral-400 dark:text-neutral-500">({{ number_format($pct, 1) }}%)</span>
                                        </span>
                                    </div>
                                    <div class="h-1.5 bg-neutral-100 dark:bg-neutral-800 rounded overflow-hidden">
                                        <div class="h-full bg-emerald-500 dark:bg-emerald-600 rounded" style="width: {{ min(100, $pct) }}%"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </x-ui.card>

                {{-- Browsers + OS --}}
                <x-ui.card size="full">
                    <x-ui.heading level="h2" size="md" class="mb-3">
                        <x-ui.icon name="browser" class="size-4 inline mr-1 text-neutral-400" />
                        {{ __('Browsers & operating systems') }}
                    </x-ui.heading>
                    @if ($browsers->isEmpty())
                        <x-ui.text class="text-neutral-500">{{ __('No browser data.') }}</x-ui.text>
                    @else
                        <table class="w-full text-sm">
                            <thead class="text-xs text-neutral-500 dark:text-neutral-400 uppercase tracking-wide">
                                <tr>
                                    <th class="text-left py-2 font-medium">{{ __('Browser') }}</th>
                                    <th class="text-left py-2 font-medium">{{ __('OS') }}</th>
                                    <th class="text-right py-2 font-medium">{{ __('Sessions') }}</th>
                                    <th class="text-right py-2 font-medium">{{ __('Engagement') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-black/5 dark:divide-white/5">
                                @foreach ($browsers->rows() as $row)
                                    <tr>
                                        <td class="py-2 font-medium text-neutral-700 dark:text-neutral-300">{{ $row->dimension('browser') }}</td>
                                        <td class="py-2 text-neutral-500 dark:text-neutral-400">{{ $row->dimension('operatingSystem') }}</td>
                                        <td class="py-2 text-right tabular-nums">{{ $formatNumber($row->metric('sessions')) }}</td>
                                        <td class="py-2 text-right tabular-nums">{{ $formatPercent((float) $row->metric('engagementRate')) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </x-ui.card>
            </div>

            {{-- Geo drill-down --}}
            <x-ui.card size="full">
                <div class="flex items-center justify-between mb-3 flex-wrap gap-3">
                    <x-ui.heading level="h2" size="md">
                        <x-ui.icon name="globe" class="size-4 inline mr-1 text-neutral-400" />
                        {{ __('Geography') }}
                    </x-ui.heading>
                    @if ($country)
                        <div class="flex items-center gap-1.5 text-xs text-neutral-500 dark:text-neutral-400">
                            <span>{{ __('Drill-down for:') }}</span>
                            <span class="font-medium text-neutral-700 dark:text-neutral-300">{{ $country }}</span>
                            <button type="button" wire:click="$set('selectedCountry', null)" class="text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-300" title="{{ __('Reset') }}">
                                <x-ui.icon name="x-circle" class="size-3.5" />
                            </button>
                        </div>
                    @endif
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {{-- Countries --}}
                    <div>
                        <h3 class="text-xs font-semibold text-neutral-500 dark:text-neutral-400 uppercase tracking-wide mb-2">{{ __('Countries') }}</h3>
                        @php $countriesTotal = $countries->rows()->sum(fn ($r) => (int) $r->metric('sessions')); @endphp
                        <div class="space-y-1.5">
                            @foreach ($countries->rows() as $row)
                                @php
                                    $sessions = (int) $row->metric('sessions');
                                    $pct = $pctOfTotal($sessions, $countriesTotal);
                                    $cName = $row->dimension('country') ?: '(unknown)';
                                    $isSelected = $cName === $country;
                                @endphp
                                <button type="button"
                                    wire:click="selectCountry(@js($cName))"
                                    @class([
                                        'w-full text-left text-sm group rounded px-2 py-1.5 -mx-2 transition-colors',
                                        'bg-blue-50 dark:bg-blue-950/30' => $isSelected,
                                        'hover:bg-neutral-50 dark:hover:bg-neutral-800/50' => !$isSelected,
                                    ])>
                                    <div class="flex items-baseline justify-between mb-1">
                                        <span @class([
                                            'font-medium',
                                            'text-blue-700 dark:text-blue-300' => $isSelected,
                                            'text-neutral-700 dark:text-neutral-300' => !$isSelected,
                                        ])>{{ $cName }}</span>
                                        <span class="text-xs tabular-nums text-neutral-500 dark:text-neutral-400">
                                            {{ $formatNumber($sessions) }}
                                            <span class="text-neutral-400 dark:text-neutral-500">({{ number_format($pct, 1) }}%)</span>
                                        </span>
                                    </div>
                                    <div class="h-1 bg-neutral-100 dark:bg-neutral-800 rounded overflow-hidden">
                                        <div @class([
                                            'h-full rounded',
                                            'bg-blue-500 dark:bg-blue-400' => $isSelected,
                                            'bg-neutral-400 dark:bg-neutral-500' => !$isSelected,
                                        ]) style="width: {{ min(100, $pct) }}%"></div>
                                    </div>
                                </button>
                            @endforeach
                        </div>
                    </div>

                    {{-- Regions of selected country --}}
                    <div>
                        <h3 class="text-xs font-semibold text-neutral-500 dark:text-neutral-400 uppercase tracking-wide mb-2">
                            {{ __('Regions') }}
                            @if ($country) <span class="text-neutral-400 dark:text-neutral-500">— {{ $country }}</span> @endif
                        </h3>
                        @if (!$country || !$regions || $regions->isEmpty())
                            <x-ui.text class="text-neutral-500 text-sm">{{ __('No region data for this country.') }}</x-ui.text>
                        @else
                            @php $regionsTotal = $regions->rows()->sum(fn ($r) => (int) $r->metric('sessions')); @endphp
                            <div class="space-y-1.5">
                                @foreach ($regions->rows() as $row)
                                    @php
                                        $sessions = (int) $row->metric('sessions');
                                        $pct = $pctOfTotal($sessions, $regionsTotal);
                                    @endphp
                                    <div class="text-sm">
                                        <div class="flex items-baseline justify-between mb-1">
                                            <span class="text-neutral-700 dark:text-neutral-300">
                                                {{ $cleanBucket($row->dimension('region')) }}
                                            </span>
                                            <span class="text-xs tabular-nums text-neutral-500 dark:text-neutral-400">
                                                {{ $formatNumber($sessions) }}
                                                <span class="text-neutral-400 dark:text-neutral-500">({{ number_format($pct, 1) }}%)</span>
                                            </span>
                                        </div>
                                        <div class="h-1 bg-neutral-100 dark:bg-neutral-800 rounded overflow-hidden">
                                            <div class="h-full bg-violet-500 dark:bg-violet-400 rounded" style="width: {{ min(100, $pct) }}%"></div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    {{-- Cities of selected country --}}
                    <div>
                        <h3 class="text-xs font-semibold text-neutral-500 dark:text-neutral-400 uppercase tracking-wide mb-2">
                            {{ __('Cities') }}
                            @if ($country) <span class="text-neutral-400 dark:text-neutral-500">— {{ $country }}</span> @endif
                        </h3>
                        @if (!$country || !$cities || $cities->isEmpty())
                            <x-ui.text class="text-neutral-500 text-sm">{{ __('No city data for this country.') }}</x-ui.text>
                        @else
                            @php $citiesTotal = $cities->rows()->sum(fn ($r) => (int) $r->metric('sessions')); @endphp
                            <div class="space-y-1.5">
                                @foreach ($cities->rows() as $row)
                                    @php
                                        $sessions = (int) $row->metric('sessions');
                                        $pct = $pctOfTotal($sessions, $citiesTotal);
                                    @endphp
                                    <div class="text-sm">
                                        <div class="flex items-baseline justify-between mb-1">
                                            <span class="text-neutral-700 dark:text-neutral-300">
                                                {{ $cleanBucket($row->dimension('city')) }}
                                            </span>
                                            <span class="text-xs tabular-nums text-neutral-500 dark:text-neutral-400">
                                                {{ $formatNumber($sessions) }}
                                                <span class="text-neutral-400 dark:text-neutral-500">({{ number_format($pct, 1) }}%)</span>
                                            </span>
                                        </div>
                                        <div class="h-1 bg-neutral-100 dark:bg-neutral-800 rounded overflow-hidden">
                                            <div class="h-full bg-orange-500 dark:bg-orange-400 rounded" style="width: {{ min(100, $pct) }}%"></div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </x-ui.card>

            <div class="text-xs text-neutral-400 dark:text-neutral-500">
                {{ __('Data refreshes per range recency. Demographics require Google Signals to be enabled in GA4.') }}
            </div>
        @endif
    @endif
</div>

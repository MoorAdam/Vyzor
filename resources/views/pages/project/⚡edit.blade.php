<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Attributes\On;
use App\Models\User;
use App\Modules\Analytics\GoogleAnalytics\Exceptions\GoogleAnalyticsException;
use App\Modules\Analytics\GoogleAnalytics\Queries\DateRange;
use App\Modules\Analytics\GoogleAnalytics\Services\GoogleAnalyticsClient;
use App\Modules\Analytics\GoogleAnalytics\Services\PropertyDiscoveryService;
use App\Modules\Projects\Models\Project;
use App\Modules\Users\Enums\PermissionEnum;
use App\Modules\Projects\Enums\ProjectStatusEnum;
use App\Modules\Users\Enums\UserRoleEnum;
use Google\Analytics\Data\V1beta\DateRange as GaDateRange;
use Google\Analytics\Data\V1beta\Metric;
use Google\Analytics\Data\V1beta\RunReportRequest;

new #[Layout('layouts.app')] class extends Component {
    public Project $project;

    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('nullable|string')]
    public string $description = '';

    #[Validate('required|exists:users,id')]
    public ?string $customer_id = null;

    #[Validate('required|string')]
    public string $status = 'active';

    #[Validate('required|url|max:255')]
    public string $domain = '';

    #[Validate('nullable|string')]
    public string $clarity_api_key = '';

    #[Validate('nullable|string|max:255')]
    public string $ga_property_id = '';

    #[Validate('array')]
    public array $collaborator_ids = [];

    public bool $isOwner = false;

    // Inline result for the "Test connection" button — not persisted.
    public ?array $gaTestResult = null;

    /** Whether the user wants to type a property ID manually instead of picking from the discovered list. */
    public bool $gaManualEntry = false;

    public function mount(Project $project): void
    {
        abort_unless(auth()->user()->can('permission', [PermissionEnum::EDIT_PROJECT_DETAILS, $project]), 403);

        $this->project = $project;
        $user = auth()->user();
        $hasEditAll = User::permissionsForRoles($user->roles ?? [])
            ->contains(PermissionEnum::EDIT_ALL_PROJECTS->value);
        $this->isOwner = $user->isAdmin() || $hasEditAll || $project->permission?->isOwner($user);
        $this->name = $project->name;
        $this->description = $project->description ?? '';
        $this->customer_id = (string) $project->customer_id;
        $this->status = $project->status->value;
        $this->domain = $project->domain;
        $this->clarity_api_key = $project->clarity_api_key ?? '';
        $this->ga_property_id = $project->ga_property_id ?? '';
        $this->collaborator_ids = $project->permission?->collaborators ?? [];
    }

    /**
     * Returns the list of accessible properties + service-account email + any
     * discovery error, all wrapped so `with()` can pass it to the Blade view.
     *
     * @return array{
     *     properties: list<array{property:string,propertyName:string,account:string,accountName:string,label:string}>,
     *     serviceAccountEmail: ?string,
     *     discoveryError: ?string
     * }
     */
    private function gaDiscovery(): array
    {
        $properties = [];
        $email      = null;
        $error      = null;

        try {
            $properties = app(PropertyDiscoveryService::class)->listAccessibleProperties();
        } catch (GoogleAnalyticsException $e) {
            $error = $e->getMessage();
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        // The service account email lives in the JSON keyfile; pluck it for display.
        $jsonPath = config('services.google_analytics.service_account_path');
        if (is_string($jsonPath) && is_readable($jsonPath)) {
            $decoded = json_decode((string) file_get_contents($jsonPath), true);
            $email   = $decoded['client_email'] ?? null;
        } else {
            $rawJson = config('services.google_analytics.service_account_json');
            if (is_string($rawJson) && $rawJson !== '') {
                $decoded = json_decode($rawJson, true);
                $email   = $decoded['client_email'] ?? null;
            }
        }

        return [
            'properties'          => $properties,
            'serviceAccountEmail' => $email,
            'discoveryError'      => $error,
        ];
    }

    public function refreshGaProperties(): void
    {
        // Force-bust the discovery cache so newly granted properties show up.
        app(PropertyDiscoveryService::class)->listAccessibleProperties(forceRefresh: true);
        $this->gaTestResult = ['ok' => true, 'message' => __('Property list refreshed.')];
    }

    public function testGaConnection(): void
    {
        $raw = trim($this->ga_property_id);
        if ($raw === '') {
            $this->gaTestResult = ['ok' => false, 'message' => __('Enter a GA property ID first.')];
            return;
        }

        $resource = str_starts_with($raw, 'properties/') ? $raw : 'properties/' . $raw;

        try {
            $req = (new RunReportRequest())
                ->setProperty($resource)
                ->setDateRanges([new GaDateRange(['start_date' => '7daysAgo', 'end_date' => 'today'])])
                ->setMetrics([new Metric(['name' => 'sessions'])])
                ->setLimit(1);

            $response = app(GoogleAnalyticsClient::class)->runReport($req);
            $sessions = count($response->getRows()) > 0
                ? (int) $response->getRows()[0]->getMetricValues()[0]->getValue()
                : 0;

            $this->gaTestResult = [
                'ok' => true,
                'message' => __('Connected. Last 7 days: :n sessions.', ['n' => $sessions]),
            ];
        } catch (GoogleAnalyticsException $e) {
            $this->gaTestResult = ['ok' => false, 'message' => $e->getMessage()];
        } catch (\Throwable $e) {
            $this->gaTestResult = ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function updatedDomain(): void
    {
        $trimmed = trim($this->domain);

        if ($trimmed !== '' && !preg_match('#^https?://#i', $trimmed)) {
            $this->domain = 'https://' . $trimmed;
        }
    }

    #[On('customer-created')]
    public function onCustomerCreated(int $id): void
    {
        $this->customer_id = (string) $id;
        $this->dispatch('close-modal', id: 'create-customer-modal');
    }

    public function updateProject(): void
    {
        $this->updatedDomain();
        $this->validate();

        $this->project->update([
            'name' => $this->name,
            'description' => $this->description ?: null,
            'customer_id' => $this->customer_id,
            'status' => $this->status,
            'domain' => $this->domain,
            'clarity_api_key' => $this->clarity_api_key ?: null,
            'ga_property_id' => $this->ga_property_id ?: null,
        ]);

        if ($this->isOwner) {
            $this->project->permission?->update([
                'collaborators' => array_map('intval', $this->collaborator_ids),
            ]);
        }

        $this->redirect(route('projects'), navigate: true);
    }

    public function with(): array
    {
        return [
            'customers' => User::whereJsonContains('roles', UserRoleEnum::CUSTOMER->value)->get(),
            'statuses' => ProjectStatusEnum::cases(),
            'availableCollaborators' => User::query()
                ->where(function ($q) {
                    $q->whereJsonDoesntContain('roles', UserRoleEnum::CUSTOMER->value)
                        ->orWhereNull('roles');
                })
                ->where('id', '!=', $this->project->permission?->owner_id)
                ->get(),
            ...$this->gaDiscovery(),
        ];
    }
};
?>

<div>
    <div class="flex items-center justify-center p-6">
        <form wire:submit="updateProject">
            <x-ui.fieldset :label="__('Edit Project')" class="w-150">
                <x-ui.field required>
                    <x-ui.label>{{ __('Project Name') }}</x-ui.label>
                    <x-ui.input wire:model.blur="name" :placeholder="__('Project name...')" :invalid="$errors->has('name')" />
                    <x-ui.error name="name" />
                </x-ui.field>

                <x-ui.field>
                    <x-ui.label>{{ __('Description') }}</x-ui.label>
                    <x-ui.input wire:model.blur="description" :placeholder="__('Project description...')" />
                    <x-ui.error name="description" />
                </x-ui.field>

                <x-ui.field required>
                    <x-ui.label>{{ __('Customer') }}</x-ui.label>
                    <div class="flex items-center gap-2">
                        <div class="flex-1" wire:key="customer-select-{{ $customer_id }}">
                            <x-ui.select wire:model="customer_id" :placeholder="__('Choose a customer...')" searchable>
                                @foreach ($customers as $customer)
                                    <x-ui.select.option :value="$customer->id">{{ $customer->name }}</x-ui.select.option>
                                @endforeach
                            </x-ui.select>
                        </div>
                        <x-ui.modal.trigger id="create-customer-modal">
                            <x-ui.button type="button" variant="outline" icon="plus" size="sm">{{ __('New') }}</x-ui.button>
                        </x-ui.modal.trigger>
                    </div>
                    <x-ui.error name="customer_id" />
                </x-ui.field>

                <x-ui.field>
                    <x-ui.label>{{ __('Collaborators') }}</x-ui.label>
                    <x-ui.select wire:model="collaborator_ids" :placeholder="__('Add collaborators...')" multiple pillbox searchable :disabled="!$isOwner">
                        @foreach ($availableCollaborators as $collab)
                            <x-ui.select.option :value="$collab->id">{{ $collab->name }}</x-ui.select.option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>

                <x-ui.field required>
                    <x-ui.label>{{ __('Status') }}</x-ui.label>
                    <x-ui.radio.group wire:model.blur="status" variant="segmented" direction="horizontal">
                        @foreach ($statuses as $statusOption)
                            <x-ui.radio.item :value="strtolower($statusOption->name)" :label="$statusOption->label()"
                                :color="$statusOption->hex()" />
                        @endforeach
                    </x-ui.radio.group>
                    <x-ui.error name="status" />
                </x-ui.field>

                <x-ui.field required>
                    <x-ui.label>{{ __('Domain') }}</x-ui.label>
                    <x-ui.input wire:model.blur="domain" :placeholder="__('example.com')" :invalid="$errors->has('domain')" />
                    <x-ui.error name="domain" />
                </x-ui.field>

                <x-ui.field id="clarity_api_key">
                    <x-ui.label>{{ __('Clarity API Key') }}</x-ui.label>
                    <x-ui.input wire:model.blur="clarity_api_key" :placeholder="__('Paste Clarity API token...')" :invalid="$errors->has('clarity_api_key')" />
                    <x-ui.error name="clarity_api_key" />
                </x-ui.field>

                <x-ui.field id="ga_property_id">
                    <x-ui.label>{{ __('Google Analytics Property ID') }}</x-ui.label>
                    @if (!empty($properties) && !$gaManualEntry)
                        {{-- Discovered properties: dropdown picker --}}
                        <div class="flex items-center gap-2">
                            <div class="flex-1">
                                <x-ui.select wire:model.live="ga_property_id" :placeholder="__('Choose a property...')" searchable>
                                    <x-ui.select.option value="">{{ __('— None —') }}</x-ui.select.option>
                                    @foreach ($properties as $p)
                                        <x-ui.select.option :value="$p['property']">{{ $p['label'] }}</x-ui.select.option>
                                    @endforeach
                                </x-ui.select>
                            </div>
                            <x-ui.button type="button" wire:click="testGaConnection" variant="outline" color="neutral" icon="plug">{{ __('Test connection') }}</x-ui.button>
                        </div>
                        <div class="mt-1 flex items-center gap-3 text-xs text-neutral-500 dark:text-neutral-400">
                            <span>{{ __(':n properties accessible', ['n' => count($properties)]) }}</span>
                            <button type="button" wire:click="refreshGaProperties" class="hover:text-neutral-700 dark:hover:text-neutral-300 underline-offset-2 hover:underline">
                                {{ __('Refresh list') }}
                            </button>
                            <span class="text-neutral-300 dark:text-neutral-600">·</span>
                            <button type="button" wire:click="$toggle('gaManualEntry')" class="hover:text-neutral-700 dark:hover:text-neutral-300 underline-offset-2 hover:underline">
                                {{ __('Enter manually') }}
                            </button>
                        </div>
                    @else
                        {{-- Empty discovery, error, or manual entry mode: free-text input --}}
                        <div class="flex items-center gap-2">
                            <div class="flex-1">
                                <x-ui.input wire:model.blur="ga_property_id" :placeholder="__('properties/123456789 or just 123456789')" :invalid="$errors->has('ga_property_id')" />
                            </div>
                            <x-ui.button type="button" wire:click="testGaConnection" variant="outline" color="neutral" icon="plug">{{ __('Test connection') }}</x-ui.button>
                        </div>
                        @if (!empty($properties))
                            <div class="mt-1 text-xs">
                                <button type="button" wire:click="$toggle('gaManualEntry')" class="text-neutral-500 dark:text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-300 underline-offset-2 hover:underline">
                                    {{ __('← Pick from accessible properties') }}
                                </button>
                            </div>
                        @endif
                    @endif
                    <x-ui.error name="ga_property_id" />

                    {{-- Discovery error / onboarding hint --}}
                    @if (empty($properties) && $serviceAccountEmail)
                        <div class="mt-2 flex items-start gap-2 text-sm text-neutral-600 dark:text-neutral-400">
                            <x-ui.icon name="info" class="size-4 shrink-0 mt-0.5" />
                            <div>
                                <p>{{ __('No GA properties accessible to the service account yet. Add this email as Viewer to a GA4 property:') }}</p>
                                <code class="mt-1 inline-block px-2 py-1 bg-neutral-100 dark:bg-neutral-800 rounded text-xs">{{ $serviceAccountEmail }}</code>
                            </div>
                        </div>
                    @endif
                    @if ($discoveryError)
                        <div class="mt-2 text-xs text-red-600 dark:text-red-400">
                            {{ $discoveryError }}
                        </div>
                    @endif

                    {{-- Test result --}}
                    @if ($gaTestResult)
                        <div @class([
                            'mt-2 flex items-start gap-2 text-sm',
                            'text-emerald-700 dark:text-emerald-400' => $gaTestResult['ok'],
                            'text-red-700 dark:text-red-400' => !$gaTestResult['ok'],
                        ])>
                            <x-ui.icon :name="$gaTestResult['ok'] ? 'check-circle' : 'warning-circle'" class="size-4 shrink-0 mt-0.5" />
                            <span>{{ $gaTestResult['message'] }}</span>
                        </div>
                    @endif
                    @if ($project->ga_last_verified_at)
                        <div class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                            {{ __('Last verified:') }} {{ $project->ga_last_verified_at->diffForHumans() }}
                        </div>
                    @endif
                </x-ui.field>

                <x-ui.separator class="my-4" hidden horizontal />

                <x-ui.field class="mt-4">
                    <x-ui.button type="submit" variant="primary" color="blue" icon="floppy-disk">{{ __('Save Changes') }}</x-ui.button>
                </x-ui.field>
            </x-ui.fieldset>
        </form>
    </div>

    <x-ui.modal id="create-customer-modal" :title="__('New Customer')" width="md">
        <livewire:customer-form />
    </x-ui.modal>
</div>

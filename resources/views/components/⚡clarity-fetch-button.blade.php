<?php

use Livewire\Component;
use App\Models\ClarityFetchCounter;

new class extends Component {

    public function getCounterProperty()
    {
        $projectId = session('current_project_id');
        return ClarityFetchCounter::where('project_id', $projectId)
            ->where('date', now()->toDateString())
            ->first();
    }

    public function getCounterMaxProperty()
    {
        return config('services.clarity.fetch_daily_limit');
    }

    public function fetchInfo(): void
    {
        $projectId = session('current_project_id');

        if (!$projectId) {
            $this->error = 'No project selected. Please select a project first.';
            return;
        }

        $this->error = null;

        $exitCode = Artisan::call('app:fetch-clarity', [
            'project' => $projectId,
        ]);

        if ($exitCode !== 0) {
            $this->error = trim(Artisan::output());
            return;
        }

        $this->redirect(route('clarity.snapshot'), navigate: true);
    }
};
?>

<div class="flex items-center gap-2">
    <x-ui.label class="opacity-50">{{ ($this->counter->fetch_count ?? 0) }} / {{ $this->counterMax }}</x-ui.label>
    <x-ui.button variant="primary" icon="arrow-clockwise" wire:click="fetchInfo" wire:loading.attr="loading">
        Fetch info
    </x-ui.button>
</div>
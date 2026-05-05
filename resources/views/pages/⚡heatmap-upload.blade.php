<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\WithFileUploads;
use App\Modules\Analytics\Clarity\Heatmaps\Exceptions\ClarityHeatmapException;
use App\Modules\Analytics\Clarity\Heatmaps\Models\Heatmap;
use App\Modules\Analytics\Clarity\Heatmaps\Services\ClarityHeatmapValidator;
use App\Modules\Users\Enums\PermissionEnum;

new #[Layout('layouts.app')] class extends Component {
    use WithFileUploads;

    #[Validate('required|file|max:2048')]
    public $csvFile = null;

    public ?string $errorMessage = null;
    public ?string $successMessage = null;

    public function mount(): void
    {
        abort_unless(auth()->user()->can('permission', [PermissionEnum::UPLOAD_HEATMAP, \App\Modules\Projects\Models\Project::current()]), 403);
    }

    public function updatedCsvFile(): void
    {
        $this->errorMessage = null;

        if (!$this->csvFile) {
            return;
        }

        $ext = strtolower($this->csvFile->getClientOriginalExtension());
        if (!in_array($ext, ['csv', 'txt'])) {
            $this->addError('csvFile', __('The file must be a CSV or TXT file.'));
            $this->csvFile = null;
            return;
        }

        // Run the Clarity signature check at upload time so the user sees the
        // problem immediately, not after pressing Save.
        try {
            $content = @file_get_contents($this->csvFile->getRealPath());
            if ($content === false) {
                $this->errorMessage = __('Could not read the uploaded file. Please try again.');
                $this->csvFile = null;
                return;
            }
            app(ClarityHeatmapValidator::class)->validate(
                $this->csvFile->getClientOriginalName(),
                $content,
            );
        } catch (ClarityHeatmapException $e) {
            $this->errorMessage = $this->mapValidationError($e);
            $this->csvFile = null;
        } catch (\Throwable $e) {
            $this->errorMessage = __('Failed to validate heatmap: :msg', ['msg' => $e->getMessage()]);
            $this->csvFile = null;
        }
    }

    public function save(): void
    {
        $this->errorMessage = null;
        $this->successMessage = null;

        $projectId = session('current_project_id');

        if (!$projectId) {
            $this->errorMessage = __('Please select a project first.');
            return;
        }

        if (!$this->csvFile) {
            $this->errorMessage = __('Please select a file first.');
            return;
        }

        $this->validate();

        try {
            $ext = strtolower($this->csvFile->getClientOriginalExtension());
            if (!in_array($ext, ['csv', 'txt'])) {
                $this->errorMessage = __('The file must be a CSV or TXT file.');
                return;
            }

            $content = @file_get_contents($this->csvFile->getRealPath());
            if ($content === false) {
                $this->errorMessage = __('Could not read the uploaded file. Please try again.');
                return;
            }
            $filename = $this->csvFile->getClientOriginalName();

            // Re-validate at save time. The temp file could have been swapped
            // between updatedCsvFile and save (and the validator is the single
            // source of truth for both signature and parsed date).
            $date = app(ClarityHeatmapValidator::class)->validate($filename, $content);

            Heatmap::create([
                'project_id' => $projectId,
                'user_id' => auth()->id(),
                'filename' => $filename,
                'heatmap' => $content,
                'date' => $date,
            ]);

            $this->redirect(route('heatmaps'), navigate: true);
        } catch (ClarityHeatmapException $e) {
            $this->errorMessage = $this->mapValidationError($e);
        } catch (\Throwable $e) {
            $this->errorMessage = __('Failed to upload heatmap: ') . $e->getMessage();
        }
    }

    /**
     * Translate a stable error code from the validator into a user-facing
     * sentence. Kept inside the component so the wording can be tuned per
     * surface without touching the service.
     */
    private function mapValidationError(ClarityHeatmapException $e): string
    {
        return match ($e->reason) {
            ClarityHeatmapException::REASON_EMPTY => __('The uploaded file is empty.'),
            ClarityHeatmapException::REASON_TOO_LARGE => __('The uploaded file is larger than the :n KB limit.', [
                'n' => $e->context['limit_kb'] ?? 2048,
            ]),
            ClarityHeatmapException::REASON_BINARY => __('The uploaded file looks like a binary file (e.g. an image or PDF), not a Clarity CSV export.'),
            ClarityHeatmapException::REASON_NO_DATE_RANGE => __('Could not find a valid "Date range" row in the CSV — only Microsoft Clarity heatmap exports are accepted here.'),
            ClarityHeatmapException::REASON_INVALID_DATE => __('The "Date range" row does not match the format Clarity emits (e.g. "12/01/2024 12:00 AM"). Please re-export the heatmap from Clarity.'),
            ClarityHeatmapException::REASON_SIGNATURE_MISMATCH => __('This does not look like a Clarity heatmap export. Expected metadata fields (URL, Heatmap type, Device type, Filters, …) are missing.'),
            default => __('The file could not be validated as a Clarity heatmap.'),
        };
    }
};
?>

<div
    x-data="{ fileSelected: false, uploadError: null }"
    x-on:livewire-upload-error.window="uploadError = '{{ __('File upload failed. Please re-select the file and try again.') }}'"
    x-on:livewire-upload-start.window="uploadError = null"
>
    <div class="flex items-center justify-center p-6">
        <form wire:submit="save">
            <x-ui.fieldset :label="__('Upload Heatmap CSV')" class="w-150">

                @if ($errorMessage)
                    <x-ui.card size="full" class="border-red-300 dark:border-red-700 bg-red-50 dark:bg-red-950">
                        <div class="text-sm text-red-600 dark:text-red-400">{{ $errorMessage }}</div>
                    </x-ui.card>
                @endif

                <template x-if="uploadError">
                    <x-ui.card size="full" class="border-red-300 dark:border-red-700 bg-red-50 dark:bg-red-950">
                        <div class="text-sm text-red-600 dark:text-red-400" x-text="uploadError"></div>
                    </x-ui.card>
                </template>

                <x-ui.field required>
                    <x-ui.label>{{ __('CSV File') }}</x-ui.label>
                    <input type="file" wire:model="csvFile" accept=".csv,.txt"
                            x-on:change="fileSelected = $event.target.files.length > 0; uploadError = null"
                            class="block w-full text-sm text-neutral-500 dark:text-neutral-400
                            file:mr-4 file:py-2 file:px-4
                            file:rounded file:border-0
                            file:text-sm file:font-semibold
                            file:bg-neutral-100 file:text-neutral-700
                            dark:file:bg-neutral-800 dark:file:text-neutral-300
                            hover:file:bg-neutral-200 dark:hover:file:bg-neutral-700
                            cursor-pointer" />
                    <x-ui.error name="csvFile" />

                    <div wire:loading wire:target="csvFile" class="mt-2">
                        <x-ui.text class="text-sm text-neutral-500">{{ __('Uploading file...') }}</x-ui.text>
                    </div>
                </x-ui.field>

                @if ($csvFile)
                    <x-ui.card size="full" class="bg-neutral-50 dark:bg-neutral-900">
                        <div class="text-sm text-neutral-600 dark:text-neutral-400">
                            <span class="font-medium">{{ __('Selected:') }}</span> {{ $csvFile->getClientOriginalName() }}
                            ({{ number_format($csvFile->getSize() / 1024, 1) }} KB)
                        </div>
                    </x-ui.card>
                @endif

                <x-ui.separator class="my-4" hidden horizontal />

                <x-ui.field class="mt-4">
                    <x-ui.button type="submit" variant="primary" color="blue" icon="upload-simple" wire:target="save" x-bind:disabled="!fileSelected">
                        {{ __('Upload Heatmap') }}
                    </x-ui.button>
                </x-ui.field>
            </x-ui.fieldset>
        </form>
    </div>
</div>
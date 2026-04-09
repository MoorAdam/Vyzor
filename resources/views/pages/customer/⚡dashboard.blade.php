<?php

use Livewire\Component;
use Livewire\Attributes\Layout;

new #[Layout('layouts.customer')] class extends Component
{
    //
};
?>

<div>
    <x-ui.empty class="h-64">
        <x-ui.empty.contents>
            <x-ui.icon name="house" class="size-10 text-neutral-300 dark:text-neutral-600" />
            <x-ui.description class="text-lg!">{{ __('Customer dashboard coming soon.') }}</x-ui.description>
        </x-ui.empty.contents>
    </x-ui.empty>
</div>

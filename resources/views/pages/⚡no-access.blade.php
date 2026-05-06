<?php

use Livewire\Component;
use Livewire\Attributes\Layout;

new #[Layout('layouts.app', ['layoutVariant' => 'bare'])] class extends Component {
    //
};
?>

<div>
    <div class="h-screen flex flex-col items-center justify-center px-6">
        <div class="mb-10 flex flex-col items-center gap-3">
            <x-ui.logo class="h-16 w-auto pl-2 text-black dark:text-white" />
            <div class="text-center">
                <h1 class="text-3xl font-semibold tracking-tight text-neutral-900 dark:text-white">Vyzor</h1>
                <span class="text-[10px] text-neutral-400 dark:text-neutral-500 tracking-widest uppercase">By Morgens</span>
            </div>
        </div>

        <div class="w-100 max-w-full text-center space-y-4">
            <x-ui.icon name="lock-key" class="size-10 text-neutral-300 dark:text-neutral-600 mx-auto" />
            <x-ui.heading level="h2" size="lg">{{ __('No access') }}</x-ui.heading>
            <x-ui.description>
                {{ __('Your account has no permissions assigned. Please contact an administrator to gain access.') }}
            </x-ui.description>

            <div class="text-xs text-neutral-500 dark:text-neutral-400 mt-4">
                {{ __('Signed in as') }}
                <span class="font-medium text-neutral-700 dark:text-neutral-200">{{ auth()->user()->name }}</span>
            </div>

            <form method="POST" action="{{ route('logout') }}" class="pt-2">
                @csrf
                <x-ui.button type="submit" variant="primary" icon="sign-out">{{ __('Logout') }}</x-ui.button>
            </form>
        </div>

        <div class="flex items-center gap-1 mt-8">
            <form method="POST" action="{{ route('locale.switch', 'en') }}">
                @csrf
                <button type="submit" class="px-2 py-1 text-xs font-semibold rounded transition-colors {{ app()->getLocale() === 'en' ? 'bg-neutral-900 text-white dark:bg-neutral-100 dark:text-neutral-900' : 'text-neutral-500 dark:text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-300' }}">
                    EN
                </button>
            </form>
            <form method="POST" action="{{ route('locale.switch', 'hu') }}">
                @csrf
                <button type="submit" class="px-2 py-1 text-xs font-semibold rounded transition-colors {{ app()->getLocale() === 'hu' ? 'bg-neutral-900 text-white dark:bg-neutral-100 dark:text-neutral-900' : 'text-neutral-500 dark:text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-300' }}">
                    HU
                </button>
            </form>
        </div>
    </div>
</div>

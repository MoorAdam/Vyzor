<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>{{ $title ?? config('app.name') }}</title>

    @livewireScriptConfig
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @livewireStyles
</head>

<body class="bg-white dark:bg-neutral-950">
    <x-ui.layout variant="header-sidebar">

        <x-ui.layout.header class="px-6">
            <x-slot:brand>
                <x-ui.brand href="/customer/dashboard" name="Vyzor" />
            </x-slot:brand>

            <div class="ml-auto flex items-center gap-6">
                <div class="flex items-center gap-1">
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

                <x-ui.separator class="my-1" vertical />

                <x-ui.dropdown position="bottom-end">
                    <x-slot:button>
                        <x-ui.avatar class="cursor-pointer" />
                    </x-slot:button>

                    <x-slot:menu class="min-w-48">
                        <div class="px-2 py-1.5 text-sm text-neutral-500 dark:text-neutral-400">
                            {{ __('Signed in as') }}
                            <span
                                class="block font-medium text-neutral-900 dark:text-neutral-100">{{ auth()->user()->customerProfile?->company_name ?? auth()->user()->email }}</span>
                        </div>

                        <x-ui.dropdown.separator />

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <x-ui.dropdown.item icon="sign-out" variant="danger" type="submit" as="button">
                                {{ __('Logout') }}
                            </x-ui.dropdown.item>
                        </form>
                    </x-slot:menu>
                </x-ui.dropdown>
            </div>
        </x-ui.layout.header>

        <x-ui.sidebar>
            <x-ui.navlist>
                <x-ui.navlist.group :label="__('General')">
                    <x-ui.navlist.item disabled :label="__('Dashboard')" icon="house" href="/customer/dashboard" />
                    <x-ui.navlist.item disabled :label="__('Projects')" icon="check-square" href="/customer/projects" />
                </x-ui.navlist.group>
            </x-ui.navlist>
        </x-ui.sidebar>

        <x-ui.layout.main>
            <div>
                {{ $slot }}
            </div>
        </x-ui.layout.main>
    </x-ui.layout>

</body>

</html>

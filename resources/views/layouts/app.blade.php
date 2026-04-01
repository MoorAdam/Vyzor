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
    @if (($layoutVariant ?? 'header-sidebar') === 'bare')
        <x-ui.layout variant="bare">
            {{ $slot }}
        </x-ui.layout>
    @else
        <x-ui.layout variant="header-sidebar">

            <x-ui.layout.header class="px-6">
                <x-slot:brand>
                    <x-ui.brand href="/dashboard" name="Vyzor" />
                </x-slot:brand>

                <div class="ml-auto flex items-center gap-6">
                    <div class="flex items-center gap-2">
                        <span
                            class="text-sm font-medium text-neutral-500 dark:text-neutral-400 whitespace-nowrap">Project:</span>
                        <livewire:project-select />
                        <x-ui.button variant="outline" color="neutral" icon="plus-circle" class="rounded-lg" size="icon"
                            href="/new-project" />
                    </div>

                    <x-ui.separator class="my-1" vertical />

                    <x-ui.dropdown position="bottom-end">
                        <x-slot:button>
                            <x-ui.avatar class="cursor-pointer" />
                        </x-slot:button>

                        <x-slot:menu class="min-w-48">
                            <div class="px-2 py-1.5 text-sm text-neutral-500 dark:text-neutral-400">
                                Signed in as
                                <span
                                    class="block font-medium text-neutral-900 dark:text-neutral-100">{{ auth()->user()->name }}</span>
                            </div>

                            <x-ui.dropdown.separator />

                            <div>
                                <x-ui.dropdown.item icon="gear" disabled>
                                    Settings
                                </x-ui.dropdown.item>
                            </div>

                            <x-ui.dropdown.separator />

                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <x-ui.dropdown.item icon="sign-out" variant="danger" type="submit" as="button">
                                    Logout
                                </x-ui.dropdown.item>
                            </form>
                        </x-slot:menu>
                    </x-ui.dropdown>
                </div>
            </x-ui.layout.header>

            <x-ui.sidebar>
                <x-ui.navlist>
                    <x-ui.navlist.group label="General">
                        {{-- <x-ui.navlist.item disabled label="Dashboard" icon="house" href="/" /> --}}
                        <x-ui.navlist.item label="Projects" icon="check-square" href="/projects" />
                    </x-ui.navlist.group>
                    <x-ui.navlist.group label="Project">
                        {{-- <x-ui.navlist.item disabled label="Overview" icon="chart-bar" href="/dashboard" /> --}}
                        <x-ui.navlist.group label="Clarity" variant="compact">
                            <x-ui.navlist.item label="Snapshot" icon="camera" href="/clarity/snapshot"
                                :active="request()->is('clarity/snapshot')" />
                            <x-ui.navlist.item label="Trends" icon="chart-line-up" href="/clarity/trends"
                                :active="request()->is('clarity/trends')" />
                        </x-ui.navlist.group>
                        {{-- <x-ui.navlist.item disabled label="ContentSquare" icon="cube" /> --}}
                        {{-- <x-ui.navlist.item disabled label="Notes" icon="note"/> --}}
                        <x-ui.navlist.group label="Reports" variant="compact">
                            <x-ui.navlist.item label="New Report" icon="plus-circle" href="/ai-reports"
                                :active="request()->is('ai-reports')" />
                            <x-ui.navlist.item label="All Reports" icon="book-bookmark" href="/reports"
                                :active="request()->is('reports') || request()->is('reports/*')" />
                        </x-ui.navlist.group>
                        <x-ui.navlist.item disabled label="Pezentations" icon="projector-screen-chart" />
                    </x-ui.navlist.group>
                    <x-ui.navlist.group label="System">
                        <x-ui.navlist.item label="Users | Customers" icon="users" href="/users" />
                        <x-ui.navlist.item disabled label="Settings" icon="gear" href="/settings" />
                    </x-ui.navlist.group>
                </x-ui.navlist>
            </x-ui.sidebar>

            <x-ui.layout.main>
                <div>
                    {{ $slot }}
                </div>
            </x-ui.layout.main>
        </x-ui.layout>
    @endif

</body>

</html>
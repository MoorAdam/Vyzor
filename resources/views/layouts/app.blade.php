<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>{{ $title ?? config('app.name') }}</title>

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

                <x-ui.navbar>
                    <x-ui.navbar.item label="New Project" href="/new-project" />
                </x-ui.navbar>

                <div class="ml-auto flex items-center gap-4">
                    <x-ui.avatar/>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <x-ui.button type="submit">Logout</x-ui.button>
                    </form>
                </div>
            </x-ui.layout.header>

            <x-ui.sidebar>
                <x-ui.navlist>
                    <x-ui.navlist.item label="Dashboard" icon="home" href="/dashboard" />
                    <x-ui.navlist.item label="Users" icon="users" href="/users" />
                    <x-ui.navlist.item label="Settings" icon="cog" href="/settings" />
                </x-ui.navlist>
            </x-ui.sidebar>

            <x-ui.layout.main>
                <div>
                    {{ $slot }}
                </div>
            </x-ui.layout.main>
        </x-ui.layout>
    @endif
    @livewireScripts
</body>

</html>
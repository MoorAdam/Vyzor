<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>{{ $title ?? config('app.name') }}</title>

    <link rel="icon" href="/favicon.svg?v=2" type="image/svg+xml">
    <link rel="alternate icon" href="/favicon.ico">

    <script>
        (function() {
            function applyTheme() {
                var theme = localStorage.getItem('theme');
                if (theme === 'dark' || (!theme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                    document.documentElement.classList.add('dark');
                } else {
                    document.documentElement.classList.remove('dark');
                }
            }
            applyTheme();
            document.addEventListener('livewire:navigated', applyTheme);
        })();
    </script>

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
                    <x-ui.brand href="/clarity/snapshot" name="Vyzor" />
                </x-slot:brand>

                <div class="ml-auto flex items-center gap-6">
                    <div class="flex items-center gap-2">
                        <span
                            class="text-sm font-medium text-neutral-500 dark:text-neutral-400 whitespace-nowrap">{{ __('Project:') }}</span>
                        <livewire:project-select />
                        <x-ui.button variant="outline" color="neutral" icon="plus-circle" class="rounded-lg" size="icon"
                            href="/new-project" :disabled="auth()->user()->cannot('permission', App\PermissionEnum::CREATE_PROJECT)" />
                    </div>

                    <x-ui.separator class="my-1" vertical />

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

                    <button
                        x-data="{ dark: document.documentElement.classList.contains('dark') }"
                        x-on:click="dark = !dark; document.documentElement.classList.toggle('dark'); localStorage.setItem('theme', dark ? 'dark' : 'light')"
                        class="p-2 rounded-lg text-neutral-500 dark:text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-300 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition-colors cursor-pointer"
                        :title="dark ? '{{ __('Switch to light mode') }}' : '{{ __('Switch to dark mode') }}'"
                    >
                        <x-ui.icon x-show="!dark" name="moon" variant="mini" class="size-5" />
                        <x-ui.icon x-show="dark" name="sun" variant="mini" class="size-5" />
                    </button>

                    <x-ui.separator class="my-1" vertical />

                    <x-ui.dropdown position="bottom-end">
                        <x-slot:button>
                            <x-ui.avatar class="cursor-pointer" />
                        </x-slot:button>

                        <x-slot:menu class="min-w-48">
                            <div class="px-2 py-1.5 text-sm text-neutral-500 dark:text-neutral-400">
                                {{ __('Signed in as') }}
                                <span
                                    class="block font-medium text-neutral-900 dark:text-neutral-100">{{ auth()->user()->name }}
                                </span>
                            </div>

                            <x-ui.dropdown.separator />

                            <form method="POST" action="{{ route('logout') }}" class="w-full">
                                @csrf
                                <x-ui.dropdown.item icon="sign-out" variant="danger" class="w-full" type="submit" as="button">
                                    {{ __('Logout') }}
                                </x-ui.dropdown.item>
                            </form>
                        </x-slot:menu>
                    </x-ui.dropdown>
                </div>
            </x-ui.layout.header>

            @php $currentProject = App\Models\Project::current(); @endphp
            <x-ui.sidebar>
                <x-ui.navlist>
                    <x-ui.navlist.group :label="__('General')">
                        <x-ui.navlist.item :label="__('Projects')" icon="check-square" href="/projects" :disabled="auth()->user()->cannot('permission', App\PermissionEnum::VIEW_PROJECTS)" />
                    </x-ui.navlist.group>
                    <x-ui.navlist.group :label="__('Project')">
                        <x-ui.navlist.group :label="__('Clarity')" collapsable>
                            <x-ui.navlist.item :label="__('Snapshot')" icon="camera" href="/clarity/snapshot"
                                :active="request()->is('clarity/snapshot')" :disabled="auth()->user()->cannot('permission', [App\PermissionEnum::VIEW_CLARITY_SNAPSHOTS, $currentProject])" />
                            <x-ui.navlist.item :label="__('Trends')" icon="chart-line-up" href="/clarity/trends"
                                :active="request()->is('clarity/trends')" :disabled="auth()->user()->cannot('permission', [App\PermissionEnum::VIEW_CLARITY_TRENDS, $currentProject])" />
                        </x-ui.navlist.group>
                        <x-ui.navlist.group :label="__('Reports')" collapsable>
                            <x-ui.navlist.item :label="__('New Report')" icon="plus-circle" href="/ai-reports"
                                :active="request()->is('ai-reports')" :disabled="auth()->user()->cannot('permission', [App\PermissionEnum::CREATE_REPORT, $currentProject])" />
                            <x-ui.navlist.item :label="__('All Reports')" icon="book-bookmark" href="/reports"
                                :active="request()->is('reports') || request()->is('reports/*')" :disabled="auth()->user()->cannot('permission', [App\PermissionEnum::VIEW_REPORTS, $currentProject])" />
                        </x-ui.navlist.group>
                        <x-ui.navlist.group :label="__('Heatmaps')" collapsable>
                            <x-ui.navlist.item :label="__('Upload')" icon="upload-simple" href="/heatmaps/upload"
                                :active="request()->is('heatmaps/upload')" :disabled="auth()->user()->cannot('permission', [App\PermissionEnum::UPLOAD_HEATMAP, $currentProject])" />
                            <x-ui.navlist.item :label="__('All Heatmaps')" icon="fire" href="/heatmaps"
                                :active="request()->is('heatmaps') && !request()->is('heatmaps/*')" :disabled="auth()->user()->cannot('permission', [App\PermissionEnum::VIEW_HEATMAPS, $currentProject])" />
                        </x-ui.navlist.group>
                        <x-ui.navlist.item disabled :label="__('Presentations')" icon="projector-screen-chart" />
                    </x-ui.navlist.group>
                    <x-ui.navlist.group :label="__('System')">
                        <x-ui.navlist.item :label="__('Users | Customers')" icon="users" href="/users" :disabled="auth()->user()->cannot('permission', App\PermissionEnum::VIEW_USERS)" />
                        <x-ui.navlist.group :label="__('Settings')" icon="gear" collapsable href="/settings" :active="request()->is('settings')">
                            <x-ui.navlist.item :label="__('Contexts')" icon="tag" href="/settings/contexts"
                                :active="request()->is('settings/contexts')" :disabled="auth()->user()->cannot('permission', App\PermissionEnum::VIEW_CONTEXTS)" />
                        </x-ui.navlist.group>
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
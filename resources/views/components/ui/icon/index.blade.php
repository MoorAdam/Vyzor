@props([
    'name' => null,
    'variant' => null,
    'asButton' => false,
])

@php
    // Detect icon set
    $isPhosphorSet = str($name)->startsWith(['ps:', 'phosphor:']);
    $isHeroiconsSet = ! $isPhosphorSet;

    // Normalize icon name safely
    $iconName = $isPhosphorSet
        ? str($name)->after(':')
        : $name;


    // Resolve component name
    $componentName = match (true) {
        $isPhosphorSet => match ($variant) {
            'bold' => "phosphor-{$iconName}-bold",
            'thin' => "phosphor-{$iconName}-thin",
            'light' => "phosphor-{$iconName}-light",
            'fill' => "phosphor-{$iconName}-fill",
            'duotone' => "phosphor-{$iconName}-duotone",
            default => "phosphor-{$iconName}",
        },
        $isHeroiconsSet => match ($variant) {
            'solid' => "heroicon-s-{$iconName}",
            'outline' => "heroicon-o-{$iconName}",
            'mini' => "heroicon-m-{$iconName}",
            'micro' => "heroicon-c-{$iconName}",
            default => "heroicon-o-{$iconName}",
        },
    };

    /* Apply size-6 fallback if no explicit size class is provided */
    if (! str($attributes->get('class'))->contains(['size-', 'w-', 'h-'])) {
        $attributes = $attributes->class('size-6');
    }
@endphp

@if ($asButton)
    <button {{ $attributes->class('cursor-pointer') }} type="button">
@endif

<x-dynamic-component :component="$componentName" {{ $attributes->class(['[:where(&)]:text-neutral-700 [:where(&)]:dark:text-neutral-300']) }}  data-slot="icon" />

@if ($asButton)
    </button>
@endif               
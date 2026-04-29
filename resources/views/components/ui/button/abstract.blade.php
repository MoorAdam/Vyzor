@aware(['disabled' => false])

@props([
    'href' => null,
    'as' => null,
    'disabled' => false
])


@php
    $type = match(true) {
        $as === 'div' && !$href => 'div',
        $as === 'a' || $href => 'a', 
        default => 'button'
    };
@endphp

@switch($type)
    @case('div')
        <div {{ $attributes }}>
            {{ $slot }}
        </div>
        @break
        
    @case('a')
        <a
            @if($disabled) aria-disabled="true" tabindex="-1" @else href="{{ $href }}" @endif
            {{ $attributes->class(['pointer-events-none opacity-55 cursor-default' => $disabled]) }}
        >
            {{ $slot }}
        </a>
        @break
        
    @default
        <button
            @disabled($disabled) 
            {{ $attributes->merge(['type' => $type]) }}
        >
            {{ $slot }}
        </button>
@endswitch
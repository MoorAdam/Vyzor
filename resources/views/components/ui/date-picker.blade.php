@props([
    'type' => 'date',
    'placeholder' => 'Select date',
    'availableDates' => [],
])

@php
    $isDatetime = $type === 'datetime-local';
    $wireModel = $attributes->whereStartsWith('wire:model')->first();
@endphp

<div
    x-data="{
        open: false,
        value: @entangle($attributes->wire('model')).live,
        availableDates: @js(array_values((array) $availableDates)),
        viewYear: null,
        viewMonth: null,
        hours: '00',
        minutes: '00',

        init() {
            this.syncFromValue();
            this.$watch('value', () => this.syncFromValue());
        },

        syncFromValue() {
            if (this.value) {
                let d;
                if ({{ $isDatetime ? 'true' : 'false' }}) {
                    d = new Date(this.value);
                    this.hours = String(d.getHours()).padStart(2, '0');
                    this.minutes = String(d.getMinutes()).padStart(2, '0');
                } else {
                    const parts = this.value.split('-');
                    d = new Date(parts[0], parts[1] - 1, parts[2]);
                }
                if (!isNaN(d)) {
                    this.viewYear = d.getFullYear();
                    this.viewMonth = d.getMonth();
                }
            }
            if (!this.viewYear) {
                const now = new Date();
                this.viewYear = now.getFullYear();
                this.viewMonth = now.getMonth();
            }
        },

        get displayValue() {
            if (!this.value) return '';
            if ({{ $isDatetime ? 'true' : 'false' }}) {
                const d = new Date(this.value);
                if (isNaN(d)) return this.value;
                return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
                    + ' ' + String(d.getHours()).padStart(2, '0')
                    + ':' + String(d.getMinutes()).padStart(2, '0');
            }
            const parts = this.value.split('-');
            const d = new Date(parts[0], parts[1] - 1, parts[2]);
            if (isNaN(d)) return this.value;
            return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
        },

        get monthName() {
            return new Date(this.viewYear, this.viewMonth).toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
        },

        get calendarDays() {
            const first = new Date(this.viewYear, this.viewMonth, 1);
            const startDay = first.getDay();
            const daysInMonth = new Date(this.viewYear, this.viewMonth + 1, 0).getDate();
            const daysInPrev = new Date(this.viewYear, this.viewMonth, 0).getDate();

            const days = [];

            for (let i = startDay - 1; i >= 0; i--) {
                days.push({ day: daysInPrev - i, current: false });
            }
            for (let i = 1; i <= daysInMonth; i++) {
                days.push({ day: i, current: true });
            }
            const remaining = 42 - days.length;
            for (let i = 1; i <= remaining; i++) {
                days.push({ day: i, current: false });
            }
            return days;
        },

        isSelected(day) {
            if (!this.value || !day.current) return false;
            const parts = this.value.substring(0, 10).split('-');
            return parseInt(parts[0]) === this.viewYear
                && parseInt(parts[1]) - 1 === this.viewMonth
                && parseInt(parts[2]) === day.day;
        },

        isToday(day) {
            if (!day.current) return false;
            const now = new Date();
            return now.getFullYear() === this.viewYear
                && now.getMonth() === this.viewMonth
                && now.getDate() === day.day;
        },

        hasData(day) {
            if (!day.current || !this.availableDates || this.availableDates.length === 0) return false;
            const m = String(this.viewMonth + 1).padStart(2, '0');
            const d = String(day.day).padStart(2, '0');
            return this.availableDates.includes(this.viewYear + '-' + m + '-' + d);
        },

        isDisabled(day) {
            if (!day.current) return true;
            // If no availableDates list is provided, nothing is restricted.
            if (!this.availableDates || this.availableDates.length === 0) return false;
            return !this.hasData(day);
        },

        selectDay(day) {
            if (this.isDisabled(day)) return;
            const m = String(this.viewMonth + 1).padStart(2, '0');
            const d = String(day.day).padStart(2, '0');
            if ({{ $isDatetime ? 'true' : 'false' }}) {
                this.value = this.viewYear + '-' + m + '-' + d + 'T' + this.hours + ':' + this.minutes;
            } else {
                this.value = this.viewYear + '-' + m + '-' + d;
                this.open = false;
            }
        },

        updateTime() {
            if (!this.value) return;
            const datePart = this.value.substring(0, 10);
            this.value = datePart + 'T' + this.hours + ':' + this.minutes;
        },

        prevMonth() {
            if (this.viewMonth === 0) { this.viewMonth = 11; this.viewYear--; }
            else { this.viewMonth--; }
        },
        nextMonth() {
            if (this.viewMonth === 11) { this.viewMonth = 0; this.viewYear++; }
            else { this.viewMonth++; }
        },
    }"
    x-on:keydown.escape.prevent.stop="open = false"
    {{ $attributes->except(['wire:model', 'wire:model.live', 'wire:model.blur', 'wire:model.defer', 'class'])->class(['relative inline-block w-full']) }}
>
    {{-- Trigger button --}}
    <button
        type="button"
        x-ref="button"
        x-on:click="open = !open"
        @class([
            'inline-flex items-center gap-2 border p-2 w-full text-sm transition-colors duration-200',
            'bg-white dark:bg-neutral-900 rounded-box shadow-xs',
            'border-black/10 focus:border-black/15 focus:ring-2 focus:ring-neutral-900/15 focus:ring-offset-0 focus:outline-none',
            'dark:border-white/15 dark:focus:border-white/20 dark:focus:ring-neutral-100/15',
            'text-neutral-800 dark:text-neutral-300',
            $attributes->get('class'),
        ])
    >
        <x-ui.icon name="calendar-blank" class="size-4 text-neutral-400 shrink-0" />
        <span x-text="displayValue || '{{ $placeholder }}'" :class="!displayValue && 'text-neutral-400'"></span>
    </button>

    {{-- Calendar dropdown --}}
    <div
        x-show="open"
        x-on:click.away="open = false"
        x-anchor.bottom-start.offset.6="$refs.button"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        style="display: none;"
        class="absolute z-50 w-72 bg-white dark:bg-neutral-900 border border-black/10 dark:border-white/10 rounded-box shadow-lg p-3"
    >
        {{-- Month navigation --}}
        <div class="flex items-center justify-between mb-3">
            <button type="button" x-on:click="prevMonth()" class="p-1 rounded hover:bg-neutral-100 dark:hover:bg-neutral-800 text-neutral-500">
                <x-ui.icon name="caret-left" class="size-4" />
            </button>
            <span class="text-sm font-medium text-neutral-700 dark:text-neutral-300" x-text="monthName"></span>
            <button type="button" x-on:click="nextMonth()" class="p-1 rounded hover:bg-neutral-100 dark:hover:bg-neutral-800 text-neutral-500">
                <x-ui.icon name="caret-right" class="size-4" />
            </button>
        </div>

        {{-- Day headers --}}
        <div class="grid grid-cols-7 mb-1">
            <template x-for="d in ['Su','Mo','Tu','We','Th','Fr','Sa']" :key="d">
                <div class="text-center text-xs font-medium text-neutral-400 py-1" x-text="d"></div>
            </template>
        </div>

        {{-- Days grid --}}
        <div class="grid grid-cols-7">
            <template x-for="(day, i) in calendarDays" :key="i">
                <button
                    type="button"
                    x-on:click="selectDay(day)"
                    :disabled="isDisabled(day)"
                    :class="{
                        'text-neutral-300 dark:text-neutral-600 cursor-not-allowed': isDisabled(day),
                        'hover:bg-neutral-100 dark:hover:bg-neutral-800': !isDisabled(day) && !isSelected(day),
                        'bg-neutral-900 text-white dark:bg-white dark:text-neutral-900 font-medium': isSelected(day),
                        'ring-1 ring-neutral-400 dark:ring-neutral-500': isToday(day) && !isSelected(day),
                    }"
                    class="relative text-center text-sm py-1.5 rounded transition-colors"
                >
                    <span x-text="day.day"></span>
                    <span
                        x-show="hasData(day)"
                        :class="isSelected(day) ? 'bg-white dark:bg-neutral-900' : 'bg-emerald-500'"
                        class="absolute bottom-0.5 left-1/2 -translate-x-1/2 size-1 rounded-full"
                    ></span>
                </button>
            </template>
        </div>

        @if($isDatetime)
            {{-- Time picker --}}
            <div class="flex items-center gap-2 mt-3 pt-3 border-t border-neutral-200 dark:border-neutral-700">
                <x-ui.icon name="clock" class="size-4 text-neutral-400 shrink-0" />
                <input
                    type="number"
                    min="0" max="23"
                    x-model="hours"
                    x-on:change="updateTime()"
                    class="w-14 text-center text-sm p-1.5 rounded-box border border-black/10 dark:border-white/15 bg-white dark:bg-neutral-900 text-neutral-800 dark:text-neutral-300 focus:ring-2 focus:ring-neutral-900/15 focus:outline-none dark:focus:ring-neutral-100/15"
                />
                <span class="text-neutral-400 font-medium">:</span>
                <input
                    type="number"
                    min="0" max="59"
                    x-model="minutes"
                    x-on:change="updateTime()"
                    class="w-14 text-center text-sm p-1.5 rounded-box border border-black/10 dark:border-white/15 bg-white dark:bg-neutral-900 text-neutral-800 dark:text-neutral-300 focus:ring-2 focus:ring-neutral-900/15 focus:outline-none dark:focus:ring-neutral-100/15"
                />
                <button
                    type="button"
                    x-on:click="open = false"
                    class="ml-auto text-xs font-medium px-2.5 py-1.5 rounded-box bg-neutral-900 text-white dark:bg-white dark:text-neutral-900 hover:opacity-90 transition-opacity"
                >
                    Done
                </button>
            </div>
        @endif
    </div>
</div>

<x-filament-widgets::widget>
    <x-filament::section heading="{{ __('Quick links') }}" description="{{ __('Jump to the consoles you use most.') }}">
        <div class="grid gap-3 sm:grid-cols-3">
            @foreach ($this->getLinks() as $link)
                <a
                    href="{{ $link['url'] }}"
                    class="flex flex-col gap-1 rounded-xl border border-gray-200 bg-white p-4 transition hover:border-primary-500 hover:shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:hover:border-primary-400"
                >
                    <span class="text-sm font-semibold text-gray-950 dark:text-white">{{ $link['label'] }}</span>
                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ $link['description'] }}</span>
                </a>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>

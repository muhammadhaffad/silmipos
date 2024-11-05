<x-filament-panels::page>
    <div class="md:flex gap-4">
        <div class="w-full md:w-2/3">
            <x-filament-panels::resources.tabs />
            <div wire:loading.class="opacity-50" class="relative"
                wire:target="tableFilters,applyTableFilters,resetTableFiltersForm, nextPage, gotoPage, previousPage, tableRecordsPerPage, activeTab">
                {{ $this->table }}
                <div class="loading_indicator hidden items-center justify-center p-4 absolute top-0 start-0 end-0 bottom-0" wire:loading.class.remove="hidden" wire:loading.class="flex"
                    wire:target="tableFilters,applyTableFilters,resetTableFiltersForm, nextPage, gotoPage, previousPage, tableRecordsPerPage, activeTab">
                    <x-filament::loading-indicator class="h-5 w-5" />
                </div>
            </div>
        </div>
        <div class="hidden md:w-full md:block md:w-1/3">
            {{ $this->form }}
        </div>
    </div>
</x-filament-panels::page>

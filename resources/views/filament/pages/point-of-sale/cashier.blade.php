<x-filament-panels::page>
    <div class="sm:flex gap-4">
        <div class="w-full sm:w-1/2 lg:w-2/3">
            <x-filament-panels::resources.tabs />
            <div wire:loading.class="opacity-50" class="relative"
                wire:target="tableFilters,applyTableFilters,resetTableFiltersForm, nextPage, gotoPage, previousPage, tableRecordsPerPage, activeTab, addToCart">
                {{ $this->table }}
                <div class="loading_indicator hidden items-center justify-center p-4 absolute top-0 start-0 end-0 bottom-0" wire:loading.class.remove="hidden" wire:loading.class="flex"
                    wire:target="tableFilters,applyTableFilters,resetTableFiltersForm, nextPage, gotoPage, previousPage, tableRecordsPerPage, activeTab, addToCart">
                    <x-filament::loading-indicator class="h-5 w-5" />
                </div>
            </div>
        </div>
        <div class="w-full mt-4 sm:mt-0 sm:w-1/2 lg:w-1/3">
            <x-filament-panels::form wire:submit="storeSalesInvoice" class="md:sticky md:top-[96px]">
                {{ $this->form }}
            </x-filament-panels::form>
        </div>
    </div>
</x-filament-panels::page>

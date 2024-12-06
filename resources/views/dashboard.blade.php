<x-layouts.app>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 rounded-lg shadow-lg text-center">
                    <h2 class="text-2xl font-bold">{{ __("Olá, Seja bem-vindo ao BULKSHIP!") }}</h2>
                    <p class="mt-2 text-lg">{{ __("Estamos felizes em tê-lo conosco. Vamos começar!") }}</p>
                </div>
            </div>
        </div>
    </div>
</x-layout.app>

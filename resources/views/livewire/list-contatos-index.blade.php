<div>
    <x-mary-header title="Lista de contatos" subtitle="Gerencie sua lista de contatos" separator>
        <x-slot:actions>
            <x-mary-button label="Grupos" icon="o-rectangle-group" @click="window.location.href = '{{ route('group-send.index') }}'" />
        </x-slot:actions>
    </x-mary-header>

    {{-- INFO: Aviso sobre como criar demandas --}}
    <x-mary-alert
        title="Sobre a lista de contatos"
        icon="o-light-bulb"
        description="{!! $descriptionCard !!}"
        class="bg-yellow-50 text-yellow-900 border-yellow-200 mb-4"
        dismissible
    />

    {{-- INFO: table --}}
    <x-mary-table
        :headers="$headers"
        :rows="$contatos_table"
        class="bg-white"
    >

        {{-- Overrides `phone` header --}}
        @scope('header_phone', $header)
            <h3 class="text-xl font-bold text-black">
                {{ $header['label'] }}
            </h3>
        @endscope

        {{-- Overrides `name` header --}}
        @scope('header_name', $header)
            <h3 class="text-xl font-bold text-black">
                {{ $header['label'] }}
            </h3>
        @endscope

    </x-mary-table>
</div>

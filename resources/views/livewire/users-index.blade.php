<div>
    <x-mary-header title="Usuários" subtitle="User Settings" separator>
        <x-slot:middle class="!justify-end">
            <x-mary-input icon="o-bolt" wire:model.live="search" placeholder="Search..." />
        </x-slot:middle>
        <x-slot:actions>
            <x-mary-button icon="o-funnel" />
            <x-mary-button icon="o-plus" class="btn-primary" @click="$wire.showModal()" />
        </x-slot:actions>
    </x-mary-header>
    {{-- INFO: table --}}
    <x-mary-table
        :headers="$headers"
        :rows="$users"
        striped @row-click="$wire.edit($event.detail.id)"
        class="bg-white"
        with-pagination per-page="perPage"
        :per-page-values="[3, 5, 10]"
    >

        {{-- Overrides `name` header --}}
        @scope('header_name', $header)
            <h3 class="text-xl font-bold text-black">
                {{ $header['label'] }}
            </h3>
        @endscope

        {{-- Overrides `email` header --}}
        @scope('header_email', $header)
            <h3 class="text-xl font-bold text-black">
                {{ $header['label'] }}
            </h3>
        @endscope

        {{-- Overrides `type_user` header --}}
        @scope('header_type_user_name', $header)
            <h3 class="text-xl font-bold text-black">
                {{ $header['label'] }}
            </h3>
        @endscope

        {{-- Overrides `created_at` header --}}
        @scope('header_formatted_created_at', $header)
            <h3 class="text-xl font-bold text-black">
                {{ $header['label'] }}
            </h3>
        @endscope

        {{-- Special `actions` slot --}}
        @scope('actions', $users)
            <x-mary-button icon="o-trash" wire:click="delete({{ $users->id }})" spinner class="btn-sm btn-error" />
        @endscope
    </x-mary-table>

    {{-- INFO: Modal users --}}
    <x-mary-modal wire:model="userModal" class="backdrop-blur">
        <x-mary-form wire:submit="save">

            {{-- INFO: campos --}}
            <x-mary-input label="Name" wire:model="form.name" />
            <x-mary-input label="E-mail" wire:model="form.email" />
            <x-mary-password label="Password" hint="It toggles visibility" wire:model="form.password" clearable />

            <x-slot:actions>
                <x-mary-button label="Cancel" @click="$wire.userModal = false" />
                <x-mary-button label="Create" class="btn-primary" type="submit" spinner="save" />
            </x-slot:actions>
        </x-mary-form>
    </x-mary-modal>
</div>

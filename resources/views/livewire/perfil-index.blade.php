{{-- INFO: Levantar questões de permissão para editar o que --}}
<div>
    <x-mary-header title="Perfil" separator />

    <div class="grid lg:grid-cols-5 gap-10">

        <x-mary-file wire:model="form.photo" accept="image/png, image/jpeg">
            <img src="{{ Storage::url($user->photo) ?? 'https://via.placeholder.com/500x200' }}" class="h-40 rounded-lg" />
        </x-mary-file>

        <x-mary-form wire:submit="save" class="col-span-3">
            <x-mary-input label="Name" wire:model="form.name" />
            <x-mary-input label="E-mail" wire:model="form.email" />

            <x-slot:actions>
                <x-mary-button label="Cancel" link="/dashboard" />
                <x-mary-button label="Save" type="submit" icon="o-paper-airplane" class="btn-primary" spinner="save" />
            </x-slot:actions>
        </x-mary-form>
    </div>
</div>

<div>
    <x-mary-header :title="$title" separator progress-indicator>
    </x-mary-header>
    <x-mary-form wire:submit="register">
        <x-mary-input label="Name" icon="o-user" wire:model="name" hint="Enter your Name" />

        <x-mary-input label="Email" icon="o-envelope" wire:model="email" hint="Enter your email" />

        <x-mary-input label="Password" wire:model="password" icon="o-key" type="password" hint="Enter your password" />

        <x-slot:actions>
            <x-mary-button class="btn-success" label="Register" type="submit" spinner="save" />
        </x-slot:actions>
    </x-mary-form>
</div>

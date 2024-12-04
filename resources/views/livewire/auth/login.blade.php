<div>
    <x-mary-header :title="$title" separator progress-indicator>
    </x-mary-header>
    <x-mary-form wire:submit="authenticate">
        <x-mary-input label="Email" icon="o-envelope" wire:model="email" hint="Enter your email" />
        <x-mary-input label="Password" wire:model="password" icon="o-key" type="password" hint="Enter your password" />

        <x-slot:actions>
            <x-mary-button link="{{ route('password.request') }}" label="Forgot Password" />
            <x-mary-button label="Login" class="btn-success" type="submit" spinner="save" />
        </x-slot:actions>
    </x-mary-form>
</div>

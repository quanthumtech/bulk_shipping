<!DOCTYPE html>
<html lang="en" data-theme="cupcake">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BulkShip</title>

    <link rel="stylesheet" href="https://unpkg.com/easymde/dist/easymde.min.css">
    <script src="https://unpkg.com/easymde/dist/easymde.min.js"></script>

    {{-- Cropper.js --}}
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.css" />

    {{-- Vanilla Calendar --}}
    <script src="https://cdn.jsdelivr.net/npm/vanilla-calendar-pro@2.9.6/build/vanilla-calendar.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/vanilla-calendar-pro@2.9.6/build/vanilla-calendar.min.css" rel="stylesheet">

    {{-- Flatpickr  --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    {{-- It will not apply locale yet  --}}
    <script src="https://npmcdn.com/flatpickr/dist/l10n/fr.js"></script>
    <script src="https://npmcdn.com/flatpickr/dist/l10n/pt.js"></script>
    <script src="https://npmcdn.com/flatpickr/dist/l10n/ru.js"></script>

    {{-- Chart.js  --}}
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen font-sans antialiased">
    {{-- The navbar with `sticky` and `full-width` --}}
    <x-mary-nav sticky full-width>

        <x-slot:brand>
            {{-- Drawer toggle for "main-drawer" --}}
            <label for="main-drawer" class="lg:hidden mr-3">
                <x-mary-icon name="o-bars-3" class="cursor-pointer" />
            </label>

            {{-- Brand Logo --}}
            <img src="{{ asset('img/BULKSHIP.png') }}" alt="Plataforma Mundo Logo" class="h-7 w-auto">
        </x-slot:brand>

        {{-- Right side actions --}}
        <x-slot:actions>
            {{--<x-mary-button label="Messages" icon="o-envelope" link="###" class="btn-ghost btn-sm" responsive />--}}
            <x-mary-button label="Notifications" icon="o-bell" link="###" class="btn-ghost btn-sm" responsive />
        </x-slot:actions>
    </x-mary-nav>

    {{-- The main content with `full-width` --}}
    <x-mary-main with-nav full-width>

        {{-- This is a sidebar that works also as a drawer on small screens --}}
        {{-- Notice the `main-drawer` reference here --}}
        <x-slot:sidebar drawer="main-drawer" collapsible class="bg-base-200">

            {{-- User --}}
            @if($user = auth()->user())
                <x-mary-list-item :item="auth()->user()" value="name" sub-value="email" no-separator no-hover class="pt-2">
                    {{-- Avatar --}}
                    <x-slot:avatar>
                        <x-mary-avatar :image="Storage::url($user->photo)" class="!w-12" />
                    </x-slot:avatar>
                    {{-- Subtitle --}}
                    <x-slot:actions>
                        <x-mary-dropdown>
                            <x-slot:trigger>
                                <x-mary-button icon="o-cog-8-tooth" class="btn-circle" />
                            </x-slot:trigger>

                            <x-mary-menu-item title="logout" icon="o-power" link="/logout"/>
                            <x-mary-menu-item title="Perfil" icon="o-user" link="/perfil" :link="route('perfil.index', ['id' => auth()->user()->id])"/>
                        {{-- <x-mary-menu-item title="Theme" icon="o-swatch" @click="$dispatch('mary-toggle-theme')" /> --}}
                        </x-dropdown>
                    </x-slot:actions>
                </x-mary-list-item>


                <x-mary-menu-separator />
            @endif

            {{-- Activates the menu item when a route matches the `link` property --}}
            <x-mary-menu activate-by-route>
                <x-mary-menu-item title="Home" icon="o-home" link="/statistic" />
                <x-mary-menu-sub title="Gerenciar mensagens" icon="o-envelope">
                    <x-mary-menu-item title="Grupos" icon="o-rectangle-group" link="/group-send" />
                    <x-mary-menu-item title="Lista de Contatos" icon="o-user-circle" link="/contatos" />
                </x-mary-menu-sub>
                <x-mary-menu-sub title="Cadência" icon="o-clock">
                    <x-mary-menu-item title="Gerenciar cadência" icon="o-document-chart-bar" link="/cadencias" />
                </x-mary-menu-sub>

                <x-mary-menu-item title="SyncFlow" icon="s-square-3-stack-3d" link="/sync-flow" />

                @if($user = auth()->user())
                    @if($user->type_user === '1' || $user->type_user === '2')
                        <x-mary-menu-sub title="Settings" icon="o-cog-6-tooth">
                            <x-mary-menu-item title="Usuários" icon="o-users" link="/users" />
                        </x-mary-menu-sub>
                    @endif
                @endif
                <x-mary-menu-item title="FAQ" icon="o-question-mark-circle" link="#" />
                <x-mary-menu-item title="Suporte" icon="o-lifebuoy" link="#" />
            </x-mary-menu>
        </x-slot:sidebar>

        {{-- The `$slot` goes here --}}
        <x-slot:content>
            {{ $slot }}
        </x-slot:content>
    </x-mary-main>

    {{--  TOAST area --}}
    <x-mary-toast />
</body>
</html>

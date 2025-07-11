<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BulkShip</title>
    <link rel="icon" type="image/x-icon" href="https://plataformamundo.com.br/assets/images/favicon.ico">

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

    {{-- Flatpickr --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://unpkg.com/flatpickr/dist/plugins/monthSelect/index.js"></script>
    <link href="https://unpkg.com/flatpickr/dist/plugins/monthSelect/style.css" rel="stylesheet">
    <script src="https://npmcdn.com/flatpickr/dist/l10n/pt.js"></script>
    <script src="https://npmcdn.com/flatpickr/dist/l10n/fr.js"></script>

    {{-- Vanilla Calendar --}}
    <script src="https://cdn.jsdelivr.net/npm/vanilla-calendar-pro@2.9.6/build/vanilla-calendar.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/vanilla-calendar-pro@2.9.6/build/vanilla-calendar.min.css" rel="stylesheet">

    {{-- DIFF2HTML --}}
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.8.0/styles/xcode.min.css" />
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/diff2html@3.4.48/bundles/css/diff2html.min.css" />
    <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/diff2html@3.4.48/bundles/js/diff2html-ui.min.js"></script>

    {{-- Chart.js  --}}
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

    {{-- Sortable.js --}}
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.1/Sortable.min.js"></script>

    {{-- PhotoSwipe --}}
    <script src="https://cdn.jsdelivr.net/npm/photoswipe@5.4.3/dist/umd/photoswipe.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/photoswipe@5.4.3/dist/umd/photoswipe-lightbox.umd.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/photoswipe@5.4.3/dist/photoswipe.min.css" rel="stylesheet">

    {{--  Currency  --}}
    <script type="text/javascript" src="https://cdn.jsdelivr.net/gh/robsontenorio/mary@0.44.2/libs/currency/currency.js"></script>

    {{-- Signature Pad  --}}
    <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.2.0/dist/signature_pad.umd.min.js"></script>

    {{-- Algolia docsearch --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@docsearch/css@3" />
    <link rel="preconnect" href="https://0AWOCS02I6-dsn.algolia.net" crossorigin />

    {{--  Pirsch Analytics  --}}
    <script defer src="https://api.pirsch.io/pa.js" id="pianjs" data-code="rOVAXMnSEiydpyfzhgPnLVbX6iWcik7m"></script>

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
            {{-- Componente Livewire para notificações --}}
            @livewire('notifications.notifications')

            {{-- Theme --}}
            <div class="flex items-center justify-end">
                <x-mary-theme-toggle lightTheme="cupcake" darkTheme="dark" />
            </div>
        </x-slot:actions>
    </x-mary-nav>

    {{-- The main content with `full-width` --}}
    <x-mary-main with-nav full-width>

        {{-- This is a sidebar that works also as a drawer on small screens --}}
        {{-- Notice the `main-drawer` reference here --}}
        <x-slot:sidebar
            drawer="main-drawer"
            collapsible
            class="bg-base-200"
        >

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
                        </x-mary-dropdown>
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
                            <x-mary-menu-item title="Scripts" icon="o-code-bracket" link="/scrpits-index" />
                        </x-mary-menu-sub>

                        <x-mary-menu-sub title="Logs" icon="s-shield-check">
                            <x-mary-menu-item title="Logs de Webhooks" icon="o-document-text" link="/webhook-logs" />
                        </x-mary-menu-sub>

                    @endif
                @endif
                <x-mary-menu-item title="FAQ" icon="o-question-mark-circle" link="/faq-info" />
                <x-mary-menu-item title="Suporte" icon="o-lifebuoy" link="https://quanthum.tec.br/" />
            </x-mary-menu>
        </x-slot:sidebar>

        {{-- The `$slot` goes here --}}
        <x-slot:content>
            {{ $slot }}
        </x-slot:content>
    </x-mary-main>

    {{--  TOAST area --}}
    <x-mary-toast />

    {{-- Spotlight --}}
    <x-mary-spotlight />

    {{-- Custom Footer --}}
    <footer class="w-full py-4 bg-base-200 text-center text-sm text-base-content/70">
        <div>
            BulkShip &copy; {{ date('Y') }} &mdash; v1.0.0
        </div>
    </footer>
</body>
</html>

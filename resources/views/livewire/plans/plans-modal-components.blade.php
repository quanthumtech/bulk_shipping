<div 
    wire:ignore.self
    x-cloak
    x-data="{ open: @entangle('isOpen') }"
    x-init="console.log('Open state:', open);"
    x-show="open" 
    x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition ease-in duration-200"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    class="fixed inset-0 z-50 flex items-center justify-center p-4 overflow-y-auto"
    x-on:click.away="$wire.closeModal()">

    {{-- Backdrop --}}
    <div class="fixed inset-0 bg-black/50" x-on:click="$wire.closeModal()"></div>

    {{-- Modal Content --}}
    <div class="relative w-full max-w-7xl bg-white rounded-xl shadow-2xl overflow-hidden mx-auto max-h-[90vh] overflow-y-auto"
         x-on:click.stop>
        {{-- Header --}}
        <div class="flex items-center justify-between p-6 border-b border-gray-200 bg-gray-50">
            <h2 class="text-2xl font-bold text-gray-900">Nossos Planos</h2>
            <button x-on:click="$wire.closeModal()" class="p-2 rounded-full hover:bg-gray-200 transition-colors">
                <svg class="w-6 h-6 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        {{-- Body: Grid de Planos --}}
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                @foreach ($plans as $plan)
                    @php
                        $isCurrentPlan = ($userPlan && ($userPlan->id ?? $userPlan) == $plan['id']);
                    @endphp
                    <div class="{{ $isCurrentPlan ? 'opacity-50 pointer-events-none' : 'hover:shadow-xl transition-all duration-300' }} bg-white rounded-lg shadow-md border {{ $isCurrentPlan ? 'border-green-500' : 'border-gray-200' }} overflow-hidden">
                        {{-- Header do Card --}}
                        <div class="p-6 text-center border-b {{ $isCurrentPlan ? 'border-green-200 bg-green-50' : 'border-gray-100 bg-gradient-to-b from-blue-50 to-white' }}">
                            <h3 class="text-lg font-bold {{ $isCurrentPlan ? 'text-green-600' : 'text-primary-600' }} uppercase tracking-wide mb-2">{{ $plan['name'] }}</h3>
                            <div class="text-3xl font-bold {{ $isCurrentPlan ? 'text-green-600' : 'text-primary-600' }} mb-1">{{ $plan['price'] }}</div>
                            @if($isCurrentPlan)
                                <div class="text-xs text-green-600 font-semibold mb-4">Plano Atual</div>
                            @else
                                <p class="text-xs text-gray-500 mb-4">
                                    @if($plan['name'] == 'Essencial')
                                        Ideal para pequenas operações que estão começando com automação.
                                    @elseif($plan['name'] == 'Avançado')
                                        Para empresas com processos definidos que buscam escalar.
                                    @elseif($plan['name'] == 'Profissional')
                                        Para operações que exigem alta performance e volume.
                                    @else
                                        Para grandes operações com volume necessitado e personalização total.
                                    @endif
                                </p>
                            @endif
                        </div>

                        {{-- Features --}}
                        <ul class="p-6 space-y-3">
                            <li class="flex items-center text-sm {{ $isCurrentPlan ? 'text-gray-500' : 'text-gray-700' }}">
                                <svg class="w-5 h-5 {{ $isCurrentPlan ? 'text-gray-400' : 'text-green-500' }} mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                </svg>
                                {{ $plan['max_cadence_flows'] }} Fluxos de Cadência
                            </li>
                            <li class="flex items-center text-sm {{ $isCurrentPlan ? 'text-gray-500' : 'text-gray-700' }}">
                                <svg class="w-5 h-5 {{ $isCurrentPlan ? 'text-gray-400' : 'text-green-500' }} mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                </svg>
                                {{ $plan['max_daily_leads'] }} Leads Diários
                            </li>
                            <li class="flex items-center text-sm {{ $isCurrentPlan ? 'text-gray-500' : 'text-gray-700' }}">
                                <svg class="w-5 h-5 {{ $isCurrentPlan ? 'text-gray-400' : 'text-green-500' }} mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                </svg>
                                {{ $plan['support_level'] }} Suporte
                            </li>
                            <li class="flex items-center text-sm {{ $isCurrentPlan ? 'text-gray-500' : 'text-gray-700' }}">
                                <svg class="w-5 h-5 {{ $isCurrentPlan ? 'text-gray-400' : 'text-green-500' }} mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                </svg>
                                Integração com CRM via API
                            </li>
                            <li class="flex items-center text-sm {{ $isCurrentPlan ? 'text-gray-500' : 'text-gray-700' }}">
                                <svg class="w-5 h-5 {{ $isCurrentPlan ? 'text-gray-400' : 'text-green-500' }} mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                </svg>
                                Relatórios Operacionais
                            </li>
                        </ul>

                        {{-- Button --}}
                        <div class="p-6 bg-blue-50 border-t border-gray-100 text-center">
                            @if($isCurrentPlan)
                                <x-mary-button 
                                    label="Plano Atual"
                                    disabled="true"
                                    class="btn-ghost bg-green-100 text-green-700 border-green-300 w-full"
                                />
                            @else
                                <x-mary-button 
                                    label="Solicitar Plano"
                                    href="{{ route('users.config', ['plan_id' => $plan['id']]) }}" 
                                    x-on:click="$wire.closeModal()"
                                    class="btn-primary w-full"
                                />
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
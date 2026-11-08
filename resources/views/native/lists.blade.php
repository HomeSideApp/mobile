<native:column class="w-full h-full bg-theme-background">
    <native:scroll-view class="w-full flex-1">
        <native:column class="w-full gap-4 p-4">

            @if ($needsLogin)
                <native:column class="w-full items-center gap-3 rounded-lg bg-theme-surface-variant px-4 py-10">
                    <native:icon ios="lock.fill" android="lock" :size="28" class="text-theme-primary" />
                    <native:text class="text-base font-semibold text-theme-on-surface">Necesitás iniciar sesión</native:text>
                    <native:text class="text-center text-sm text-theme-on-surface-variant">
                        Tu sesión venció o todavía no entraste en este dispositivo.
                    </native:text>
                    <native:button label="Entrar" class="w-full" @press="goToLogin" />
                </native:column>
            @else

                <native:outlined-text-input
                    placeholder="Buscar una lista"
                    leading-icon="search"
                    native:model.debounce.400ms="search"
                    @change="onSearch"
                />

                <native:button label="Nueva lista" icon="plus" class="w-full" @press="createList" />

                @if ($loading)
                    <native:row class="w-full items-center justify-center gap-2 py-6">
                        <native:activity-indicator />
                        <native:text class="text-sm text-theme-on-surface-variant">Cargando listas…</native:text>
                    </native:row>
                @endif

                @if (! $loading && count($personal) > 0)
                    <native:text class="text-xs font-semibold uppercase text-theme-on-surface-variant">
                        Mis listas
                    </native:text>

                    @foreach ($personal as $list)
                        <native:pressable
                            :native:key="$list['id']"
                            class="w-full rounded-lg bg-theme-surface p-4"
                            @press="openList('{{ $list['id'] }}')"
                        >
                            <native:row class="w-full items-center gap-3">
                                <native:icon ios="cart.fill" android="shopping_cart" :size="20" class="text-theme-primary" />
                                <native:column class="flex-1 gap-0.5">
                                    <native:text class="text-base font-semibold text-theme-on-surface">{{ $list['name'] }}</native:text>
                                    <native:text class="text-xs text-theme-on-surface-variant">Personal</native:text>
                                </native:column>
                                <native:icon ios="chevron.right" android="chevron_right" :size="16" class="text-theme-on-surface-variant" />
                            </native:row>
                        </native:pressable>
                    @endforeach
                @endif

                @if (! $loading && $activeHouseholdName !== null)
                    <native:text class="mt-2 text-xs font-semibold uppercase text-theme-on-surface-variant">
                        {{ $activeHouseholdName }}
                    </native:text>

                    @forelse ($householdLists as $list)
                        <native:pressable
                            :native:key="$list['id']"
                            class="w-full rounded-lg bg-theme-surface p-4"
                            @press="openHouseholdList('{{ $list['id'] }}')"
                        >
                            <native:row class="w-full items-center gap-3">
                                <native:icon ios="house.fill" android="home" :size="20" class="text-theme-accent-foreground" />
                                <native:column class="flex-1 gap-0.5">
                                    <native:text class="text-base font-semibold text-theme-on-surface">{{ $list['name'] }}</native:text>
                                    <native:text class="text-xs text-theme-on-surface-variant">Compartida</native:text>
                                </native:column>
                                <native:icon ios="chevron.right" android="chevron_right" :size="16" class="text-theme-on-surface-variant" />
                            </native:row>
                        </native:pressable>
                    @empty
                        <native:text class="text-sm text-theme-on-surface-variant">
                            El hogar todavía no tiene listas.
                        </native:text>
                    @endforelse
                @endif

                @if (! $loading && count($personal) === 0 && count($householdLists) === 0)
                    <native:column class="w-full items-center gap-2 rounded-lg bg-theme-surface-variant px-4 py-10">
                        <native:icon ios="cart.fill" android="shopping_cart" :size="30" class="text-theme-primary" />
                        <native:text class="text-base font-semibold text-theme-on-surface">
                            {{ $search !== '' ? 'No encontramos listas' : 'Todavía no hay listas' }}
                        </native:text>
                        <native:text class="text-center text-sm text-theme-on-surface-variant">
                            {{ $search !== '' ? 'Probá con otro nombre.' : 'Creá la primera y empezá a tildar cosas.' }}
                        </native:text>
                    </native:column>
                @endif

            @endif

            @if ($error)
                <native:column class="w-full rounded-lg bg-theme-destructive/10 p-4">
                    <native:text class="text-sm text-theme-destructive">{{ $error }}</native:text>
                </native:column>
            @endif

        </native:column>
    </native:scroll-view>
</native:column>

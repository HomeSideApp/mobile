<native:column class="w-full h-full bg-theme-background">
    <native:scroll-view class="w-full flex-1">
        <native:column class="w-full gap-5 p-5">

            <native:outlined-text-input
                :label="__('app.list_create.name')"
                :placeholder="__('app.list_create.name_placeholder')"
                autofocus
                native:model.debounce.300ms="name"
                @submit="save"
            />

            <native:column class="w-full gap-2">
                <native:text class="text-xs font-semibold uppercase text-theme-on-surface-variant">
                    {{ __('app.list_create.scope') }}
                </native:text>

                {{-- Misma tarjeta que el resto de la app: ícono, título, pista y
                     un tilde en la opción elegida. Las comillas del `json_encode`
                     van simples: con dobles, Blade corta el atributo y el callback
                     llega sin argumentos. --}}
                <native:pressable
                    class="w-full rounded-lg border p-3 {{ $scope === 'personal' ? 'border-theme-primary' : 'border-theme-outline' }} bg-theme-surface"
                    @press="chooseScope({{ json_encode('personal') }})"
                >
                    <native:row class="w-full items-center gap-2">
                        <native:icon ios="cart.fill" android="shopping_cart" :size="20" class="text-theme-primary" />
                        <native:column class="flex-1 gap-0.5">
                            <native:text class="text-sm font-semibold text-theme-on-surface">{{ __('app.list_create.personal') }}</native:text>
                            <native:text class="text-xs text-theme-on-surface-variant">
                                {{ __('app.list_create.personal_hint') }}
                            </native:text>
                        </native:column>
                        @if ($scope === 'personal')
                            <native:icon ios="checkmark.circle.fill" android="check_circle" :size="20" class="text-theme-primary" />
                        @endif
                    </native:row>
                </native:pressable>

                @if ($householdId !== null && $householdName !== null)
                    <native:pressable
                        class="w-full rounded-lg border p-3 {{ $scope === 'household' ? 'border-theme-primary' : 'border-theme-outline' }} bg-theme-surface"
                        @press="chooseScope({{ json_encode('household') }})"
                    >
                        <native:row class="w-full items-center gap-2">
                            <native:icon ios="house.fill" android="home" :size="20" class="text-theme-primary" />
                            <native:column class="flex-1 gap-0.5">
                                <native:text class="text-sm font-semibold text-theme-on-surface">{{ $householdName }}</native:text>
                                <native:text class="text-xs text-theme-on-surface-variant">
                                    {{ __('app.list_create.household_hint') }}
                                </native:text>
                            </native:column>
                            @if ($scope === 'household')
                                <native:icon ios="checkmark.circle.fill" android="check_circle" :size="20" class="text-theme-primary" />
                            @endif
                        </native:row>
                    </native:pressable>
                @endif
            </native:column>

            @if ($error)
                <native:column class="w-full rounded-lg bg-theme-destructive/10 p-4">
                    <native:text class="text-sm text-theme-destructive">{{ $error }}</native:text>
                </native:column>
            @endif

            <native:button
                :label="__('app.list_create.submit')"
                class="w-full"
                :loading="$busy"
                :disabled="$busy"
                @press="save"
            />

        </native:column>
    </native:scroll-view>
</native:column>

<native:column class="w-full h-full bg-theme-background">
    <native:scroll-view class="w-full flex-1">
        <native:column class="w-full gap-5 p-5">

            <native:outlined-text-input
                label="Nombre de la lista"
                placeholder="Compra semanal"
                autofocus
                native:model.debounce.300ms="name"
                @submit="save"
            />

            <native:column class="w-full gap-2">
                <native:text class="text-xs font-semibold uppercase text-theme-on-surface-variant">
                    ¿Para quién es?
                </native:text>

                <native:pressable
                    class="w-full rounded-lg border p-4"
                    :class="$scope === 'personal' ? 'border-theme-primary bg-theme-surface' : 'border-theme-outline bg-theme-surface'"
                    @press="chooseScope('personal')"
                >
                    <native:column class="w-full gap-1">
                        <native:text class="text-base font-semibold text-theme-on-surface">Solo para mí</native:text>
                        <native:text class="text-xs text-theme-on-surface-variant">
                            Lista privada, no la ve nadie más.
                        </native:text>
                    </native:column>
                </native:pressable>

                @if ($householdId !== null && $householdName !== null)
                    <native:pressable
                        class="w-full rounded-lg border p-4"
                        :class="$scope === 'household' ? 'border-theme-primary bg-theme-surface' : 'border-theme-outline bg-theme-surface'"
                        @press="chooseScope('household')"
                    >
                        <native:column class="w-full gap-1">
                            <native:text class="text-base font-semibold text-theme-on-surface">{{ $householdName }}</native:text>
                            <native:text class="text-xs text-theme-on-surface-variant">
                                Compartida con todo el hogar.
                            </native:text>
                        </native:column>
                    </native:pressable>
                @endif
            </native:column>

            @if ($error)
                <native:column class="w-full rounded-lg bg-theme-destructive/10 p-4">
                    <native:text class="text-sm text-theme-destructive">{{ $error }}</native:text>
                </native:column>
            @endif

            <native:button
                label="Crear lista"
                class="w-full"
                :loading="$busy"
                :disabled="$busy"
                @press="save"
            />

        </native:column>
    </native:scroll-view>
</native:column>

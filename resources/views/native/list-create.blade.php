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

                <native:pressable
                    class="w-full rounded-lg border p-4"
                    :class="$scope === 'personal' ? 'border-theme-primary bg-theme-surface' : 'border-theme-outline bg-theme-surface'"
                    @press="chooseScope({{ json_encode("personal") }})"
                >
                    <native:column class="w-full gap-1">
                        <native:text class="text-base font-semibold text-theme-on-surface">{{ __('app.list_create.personal') }}</native:text>
                        <native:text class="text-xs text-theme-on-surface-variant">
                            {{ __('app.list_create.personal_hint') }}
                        </native:text>
                    </native:column>
                </native:pressable>

                @if ($householdId !== null && $householdName !== null)
                    <native:pressable
                        class="w-full rounded-lg border p-4"
                        :class="$scope === 'household' ? 'border-theme-primary bg-theme-surface' : 'border-theme-outline bg-theme-surface'"
                        @press="chooseScope({{ json_encode("household") }})"
                    >
                        <native:column class="w-full gap-1">
                            <native:text class="text-base font-semibold text-theme-on-surface">{{ $householdName }}</native:text>
                            <native:text class="text-xs text-theme-on-surface-variant">
                                {{ __('app.list_create.household_hint') }}
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
                :label="__('app.list_create.submit')"
                class="w-full"
                :loading="$busy"
                :disabled="$busy"
                @press="save"
            />

        </native:column>
    </native:scroll-view>
</native:column>

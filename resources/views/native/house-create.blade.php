<native:column class="w-full h-full bg-theme-background">
    <native:scroll-view class="w-full flex-1">
        <native:column class="w-full gap-4 p-5">

            <native:column class="w-full gap-2 rounded-lg bg-theme-surface p-4">
                <native:outlined-text-input
                    :label="__('app.house_create.name_label')"
                    :placeholder="__('app.house_create.name_placeholder')"
                    native:model.debounce.300ms="name"
                    @submit="create"
                />
                <native:text class="text-xs text-theme-on-surface-variant">
                    {{ __('app.house_create.name_hint') }}
                </native:text>
            </native:column>

            <native:column class="w-full gap-3 rounded-lg bg-theme-surface p-4">
                <native:text class="text-xs font-semibold uppercase text-theme-on-surface-variant">
                    {{ __('app.house_create.color') }}
                </native:text>

                <native:row class="w-full items-center gap-3">
                    @foreach ($this->colors() as $option)
                        <native:pressable class="rounded-full" @press="chooseColor({{ json_encode($option) }})">
                            <native:column class="h-9 w-9 items-center justify-center rounded-full bg-[{{ $option }}]">
                                @if ($option === $color)
                                    <native:icon ios="checkmark" android="check" :size="14" color="#FFFFFF" />
                                @endif
                            </native:column>
                        </native:pressable>
                    @endforeach
                </native:row>
            </native:column>

            <native:column class="w-full gap-2 rounded-lg bg-theme-surface p-4">
                <native:text class="text-xs font-semibold uppercase text-theme-on-surface-variant">
                    {{ __('app.house_create.modules') }}
                </native:text>

                @foreach ([
                    ['key' => 'shopping_lists', 'label' => __('app.modules.shopping_lists'), 'on' => $shoppingLists],
                    ['key' => 'recipes', 'label' => __('app.modules.recipes'), 'on' => $recipes],
                    ['key' => 'economy', 'label' => __('app.modules.economy'), 'on' => $economy],
                ] as $module)
                    <native:pressable class="w-full" @press="toggleModule({{ json_encode($module['key']) }})">
                        <native:row class="w-full items-center gap-2 py-1">
                            <native:icon
                                ios="{{ $module['on'] ? 'checkmark.circle.fill' : 'circle' }}"
                                android="{{ $module['on'] ? 'check_circle' : 'radio_button_unchecked' }}"
                                :size="18"
                                class="{{ $module['on'] ? 'text-theme-primary' : 'text-theme-on-surface-variant' }}"
                            />
                            <native:text class="flex-1 text-sm text-theme-on-surface">{{ $module['label'] }}</native:text>
                        </native:row>
                    </native:pressable>
                @endforeach

                <native:text class="text-xs text-theme-on-surface-variant">
                    {{ __('app.house_create.modules_hint') }}
                </native:text>
            </native:column>

            @if ($error)
                <native:column class="w-full rounded-lg bg-theme-destructive/10 p-4">
                    <native:text class="text-sm text-theme-destructive">{{ $error }}</native:text>
                </native:column>
            @endif

            <native:button
                :label="__('app.house_create.submit')"
                :loading="$saving"
                class="w-full"
                @press="create"
            />

        </native:column>
    </native:scroll-view>
</native:column>

<native:column class="w-full h-full bg-theme-background">
    <native:scroll-view class="w-full flex-1">
        <native:column class="w-full gap-4 p-4">

            @if ($needsLogin)
                <native:column class="w-full items-center gap-3 rounded-lg bg-theme-surface-variant px-4 py-10">
                    <native:icon ios="lock.fill" android="lock" :size="28" class="text-theme-primary" />
                    <native:text class="text-base font-semibold text-theme-on-surface">{{ __('app.recipes.needs_login') }}</native:text>
                    <native:button :label="__('app.lists.sign_in')" class="w-full" @press="goToLogin" />
                </native:column>
            @elseif ($loading)
                <native:row class="w-full items-center justify-center gap-2 py-10">
                    <native:activity-indicator />
                    <native:text class="text-sm text-theme-on-surface-variant">{{ __('app.common.loading') }}</native:text>
                </native:row>
            @else
                <native:row class="w-full gap-2">
                    <native:button :label="__('app.recipes.new')" class="flex-1" @press="create" />
                    <native:button :label="__('app.recipes.import')" variant="secondary" class="flex-1" @press="import" />
                </native:row>

                <native:button :label="__('app.recipes.generate')" variant="secondary" class="w-full" @press="generateWithAi" />

                @if (count($personal) > 0)
                    <native:text class="text-xs font-semibold uppercase text-theme-on-surface-variant">
                        {{ __('app.recipes.mine') }}
                    </native:text>

                    @foreach ($personal as $recipe)
                        <native:pressable
                            :native:key="'recipe-'.$recipe['id']"
                            class="w-full rounded-lg bg-theme-surface p-3"
                            @press="open({{ json_encode($recipe['id']) }})"
                        >
                            <native:row class="w-full items-center gap-3">
                                @if ($recipe['image_url'] !== null)
                                    <native:image :src="$recipe['image_url']" :width="52" :height="52" class="rounded-md" fit="cover" />
                                @else
                                    <native:column class="h-[52] w-[52] items-center justify-center rounded-md bg-theme-surface-variant">
                                        <native:icon ios="book.fill" android="menu_book" :size="22" class="text-theme-primary" />
                                    </native:column>
                                @endif

                                <native:column class="flex-1 gap-0.5">
                                    <native:text class="text-base font-semibold text-theme-on-surface" :max-lines="2">{{ $recipe['name'] }}</native:text>
                                    <native:text class="text-xs text-theme-on-surface-variant">
                                        {{ collect([$recipe['time'], $recipe['servings'] ? __('app.recipes.servings_short', ['count' => $recipe['servings']]) : null, $recipe['difficulty'] ? __('app.recipes.difficulty.'.strtolower($recipe['difficulty'])) : null])->filter()->implode(' · ') }}
                                    </native:text>
                                </native:column>

                                <native:icon ios="chevron.right" android="chevron_right" :size="18" class="text-theme-on-surface-variant" />
                            </native:row>
                        </native:pressable>
                    @endforeach
                @endif

                @if (count($household) > 0)
                    <native:text class="mt-2 text-xs font-semibold uppercase text-theme-on-surface-variant">
                        {{ __('app.recipes.household', ['name' => $activeHouseholdName ?? '']) }}
                    </native:text>

                    @foreach ($household as $recipe)
                        <native:pressable
                            :native:key="'hrecipe-'.$recipe['id']"
                            class="w-full rounded-lg bg-theme-surface p-3"
                            @press="open({{ json_encode($recipe['id']) }})"
                        >
                            <native:row class="w-full items-center gap-3">
                                @if ($recipe['image_url'] !== null)
                                    <native:image :src="$recipe['image_url']" :width="52" :height="52" class="rounded-md" fit="cover" />
                                @else
                                    <native:column class="h-[52] w-[52] items-center justify-center rounded-md bg-theme-surface-variant">
                                        <native:icon ios="house.fill" android="home" :size="22" class="text-theme-primary" />
                                    </native:column>
                                @endif

                                <native:column class="flex-1 gap-0.5">
                                    <native:text class="text-base font-semibold text-theme-on-surface" :max-lines="2">{{ $recipe['name'] }}</native:text>
                                    <native:text class="text-xs text-theme-on-surface-variant">
                                        {{ collect([$recipe['time'], $recipe['servings'] ? __('app.recipes.servings_short', ['count' => $recipe['servings']]) : null])->filter()->implode(' · ') }}
                                    </native:text>
                                </native:column>

                                <native:icon ios="chevron.right" android="chevron_right" :size="18" class="text-theme-on-surface-variant" />
                            </native:row>
                        </native:pressable>
                    @endforeach
                @endif

                @if (count($personal) === 0 && count($household) === 0)
                    <native:column class="w-full items-center gap-2 rounded-lg bg-theme-surface-variant px-4 py-10">
                        <native:icon ios="book.fill" android="menu_book" :size="30" class="text-theme-primary" />
                        <native:text class="text-base font-semibold text-theme-on-surface">{{ __('app.recipes.empty') }}</native:text>
                        <native:text class="text-center text-sm text-theme-on-surface-variant">
                            {{ __('app.recipes.empty_hint') }}
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

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
                @if ($imageUrl !== null)
                    <native:image :src="$imageUrl" class="w-full" :height="200" fit="cover" />
                @endif

                @if ($description !== null)
                    <native:text class="text-sm text-theme-on-surface-variant">{{ $description }}</native:text>
                @endif

                @if ($meta !== '')
                    <native:row class="w-full items-center gap-2">
                        <native:icon ios="clock" android="schedule" :size="16" class="text-theme-on-surface-variant" />
                        <native:text class="text-xs text-theme-on-surface-variant">{{ $meta }}</native:text>
                    </native:row>
                @endif

                <native:row class="w-full gap-2">
                    <native:button :label="__('app.recipes.cook')" class="flex-1" @press="cook" />
                    <native:button :label="__('app.recipes.edit')" variant="secondary" class="flex-1" @press="edit" />
                </native:row>

                <native:button
                    :label="$actionsOpen ? __('app.common.cancel') : __('app.list_detail.actions')"
                    variant="ghost"
                    class="w-full"
                    @press="toggleActions"
                />

                @if ($actionsOpen)
                    <native:column class="w-full gap-2 rounded-lg bg-theme-surface p-3">
                        @if ($householdId !== null)
                            <native:button
                                :label="$shared ? __('app.recipes.unshare') : __('app.recipes.share')"
                                variant="secondary"
                                class="w-full"
                                :disabled="$busy"
                                @press="toggleShare"
                            />
                        @endif

                        @if (count($listOptions) > 0)
                            <native:select
                                :label="__('app.recipes.send_to_list')"
                                :value="$listValue"
                                :options="$listOptions"
                                @change="listValue = $event"
                            />
                            <native:button
                                :label="__('app.recipes.add_to_list')"
                                variant="secondary"
                                class="w-full"
                                :disabled="$busy"
                                @press="addToList"
                            />
                        @endif

                        @if ($confirmDelete)
                            <native:column class="w-full gap-2 rounded-lg border border-theme-destructive p-3">
                                <native:text class="text-sm font-semibold text-theme-destructive">
                                    {{ __('app.recipes.delete_confirm', ['name' => $name]) }}
                                </native:text>
                                <native:row class="w-full gap-2">
                                    <native:button :label="__('app.common.cancel')" variant="secondary" class="flex-1" @press="cancelDelete" />
                                    <native:button :label="__('app.recipes.delete')" variant="destructive" class="flex-1" @press="deleteRecipe" />
                                </native:row>
                            </native:column>
                        @else
                            <native:button :label="__('app.recipes.delete')" variant="ghost" class="w-full" @press="requestDelete" />
                        @endif
                    </native:column>
                @endif

                @if ($notice)
                    <native:text class="text-xs text-theme-success">{{ $notice }}</native:text>
                @endif

                @if (count($ingredients) > 0)
                    <native:text class="mt-2 text-xs font-semibold uppercase text-theme-on-surface-variant">
                        {{ __('app.recipes.ingredients') }}
                    </native:text>

                    <native:column class="w-full gap-1 rounded-lg bg-theme-surface p-3">
                        @foreach ($ingredients as $ingredient)
                            <native:row class="w-full items-center gap-2 py-1">
                                <native:text class="flex-1 text-sm text-theme-on-surface">
                                    {{ $ingredient['name'] }}{{ $ingredient['optional'] ? ' '.__('app.recipes.optional') : '' }}
                                </native:text>
                                @if ($ingredient['amount'] !== null)
                                    <native:text class="text-xs text-theme-on-surface-variant">{{ $ingredient['amount'] }}</native:text>
                                @endif
                            </native:row>
                        @endforeach
                    </native:column>
                @endif

                @if (count($steps) > 0)
                    <native:text class="mt-2 text-xs font-semibold uppercase text-theme-on-surface-variant">
                        {{ __('app.recipes.steps') }}
                    </native:text>

                    @foreach ($steps as $step)
                        <native:column class="w-full gap-2 rounded-lg bg-theme-surface p-3">
                            <native:row class="w-full items-center gap-2">
                                <native:column class="h-[24] w-[24] items-center justify-center rounded-full bg-theme-primary">
                                    <native:text class="text-xs font-bold text-theme-on-primary">{{ $step['number'] }}</native:text>
                                </native:column>
                                <native:text class="flex-1 text-xs text-theme-on-surface-variant">
                                    {{ $step['description'] }}
                                </native:text>
                            </native:row>
                            @if ($step['image_url'] !== null)
                                <native:image :src="$step['image_url']" class="w-full" :height="140" fit="cover" />
                            @endif
                        </native:column>
                    @endforeach
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

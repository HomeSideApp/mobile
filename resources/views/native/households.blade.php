<native:column class="w-full h-full bg-theme-background">
    <native:scroll-view class="w-full flex-1">
        <native:column class="w-full gap-3 p-5">

            @if ($needsLogin)
                <native:column class="w-full items-center gap-3 rounded-lg bg-theme-surface-variant px-4 py-10">
                    <native:icon ios="lock.fill" android="lock" :size="28" class="text-theme-primary" />
                    <native:text class="text-base font-semibold text-theme-on-surface">{{ __('app.lists.needs_login') }}</native:text>
                    <native:button :label="__('app.profile.login')" class="w-full" @press="goToLogin" />
                </native:column>
            @elseif ($error)
                <native:column class="w-full rounded-lg bg-theme-destructive/10 p-4">
                    <native:text class="text-sm text-theme-destructive">{{ $error }}</native:text>
                </native:column>
            @else
                <native:text class="text-xs font-semibold uppercase text-theme-on-surface-variant">
                    {{ __('app.households.subtitle') }}
                </native:text>

                <native:button
                    :label="__('app.households.create')"
                    class="w-full"
                    @press="createHousehold"
                />

                @forelse ($households as $household)
                    <native:pressable
                        class="w-full rounded-lg bg-theme-surface p-3"
                        @press="choose({{ json_encode($household['id']) }})"
                    >
                    <native:row class="w-full items-center gap-3">
                        <native:column class="h-10 w-10 items-center justify-center rounded-full bg-[{{ $household['color'] ?? '#1E6B63' }}]">
                            <native:text class="text-base font-bold" color="#FFFFFF">{{ $household['initial'] }}</native:text>
                        </native:column>

                        <native:column class="flex-1 gap-1">
                            <native:text class="text-sm font-medium text-theme-on-surface">{{ $household['name'] }}</native:text>
                            <native:text class="text-xs text-theme-on-surface-variant">
                                {{ trans_choice('app.house.members_count', $household['members'], ['count' => $household['members']]) }}
                            </native:text>
                        </native:column>

                        @if ($household['active'])
                            <native:icon ios="checkmark" android="check" :size="18" class="text-theme-primary" />
                        @endif
                    </native:row>
                    </native:pressable>
                @empty
                    <native:column class="w-full items-center gap-2 rounded-lg bg-theme-surface-variant px-4 py-10">
                        <native:icon ios="house.fill" android="home" :size="30" class="text-theme-primary" />
                        <native:text class="text-base font-semibold text-theme-on-surface">
                            {{ __('app.house.empty_title') }}
                        </native:text>
                        <native:text class="text-center text-sm text-theme-on-surface-variant">
                            {{ __('app.house.empty_subtitle') }}
                        </native:text>
                    </native:column>
                @endforelse
            @endif

        </native:column>
    </native:scroll-view>
</native:column>

<native:column class="w-full h-full bg-theme-background">
    <native:scroll-view class="w-full flex-1">
        <native:column class="w-full gap-4 p-5">

            @if ($needsLogin)
                <native:column class="w-full items-center gap-3 rounded-lg bg-theme-surface-variant px-4 py-10">
                    <native:icon ios="lock.fill" android="lock" :size="28" class="text-theme-primary" />
                    <native:text class="text-base font-semibold text-theme-on-surface">{{ __('app.lists.needs_login') }}</native:text>
                    <native:button :label="__('app.profile.login')" class="w-full" @press="goToLogin" />
                </native:column>
            @elseif ($moduleDisabled)
                <native:column class="w-full items-center gap-2 rounded-lg bg-theme-surface-variant px-4 py-10">
                    <native:icon ios="creditcard" android="credit_card" :size="30" class="text-theme-primary" />
                    <native:text class="text-base font-semibold text-theme-on-surface">{{ __('app.expenses.module_off_title') }}</native:text>
                    <native:text class="text-center text-sm text-theme-on-surface-variant">
                        {{ __('app.expenses.module_off_hint') }}
                    </native:text>
                </native:column>
            @elseif ($error)
                <native:column class="w-full rounded-lg bg-theme-destructive/10 p-4">
                    <native:text class="text-sm text-theme-destructive">{{ $error }}</native:text>
                </native:column>
            @else
                <native:column class="w-full gap-3 rounded-lg bg-theme-surface p-4">
                    <native:row class="w-full items-center gap-2">
                        <native:text class="text-xs font-semibold uppercase text-theme-on-surface-variant">
                            {{ __('app.expenses.this_month') }}
                        </native:text>
                        <native:spacer />
                        <native:text class="text-xs text-theme-on-surface-variant">
                            {{ $householdScope ? __('app.expenses.scope_home') : __('app.expenses.scope_personal') }}
                        </native:text>
                    </native:row>

                    <native:text class="text-2xl font-bold text-theme-on-surface">{{ $expenses }}</native:text>
                    <native:text class="text-xs text-theme-on-surface-variant">{{ __('app.expenses.spent') }}</native:text>

                    <native:row class="w-full items-center gap-2 pt-2">
                        <native:text class="text-xs text-theme-on-surface-variant">{{ __('app.expenses.income') }}</native:text>
                        <native:spacer />
                        <native:text class="text-sm text-theme-on-surface">{{ $income }}</native:text>
                    </native:row>
                    <native:row class="w-full items-center gap-2">
                        <native:text class="text-xs text-theme-on-surface-variant">{{ __('app.expenses.balance') }}</native:text>
                        <native:spacer />
                        <native:text class="text-sm font-semibold text-theme-on-surface">{{ $balance }}</native:text>
                    </native:row>
                </native:column>

                @if ($places !== [])
                    <native:column class="w-full gap-2 rounded-lg bg-theme-surface p-4">
                        <native:text class="text-xs font-semibold uppercase text-theme-on-surface-variant">
                            {{ __('app.expenses.by_place') }}
                        </native:text>

                        @foreach ($places as $place)
                            <native:row class="w-full items-center gap-2">
                                <native:text class="flex-1 text-sm text-theme-on-surface">{{ $place['place'] }}</native:text>
                                <native:text class="text-sm text-theme-on-surface-variant">{{ $place['total'] }}</native:text>
                            </native:row>
                        @endforeach
                    </native:column>
                @endif
            @endif

        </native:column>
    </native:scroll-view>
</native:column>

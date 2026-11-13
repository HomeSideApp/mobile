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
                @if ($householdScope || $scope === 'personal')
                    <native:row class="w-full items-center gap-2 rounded-lg bg-theme-surface-variant p-1">
                        <native:pressable class="flex-1" @press="useScope({{ json_encode('home') }})">
                            <native:column class="w-full items-center rounded-lg px-3 py-2 {{ $scope === 'home' ? 'bg-theme-surface' : '' }}">
                                <native:text class="text-xs font-semibold {{ $scope === 'home' ? 'text-theme-on-surface' : 'text-theme-on-surface-variant' }}">
                                    {{ __('app.expenses.scope_home') }}
                                </native:text>
                            </native:column>
                        </native:pressable>
                        <native:pressable class="flex-1" @press="useScope({{ json_encode('personal') }})">
                            <native:column class="w-full items-center rounded-lg px-3 py-2 {{ $scope === 'personal' ? 'bg-theme-surface' : '' }}">
                                <native:text class="text-xs font-semibold {{ $scope === 'personal' ? 'text-theme-on-surface' : 'text-theme-on-surface-variant' }}">
                                    {{ __('app.expenses.scope_personal') }}
                                </native:text>
                            </native:column>
                        </native:pressable>
                    </native:row>
                @endif

                <native:column class="w-full gap-3 rounded-lg bg-theme-surface p-4">
                    <native:row class="w-full items-center gap-2">
                        <native:text class="text-xs font-semibold uppercase text-theme-on-surface-variant">
                            {{ __('app.expenses.this_month') }}
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

                <native:button
                    :label="__('app.expenses.new')"
                    class="w-full"
                    @press="newExpense"
                />

                <native:row class="w-full items-center gap-2 rounded-lg bg-theme-surface-variant p-1">
                    @foreach ([
                        ['key' => 'month', 'label' => __('app.expenses.range_month')],
                        ['key' => 'last', 'label' => __('app.expenses.range_last')],
                        ['key' => 'year', 'label' => __('app.expenses.range_year')],
                        ['key' => 'all', 'label' => __('app.expenses.range_all')],
                    ] as $option)
                        <native:pressable class="flex-1" @press="useRange({{ json_encode($option['key']) }})">
                            <native:column class="w-full items-center rounded-lg px-1 py-2 {{ $range === $option['key'] ? 'bg-theme-surface' : '' }}">
                                <native:text class="text-[11px] font-semibold {{ $range === $option['key'] ? 'text-theme-on-surface' : 'text-theme-on-surface-variant' }}">
                                    {{ $option['label'] }}
                                </native:text>
                            </native:column>
                        </native:pressable>
                    @endforeach
                </native:row>

                @if ($movements !== [])
                    <native:column class="w-full gap-2 rounded-lg bg-theme-surface p-4">
                        <native:text class="text-xs font-semibold uppercase text-theme-on-surface-variant">
                            {{ __('app.expenses.recent') }}
                        </native:text>

                        @foreach ($movements as $movement)
                            <native:pressable class="w-full" @press="openExpense({{ json_encode($movement['id']) }})">
                                <native:row class="w-full items-center gap-2 py-1">
                                    <native:column class="flex-1 gap-1">
                                        <native:text class="text-sm text-theme-on-surface">{{ $movement['title'] }}</native:text>
                                        <native:text class="text-[10px] text-theme-on-surface-variant">{{ $movement['date'] }}</native:text>
                                    </native:column>
                                    <native:text class="text-sm font-semibold {{ $movement['income'] ? 'text-theme-primary' : 'text-theme-on-surface' }}">
                                        {{ $movement['income'] ? '+' : '−' }}{{ $movement['amount'] }}
                                    </native:text>
                                </native:row>
                            </native:pressable>
                        @endforeach
                    </native:column>
                @endif

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

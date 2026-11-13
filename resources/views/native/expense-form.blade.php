<native:column class="w-full h-full bg-theme-background">
    <native:scroll-view class="w-full flex-1">
        <native:column class="w-full gap-4 p-5">

            @if ($error)
                <native:column class="w-full rounded-lg bg-theme-destructive/10 p-4">
                    <native:text class="text-sm text-theme-destructive">{{ $error }}</native:text>
                </native:column>
            @endif

            <native:row class="w-full items-center gap-2 rounded-lg bg-theme-surface-variant p-1">
                <native:pressable class="flex-1" @press="setType({{ json_encode('expense') }})">
                    <native:column class="w-full items-center rounded-lg px-3 py-2 {{ $type === 'expense' ? 'bg-theme-surface' : '' }}">
                        <native:text class="text-xs font-semibold {{ $type === 'expense' ? 'text-theme-on-surface' : 'text-theme-on-surface-variant' }}">
                            {{ __('app.expense_form.expense') }}
                        </native:text>
                    </native:column>
                </native:pressable>
                <native:pressable class="flex-1" @press="setType({{ json_encode('income') }})">
                    <native:column class="w-full items-center rounded-lg px-3 py-2 {{ $type === 'income' ? 'bg-theme-surface' : '' }}">
                        <native:text class="text-xs font-semibold {{ $type === 'income' ? 'text-theme-on-surface' : 'text-theme-on-surface-variant' }}">
                            {{ __('app.expense_form.income') }}
                        </native:text>
                    </native:column>
                </native:pressable>
            </native:row>

            <native:column class="w-full gap-3 rounded-lg bg-theme-surface p-4">
                <native:outlined-text-input
                    :label="__('app.expense_form.title')"
                    :placeholder="__('app.expense_form.title_placeholder')"
                    native:model.debounce.300ms="title"
                />
                <native:outlined-text-input
                    :label="__('app.expense_form.amount', ['currency' => $currency])"
                    keyboard="decimal"
                    native:model.debounce.300ms="amount"
                />
                @if ($accountOptions !== [])
                    <native:select
                        :label="__('app.expense_form.account')"
                        :value="collect($accountOptions)->firstWhere('value', $accountId)['label'] ?? ''"
                        :options="array_column($accountOptions, 'label')"
                        @change="setAccount"
                    />
                @endif

                <native:outlined-text-input
                    :label="__('app.expense_form.place')"
                    native:model.debounce.300ms="place"
                />
                <native:outlined-text-input
                    :label="__('app.expense_form.date')"
                    :placeholder="__('app.expense_form.date_hint')"
                    native:model.debounce.300ms="occurredAt"
                />
                <native:outlined-text-input
                    :label="__('app.expense_form.notes')"
                    native:model.debounce.300ms="notes"
                />
            </native:column>

            <native:button
                :label="__('app.common.save')"
                :loading="$saving"
                class="w-full"
                @press="save"
            />

            @if ($transactionId)
                @if ($confirmDelete)
                    <native:column class="w-full gap-2 rounded-lg bg-theme-destructive/10 p-4">
                        <native:text class="text-sm text-theme-destructive">{{ __('app.expense_form.delete_confirm') }}</native:text>
                        <native:row class="w-full items-center gap-2">
                            <native:button
                                :label="__('app.common.cancel')"
                                variant="secondary"
                                class="flex-1"
                                @press="cancelDelete"
                            />
                            <native:button
                                :label="__('app.expense_form.delete')"
                                variant="destructive"
                                class="flex-1"
                                @press="delete"
                            />
                        </native:row>
                    </native:column>
                @else
                    <native:button
                        :label="__('app.expense_form.delete')"
                        variant="destructive"
                        class="w-full"
                        @press="askDelete"
                    />
                @endif
            @endif

        </native:column>
    </native:scroll-view>
</native:column>

<native:column class="w-full h-full bg-theme-background">
    <native:scroll-view class="w-full flex-1">
        <native:column class="w-full gap-4 p-5">

            @if ($error)
                <native:column class="w-full rounded-lg bg-theme-destructive/10 p-4">
                    <native:text class="text-sm text-theme-destructive">{{ $error }}</native:text>
                </native:column>
            @endif

            @if ($importId === null)
                <native:column class="w-full items-center gap-3 rounded-lg bg-theme-surface px-4 py-10">
                    <native:icon ios="doc.text.viewfinder" android="document_scanner" :size="30" class="text-theme-primary" />
                    <native:text class="text-base font-semibold text-theme-on-surface">{{ __('app.ticket.pick_title') }}</native:text>
                    <native:text class="text-center text-sm text-theme-on-surface-variant">{{ __('app.ticket.pick_hint') }}</native:text>
                    <native:button :label="__('app.ticket.pick')" class="w-full" @press="pickTicket" />
                </native:column>
            @elseif (in_array($status, ['queued', 'processing'], true))
                <native:column class="w-full items-center gap-3 rounded-lg bg-theme-surface-variant px-4 py-10">
                    <native:activity-indicator />
                    <native:text class="text-sm text-theme-on-surface">{{ __('app.ticket.reading') }}</native:text>
                    <native:text class="text-xs text-theme-on-surface-variant">{{ $progress }}%</native:text>
                </native:column>
            @elseif ($status === 'review_required')
                <native:column class="w-full gap-3 rounded-lg bg-theme-surface p-4">
                    <native:text class="text-xs font-semibold uppercase text-theme-on-surface-variant">
                        {{ __('app.ticket.review') }}
                    </native:text>

                    <native:text class="text-2xl font-bold text-theme-on-surface">{{ $total }}</native:text>

                    @if ($title)
                        <native:row class="w-full items-center gap-2">
                            <native:text class="text-xs text-theme-on-surface-variant">{{ __('app.expense_form.title') }}</native:text>
                            <native:spacer />
                            <native:text class="text-sm text-theme-on-surface">{{ $title }}</native:text>
                        </native:row>
                    @endif

                    @if ($place)
                        <native:row class="w-full items-center gap-2">
                            <native:text class="text-xs text-theme-on-surface-variant">{{ __('app.expense_form.place') }}</native:text>
                            <native:spacer />
                            <native:text class="text-sm text-theme-on-surface">{{ $place }}</native:text>
                        </native:row>
                    @endif

                    @if ($date)
                        <native:row class="w-full items-center gap-2">
                            <native:text class="text-xs text-theme-on-surface-variant">{{ __('app.expense_form.date') }}</native:text>
                            <native:spacer />
                            <native:text class="text-sm text-theme-on-surface">{{ $date }}</native:text>
                        </native:row>
                    @endif

                    @foreach ($warnings as $warning)
                        <native:text class="text-xs text-theme-on-surface-variant">· {{ $warning }}</native:text>
                    @endforeach
                </native:column>

                <native:row class="w-full items-center gap-2">
                    <native:button
                        :label="__('app.ticket.discard')"
                        variant="secondary"
                        class="flex-1"
                        @press="discard"
                    />
                    <native:button
                        :label="__('app.ticket.confirm')"
                        :loading="$busy"
                        class="flex-1"
                        @press="confirm"
                    />
                </native:row>
            @else
                <native:column class="w-full gap-3 rounded-lg bg-theme-surface p-4">
                    <native:text class="text-sm text-theme-destructive">{{ __('app.ticket.failed') }}</native:text>
                    <native:button :label="__('app.ticket.retry')" class="w-full" @press="discard" />
                </native:column>
            @endif

        </native:column>
    </native:scroll-view>
</native:column>

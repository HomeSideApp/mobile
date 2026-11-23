<native:column class="w-full h-full bg-theme-background">
    <native:scroll-view class="w-full flex-1">
        <native:column class="w-full gap-4 p-4">

            @if ($loading)
                <native:row class="w-full items-center justify-center gap-2 py-10">
                    <native:activity-indicator />
                    <native:text class="text-sm text-theme-on-surface-variant">{{ __('app.common.loading') }}</native:text>
                </native:row>
            @elseif (count($steps) === 0)
                <native:column class="w-full items-center gap-2 rounded-lg bg-theme-surface-variant px-4 py-10">
                    <native:text class="text-sm text-theme-on-surface-variant">{{ __('app.recipes.no_steps') }}</native:text>
                </native:column>
            @else
                <native:row class="w-full items-center gap-2">
                    <native:text class="flex-1 text-xs font-semibold uppercase text-theme-on-surface-variant">
                        {{ __('app.recipes.step_of', ['current' => $steps[$index]['number'], 'total' => count($steps)]) }}
                    </native:text>
                </native:row>

                <native:column class="w-full gap-3 rounded-lg bg-theme-surface p-4">
                    <native:text class="text-base text-theme-on-surface">{{ $steps[$index]['description'] }}</native:text>

                    @if ($steps[$index]['image_url'] !== null)
                        <native:image :src="$steps[$index]['image_url']" class="w-full" :height="180" fit="cover" />
                    @endif

                    @foreach ($steps[$index]['timers'] as $timer)
                        <native:button
                            :label="__('app.recipes.start_timer', ['name' => $timer['name'] !== '' ? $timer['name'] : __('app.recipes.timer'), 'minutes' => max(1, intdiv($timer['duration'], 60))])"
                            variant="secondary"
                            class="w-full"
                            @press="startTimer({{ json_encode($timer['name']) }}, {{ $timer['duration'] }})"
                        />
                    @endforeach
                </native:column>

                @if ($remaining !== null)
                    <native:column class="w-full items-center gap-2 rounded-lg bg-theme-surface-variant p-4">
                        <native:text class="text-2xl font-bold text-theme-primary">{{ $timerText }}</native:text>
                        @if ($timerLabel)
                            <native:text class="text-xs text-theme-on-surface-variant">{{ $timerLabel }}</native:text>
                        @endif
                        <native:button :label="__('app.recipes.stop_timer')" variant="ghost" class="w-full" @press="stopTimer" />
                    </native:column>
                @endif

                @if ($notice)
                    <native:text class="text-sm font-semibold text-theme-success">{{ $notice }}</native:text>
                @endif

                <native:row class="w-full gap-2">
                    <native:button
                        :label="__('app.recipes.previous')"
                        variant="secondary"
                        class="flex-1"
                        :disabled="$index === 0"
                        @press="previous"
                    />
                    <native:button
                        :label="__('app.recipes.next')"
                        class="flex-1"
                        :disabled="$index >= count($steps) - 1"
                        @press="next"
                    />
                </native:row>
            @endif

            @if ($error)
                <native:column class="w-full rounded-lg bg-theme-destructive/10 p-4">
                    <native:text class="text-sm text-theme-destructive">{{ $error }}</native:text>
                </native:column>
            @endif

        </native:column>
    </native:scroll-view>
</native:column>

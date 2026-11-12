<native:column class="w-full h-full bg-theme-background">
    <native:scroll-view class="w-full flex-1">
        <native:column class="w-full gap-6 p-5">

            <native:column class="w-full items-center gap-2 pt-6">
                <native:image src="logo-mark.png" :width="76" :height="76" fit="contain" alt="HomeSide" />
                <native:text class="text-2xl font-bold text-theme-on-background">HomeSide</native:text>
                <native:text class="text-center text-sm text-theme-on-surface-variant">
                    {{ __('app.server_setup.headline') }}
                </native:text>
            </native:column>

            <native:outlined-text-input
                :label="__('app.server_setup.server_label')"
                :placeholder="__('app.server_setup.placeholder')"
                keyboard="url"
                native:model.debounce.400ms="server"
                @change="onServerEdited"
                @submit="checkConnection"
            />

            <native:text class="-mt-3 text-xs text-theme-on-surface-variant">
                {{ __('app.server_setup.hint') }}
            </native:text>

            @if ($status === 'checking')
                <native:row class="items-center gap-2">
                    <native:activity-indicator />
                    <native:text class="text-sm text-theme-on-surface-variant">{{ __('app.server_setup.checking') }}</native:text>
                </native:row>
            @endif

            @if ($status === 'ok')
                <native:column class="w-full gap-1 rounded-lg bg-theme-surface-variant p-4">
                    <native:text class="text-sm font-semibold text-theme-on-surface">
                        {{ __('app.server_setup.connected_to', ['app' => $serverName]) }}
                    </native:text>
                    <native:text class="text-xs text-theme-on-surface-variant">{{ $server }}</native:text>
                </native:column>
            @endif

            @if ($error !== null)
                <native:column class="w-full rounded-lg bg-theme-destructive/10 p-4">
                    <native:text class="text-sm text-theme-destructive">{{ $error }}</native:text>
                </native:column>
            @endif

            <native:button
                :label="__('app.server_setup.test')"
                class="w-full"
                :loading="$status === 'checking'"
                :disabled="$status === 'checking'"
                @press="checkConnection"
            />

            @if ($alreadyConfigured)
                <native:button
                    :label="__('app.server_setup.continue')"
                    variant="secondary"
                    class="w-full"
                    @press="continueToApp"
                />
            @endif

            <native:text class="text-center text-xs text-theme-on-surface-variant">
                {{ __('app.server_setup.footer') }}
            </native:text>

        </native:column>
    </native:scroll-view>
</native:column>

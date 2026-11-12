<native:column class="w-full h-full bg-theme-background">
    <native:scroll-view class="w-full flex-1">
        <native:column class="w-full gap-5 p-5">

            <native:column class="w-full items-center gap-2 pt-6">
                <native:image src="logo-mark.png" :width="72" :height="72" fit="contain" alt="HomeSide" />
                <native:text class="text-2xl font-bold text-theme-on-background">HomeSide</native:text>
                <native:text class="text-center text-sm text-theme-on-surface-variant">
                    {{ $step === 'credentials' ? __('app.login.tagline_credentials') : __('app.login.tagline_otp') }}
                </native:text>
            </native:column>

            @if ($step === 'credentials')

                <native:outlined-text-input
                    :label="__('app.login.email')"
                    :placeholder="__('app.login.email_placeholder')"
                    keyboard="email"
                    autocomplete="email"
                    native:model.debounce.300ms="email"
                    @submit="submitCredentials"
                />

                <native:outlined-text-input
                    :label="__('app.login.password')"
                    secure
                    revealable
                    native:model.debounce.300ms="password"
                    @submit="submitCredentials"
                />

                <native:button
                    :label="__('app.login.continue')"
                    class="w-full"
                    :loading="$busy"
                    :disabled="$busy"
                    @press="submitCredentials"
                />

            @else

                @if ($notice)
                    <native:column class="w-full rounded-lg bg-theme-info p-4">
                        <native:text class="text-sm text-theme-on-info">{{ $notice }}</native:text>
                    </native:column>
                @endif

                <native:outlined-text-input
                    :label="__('app.login.code')"
                    placeholder="123456"
                    keyboard="number"
                    max-length="6"
                    autofocus
                    native:model.debounce.200ms="code"
                    @submit="submitOtp"
                />

                <native:button
                    :label="__('app.login.submit')"
                    class="w-full"
                    :loading="$busy"
                    :disabled="$busy"
                    @press="submitOtp"
                />

                <native:row class="w-full items-center justify-between">
                    <native:pressable @press="resend">
                        <native:text class="text-sm font-semibold text-theme-primary">{{ __('app.login.resend') }}</native:text>
                    </native:pressable>

                    <native:pressable @press="backToCredentials">
                        <native:text class="text-sm text-theme-on-surface-variant">{{ __('app.login.other_account') }}</native:text>
                    </native:pressable>
                </native:row>

            @endif

            @if ($error)
                <native:column class="w-full rounded-lg bg-theme-destructive/10 p-4">
                    <native:text class="text-sm text-theme-destructive">{{ $error }}</native:text>
                </native:column>
            @endif

            <native:pressable class="w-full items-center pt-2" @press="changeServer">
                <native:text class="text-xs text-theme-on-surface-variant">
                    {{ __('app.common.change_server') }}
                </native:text>
            </native:pressable>

        </native:column>
    </native:scroll-view>
</native:column>

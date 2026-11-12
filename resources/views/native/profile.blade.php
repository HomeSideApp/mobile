<native:column class="w-full h-full bg-theme-background">
    <native:scroll-view class="w-full flex-1">
        <native:column class="w-full gap-4 p-5">

            <native:column class="w-full gap-1 rounded-lg bg-theme-surface p-4">
                <native:text class="text-xs font-semibold uppercase text-theme-on-surface-variant">
                    {{ __('app.profile.account') }}
                </native:text>
                <native:text class="text-base font-semibold text-theme-on-surface">
                    {{ $userName ?? ($hasSession ? __('app.profile.session_active') : __('app.profile.no_session')) }}
                </native:text>
                @if ($userEmail)
                    <native:text class="text-sm text-theme-on-surface-variant">{{ $userEmail }}</native:text>
                @endif
            </native:column>

            {{-- ── Idioma ─────────────────────────────────────────────── --}}
            <native:column class="w-full gap-2 rounded-lg bg-theme-surface p-4">
                <native:text class="text-xs font-semibold uppercase text-theme-on-surface-variant">
                    {{ __('app.profile.language') }}
                </native:text>

                <native:select
                    :label="__('app.profile.language')"
                    :value="$localeValue"
                    :options="$localeOptions"
                    @change="setLocaleOption"
                />

                @if ($languageNotice)
                    <native:text class="text-xs text-theme-on-surface-variant">{{ $languageNotice }}</native:text>
                @endif

                <native:select
                    :label="__('app.profile.appearance')"
                    :value="$appearanceValue"
                    :options="$appearanceOptions"
                    @change="setAppearanceOption"
                />
            </native:column>

            <native:column class="w-full gap-1 rounded-lg bg-theme-surface p-4">
                <native:text class="text-xs font-semibold uppercase text-theme-on-surface-variant">
                    {{ __('app.profile.server') }}
                </native:text>
                <native:text class="text-sm text-theme-on-surface">{{ $server ?? __('app.profile.not_configured') }}</native:text>
            </native:column>

            {{-- ── Actualizaciones ─────────────────────────────────────── --}}
            <native:column class="w-full gap-3 rounded-lg bg-theme-surface p-4">
                <native:text class="text-xs font-semibold uppercase text-theme-on-surface-variant">
                    {{ __('app.profile.version') }}
                </native:text>

                <native:row class="w-full items-center gap-2">
                    <native:text class="text-sm text-theme-on-surface">{{ __('app.common.app_name') }} {{ $update['current_version'] ?? '—' }}</native:text>
                    <native:spacer />
                    <native:text class="text-xs text-theme-on-surface-variant">{{ $update['channel_label'] ?? '' }}</native:text>
                </native:row>

                @if ($updateAvailable)
                    <native:column class="w-full gap-1 rounded-lg bg-theme-accent p-3">
                        <native:text class="text-sm font-semibold text-theme-on-accent">
                            {{ is_string($update['latest_version'] ?? null)
                                ? __('app.updates.available_version', ['version' => $update['latest_version']])
                                : __('app.updates.available') }}
                        </native:text>
                        @if (is_string($update['notes'] ?? null))
                            <native:text class="text-xs text-theme-on-accent">{{ $update['notes'] }}</native:text>
                        @endif
                    </native:column>

                    @if ($downloadStatus === 'running')
                        <native:row class="w-full items-center gap-2">
                            <native:activity-indicator />
                            <native:text class="flex-1 text-sm text-theme-on-surface">
                                {{ __('app.updates.downloading', ['progress' => $downloadProgress]) }}
                            </native:text>
                        </native:row>
                    @endif

                    <native:button
                        :label="$downloadStatus === 'failed'
                            ? __('app.updates.retry')
                            : ($downloadStatus === 'successful' ? __('app.updates.install') : __('app.updates.download'))"
                        class="w-full"
                        :disabled="$downloadStatus === 'running'"
                        @press="downloadUpdate"
                    />

                    @if ($downloadMessage)
                        <native:text class="text-xs text-theme-on-surface-variant">{{ $downloadMessage }}</native:text>
                    @endif
                @elseif (($update['state'] ?? '') === 'up_to_date')
                    <native:text class="text-sm text-theme-success">{{ __('app.profile.up_to_date') }}</native:text>
                @elseif (($update['state'] ?? '') === 'unavailable')
                    <native:text class="text-xs text-theme-on-surface-variant">{{ $update['message'] ?? '' }}</native:text>
                @endif

                <native:button
                    :label="__('app.profile.check')"
                    variant="secondary"
                    class="w-full"
                    :loading="$checkingUpdate"
                    :disabled="$checkingUpdate"
                    @press="checkForUpdates"
                />

                <native:text class="text-xs text-theme-on-surface-variant">
                    {{ __('app.updates.hint') }}
                </native:text>
            </native:column>

            {{-- ── Canal ──────────────────────────────────────────────── --}}
            <native:column class="w-full gap-2 rounded-lg bg-theme-surface p-4">
                <native:text class="text-xs font-semibold uppercase text-theme-on-surface-variant">
                    {{ __('app.profile.channel') }}
                </native:text>

                <native:select
                    :label="__('app.profile.channel')"
                    :value="$channelValue"
                    :options="$channelOptions"
                    :supporting="$channelDescription"
                    @change="setChannelOption"
                />
            </native:column>

            <native:column class="w-full gap-1 rounded-lg bg-theme-surface p-4">
                <native:text class="text-xs font-semibold uppercase text-theme-on-surface-variant">
                    {{ __('app.profile.security') }}
                </native:text>
                <native:text class="text-sm text-theme-on-surface">
                    {{ __('app.profile.two_factor', ['state' => $twoFactorEnabled ? __('app.profile.two_factor_on') : __('app.profile.two_factor_off')]) }}
                </native:text>
                <native:text class="text-xs text-theme-on-surface-variant">
                    {{ __('app.profile.security_hint') }}
                </native:text>
            </native:column>

            <native:button
                :label="__('app.profile.change_server')"
                variant="secondary"
                class="w-full"
                @press="changeServer"
            />

            @if ($hasSession)
                <native:button
                    :label="__('app.profile.logout')"
                    variant="destructive"
                    class="w-full"
                    :loading="$busy"
                    @press="logout"
                />
            @else
                <native:button :label="__('app.profile.login')" class="w-full" @press="goToLogin" />
            @endif

            @if ($error)
                <native:column class="w-full rounded-lg bg-theme-destructive/10 p-4">
                    <native:text class="text-sm text-theme-destructive">{{ $error }}</native:text>
                </native:column>
            @endif

            {{-- TEMPORAL: diagnóstico de apariencia. Borrar cuando el bug esté cerrado. --}}
            <native:column class="w-full gap-1 rounded-lg bg-theme-surface-variant p-4">
                <native:text class="text-xs font-semibold uppercase text-theme-on-surface-variant">Diagnostic</native:text>
                @foreach ($this->diagnostics() as $line)
                    <native:row class="w-full items-center gap-2">
                        <native:text class="text-xs text-theme-on-surface-variant">{{ $line['label'] }}</native:text>
                        <native:spacer />
                        <native:text class="text-xs text-theme-on-surface">{{ $line['value'] }}</native:text>
                    </native:row>
                @endforeach
            </native:column>

            <native:text class="text-center text-xs text-theme-on-surface-variant">
                {{ __('app.profile.footer', ['version' => config('nativephp.version')]) }}
            </native:text>

        </native:column>
    </native:scroll-view>
</native:column>

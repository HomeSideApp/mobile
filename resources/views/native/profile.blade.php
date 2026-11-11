<native:column class="w-full h-full bg-theme-background">
    <native:scroll-view class="w-full flex-1">
        <native:column class="w-full gap-4 p-5">

            <native:column class="w-full gap-1 rounded-lg bg-theme-surface p-4">
                <native:text class="text-xs font-semibold uppercase text-theme-on-surface-variant">
                    Cuenta
                </native:text>
                <native:text class="text-base font-semibold text-theme-on-surface">
                    {{ $userName ?? ($hasSession ? 'Sesión activa' : 'Sin iniciar sesión') }}
                </native:text>
                @if ($userEmail)
                    <native:text class="text-sm text-theme-on-surface-variant">{{ $userEmail }}</native:text>
                @endif
                @if ($locale)
                    <native:text class="text-xs text-theme-on-surface-variant">Idioma: {{ $locale }}</native:text>
                @endif
            </native:column>

            <native:column class="w-full gap-1 rounded-lg bg-theme-surface p-4">
                <native:text class="text-xs font-semibold uppercase text-theme-on-surface-variant">
                    Servidor
                </native:text>
                <native:text class="text-sm text-theme-on-surface">{{ $server ?? 'Sin configurar' }}</native:text>
            </native:column>

            {{-- ── Actualizaciones ─────────────────────────────────────── --}}
            <native:column class="w-full gap-3 rounded-lg bg-theme-surface p-4">
                <native:text class="text-xs font-semibold uppercase text-theme-on-surface-variant">
                    Versión
                </native:text>

                <native:row class="w-full items-center gap-2">
                    <native:text class="text-sm text-theme-on-surface">HomeSide {{ $update['current_version'] ?? '—' }}</native:text>
                    <native:spacer />
                    <native:text class="text-xs text-theme-on-surface-variant">Canal {{ $update['channel_label'] ?? '' }}</native:text>
                </native:row>

                @if ($updateAvailable)
                    <native:column class="w-full gap-1 rounded-lg bg-theme-accent p-3">
                        <native:text class="text-sm font-semibold text-theme-on-accent">
                            Hay una versión nueva{{ is_string($update['latest_version'] ?? null) ? ': '.$update['latest_version'] : '' }}
                        </native:text>
                        @if (is_string($update['notes'] ?? null))
                            <native:text class="text-xs text-theme-on-accent">{{ $update['notes'] }}</native:text>
                        @endif
                    </native:column>

                    <native:button
                        label="Descargar actualización"
                        class="w-full"
                        @press="downloadUpdate"
                    />
                @elseif (($update['state'] ?? '') === 'up_to_date')
                    <native:text class="text-sm text-theme-success">Estás al día.</native:text>
                @elseif (($update['state'] ?? '') === 'unavailable')
                    <native:text class="text-xs text-theme-on-surface-variant">{{ $update['message'] ?? '' }}</native:text>
                @endif

                <native:button
                    label="Buscar actualizaciones"
                    variant="secondary"
                    class="w-full"
                    :loading="$checkingUpdate"
                    :disabled="$checkingUpdate"
                    @press="checkForUpdates"
                />

                <native:text class="text-xs text-theme-on-surface-variant">
                    Las actualizaciones se instalan desde el navegador; Android va a pedir permiso
                    la primera vez.
                </native:text>
            </native:column>

            {{-- ── Canal ──────────────────────────────────────────────── --}}
            <native:column class="w-full gap-2 rounded-lg bg-theme-surface p-4">
                <native:text class="text-xs font-semibold uppercase text-theme-on-surface-variant">
                    Canal de actualizaciones
                </native:text>

                @foreach ($channels as $key => $meta)
                    <native:pressable
                        :native:key="'channel-'.$key"
                        class="w-full rounded-lg border p-3"
                        :class="$channel === $key ? 'border-theme-primary bg-theme-surface-variant' : 'border-theme-outline bg-theme-surface'"
                        @press="setChannel({{ json_encode($key) }})"
                    >
                        <native:column class="w-full gap-0.5">
                            <native:text class="text-sm font-semibold text-theme-on-surface">{{ $meta['label'] }}</native:text>
                            <native:text class="text-xs text-theme-on-surface-variant">{{ $meta['description'] }}</native:text>
                        </native:column>
                    </native:pressable>
                @endforeach
            </native:column>

            <native:column class="w-full gap-1 rounded-lg bg-theme-surface p-4">
                <native:text class="text-xs font-semibold uppercase text-theme-on-surface-variant">
                    Seguridad
                </native:text>
                <native:text class="text-sm text-theme-on-surface">
                    Verificación en dos pasos: {{ $twoFactorEnabled ? 'activa' : 'sin configurar' }}
                </native:text>
                <native:text class="text-xs text-theme-on-surface-variant">
                    La contraseña y los códigos de recuperación se gestionan desde la web de tu servidor.
                </native:text>
            </native:column>

            <native:button
                label="Cambiar servidor"
                variant="secondary"
                class="w-full"
                @press="changeServer"
            />

            @if ($hasSession)
                <native:button
                    label="Cerrar sesión"
                    variant="destructive"
                    class="w-full"
                    :loading="$busy"
                    @press="logout"
                />
            @else
                <native:button label="Iniciar sesión" class="w-full" @press="goToLogin" />
            @endif

            @if ($error)
                <native:column class="w-full rounded-lg bg-theme-destructive/10 p-4">
                    <native:text class="text-sm text-theme-destructive">{{ $error }}</native:text>
                </native:column>
            @endif

            <native:text class="text-center text-xs text-theme-on-surface-variant">
                HomeSide {{ config('nativephp.version') }} · la administración se gestiona desde la web
            </native:text>

        </native:column>
    </native:scroll-view>
</native:column>

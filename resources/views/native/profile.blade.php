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

<native:column class="w-full h-full bg-theme-background">
    <native:scroll-view class="w-full flex-1">
        <native:column class="w-full gap-4 p-5">

            <native:column class="w-full gap-1 rounded-lg bg-theme-surface p-4">
                <native:text class="text-xs font-semibold uppercase text-theme-on-surface-variant">
                    Servidor
                </native:text>
                <native:text class="text-sm text-theme-on-surface">{{ $server ?? 'Sin configurar' }}</native:text>
            </native:column>

            <native:column class="w-full gap-1 rounded-lg bg-theme-surface p-4">
                <native:text class="text-xs font-semibold uppercase text-theme-on-surface-variant">
                    Sesión
                </native:text>
                <native:text class="text-sm text-theme-on-surface">
                    {{ $hasSession ? 'Conectada' : 'Sin iniciar sesión' }}
                </native:text>
            </native:column>

            <native:button
                label="Cambiar servidor"
                variant="secondary"
                class="w-full"
                @press="changeServer"
            />

            <native:text class="text-center text-xs text-theme-on-surface-variant">
                HomeSide {{ config('nativephp.version') }} · la administración se gestiona desde la web
            </native:text>

        </native:column>
    </native:scroll-view>
</native:column>

<native:column class="w-full h-full bg-theme-background">
    <native:scroll-view class="w-full flex-1">
        <native:column class="w-full gap-6 p-5">

            <native:column class="w-full items-center gap-2 pt-6">
                <native:image src="logo-mark.png" :width="76" :height="76" fit="contain" alt="HomeSide" />
                <native:text class="text-2xl font-bold text-theme-on-background">HomeSide</native:text>
                <native:text class="text-center text-sm text-theme-on-surface-variant">
                    Tu casa, ordenada. Elegí a qué servidor conectarte.
                </native:text>
            </native:column>

            <native:outlined-text-input
                label="Servidor"
                placeholder="https://homeside.cloud"
                keyboard="url"
                native:model.debounce.400ms="server"
                @change="onServerEdited"
                @submit="checkConnection"
            />

            <native:text class="-mt-3 text-xs text-theme-on-surface-variant">
                Por defecto homeside.cloud. Si tenés tu propia instancia, escribí su dirección.
            </native:text>

            @if ($status === 'checking')
                <native:row class="items-center gap-2">
                    <native:activity-indicator />
                    <native:text class="text-sm text-theme-on-surface-variant">Probando conexión…</native:text>
                </native:row>
            @endif

            @if ($status === 'ok')
                <native:column class="w-full gap-1 rounded-lg bg-theme-surface-variant p-4">
                    <native:text class="text-sm font-semibold text-theme-on-surface">
                        Conectado a {{ $serverName }}
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
                label="Probar conexión"
                class="w-full"
                :loading="$status === 'checking'"
                :disabled="$status === 'checking'"
                @press="checkConnection"
            />

            @if ($alreadyConfigured)
                <native:button
                    label="Continuar"
                    variant="secondary"
                    class="w-full"
                    @press="continueToApp"
                />
            @endif

            <native:text class="text-center text-xs text-theme-on-surface-variant">
                HomeSide es software libre: podés usar el servidor de la comunidad o el tuyo.
            </native:text>

        </native:column>
    </native:scroll-view>
</native:column>

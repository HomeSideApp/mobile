<native:column class="w-full h-full bg-theme-background">
    <native:scroll-view class="w-full flex-1">
        <native:column class="w-full gap-4 p-4">

            @if ($needsLogin)
                <native:column class="w-full items-center gap-3 rounded-lg bg-theme-surface-variant px-4 py-10">
                    <native:icon ios="lock.fill" android="lock" :size="28" class="text-theme-primary" />
                    <native:text class="text-base font-semibold text-theme-on-surface">Necesitás iniciar sesión</native:text>
                    <native:button label="Entrar" class="w-full" @press="goToLogin" />
                </native:column>
            @else

                <native:button label="Agregar ítem" icon="plus" class="w-full" @press="addItems" />

                @if ($loading)
                    <native:row class="w-full items-center justify-center gap-2 py-6">
                        <native:activity-indicator />
                        <native:text class="text-sm text-theme-on-surface-variant">Cargando lista…</native:text>
                    </native:row>
                @endif

                @if (! $loading && count($pending) > 0)
                    <native:text class="text-xs font-semibold uppercase text-theme-on-surface-variant">
                        Pendientes ({{ count($pending) }})
                    </native:text>

                    @foreach (array_chunk($pending, 3) as $row)
                        <native:row class="w-full gap-2">
                            @foreach ($row as $item)
                                <native:pressable
                                    :native:key="$item['id']"
                                    class="h-[104] flex-1 items-center justify-center rounded-lg p-2 bg-[{{ $item['color'] }}]"
                                    @press="toggle('{{ $item['id'] }}')"
                                    @longPress="openItem('{{ $item['id'] }}')"
                                >
                                    @if ($item['icon_url'] !== null)
                                        <native:image :src="$item['icon_url']" class="h-[26] w-[26]" fit="contain" />
                                    @else
                                        <native:text class="text-base font-bold" :color="$item['color_text']">
                                            {{ mb_strtoupper(mb_substr($item['name'], 0, 1)) }}
                                        </native:text>
                                    @endif

                                    <native:text class="mt-1 text-center text-[11px] font-medium" :color="$item['color_text']" :max-lines="2">
                                        {{ $item['name'] }}
                                    </native:text>

                                    @if ($item['quantity'] !== null)
                                        <native:text class="text-[10px]" :color="$item['color_text']">
                                            {{ rtrim(rtrim(number_format($item['quantity'], 2, ',', ''), '0'), ',') }}{{ $item['unit'] ? ' '.$item['unit'] : '' }}
                                        </native:text>
                                    @endif
                                </native:pressable>
                            @endforeach

                            @for ($filler = count($row); $filler < 3; $filler++)
                                <native:column class="flex-1" />
                            @endfor
                        </native:row>
                    @endforeach
                @endif

                @if (! $loading && count($pending) === 0)
                    <native:column class="w-full items-center gap-2 rounded-lg bg-theme-surface-variant px-4 py-8">
                        <native:icon ios="checkmark.circle.fill" android="check_circle" :size="28" class="text-theme-success" />
                        <native:text class="text-base font-semibold text-theme-on-surface">No queda nada pendiente</native:text>
                        <native:text class="text-center text-sm text-theme-on-surface-variant">
                            Agregá ítems con el botón de arriba.
                        </native:text>
                    </native:column>
                @endif

                @if (count($checked) > 0)
                    <native:text class="mt-2 text-xs font-semibold uppercase text-theme-on-surface-variant">
                        Comprados ({{ count($checked) }})
                    </native:text>

                    @foreach (array_chunk($checked, 3) as $row)
                        <native:row class="w-full gap-2">
                            @foreach ($row as $item)
                                <native:pressable
                                    :native:key="$item['id']"
                                    class="h-[104] flex-1 items-center justify-center rounded-lg p-2 bg-[{{ $item['color'] }}]"
                                    @press="toggle('{{ $item['id'] }}')"
                                    @longPress="openItem('{{ $item['id'] }}')"
                                >
                                    @if ($item['icon_url'] !== null)
                                        <native:image :src="$item['icon_url']" class="h-[26] w-[26]" fit="contain" />
                                    @endif
                                    <native:text class="mt-1 text-center text-[11px] font-medium line-through" :color="$item['color_text']" :max-lines="2">
                                        {{ $item['name'] }}
                                    </native:text>
                                </native:pressable>
                            @endforeach

                            @for ($filler = count($row); $filler < 3; $filler++)
                                <native:column class="flex-1" />
                            @endfor
                        </native:row>
                    @endforeach
                @endif

                @if ($confirmDelete)
                    <native:column class="mt-4 w-full gap-2 rounded-lg border border-theme-destructive p-4">
                        <native:text class="text-sm font-semibold text-theme-destructive">
                            ¿Borrar «{{ $name }}» y sus ítems?
                        </native:text>
                        <native:row class="w-full gap-2">
                            <native:button label="Cancelar" variant="secondary" class="flex-1" @press="cancelDelete" />
                            <native:button label="Borrar" variant="destructive" class="flex-1" @press="deleteList" />
                        </native:row>
                    </native:column>
                @else
                    <native:button label="Borrar lista" variant="ghost" class="mt-4 w-full" @press="requestDelete" />
                @endif

            @endif

            @if ($error)
                <native:column class="w-full rounded-lg bg-theme-destructive/10 p-4">
                    <native:text class="text-sm text-theme-destructive">{{ $error }}</native:text>
                </native:column>
            @endif

        </native:column>
    </native:scroll-view>

    <native:bottom-sheet :visible="$openItemId !== null" @dismiss="closeItem" detents="fit">
        <native:column class="w-full gap-4 p-5">
            <native:text class="text-lg font-bold text-theme-on-background">Editar ítem</native:text>

            <native:outlined-text-input
                label="Cantidad"
                keyboard="decimal"
                native:model.debounce.300ms="draftQuantity"
            />

            <native:outlined-text-input
                label="Unidad"
                placeholder="kg, un, l"
                native:model.debounce.300ms="draftUnit"
            />

            <native:outlined-text-input
                label="Nota"
                placeholder="Sin sal, marca blanca…"
                native:model.debounce.300ms="draftNotes"
            />

            <native:button label="Guardar" class="w-full" @press="saveItem" />
            <native:button label="Quitar de la lista" variant="destructive" class="w-full" @press="deleteItem" />
        </native:column>
    </native:bottom-sheet>
</native:column>

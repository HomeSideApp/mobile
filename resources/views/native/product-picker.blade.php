<native:column class="w-full h-full bg-theme-background">
    <native:scroll-view class="w-full flex-1">
        <native:column class="w-full gap-4 p-4">

            <native:outlined-text-input
                placeholder="Buscar producto"
                leading-icon="search"
                autofocus
                native:model.debounce.400ms="query"
                @change="onQuery"
            />

            @if (count($categories) > 0)
                <native:row class="w-full flex-wrap gap-2">
                    @foreach ($categories as $category)
                        <native:pressable
                            :native:key="$category['id']"
                            class="rounded-full px-3 py-1.5 bg-[{{ $category['color'] }}]"
                            @press="selectCategory('{{ $category['id'] }}')"
                        >
                            <native:text class="text-[11px] font-semibold" :color="$category['color_text']">
                                {{ $category['name'] }}
                            </native:text>
                        </native:pressable>
                    @endforeach
                </native:row>
            @endif

            @if ($loading)
                <native:row class="w-full items-center justify-center gap-2 py-4">
                    <native:activity-indicator />
                    <native:text class="text-sm text-theme-on-surface-variant">Buscando…</native:text>
                </native:row>
            @endif

            @if (count($results) > 0)
                <native:text class="text-xs font-semibold uppercase text-theme-on-surface-variant">
                    Resultados
                </native:text>

                @foreach (array_chunk($results, 2) as $row)
                    <native:row class="w-full gap-2">
                        @foreach ($row as $product)
                            <native:pressable
                                :native:key="$product['id']"
                                class="flex-1 items-center gap-2 rounded-lg p-3 bg-[{{ $product['color'] }}]"
                                @press="addProduct('{{ $product['id'] }}')"
                            >
                                <native:row class="w-full items-center gap-2">
                                    @if ($product['icon_url'] !== null)
                                        <native:image :src="$product['icon_url']" class="h-[24] w-[24]" fit="contain" />
                                    @endif
                                    <native:column class="flex-1 gap-0.5">
                                        <native:text class="text-[13px] font-semibold" :color="$product['color_text']" :max-lines="2">
                                            {{ $product['name'] }}
                                        </native:text>
                                        @if ($product['category_name'] !== null)
                                            <native:text class="text-[10px]" :color="$product['color_text']">
                                                {{ $product['category_name'] }}
                                            </native:text>
                                        @endif
                                    </native:column>
                                </native:row>
                            </native:pressable>
                        @endforeach

                        @for ($filler = count($row); $filler < 2; $filler++)
                            <native:column class="flex-1" />
                        @endfor
                    </native:row>
                @endforeach
            @endif

            @if (! $loading && count($results) === 0 && trim($query) !== '')
                <native:column class="w-full gap-3 rounded-lg bg-theme-surface-variant p-4">
                    <native:text class="text-sm text-theme-on-surface-variant">
                        No encontramos «{{ $query }}» en el catálogo.
                    </native:text>
                    <native:button
                        :label="'Crear «'.trim($query).'» y agregarlo'"
                        class="w-full"
                        @press="createAndAdd"
                    />
                </native:column>
            @endif

            @if (trim($query) === '' && count($recent) > 0)
                <native:text class="text-xs font-semibold uppercase text-theme-on-surface-variant">
                    Usados hace poco
                </native:text>

                @foreach (array_chunk($recent, 2) as $row)
                    <native:row class="w-full gap-2">
                        @foreach ($row as $product)
                            <native:pressable
                                :native:key="$product['id']"
                                class="flex-1 items-center gap-2 rounded-lg p-3 bg-[{{ $product['color'] }}]"
                                @press="addProduct('{{ $product['id'] }}')"
                            >
                                <native:row class="w-full items-center gap-2">
                                    @if ($product['icon_url'] !== null)
                                        <native:image :src="$product['icon_url']" class="h-[24] w-[24]" fit="contain" />
                                    @endif
                                    <native:column class="flex-1 gap-0.5">
                                        <native:text class="text-[13px] font-semibold" :color="$product['color_text']" :max-lines="2">
                                            {{ $product['name'] }}
                                        </native:text>
                                        @if ($product['category_name'] !== null)
                                            <native:text class="text-[10px]" :color="$product['color_text']">
                                                {{ $product['category_name'] }}
                                            </native:text>
                                        @endif
                                    </native:column>
                                </native:row>
                            </native:pressable>
                        @endforeach

                        @for ($filler = count($row); $filler < 2; $filler++)
                            <native:column class="flex-1" />
                        @endfor
                    </native:row>
                @endforeach
            @endif

            @if ($error)
                <native:column class="w-full rounded-lg bg-theme-destructive/10 p-4">
                    <native:text class="text-sm text-theme-destructive">{{ $error }}</native:text>
                </native:column>
            @endif

        </native:column>
    </native:scroll-view>
</native:column>

<native:column class="w-full h-full bg-theme-background">
    <native:scroll-view class="w-full flex-1">
        <native:column class="w-full gap-4 p-4">

            @if ($needsLogin)
                <native:column class="w-full items-center gap-3 rounded-lg bg-theme-surface-variant px-4 py-10">
                    <native:icon ios="lock.fill" android="lock" :size="28" class="text-theme-primary" />
                    <native:text class="text-base font-semibold text-theme-on-surface">{{ __('app.list_detail.needs_login') }}</native:text>
                    <native:button :label="__('app.list_detail.needs_login')" class="w-full" @press="goToLogin" />
                </native:column>
            @else

                {{-- ── Acciones de la lista ─────────────────────────── --}}
                <native:row class="w-full items-center gap-2">
                    <native:pressable class="rounded-lg bg-theme-surface px-3 py-2" @press="toggleListActions">
                        <native:row class="items-center gap-1">
                            <native:icon ios="ellipsis" android="more_horiz" :size="18" class="text-theme-on-surface" />
                            <native:text class="text-xs font-semibold text-theme-on-surface">{{ __('app.list_detail.actions') }}</native:text>
                        </native:row>
                    </native:pressable>
                    <native:spacer />
                    <native:text class="text-xs text-theme-on-surface-variant">
                        {{ __('app.list_detail.pending_count', ['count' => count($pending)]) }}
                    </native:text>
                </native:row>

                @if ($listActionsOpen)
                    <native:column class="w-full gap-2 rounded-lg bg-theme-surface p-4">
                        @if ($renaming)
                            <native:outlined-text-input
                                :label="__('app.list_detail.rename')"
                                :value="$renameDraft"
                                @change="renameDraft = $event"
                                @submit="rename"
                            />
                            <native:row class="w-full gap-2">
                                <native:button :label="__('app.common.cancel')" variant="secondary" class="flex-1" @press="cancelRenaming" />
                                <native:button :label="__('app.common.save')" class="flex-1" @press="rename" />
                            </native:row>
                        @else
                            <native:button :label="__('app.list_detail.rename')" variant="secondary" class="w-full" @press="startRenaming" />

                            @if ($confirmDelete)
                                <native:column class="w-full gap-2 rounded-lg border border-theme-destructive p-3">
                                    <native:text class="text-sm font-semibold text-theme-destructive">
                                        {{ __('app.list_detail.delete_confirm', ['name' => $name]) }}
                                    </native:text>
                                    <native:row class="w-full gap-2">
                                        <native:button :label="__('app.common.cancel')" variant="secondary" class="flex-1" @press="cancelDelete" />
                                        <native:button :label="__('app.list_detail.delete_list')" variant="destructive" class="flex-1" @press="deleteList" />
                                    </native:row>
                                </native:column>
                            @else
                                <native:button :label="__('app.list_detail.delete_list')" variant="ghost" class="w-full" @press="requestDelete" />
                            @endif
                        @endif
                    </native:column>
                @endif

                {{-- ── Buscador ─────────────────────────────────────── --}}
                <native:outlined-text-input
                    :placeholder="__('app.list_detail.search_placeholder')"
                    leading-icon="search"
                    native:model.debounce.400ms="search"
                    @change="onSearch"
                />

                @if ($searching)
                    <native:row class="w-full items-center gap-2 py-2">
                        <native:activity-indicator />
                        <native:text class="text-sm text-theme-on-surface-variant">{{ __('app.picker.searching') }}</native:text>
                    </native:row>
                @endif

                @if (count($results) > 0)
                    @foreach (array_chunk($results, 2) as $row)
                        <native:row class="w-full gap-2">
                            @foreach ($row as $product)
                                <native:pressable
                                    :native:key="'res-'.$product['id']"
                                    class="flex-1 items-center gap-2 rounded-lg p-3 bg-[{{ $product['color'] }}]"
                                    @press="addProduct({{ json_encode($product['id']) }})"
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
                                                <native:text class="text-[10px]" :color="$product['color_text']">{{ $product['category_name'] }}</native:text>
                                            @endif
                                        </native:column>
                                    </native:row>
                                </native:pressable>
                            @endforeach

                            @for ($filler = count($row); $filler < 2; $filler++)
                                <native:column class="flex-1"></native:column>
                            @endfor
                        </native:row>
                    @endforeach
                @endif

                @if (! $searching && $searchDone && count($results) === 0 && trim($search) !== '')
                    <native:column class="w-full gap-2 rounded-lg bg-theme-surface-variant p-4">
                        <native:text class="text-sm text-theme-on-surface-variant">
                            {{ __('app.picker.empty', ['query' => trim($search)]) }}
                        </native:text>
                        <native:button
                            :label="__('app.picker.create_and_add', ['query' => trim($search)])"
                            class="w-full"
                            @press="createAndAdd"
                        />
                    </native:column>
                @endif

                {{-- ── Pendientes ───────────────────────────────────── --}}
                @if ($loading)
                    <native:row class="w-full items-center justify-center gap-2 py-6">
                        <native:activity-indicator />
                        <native:text class="text-sm text-theme-on-surface-variant">{{ __('app.list_detail.loading') }}</native:text>
                    </native:row>
                @endif

                @if (! $loading && count($pending) > 0)
                    <native:row class="w-full items-center gap-2">
                        <native:text class="text-xs font-semibold uppercase text-theme-on-surface-variant">
                            {{ __('app.list_detail.pending', ['count' => count($pending)]) }}
                        </native:text>
                        <native:spacer />
                        <native:text class="text-[10px] text-theme-on-surface-variant">
                            {{ __('app.list_detail.pending_hint') }}
                        </native:text>
                    </native:row>

                    @foreach (array_chunk($pending, 3) as $row)
                        <native:row class="w-full gap-2">
                            @foreach ($row as $item)
                                <native:pressable
                                    :native:key="$item['id']"
                                    class="h-[104] flex-1 items-center justify-center rounded-lg p-2 bg-[{{ $item['color'] }}]"
                                    @press="toggle({{ json_encode($item['id']) }})"
                                    @longPress="openItem({{ json_encode($item['id']) }})"
                                >
                                    @if ($item['image_url'] !== null)
                                        <native:image :src="$item['image_url']" :width="34" :height="34" class="rounded-md" fit="cover" />
                                    @elseif ($item['icon_url'] !== null)
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
                                <native:column class="flex-1"></native:column>
                            @endfor
                        </native:row>
                    @endforeach
                @endif

                @if (! $loading && count($pending) === 0 && trim($search) === '')
                    <native:column class="w-full items-center gap-2 rounded-lg bg-theme-surface-variant px-4 py-8">
                        <native:icon ios="checkmark.circle.fill" android="check_circle" :size="28" class="text-theme-success" />
                        <native:text class="text-base font-semibold text-theme-on-surface">{{ __('app.list_detail.nothing_pending') }}</native:text>
                        <native:text class="text-center text-sm text-theme-on-surface-variant">
                            {{ __('app.list_detail.nothing_pending_hint') }}
                        </native:text>
                    </native:column>
                @endif

                {{-- ── Recientes ────────────────────────────────────── --}}
                @if (count($recent) > 0 && trim($search) === '')
                    <native:text class="mt-2 text-xs font-semibold uppercase text-theme-on-surface-variant">
                        {{ __('app.picker.recent') }}
                    </native:text>

                    @foreach (array_chunk($recent, 3) as $row)
                        <native:row class="w-full gap-2">
                            @foreach ($row as $product)
                                <native:pressable
                                    :native:key="'recent-'.$product['id']"
                                    class="h-[92] flex-1 items-center justify-center rounded-lg p-2 bg-[{{ $product['color'] }}]"
                                    @press="addProduct({{ json_encode($product['id']) }})"
                                >
                                    @if ($product['icon_url'] !== null)
                                        <native:image :src="$product['icon_url']" class="h-[24] w-[24]" fit="contain" />
                                    @endif
                                    <native:text class="mt-1 text-center text-[11px] font-medium" :color="$product['color_text']" :max-lines="2">
                                        {{ $product['name'] }}
                                    </native:text>
                                </native:pressable>
                            @endforeach

                            @for ($filler = count($row); $filler < 3; $filler++)
                                <native:column class="flex-1"></native:column>
                            @endfor
                        </native:row>
                    @endforeach
                @endif

                {{-- ── Categorías ───────────────────────────────────── --}}
                @if (count($categories) > 0 && trim($search) === '')
                    <native:text class="mt-2 text-xs font-semibold uppercase text-theme-on-surface-variant">
                        {{ __('app.list_detail.categories') }}
                    </native:text>

                    @foreach ($categories as $category)
                        {{-- `overflow-hidden` no existe en EDGE: en vez de recortar,
                             cada hijo lleva el radio que le toca. --}}
                        <native:column :native:key="'cat-'.$category['id']" class="w-full rounded-xl border border-theme-outline">
                            <native:pressable
                                class="w-full px-4 py-3 bg-[{{ $category['color'] }}] {{ $category['open'] ? 'rounded-t-xl' : 'rounded-xl' }}"
                                @press="toggleCategory({{ json_encode($category['id']) }})"
                            >
                                <native:row class="w-full items-center gap-2">
                                    <native:text class="flex-1 text-sm font-semibold" :color="\App\Support\CardColor::foreground($category['color'])">
                                        {{ $category['name'] }}
                                    </native:text>
                                    <native:icon
                                        :ios="$category['open'] ? 'chevron.up' : 'chevron.down'"
                                        :android="$category['open'] ? 'expand_less' : 'expand_more'"
                                        :size="16"
                                        :color="\App\Support\CardColor::foreground($category['color'])"
                                    />
                                </native:row>
                            </native:pressable>

                            @if ($category['open'])
                                @if (count($category['products']) === 0)
                                    <native:row class="w-full items-center justify-center gap-2 p-4">
                                        <native:activity-indicator />
                                        <native:text class="text-xs text-theme-on-surface-variant">{{ __('app.common.loading') }}</native:text>
                                    </native:row>
                                @else
                                    <native:column class="w-full gap-2 rounded-b-xl p-2 bg-[{{ $category['tint'] }}]">
                                        @foreach (array_chunk($category['products'], 3) as $row)
                                            <native:row class="w-full gap-2">
                                                @foreach ($row as $product)
                                                    <native:pressable
                                                        :native:key="'catprod-'.$product['id']"
                                                        class="h-[92] flex-1 items-center justify-center rounded-lg p-2 bg-[{{ $product['color'] }}]"
                                                        @press="addProduct({{ json_encode($product['id']) }})"
                                                    >
                                                        @if ($product['icon_url'] !== null)
                                                            <native:image :src="$product['icon_url']" class="h-[24] w-[24]" fit="contain" />
                                                        @endif
                                                        <native:text class="mt-1 text-center text-[11px] font-medium" :color="$product['color_text']" :max-lines="2">
                                                            {{ $product['name'] }}
                                                        </native:text>
                                                    </native:pressable>
                                                @endforeach

                                                @for ($filler = count($row); $filler < 3; $filler++)
                                                    <native:column class="flex-1"></native:column>
                                                @endfor
                                            </native:row>
                                        @endforeach
                                    </native:column>
                                @endif
                            @endif
                        </native:column>
                    @endforeach
                @endif

            @endif

            @if ($error)
                <native:column class="w-full rounded-lg bg-theme-destructive/10 p-4">
                    <native:text class="text-sm text-theme-destructive">{{ $error }}</native:text>
                </native:column>
            @endif

        </native:column>
    </native:scroll-view>

    {{-- ── Hoja de detalle del ítem ──────────────────────────────────── --}}
    <native:bottom-sheet :visible="$openItemId !== null" @dismiss="closeItem" detents="large">
        {{-- Todo el cuerpo scrollea: con el teclado abierto, los campos de abajo
             tienen que poder alcanzarse. El selector de íconos reemplaza al
             formulario en vez de sumarse, para no hacer una hoja interminable. --}}
        <native:scroll-view class="w-full flex-1">
            <native:column class="w-full gap-4 p-5 pb-10">

                <native:row class="w-full items-center gap-2">
                    <native:text class="flex-1 text-lg font-bold text-theme-on-background">
                        {{ $iconPickerOpen ? __('app.list_detail.change_icon') : __('app.list_detail.edit_item') }}
                    </native:text>

                    @if ($iconPickerOpen)
                        <native:pressable class="rounded-lg bg-theme-surface px-3 py-2" @press="toggleIconPicker">
                            <native:text class="text-xs font-semibold text-theme-primary">{{ __('app.common.back') }}</native:text>
                        </native:pressable>
                    @endif
                </native:row>

                @if ($iconPickerOpen)

                    <native:outlined-text-input
                        :placeholder="__('app.list_detail.icon_search')"
                        leading-icon="search"
                        native:model.debounce.300ms="iconSearch"
                    />

                    <native:row class="w-full flex-wrap gap-2">
                        @foreach ($this->filteredIcons() as $icon)
                            <native:pressable
                                :native:key="'icon-'.$icon['name']"
                                class="size-[44] items-center justify-center rounded-lg border"
                                :class="$draftIcon === $icon['name'] ? 'border-theme-primary bg-theme-surface-variant' : 'border-theme-outline bg-theme-surface'"
                                @press="selectIcon({{ json_encode($icon['name']) }})"
                            >
                                <native:image :src="$icon['url']" class="h-[26] w-[26]" fit="contain" />
                            </native:pressable>
                        @endforeach
                    </native:row>

                @else

                    {{-- Foto: preview de lo guardado o de lo recién elegido --}}
                    <native:column class="w-full gap-2">
                        @if ($draftImageUrl !== null)
                            <native:stack class="w-full h-[140]">
                                <native:image :src="$draftImageUrl" class="h-[140] w-full rounded-lg" fit="cover" />

                                @if ($uploadState === 'uploading')
                                    <native:progress-bar
                                        indeterminate
                                        class="absolute bottom-0 left-0 right-0 h-[4]"
                                    />
                                @endif
                            </native:stack>
                        @else
                            <native:column class="h-[100] w-full items-center justify-center rounded-lg border border-theme-outline bg-theme-surface-variant">
                                <native:text class="text-xs text-theme-on-surface-variant">{{ __('app.list_detail.photo_hint') }}</native:text>
                            </native:column>
                        @endif

                        @if ($uploadState === 'uploading')
                            <native:text class="text-xs text-theme-on-surface-variant">{{ __('app.list_detail.uploading') }}</native:text>
                        @endif

                        <native:row class="w-full gap-2">
                            <native:button
                                :label="$draftImageUrl !== null ? __('app.list_detail.change_photo') : __('app.list_detail.upload_photo')"
                                variant="secondary"
                                class="flex-1"
                                :disabled="$uploadState === 'uploading'"
                                @press="pickPhoto"
                            />
                            <native:button
                                :label="__('app.list_detail.change_icon')"
                                variant="secondary"
                                class="flex-1"
                                @press="toggleIconPicker"
                            />
                        </native:row>

                        @if ($itemNotice)
                            <native:text class="text-xs text-theme-success">{{ $itemNotice }}</native:text>
                        @endif
                    </native:column>

                    <native:outlined-text-input
                        :label="__('app.list_detail.quantity')"
                        keyboard="decimal"
                        native:model.debounce.300ms="draftQuantity"
                    />

                    <native:outlined-text-input
                        :label="__('app.list_detail.unit')"
                        :placeholder="__('app.list_detail.unit_placeholder')"
                        native:model.debounce.300ms="draftUnit"
                    />

                    <native:outlined-text-input
                        :label="__('app.list_detail.note')"
                        :placeholder="__('app.list_detail.note_placeholder')"
                        native:model.debounce.300ms="draftNotes"
                    />

                    @if (count($categoryOptions) > 0)
                        <native:select
                            :label="__('app.list_detail.category')"
                            :value="$draftCategoryName"
                            :options="$categoryOptions"
                            @change="setDraftCategory"
                        />
                    @endif

                    @if (count($storeOptions) > 0)
                        <native:select
                            :label="__('app.list_detail.store')"
                            :value="$draftStoreName"
                            :options="$storeOptions"
                            @change="setDraftStore"
                        />
                    @endif

                    @if ($itemError)
                        <native:column class="w-full rounded-lg bg-theme-destructive/10 p-3">
                            <native:text class="text-sm text-theme-destructive">{{ $itemError }}</native:text>
                        </native:column>
                    @endif

                    <native:button :label="__('app.list_detail.save')" class="w-full" @press="saveItem" />

                    {{-- Acción destructiva visible, no escondida al final de la hoja. --}}
                    <native:button :label="__('app.list_detail.remove')" variant="destructive" class="w-full" @press="deleteItem" />

                @endif

            </native:column>
        </native:scroll-view>
    </native:bottom-sheet>
</native:column>

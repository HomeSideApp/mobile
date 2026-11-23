<native:column class="w-full h-full bg-theme-background">
    <native:scroll-view class="w-full flex-1">
        <native:column class="w-full gap-4 p-4">

            <native:row class="w-full gap-2">
                <native:pressable
                    class="flex-1 items-center rounded-lg px-3 py-2 {{ $mode === 'page' ? 'bg-theme-primary' : 'bg-theme-surface' }}"
                    @press="usePage"
                >
                    <native:text class="text-xs font-semibold {{ $mode === 'page' ? 'text-theme-on-primary' : 'text-theme-on-surface' }}">
                        {{ __('app.recipes.import_page') }}
                    </native:text>
                </native:pressable>
                <native:pressable
                    class="flex-1 items-center rounded-lg px-3 py-2 {{ $mode === 'cooklang' ? 'bg-theme-primary' : 'bg-theme-surface' }}"
                    @press="useCooklang"
                >
                    <native:text class="text-xs font-semibold {{ $mode === 'cooklang' ? 'text-theme-on-primary' : 'text-theme-on-surface' }}">
                        {{ __('app.recipes.import_cooklang') }}
                    </native:text>
                </native:pressable>
            </native:row>

            @if ($mode === 'page')
                <native:outlined-text-input
                    :label="__('app.recipes.import_url')"
                    :placeholder="__('app.recipes.import_url_placeholder')"
                    keyboard="url"
                    native:model.debounce.300ms="value"
                />
                <native:text class="text-xs text-theme-on-surface-variant">{{ __('app.recipes.import_page_hint') }}</native:text>
            @else
                <native:outlined-text-input
                    :label="__('app.recipes.import_cooklang')"
                    :placeholder="__('app.recipes.import_cooklang_placeholder')"
                    native:model.debounce.300ms="value"
                />
            @endif

            <native:button
                :label="$working ? __('app.recipes.importing') : __('app.recipes.import_action')"
                class="w-full"
                :disabled="$working"
                @press="run"
            />

            @if ($notice)
                <native:text class="text-xs text-theme-success">{{ $notice }}</native:text>
            @endif

            @if ($error)
                <native:column class="w-full rounded-lg bg-theme-destructive/10 p-4">
                    <native:text class="text-sm text-theme-destructive">{{ $error }}</native:text>
                </native:column>
            @endif

        </native:column>
    </native:scroll-view>
</native:column>

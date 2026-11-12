<native:column class="w-full h-full bg-theme-background">
    <native:scroll-view class="w-full flex-1">
        <native:column class="w-full gap-4 p-5">

            <native:column class="w-full items-center gap-2 rounded-lg bg-theme-surface-variant px-4 py-10">
                <native:icon ios="book.fill" android="menu_book" :size="30" class="text-theme-primary" />
                <native:text class="text-base font-semibold text-theme-on-surface">
                    {{ __('app.recipes.title') }}
                </native:text>
                <native:text class="text-center text-sm text-theme-on-surface-variant">
                    {{ __('app.recipes.subtitle') }}
                </native:text>
            </native:column>

        </native:column>
    </native:scroll-view>
</native:column>

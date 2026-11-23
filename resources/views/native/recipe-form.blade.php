<native:column class="w-full h-full bg-theme-background">
    <native:scroll-view class="w-full flex-1">
        <native:column class="w-full gap-4 p-4">

            <native:outlined-text-input
                :label="__('app.recipes.name')"
                :placeholder="__('app.recipes.name_placeholder')"
                native:model.debounce.300ms="name"
            />

            <native:outlined-text-input
                :label="__('app.recipes.description')"
                native:model.debounce.300ms="description"
            />

            <native:row class="w-full gap-2">
                <native:column class="flex-1">
                    <native:outlined-text-input
                        :label="__('app.recipes.servings')"
                        keyboard="number"
                        native:model.debounce.300ms="servings"
                    />
                </native:column>
                <native:column class="flex-1">
                    <native:outlined-text-input
                        :label="__('app.recipes.prep_minutes')"
                        keyboard="number"
                        native:model.debounce.300ms="prepMinutes"
                    />
                </native:column>
                <native:column class="flex-1">
                    <native:outlined-text-input
                        :label="__('app.recipes.cook_minutes')"
                        keyboard="number"
                        native:model.debounce.300ms="cookMinutes"
                    />
                </native:column>
            </native:row>

            <native:select
                :label="__('app.recipes.difficulty_label')"
                :value="$difficulty"
                :options="['easy', 'medium', 'hard', 'expert']"
                @change="difficulty = $event"
            />

            {{-- ── Generar con IA ────────────────────────────────────── --}}
            <native:column class="w-full gap-2 rounded-lg bg-theme-surface p-3">
                <native:text class="text-xs font-semibold uppercase text-theme-on-surface-variant">
                    {{ __('app.recipes.generate') }}
                </native:text>
                <native:outlined-text-input
                    :placeholder="__('app.recipes.prompt_placeholder')"
                    native:model.debounce.300ms="prompt"
                />
                <native:button
                    :label="$generating ? __('app.recipes.generating') : __('app.recipes.generate_action')"
                    variant="secondary"
                    class="w-full"
                    :disabled="$generating"
                    @press="generate"
                />
                <native:text class="text-xs text-theme-on-surface-variant">{{ __('app.recipes.ai_hint') }}</native:text>
            </native:column>

            {{-- ── Ingredientes ──────────────────────────────────────── --}}
            <native:row class="w-full items-center gap-2">
                <native:text class="flex-1 text-xs font-semibold uppercase text-theme-on-surface-variant">
                    {{ __('app.recipes.ingredients') }}
                </native:text>
                <native:pressable class="rounded-lg bg-theme-surface px-3 py-1" @press="addIngredient">
                    <native:text class="text-xs font-semibold text-theme-primary">{{ __('app.recipes.add') }}</native:text>
                </native:pressable>
            </native:row>

            @foreach ($ingredients as $index => $ingredient)
                <native:row class="w-full items-center gap-2">
                    <native:column class="flex-1">
                        <native:outlined-text-input
                            :placeholder="__('app.recipes.ingredient_name')"
                            native:model.debounce.300ms="ingredients.{{ $index }}.name"
                        />
                    </native:column>
                    <native:column class="w-[70]">
                        <native:outlined-text-input
                            :placeholder="__('app.recipes.quantity')"
                            keyboard="number"
                            native:model.debounce.300ms="ingredients.{{ $index }}.quantity"
                        />
                    </native:column>
                    <native:column class="w-[70]">
                        <native:outlined-text-input
                            :placeholder="__('app.recipes.unit')"
                            native:model.debounce.300ms="ingredients.{{ $index }}.unit"
                        />
                    </native:column>
                    <native:pressable class="rounded-lg bg-theme-surface-variant px-2 py-2" @press="removeIngredient({{ $index }})">
                        <native:icon ios="minus.circle" android="remove_circle" :size="18" class="text-theme-destructive" />
                    </native:pressable>
                </native:row>
            @endforeach

            {{-- ── Pasos ─────────────────────────────────────────────── --}}
            <native:row class="w-full items-center gap-2">
                <native:text class="flex-1 text-xs font-semibold uppercase text-theme-on-surface-variant">
                    {{ __('app.recipes.steps') }}
                </native:text>
                <native:pressable class="rounded-lg bg-theme-surface px-3 py-1" @press="addStep">
                    <native:text class="text-xs font-semibold text-theme-primary">{{ __('app.recipes.add') }}</native:text>
                </native:pressable>
            </native:row>

            @foreach ($steps as $index => $step)
                <native:row class="w-full items-start gap-2">
                    <native:column class="h-[24] w-[24] items-center justify-center rounded-full bg-theme-primary">
                        <native:text class="text-xs font-bold text-theme-on-primary">{{ $index + 1 }}</native:text>
                    </native:column>
                    <native:column class="flex-1">
                        <native:outlined-text-input
                            :placeholder="__('app.recipes.step_placeholder')"
                            native:model.debounce.300ms="steps.{{ $index }}.description"
                        />
                    </native:column>
                    <native:pressable class="rounded-lg bg-theme-surface-variant px-2 py-2" @press="removeStep({{ $index }})">
                        <native:icon ios="minus.circle" android="remove_circle" :size="18" class="text-theme-destructive" />
                    </native:pressable>
                </native:row>
            @endforeach

            {{-- ── Foto de portada ───────────────────────────────────── --}}
            <native:column class="w-full gap-2 rounded-lg bg-theme-surface p-3">
                <native:text class="text-xs font-semibold uppercase text-theme-on-surface-variant">
                    {{ __('app.recipes.cover') }}
                </native:text>
                @if ($imageUrl !== null)
                    <native:image :src="$imageUrl" class="w-full" :height="160" fit="cover" />
                @endif
                <native:button
                    :label="$uploading ? __('app.recipes.uploading') : __('app.recipes.pick_photo')"
                    variant="secondary"
                    class="w-full"
                    :disabled="$uploading"
                    @press="pickCover"
                />
                <native:text class="text-xs text-theme-on-surface-variant">{{ __('app.recipes.photo_hint') }}</native:text>
            </native:column>

            <native:button
                :label="$saving ? __('app.common.saving') : __('app.common.save')"
                class="w-full"
                :disabled="$saving"
                @press="save"
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

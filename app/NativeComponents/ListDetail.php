<?php

namespace App\NativeComponents;

use App\Exceptions\HomesideException;
use App\Services\Homeside\AuthService;
use App\Services\Homeside\CatalogService;
use App\Services\Homeside\ListsService;
use App\Services\Homeside\Session;
use App\Services\Media\GalleryPicker;
use App\Services\Media\ItemImageCache;
use App\Services\Settings\LocaleService;
use App\Support\CardColor;
use App\Support\ProductIcon;
use Illuminate\View\View;
use Native\Mobile\Attributes\On;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Events\Gallery\MediaSelected;

/**
 * Detalle de una lista, con el mismo repertorio que la web:
 *
 *   buscador → cards rojas de pendientes → recientes → acordeón por categoría
 *
 * y una hoja de detalle por ítem con foto, cantidad, unidad, nota, local,
 * categoría e ícono.
 *
 * Los datos llegan con las llamadas que ya existen (lista, categorías,
 * recientes, locales) y los productos de cada categoría se piden al abrir el
 * acordeón, para no traer el catálogo entero en el primer render.
 */
final class ListDetail extends NativeComponent
{
    public ?string $listId = null;

    public ?string $householdId = null;

    public string $name = '';

    /** @var array<int, array<string, mixed>> */
    public array $pending = [];

    /** @var array<int, array<string, mixed>> */
    public array $categories = [];

    /** @var array<int, array<string, mixed>> */
    public array $recent = [];

    /** @var array<int, array<string, mixed>> */
    public array $stores = [];

    public bool $loading = true;

    public bool $needsLogin = false;

    public ?string $error = null;

    public bool $busy = false;

    // ── Buscador ─────────────────────────────────────────────────────────
    public string $search = '';

    /** @var array<int, array<string, mixed>> */
    public array $results = [];

    public bool $searching = false;

    public bool $searchDone = false;

    // ── Acciones de la lista ─────────────────────────────────────────────
    public bool $listActionsOpen = false;

    public string $renameDraft = '';

    public bool $renaming = false;

    public bool $confirmDelete = false;

    // ── Hoja de detalle ──────────────────────────────────────────────────
    public ?string $openItemId = null;

    public string $draftQuantity = '';

    public string $draftUnit = '';

    public string $draftNotes = '';

    public string $draftStoreId = '';

    public string $draftCategoryId = '';

    public string $draftIcon = '';

    public string $iconSearch = '';

    public bool $iconPickerOpen = false;

    /** @var list<string> Nombres para el select de categoría. */
    public array $categoryOptions = [];

    public string $draftCategoryName = '';

    /** @var list<string> Nombres para el select de local. */
    public array $storeOptions = [];

    public string $draftStoreName = '';

    /** @var array<int, array{name: string, url: string}> */
    public array $icons = [];

    public ?string $itemError = null;

    public ?string $itemNotice = null;

    public function mount(): void
    {
        (new LocaleService)->apply();

        $this->listId = $this->param('list');
        $this->householdId = $this->param('household');

        $this->load();
    }

    public function onResume(): void
    {
        $this->load();
    }

    public function navTitle(): string
    {
        return $this->name !== '' ? $this->name : __('app.list_detail.title');
    }

    // ── Carga ────────────────────────────────────────────────────────────

    public function load(): void
    {
        if ($this->listId === null || $this->listId === '') {
            $this->loading = false;
            $this->error = __('app.list_detail.not_found');

            return;
        }

        $this->loading = true;
        $this->error = null;

        $lists = new ListsService;
        $catalog = new CatalogService;
        $images = new ItemImageCache;

        try {
            $list = $lists->show($this->listId, $this->householdId);
            $categories = $catalog->categories();
            $recent = $catalog->recent(24, $this->householdId);
        } catch (HomesideException $exception) {
            $this->needsLogin = $exception->requiresLogin();
            $this->error = $exception->getMessage();
            $this->loading = false;

            return;
        }

        $this->name = is_string($list['name'] ?? null) ? $list['name'] : __('app.list_detail.title');

        $items = is_array($list['items'] ?? null) ? $list['items'] : [];

        $this->pending = array_values(array_filter(
            array_map(fn (array $item): array => $this->normalizeItem($item, $images), $items),
            fn (array $item): bool => ! $item['checked'],
        ));

        $this->categories = array_map(fn (array $category): array => [
            'id' => (string) ($category['id'] ?? ''),
            'name' => (string) ($category['name'] ?? ''),
            'color' => CardColor::normalize(is_string($category['color'] ?? null) ? $category['color'] : null),
            'tint' => CardColor::tint(is_string($category['color'] ?? null) ? $category['color'] : null),
            'open' => false,
            'loaded' => false,
            'products' => [],
        ], $categories);

        $this->recent = array_map(fn (array $product): array => $this->normalizeProduct($product), $recent);

        $this->loadStores();

        $this->loading = false;
    }

    private function loadStores(): void
    {
        // El endpoint de locales valida pertenencia a un hogar y devuelve el
        // catálogo global: sin hogar activo no hay locales que ofrecer.
        $householdId = $this->householdId;

        if ($householdId === null) {
            try {
                $me = (new AuthService)->me();
                $householdId = is_string($me['active_household_id'] ?? null) ? $me['active_household_id'] : null;
            } catch (HomesideException) {
                $householdId = null;
            }
        }

        $this->stores = [];

        if ($householdId === null) {
            return;
        }

        try {
            $this->stores = array_map(fn (array $store): array => [
                'id' => (string) ($store['id'] ?? ''),
                'name' => (string) ($store['name'] ?? ''),
            ], (new ListsService)->stores($householdId));
        } catch (HomesideException) {
            // Sin locales el detalle sigue funcionando.
            $this->stores = [];
        }
    }

    // ── Buscador ─────────────────────────────────────────────────────────

    public function updatedSearch(?string $value = null): void
    {
        $this->onSearch($value);
    }

    public function onSearch(?string $value = null): void
    {
        if (is_string($value)) {
            $this->search = $value;
        }

        $term = trim($this->search);
        $this->results = [];
        $this->searchDone = false;

        if ($term === '') {
            $this->searching = false;

            return;
        }

        $this->searching = true;

        try {
            $products = (new CatalogService)->search($term);
        } catch (HomesideException $exception) {
            $this->error = $exception->getMessage();
            $this->searching = false;

            return;
        }

        $this->results = array_map(fn (array $product): array => $this->normalizeProduct($product), $products);
        $this->searching = false;
        $this->searchDone = true;
    }

    public function clearSearch(): void
    {
        $this->search = '';
        $this->results = [];
        $this->searching = false;
        $this->searchDone = false;
    }

    public function addProduct(string $productId): void
    {
        if ($this->listId === null || $this->busy) {
            return;
        }

        $this->busy = true;

        try {
            (new ListsService)->addItem($this->listId, ['product_id' => $productId, 'quantity' => 1], $this->householdId);
        } catch (HomesideException $exception) {
            $this->error = $exception->getMessage();
            $this->busy = false;

            return;
        }

        $this->busy = false;
        $this->clearSearch();
        $this->load();
    }

    public function createAndAdd(): void
    {
        if ($this->listId === null || trim($this->search) === '' || $this->busy) {
            return;
        }

        $this->busy = true;

        try {
            (new ListsService)->quickCreateItem(
                $this->listId,
                ['name' => trim($this->search)],
                ['quantity' => 1],
                $this->householdId,
            );
        } catch (HomesideException $exception) {
            $this->error = $exception->getMessage();
            $this->busy = false;

            return;
        }

        $this->busy = false;
        $this->clearSearch();
        $this->load();
    }

    // ── Pendientes ───────────────────────────────────────────────────────

    public function toggle(string $itemId): void
    {
        if ($this->listId === null || $this->busy) {
            return;
        }

        $item = collect($this->pending)->firstWhere('id', $itemId);

        if ($item === null) {
            return;
        }

        $this->busy = true;

        try {
            (new ListsService)->updateItem($this->listId, $itemId, ['is_checked' => ! $item['checked']], $this->householdId);
        } catch (HomesideException $exception) {
            $this->error = $exception->getMessage();
            $this->busy = false;

            return;
        }

        $this->busy = false;
        $this->load();
    }

    // ── Acordeón de categorías ───────────────────────────────────────────

    public function toggleCategory(string $categoryId): void
    {
        foreach ($this->categories as $index => $category) {
            if ($category['id'] !== $categoryId) {
                continue;
            }

            $open = ! $category['open'];
            $this->categories[$index]['open'] = $open;

            if ($open && ! $category['loaded']) {
                $this->loadCategoryProducts($categoryId);
            }

            return;
        }
    }

    private function loadCategoryProducts(string $categoryId): void
    {
        try {
            $products = (new CatalogService)->search('', $categoryId, 60);
        } catch (HomesideException $exception) {
            $this->error = $exception->getMessage();

            return;
        }

        foreach ($this->categories as $index => $category) {
            if ($category['id'] !== $categoryId) {
                continue;
            }

            $this->categories[$index]['products'] = array_map(
                fn (array $product): array => $this->normalizeProduct($product),
                $products,
            );
            $this->categories[$index]['loaded'] = true;

            return;
        }
    }

    // ── Hoja de detalle ──────────────────────────────────────────────────

    public function openItem(string $itemId): void
    {
        $item = collect($this->pending)->firstWhere('id', $itemId);

        if ($item === null) {
            return;
        }

        $this->openItemId = $itemId;
        $this->itemError = null;
        $this->itemNotice = null;
        $this->iconPickerOpen = false;
        $this->iconSearch = '';

        $this->draftQuantity = $item['quantity'] !== null ? (string) $item['quantity'] : '';
        $this->draftUnit = (string) ($item['unit'] ?? '');
        $this->draftNotes = (string) ($item['notes'] ?? '');
        $this->draftStoreId = (string) ($item['store_id'] ?? '');
        $this->draftCategoryId = (string) ($item['category_id'] ?? '');
        $this->draftIcon = (string) ($item['icon'] ?? '');
        $this->refreshDraftOptions();
    }

    /**
     * Los selects nativos sólo aceptan strings, así que las opciones son los
     * nombres y acá se vuelven a su id.
     */
    private function refreshDraftOptions(): void
    {
        $this->categoryOptions = array_values(array_map(
            static fn (array $category): string => (string) $category['name'],
            $this->categories,
        ));

        $current = collect($this->categories)->firstWhere('id', $this->draftCategoryId);
        $this->draftCategoryName = is_array($current) ? (string) $current['name'] : '';

        $this->storeOptions = array_values(array_map(
            static fn (array $store): string => (string) $store['name'],
            $this->stores,
        ));

        $store = collect($this->stores)->firstWhere('id', $this->draftStoreId);
        $this->draftStoreName = is_array($store) ? (string) $store['name'] : '';
    }

    public function setDraftCategory(?string $name = null): void
    {
        $name = $name ?? $this->draftCategoryName;

        foreach ($this->categories as $category) {
            if ($category['name'] === $name) {
                $this->draftCategoryId = (string) $category['id'];
                $this->draftCategoryName = (string) $category['name'];

                return;
            }
        }

        $this->draftCategoryId = '';
        $this->draftCategoryName = '';
    }

    public function setDraftStore(?string $name = null): void
    {
        $name = $name ?? $this->draftStoreName;

        if ($name === '' || $name === __('app.list_detail.no_store')) {
            $this->draftStoreId = '';
            $this->draftStoreName = '';

            return;
        }

        foreach ($this->stores as $store) {
            if ($store['name'] === $name) {
                $this->draftStoreId = (string) $store['id'];
                $this->draftStoreName = (string) $store['name'];

                return;
            }
        }
    }

    public function closeItem(): void
    {
        $this->openItemId = null;
        $this->iconPickerOpen = false;
        $this->itemError = null;
        $this->itemNotice = null;
    }

    public function saveItem(): void
    {
        if ($this->listId === null || $this->openItemId === null) {
            return;
        }

        $payload = [
            'unit' => trim($this->draftUnit) !== '' ? trim($this->draftUnit) : null,
            'notes' => trim($this->draftNotes) !== '' ? trim($this->draftNotes) : null,
            'store_id' => $this->draftStoreId !== '' ? $this->draftStoreId : null,
            'category_id' => $this->draftCategoryId !== '' ? $this->draftCategoryId : null,
            'icon' => $this->draftIcon !== '' ? $this->draftIcon : null,
        ];

        if (is_numeric($this->draftQuantity) && (float) $this->draftQuantity > 0) {
            $payload['quantity'] = (float) $this->draftQuantity;
        }

        try {
            (new ListsService)->updateItem($this->listId, $this->openItemId, $payload, $this->householdId);
        } catch (HomesideException $exception) {
            $this->itemError = $exception->getMessage();

            return;
        }

        $this->closeItem();
        $this->load();
    }

    public function deleteItem(): void
    {
        if ($this->listId === null || $this->openItemId === null) {
            return;
        }

        try {
            (new ListsService)->deleteItem($this->listId, $this->openItemId, $this->householdId);
        } catch (HomesideException $exception) {
            $this->itemError = $exception->getMessage();

            return;
        }

        (new ItemImageCache)->forget($this->listId, $this->openItemId);

        $this->closeItem();
        $this->load();
    }

    // ── Íconos ───────────────────────────────────────────────────────────

    public function toggleIconPicker(): void
    {
        $this->iconPickerOpen = ! $this->iconPickerOpen;

        if ($this->iconPickerOpen && $this->icons === []) {
            $this->loadIcons();
        }
    }

    private function loadIcons(): void
    {
        try {
            $payload = (new Session)->client()->get('/icons');
        } catch (HomesideException $exception) {
            $this->itemError = $exception->getMessage();

            return;
        }

        $icons = is_array($payload['data'] ?? null) ? $payload['data'] : $payload;

        $this->icons = array_values(array_map(
            fn (array $icon): array => [
                'name' => (string) ($icon['name'] ?? ''),
                'url' => ProductIcon::url(is_string($icon['name'] ?? null) ? $icon['name'] : null) ?? '',
            ],
            array_filter($icons, 'is_array'),
        ));
    }

    public function selectIcon(string $name): void
    {
        $this->draftIcon = $name;
        $this->iconPickerOpen = false;
        $this->iconSearch = '';
    }

    /**
     * @return array<int, array{name: string, url: string}>
     */
    public function filteredIcons(): array
    {
        $term = mb_strtolower(trim($this->iconSearch));

        if ($term === '') {
            return array_slice($this->icons, 0, 120);
        }

        return array_slice(array_values(array_filter(
            $this->icons,
            static fn (array $icon): bool => str_contains(mb_strtolower($icon['name']), $term),
        )), 0, 120);
    }

    // ── Foto del ítem ────────────────────────────────────────────────────

    public function pickPhoto(): void
    {
        if ($this->openItemId === null) {
            return;
        }

        $this->itemError = null;
        $this->itemNotice = null;

        // `GalleryPicker` consulta el bridge sin depender del bool de
        // `PendingMediaPicker::start()`, que siempre da false con este plugin.
        if (! (new GalleryPicker)->pickImage()) {
            $this->itemError = __('app.list_detail.photo_unavailable');
        }
    }

    /**
     * El listener de un evento nativo recibe los campos del payload por
     * nombre, no el objeto del evento: pedir `MediaSelected $event` hace que el
     * contenedor intente construirlo y falle con "unresolvable dependency".
     */
    #[On(MediaSelected::class)]
    public function onMediaSelected(bool $success = false, array $files = []): void
    {
        if (! $success || $files === [] || $this->listId === null || $this->openItemId === null) {
            return;
        }

        // El plugin manda cada archivo como objeto:
        //   { path, mimeType, extension, type, takenAt?, latitude?, longitude? }
        // Se acepta también un string suelto por si el contrato cambia.
        $first = $files[0] ?? null;
        $path = is_array($first) ? ($first['path'] ?? null) : $first;
        $extension = is_array($first) && is_string($first['extension'] ?? null)
            ? $first['extension']
            : 'jpg';

        if (! is_string($path) || $path === '') {
            $this->itemError = __('app.list_detail.photo_failed');
            $this->itemNotice = null;

            return;
        }

        $contents = @file_get_contents($path);

        if (! is_string($contents) || $contents === '') {
            $this->itemError = __('app.list_detail.photo_failed');

            return;
        }

        $this->itemNotice = __('app.list_detail.uploading');

        try {
            (new ListsService)->uploadItemImage(
                $this->listId,
                $this->openItemId,
                $contents,
                'photo.'.$extension,
                $this->householdId,
            );
        } catch (HomesideException $exception) {
            $this->itemError = $exception->getMessage();
            $this->itemNotice = null;

            return;
        }

        (new ItemImageCache)->forget($this->listId, $this->openItemId);

        // El plugin de cámara deja los archivos elegidos en storage privado y no
        // los borra nunca: una vez subida, la copia local no sirve.
        if (is_file($path)) {
            @unlink($path);
        }

        $this->itemNotice = __('app.list_detail.photo_uploaded');
        $this->load();
    }

    // ── Acciones de la lista ─────────────────────────────────────────────

    public function toggleListActions(): void
    {
        $this->listActionsOpen = ! $this->listActionsOpen;
        $this->confirmDelete = false;

        if ($this->listActionsOpen) {
            $this->renameDraft = $this->name;
        }
    }

    public function startRenaming(): void
    {
        $this->renaming = true;
        $this->renameDraft = $this->name;
    }

    public function cancelRenaming(): void
    {
        $this->renaming = false;
    }

    public function rename(): void
    {
        if ($this->listId === null || trim($this->renameDraft) === '') {
            return;
        }

        try {
            (new ListsService)->rename($this->listId, $this->renameDraft, $this->householdId);
        } catch (HomesideException $exception) {
            $this->error = $exception->getMessage();

            return;
        }

        $this->name = trim($this->renameDraft);
        $this->renaming = false;
        $this->listActionsOpen = false;
    }

    public function requestDelete(): void
    {
        $this->confirmDelete = true;
    }

    public function cancelDelete(): void
    {
        $this->confirmDelete = false;
    }

    public function deleteList(): void
    {
        if ($this->listId === null) {
            return;
        }

        try {
            (new ListsService)->delete($this->listId, $this->householdId);
        } catch (HomesideException $exception) {
            $this->error = $exception->getMessage();

            return;
        }

        $this->back();
    }

    public function goToLogin(): void
    {
        $this->navigate('/login');
    }

    // ── Normalización ────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function normalizeItem(array $item, ItemImageCache $images): array
    {
        $product = is_array($item['product'] ?? null) ? $item['product'] : null;
        $category = is_array($item['category'] ?? null)
            ? $item['category']
            : (is_array($product['category'] ?? null) ? $product['category'] : null);

        $icon = is_string($item['icon'] ?? null) && $item['icon'] !== ''
            ? $item['icon']
            : (is_string($product['icon'] ?? null) ? $product['icon'] : null);

        $name = is_string($item['custom_name'] ?? null) && $item['custom_name'] !== ''
            ? $item['custom_name']
            : (is_string($product['name'] ?? null) ? $product['name'] : __('app.list_detail.title'));

        $checked = (bool) ($item['is_checked'] ?? false);
        $cardColor = (string) ($checked
            ? theme('success', CardColor::CHECKED)
            : theme('destructive', CardColor::PENDING));

        $id = (string) ($item['id'] ?? '');
        $hasImage = is_string($item['image_url'] ?? null) && $item['image_url'] !== '';

        return [
            'id' => $id,
            'name' => $name,
            'icon_url' => ProductIcon::url($icon),
            'image_url' => $hasImage
                ? $images->url(
                    (string) $this->listId,
                    $id,
                    $this->householdId,
                    is_string($item['updated_at'] ?? null) ? $item['updated_at'] : null,
                )
                : null,
            'quantity' => is_numeric($item['quantity'] ?? null) ? (float) $item['quantity'] : null,
            'unit' => is_string($item['unit'] ?? null) ? $item['unit'] : null,
            'notes' => is_string($item['notes'] ?? null) ? $item['notes'] : null,
            'store_id' => is_string($item['store_id'] ?? null) ? $item['store_id'] : null,
            'category_id' => is_string($item['category_id'] ?? null)
                ? $item['category_id']
                : (is_string($category['id'] ?? null) ? $category['id'] : null),
            'icon' => $icon,
            'checked' => $checked,
            'color' => $cardColor,
            'color_text' => CardColor::foreground($cardColor),
        ];
    }

    /**
     * @param  array<string, mixed>  $product
     * @return array<string, mixed>
     */
    private function normalizeProduct(array $product): array
    {
        $category = is_array($product['category'] ?? null) ? $product['category'] : null;
        $color = CardColor::normalize(is_string($category['color'] ?? null) ? $category['color'] : null);

        return [
            'id' => (string) ($product['id'] ?? ''),
            'name' => (string) ($product['name'] ?? ''),
            'icon_url' => ProductIcon::url(is_string($product['icon'] ?? null) ? $product['icon'] : null),
            'category_name' => is_string($category['name'] ?? null) ? $category['name'] : null,
            'color' => $color,
            'color_text' => CardColor::foreground($color),
        ];
    }

    public function render(): View
    {
        return view('native.list-detail');
    }
}

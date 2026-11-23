<?php

namespace App\NativeComponents;

use App\Exceptions\HomesideException;
use App\Services\Homeside\AuthService;
use App\Services\Homeside\CatalogService;
use App\Services\Homeside\ListsService;
use App\Services\Homeside\Session;
use App\Services\Media\GalleryPicker;
use App\Services\Media\ItemImageCache;
use App\Services\Offline\LocalStore;
use App\Services\Offline\Outbox;
use App\Services\Offline\SyncEngine;
use App\Services\Offline\WriteThrough;
use App\Services\Settings\LocaleService;
use App\Support\CardColor;
use App\Support\ProductIcon;
use Illuminate\Support\Str;
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

    /** Error de la hoja de acciones, donde el usuario está mirando. */
    public ?string $listError = null;

    // ── Hoja de detalle ──────────────────────────────────────────────────
    public ?string $openItemId = null;

    /** Nombre del ítem abierto, para el encabezado de la hoja. */
    public string $openItemName = '';

    public string $draftQuantity = '';

    public string $draftUnit = '';

    public string $draftNotes = '';

    public string $draftStoreId = '';

    public string $draftCategoryId = '';

    public string $draftIcon = '';

    public string $iconSearch = '';

    public bool $iconPickerOpen = false;

    /**
     * Los íconos del catálogo son WebP a color: sobre una superficie oscura se
     * pierden. En modo oscuro se tiñen al color del texto; en claro se dejan
     * como están.
     */
    public ?string $iconTint = null;

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

    /** Foto que se muestra en la hoja: la guardada o la recién elegida. */
    public ?string $draftImageUrl = null;

    public bool $offline = false;

    /** @var list<string> Ítems cuya última escritura fue rechazada por el servidor. */
    public array $failedItemIds = [];

    public int $pendingCount = 0;

    /** idle | uploading | done | failed */
    public string $uploadState = 'idle';

    /** Foto guardada antes de la subida, para volver atrás si falla. */
    public ?string $previousImageUrl = null;

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
        // La apariencia puede haber cambiado desde Perfil (o desde el sistema):
        // se recalcula en cada carga.
        $this->refreshIconTint();

        if ($this->listId === null || $this->listId === '') {
            $this->loading = false;
            $this->error = __('app.list_detail.not_found');

            return;
        }

        $this->loading = true;
        $this->error = null;

        $images = new ItemImageCache;

        // Best effort: drena el outbox y trae el delta. Sin red sigue de largo
        // con lo que el espejo ya tenga.
        $result = app(SyncEngine::class)->run();
        $this->pendingCount = $result['pending'];
        $this->offline = $result['offline'];
        $this->needsLogin = $this->needsLogin || (bool) ($result['needsLogin'] ?? false);

        $this->failedItemIds = app(Outbox::class)->failedIds(LocalStore::LIST_ITEMS);

        $store = app(LocalStore::class);
        $list = $store->find(LocalStore::LISTS, $this->listId);
        $items = $store->itemsOf($this->listId);

        if ($list === null && $items === []) {
            // Primer arranque, o una lista recién creada: todavía no hay espejo.
            try {
                $list = (new ListsService)->show($this->listId, $this->householdId);
            } catch (HomesideException $exception) {
                $this->needsLogin = $exception->requiresLogin();
                $this->error = $exception->getMessage();
                $this->loading = false;

                return;
            }

            $items = is_array($list['items'] ?? null) ? $list['items'] : [];
        }

        $this->name = is_string($list['name'] ?? null) && $list['name'] !== ''
            ? $list['name']
            : __('app.list_detail.title');

        $this->pending = array_values(array_filter(
            array_map(fn (array $item): array => $this->normalizeItem($item, $images), $items),
            fn (array $item): bool => ! $item['checked'],
        ));

        $this->loadCatalog();

        $this->loading = false;
    }

    /**
     * Catálogo de categorías y recientes.
     *
     * Sin red no es imprescindible: la lista se sigue viendo y se puede tildar,
     * así que se conserva lo último cargado en vez de vaciar la pantalla.
     */
    private function loadCatalog(): void
    {
        $catalog = new CatalogService;
        $store = app(LocalStore::class);

        try {
            $categories = $catalog->categories();
            $recent = $catalog->recent(24, $this->householdId);

            // Se guarda lo que acabamos de ver: sin red, esto es lo único que
            // permite seguir agregando productos.
            foreach ($categories as $category) {
                if (is_array($category)) {
                    $store->put(LocalStore::CATEGORIES, $category);
                }
            }

            foreach ($recent as $product) {
                if (is_array($product)) {
                    $store->put(LocalStore::RECENT_PRODUCTS, $product);
                    $store->put(LocalStore::PRODUCTS, $product);
                }
            }

        } catch (HomesideException $exception) {
            $this->needsLogin = $this->needsLogin || $exception->requiresLogin();

            // Sin red: el catálogo conocido alcanza para seguir trabajando.
            $categories = $store->all(LocalStore::CATEGORIES);
            $recent = $store->all(LocalStore::RECENT_PRODUCTS);

            if ($categories === [] && $recent === []) {
                return;
            }
        }

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
    }

    private function refreshIconTint(): void
    {
        $this->iconTint = isDark() ? (string) theme('on-surface', '#F4F1E8') : null;
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
        $store = app(LocalStore::class);

        try {
            $products = (new CatalogService)->search($term);

            foreach ($products as $product) {
                if (is_array($product)) {
                    $store->put(LocalStore::PRODUCTS, $product);
                }
            }
        } catch (HomesideException $exception) {
            if (! in_array($exception->reason, [HomesideException::NETWORK, HomesideException::NOT_CONFIGURED], true)) {
                $this->error = $exception->getMessage();
                $this->searching = false;

                return;
            }

            // Sin red se busca en el catálogo guardado: el usuario tiene que
            // poder encontrar y agregar lo que ya conocía.
            $needle = mb_strtolower($term);

            $products = array_values(array_filter(
                $store->all(LocalStore::PRODUCTS),
                fn (array $product): bool => str_contains(
                    mb_strtolower(is_string($product['name'] ?? null) ? $product['name'] : ''),
                    $needle,
                ),
            ));

            $this->offline = true;
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

    public function addProduct(string $productId, string $name = '', ?string $icon = null): void
    {
        if ($this->listId === null || $this->busy) {
            return;
        }

        $this->busy = true;

        $lists = new ListsService;
        $product = $name !== '' ? ['name' => $name, 'icon' => $icon] : $this->knownProduct($productId);

        // Id propio: el ítem existe en el teléfono con su id final antes de
        // sincronizar, así el espejo no tiene que remapear nada después.
        $clientId = (string) Str::uuid();

        $payload = [
            'id' => $clientId,
            'product_id' => $productId,
            'quantity' => 1,
        ];

        try {
            $applied = app(WriteThrough::class)->run(
                'post',
                $this->itemsPath(),
                $payload,
                fn () => $lists->addItem($this->listId, $payload, $this->householdId),
                LocalStore::LIST_ITEMS,
                $clientId,
            );
        } catch (HomesideException $exception) {
            $this->error = $exception->getMessage();
            $this->busy = false;

            return;
        }

        if (! $applied) {
            // Sin red: la fila optimista entra al espejo para que se vea ya.
            app(LocalStore::class)->put(LocalStore::LIST_ITEMS, [
                'id' => $clientId,
                'list_id' => $this->listId,
                'product_id' => $productId,
                'custom_name' => $product['name'] ?? null,
                'icon' => $product['icon'] ?? null,
                'quantity' => 1,
                'is_checked' => false,
                'updated_at' => now()->toIso8601String(),
            ]);

            $this->pendingCount = app(Outbox::class)->pending();
            $this->offline = true;
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

        $lists = new ListsService;
        $product = ['name' => trim($this->search)];
        $item = ['quantity' => 1];

        // Id de la fila optimista: el vínculo con la operación encolada, para
        // poder decir en la tarjeta si el servidor la rechazó.
        $pendingId = 'pending-'.Str::uuid()->toString();

        try {
            // `quick-create` no acepta id de cliente (crea el producto y el ítem
            // juntos), así que sin red no hay fila optimista: el ítem aparece
            // cuando la operación se aplica y el delta lo trae.
            $created = null;

            $applied = app(WriteThrough::class)->run(
                'post',
                $this->itemsPath().'/quick-create',
                ['product' => $product, 'item' => $item],
                function () use ($lists, $product, $item, &$created) {
                    $created = $lists->quickCreateItem($this->listId, $product, $item, $this->householdId);

                    return $created;
                },
                LocalStore::LIST_ITEMS,
                $pendingId,
            );

            // El servidor puede haber reutilizado un producto que ya existía:
            // guardamos el que devolvió para que la próxima búsqueda lo tenga.
            if (is_array($created) && is_array($created['product'] ?? null)) {
                app(LocalStore::class)->put(LocalStore::PRODUCTS, $created['product']);
            }
        } catch (HomesideException $exception) {
            $this->error = $exception->getMessage();
            $this->busy = false;

            return;
        }

        if (! $applied) {
            // Sin red: se muestra el ítem ya, marcado como pendiente. El id del
            // producto nace en el servidor, así que es una fila local que se
            // retira sola cuando el servidor devuelve la definitiva.
            app(LocalStore::class)->put(LocalStore::LIST_ITEMS, [
                'id' => $pendingId,
                'list_id' => $this->listId,
                'product_id' => null,
                'custom_name' => $product['name'],
                'quantity' => 1,
                'is_checked' => false,
                'pending_sync' => true,
                'updated_at' => now()->toIso8601String(),
            ]);

            $this->pendingCount = app(Outbox::class)->pending();
            $this->offline = true;
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
        $checked = ! $item['checked'];

        // La versión que se editó viaja en el cuerpo: si el ítem cambió en el
        // servidor mientras estábamos offline, la API responde `stale_write` en
        // vez de pisar el cambio del otro.
        $payload = ['is_checked' => $checked];

        if (is_string($item['updated_at'] ?? null) && $item['updated_at'] !== '') {
            $payload['updated_at'] = $item['updated_at'];
        }

        try {
            $applied = app(WriteThrough::class)->run(
                'put',
                $this->itemPath($itemId),
                $payload,
                fn () => (new ListsService)->updateItem($this->listId, $itemId, $payload, $this->householdId),
                LocalStore::LIST_ITEMS,
                $itemId,
            );
        } catch (HomesideException $exception) {
            $this->error = $exception->getMessage();
            $this->busy = false;

            return;
        }

        if (! $applied) {
            // Sin red: se refleja en el espejo **ya**, si no la pantalla no
            // cambia y parece que el toque no hizo nada. El `updated_at` se
            // deja como estaba a propósito: es la versión del servidor sobre la
            // que se basa esta edición, y el push la manda para detectar
            // escrituras viejas.
            $store = app(LocalStore::class);
            $cached = $store->find(LocalStore::LIST_ITEMS, $itemId);

            if ($cached !== null) {
                $store->put(LocalStore::LIST_ITEMS, [...$cached, 'is_checked' => $checked]);
            }

            $this->pendingCount = app(Outbox::class)->pending();
            $this->offline = true;
        }

        $this->busy = false;
        $this->load();
    }

    // ── Acordeón de categorías ───────────────────────────────────────────

    /**
     * Ruta de la API para un ítem, según el ámbito de la lista.
     */
    private function itemsPath(): string
    {
        $base = $this->householdId !== null && $this->householdId !== ''
            ? '/households/'.$this->householdId.'/lists'
            : '/lists';

        return $base.'/'.$this->listId.'/items';
    }

    private function itemPath(string $itemId): string
    {
        return $this->itemsPath().'/'.$itemId;
    }

    /**
     * Producto ya visible en la pantalla, para poder pintar la fila optimista.
     *
     * @return array<string, mixed>
     */
    private function knownProduct(string $productId): array
    {
        $pool = [...$this->recent, ...$this->results];

        foreach ($this->categories as $category) {
            $pool = [...$pool, ...(is_array($category['products'] ?? null) ? $category['products'] : [])];
        }

        foreach ($pool as $product) {
            if (is_array($product) && ($product['id'] ?? null) === $productId) {
                return $product;
            }
        }

        return [];
    }

    /**
     * Reintenta las escrituras que el servidor rechazó.
     *
     * Un rechazo no es falta de red: no se reintenta solo, pero el usuario sí
     * puede pedirlo después de arreglar lo que corresponda.
     */
    public function retryFailed(): void
    {
        $ids = array_values(array_filter(
            array_map(fn (array $item): string => (string) ($item['id'] ?? ''), $this->pending),
            fn (string $id): bool => $id !== '' && in_array($id, $this->failedItemIds, true),
        ));

        if ($ids === []) {
            return;
        }

        $this->busy = true;

        app(Outbox::class)->retry(LocalStore::LIST_ITEMS, $ids);
        app(SyncEngine::class)->run();

        $this->busy = false;
        $this->load();
    }

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
        $store = app(LocalStore::class);

        try {
            $products = (new CatalogService)->search('', $categoryId, 60);

            foreach ($products as $product) {
                if (is_array($product)) {
                    $store->put(LocalStore::PRODUCTS, $product);
                }
            }
        } catch (HomesideException $exception) {
            // Sin red se cae al catálogo guardado y se filtra por categoría: el
            // usuario no tiene por qué quedarse sin productos.
            $products = array_values(array_filter(
                $store->all(LocalStore::PRODUCTS),
                fn (array $product): bool => ($product['category']['id'] ?? null) === $categoryId
                    || ($product['category_id'] ?? null) === $categoryId,
            ));

            if ($products === []) {
                // Sin catálogo guardado para esta categoría no hay nada que
                // mostrar, pero el spinner no puede quedarse girando: se marca
                // como cargada (vacía) y se avisa.
                $this->error = __('app.list_detail.category_offline_empty');

                foreach ($this->categories as $index => $category) {
                    if ($category['id'] === $categoryId) {
                        $this->categories[$index]['loaded'] = true;

                        break;
                    }
                }

                return;
            }
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
        $this->openItemName = (string) ($item['name'] ?? '');
        $this->itemError = null;
        $this->itemNotice = null;
        $this->draftImageUrl = is_string($item['image_url'] ?? null) ? $item['image_url'] : null;
        $this->uploadState = 'idle';
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
        $this->openItemName = '';
        $this->iconPickerOpen = false;
        $this->itemError = null;
        $this->itemNotice = null;
        $this->draftImageUrl = null;
        $this->previousImageUrl = null;
        $this->uploadState = 'idle';
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

    /**
     * Vuelve a la foto que tiene el servidor cuando la subida falla.
     *
     * El servidor es la verdad: puede que haya guardado la foto igual (un 500
     * de la respuesta no prueba lo contrario) o que la foto sea otra. Así que se
     * descarta el cache local, se vuelve a pedir la lista y se muestra lo que
     * haya. Si esa petición también falla, queda la última foto que veíamos.
     */
    private function rollbackImage(): void
    {
        $this->uploadState = 'failed';

        if ($this->listId === null || $this->openItemId === null) {
            $this->draftImageUrl = $this->previousImageUrl;

            return;
        }

        (new ItemImageCache)->forget($this->listId, $this->openItemId);

        $this->load();

        $fresh = collect($this->pending)->firstWhere('id', $this->openItemId);

        $this->draftImageUrl = is_array($fresh) && is_string($fresh['image_url'] ?? null)
            ? $fresh['image_url']
            : $this->previousImageUrl;
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
            $this->uploadState = 'failed';

            return;
        }

        // Se previsualiza lo elegido antes de subirlo: el archivo ya está en el
        // dispositivo y el usuario necesita ver que la selección funcionó.
        $this->previousImageUrl = $this->draftImageUrl;
        $this->draftImageUrl = 'file://'.$path;
        $this->uploadState = 'uploading';

        // Fotos de móvil: 3-10 MB es normal y la API acepta hasta
        // `homeside.image_upload_max_kb`. Rechazar antes de leer y enviar
        // ahorra subir decenas de megas para nada.
        $limitKb = (int) config('homeside.image_upload_max_kb', 12288);
        $size = @filesize($path);

        if ($limitKb > 0 && is_int($size) && $size > $limitKb * 1024) {
            $this->draftImageUrl = $this->previousImageUrl;
            $this->uploadState = 'failed';
            $this->itemNotice = null;
            $this->itemError = __('app.list_detail.photo_too_large', [
                'mb' => round($limitKb / 1024),
            ]);

            return;
        }

        $contents = @file_get_contents($path);

        if (! is_string($contents) || $contents === '') {
            $this->itemError = __('app.list_detail.photo_failed');
            $this->rollbackImage();

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
            $this->rollbackImage();

            return;
        }

        (new ItemImageCache)->forget($this->listId, $this->openItemId);

        $this->itemNotice = __('app.list_detail.photo_uploaded');
        $this->uploadState = 'done';
        $this->previousImageUrl = null;

        $this->load();

        // Tras recargar, la foto que vale es la que devuelve el servidor. Si la
        // recarga no trae el ítem (red, error), se conserva el archivo local:
        // mejor mostrar la foto elegida que un hueco.
        $fresh = collect($this->pending)->firstWhere('id', $this->openItemId);

        if (is_array($fresh) && is_string($fresh['image_url'] ?? null)) {
            $this->draftImageUrl = $fresh['image_url'];
        }

        // El plugin de cámara deja los archivos elegidos en storage privado y no
        // los borra nunca: sólo se descartan cuando el servidor ya tiene la foto.
        if ($this->draftImageUrl !== 'file://'.$path && is_file($path)) {
            @unlink($path);
        }
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
        $this->listError = null;
        $this->confirmDelete = true;
    }

    public function cancelDelete(): void
    {
        $this->listError = null;
        $this->confirmDelete = false;
    }

    public function deleteList(): void
    {
        if ($this->listId === null || $this->busy) {
            return;
        }

        $this->busy = true;
        $this->listError = null;

        $base = $this->householdId !== null && $this->householdId !== ''
            ? '/households/'.$this->householdId.'/lists'
            : '/lists';

        try {
            $applied = app(WriteThrough::class)->run(
                'delete',
                $base.'/'.$this->listId,
                [],
                fn () => (new ListsService)->delete((string) $this->listId, $this->householdId),
                LocalStore::LISTS,
                $this->listId,
            );
        } catch (HomesideException $exception) {
            // El aviso va en la hoja de acciones: el `error` de la pantalla vive
            // al final del scroll y desde acá no se veía, así que confirmar el
            // borrado parecía no hacer nada.
            $this->busy = false;
            $this->listError = $exception->getMessage();

            return;
        }

        if (! $applied) {
            // Sin red: se va del espejo ya y la baja queda encolada.
            app(LocalStore::class)->forget(LocalStore::LISTS, (string) $this->listId);
            $this->pendingCount = app(Outbox::class)->pending();
        }

        $this->busy = false;
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
            // Una escritura rechazada no se reintenta sola: la tarjeta lo dice
            // y la pantalla ofrece el reintento.
            'failed' => in_array($id, $this->failedItemIds, true),
            // La versión del servidor viaja en el cuerpo del PUT: sin ella la
            // API no puede responder `stale_write` cuando el ítem cambió en otro
            // dispositivo mientras estábamos sin red.
            'updated_at' => is_string($item['updated_at'] ?? null) ? $item['updated_at'] : null,
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
            'icon' => is_string($product['icon'] ?? null) ? $product['icon'] : null,
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

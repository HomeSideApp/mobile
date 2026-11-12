<?php

namespace App\NativeComponents;

use App\Exceptions\HomesideException;
use App\Services\Homeside\ListsService;
use App\Services\Settings\LocaleService;
use App\Support\CardColor;
use App\Support\ProductIcon;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;

/**
 * Detalle de una lista: ítems pendientes y comprados como cards.
 *
 * Colores (los mismos que la web):
 *  - pendientes: `destructive` (#B42318 en claro)
 *  - comprados: `success` (#1E6B63 en claro)
 * con el color de texto calculado por luminancia, porque el catálogo trae
 * amarillos y grises que con blanco fijo no se leen.
 */
final class ListDetail extends NativeComponent
{
    public ?string $listId = null;

    public ?string $householdId = null;

    public string $name = '';

    /** @var array<int, array<string, mixed>> */
    public array $pending = [];

    /** @var array<int, array<string, mixed>> */
    public array $checked = [];

    public bool $loading = true;

    public ?string $error = null;

    public bool $needsLogin = false;

    public bool $confirmDelete = false;

    public ?string $openItemId = null;

    public string $draftQuantity = '';

    public string $draftUnit = '';

    public string $draftNotes = '';

    public bool $busy = false;

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

    public function refresh(): void
    {
        $this->load();
    }

    public function navTitle(): string
    {
        return $this->name !== '' ? $this->name : __('app.list_detail.title');
    }

    public function addItems(): void
    {
        $this->navigate($this->basePath().'/add');
    }

    /**
     * Tildar / destildar. La web hace exactamente esto: el ítem comprado sale
     * de la grilla de pendientes.
     */
    public function toggle(string $itemId): void
    {
        if ($this->busy || $this->listId === null) {
            return;
        }

        $item = collect($this->pending)->firstWhere('id', $itemId)
            ?? collect($this->checked)->firstWhere('id', $itemId);

        if ($item === null) {
            return;
        }

        $this->busy = true;

        try {
            (new ListsService)->updateItem(
                $this->listId,
                $itemId,
                ['is_checked' => ! $item['checked']],
                $this->householdId,
            );
        } catch (HomesideException $exception) {
            $this->fail($exception);
            $this->busy = false;

            return;
        }

        $this->busy = false;
        $this->load();
    }

    public function openItem(string $itemId): void
    {
        $item = collect($this->pending)->firstWhere('id', $itemId)
            ?? collect($this->checked)->firstWhere('id', $itemId);

        if ($item === null) {
            return;
        }

        $this->openItemId = $itemId;
        $this->draftQuantity = $item['quantity'] !== null ? (string) $item['quantity'] : '';
        $this->draftUnit = (string) ($item['unit'] ?? '');
        $this->draftNotes = (string) ($item['notes'] ?? '');
    }

    public function closeItem(): void
    {
        $this->openItemId = null;
    }

    public function saveItem(): void
    {
        if ($this->listId === null || $this->openItemId === null) {
            return;
        }

        $payload = [
            'unit' => trim($this->draftUnit) !== '' ? trim($this->draftUnit) : null,
            'notes' => trim($this->draftNotes) !== '' ? trim($this->draftNotes) : null,
        ];

        if (is_numeric($this->draftQuantity) && (float) $this->draftQuantity > 0) {
            $payload['quantity'] = (float) $this->draftQuantity;
        }

        try {
            (new ListsService)->updateItem($this->listId, $this->openItemId, $payload, $this->householdId);
        } catch (HomesideException $exception) {
            $this->fail($exception);

            return;
        }

        $this->openItemId = null;
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
            $this->fail($exception);

            return;
        }

        $this->openItemId = null;
        $this->load();
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
            $this->fail($exception);

            return;
        }

        $this->confirmDelete = false;
        $this->back();
    }

    private function load(): void
    {
        if ($this->listId === null || $this->listId === '') {
            $this->loading = false;
            $this->error = __('app.list_detail.not_found');

            return;
        }

        $this->loading = true;
        $this->error = null;

        try {
            $list = (new ListsService)->show($this->listId, $this->householdId);
        } catch (HomesideException $exception) {
            $this->fail($exception);
            $this->loading = false;

            return;
        }

        $this->name = is_string($list['name'] ?? null) ? $list['name'] : 'Lista';
        $items = is_array($list['items'] ?? null) ? $list['items'] : [];

        $normalized = array_map(fn (array $item): array => $this->normalizeItem($item), $items);

        $this->pending = array_values(array_filter($normalized, fn (array $item): bool => ! $item['checked']));
        $this->checked = array_values(array_filter($normalized, fn (array $item): bool => $item['checked']));
        $this->loading = false;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function normalizeItem(array $item): array
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
            : (is_string($product['name'] ?? null) ? $product['name'] : 'Ítem');

        $quantity = $item['quantity'] ?? null;

        $checked = (bool) ($item['is_checked'] ?? false);
        $cardColor = (string) ($checked
            ? theme('success', CardColor::CHECKED)
            : theme('destructive', CardColor::PENDING));

        return [
            'id' => (string) ($item['id'] ?? ''),
            'name' => $name,
            'color' => $cardColor,
            'color_text' => CardColor::foreground($cardColor),
            'icon_url' => ProductIcon::url($icon),
            'quantity' => is_numeric($quantity) ? (float) $quantity : null,
            'unit' => is_string($item['unit'] ?? null) ? $item['unit'] : null,
            'notes' => is_string($item['notes'] ?? null) ? $item['notes'] : null,
            'checked' => $checked,
            'category_color' => CardColor::normalize(
                is_string($category['color'] ?? null) ? $category['color'] : null,
            ),
        ];
    }

    private function basePath(): string
    {
        return $this->householdId !== null
            ? '/households/'.$this->householdId.'/lists/'.$this->listId
            : '/lists/'.$this->listId;
    }

    private function fail(HomesideException $exception): void
    {
        $this->needsLogin = $exception->requiresLogin();
        $this->error = $exception->getMessage();
    }

    public function goToLogin(): void
    {
        $this->navigate('/login');
    }

    public function render(): View
    {
        return view('native.list-detail');
    }
}

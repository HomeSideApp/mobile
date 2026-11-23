<?php

namespace Tests\Feature;

use App\Services\Offline\LocalStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El espejo local: escribir una fila no puede tocar las demás.
 */
class LocalStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_writing_one_row_leaves_the_others_untouched(): void
    {
        $store = app(LocalStore::class);

        $store->put(LocalStore::LIST_ITEMS, ['id' => 'item-a', 'is_checked' => false, 'name' => 'Leche']);
        $store->put(LocalStore::LIST_ITEMS, ['id' => 'item-b', 'is_checked' => false, 'name' => 'Pan']);

        $store->put(LocalStore::LIST_ITEMS, ['id' => 'item-a', 'is_checked' => true, 'name' => 'Leche']);

        $this->assertTrue((bool) $store->find(LocalStore::LIST_ITEMS, 'item-a')['is_checked']);
        $this->assertFalse(
            (bool) $store->find(LocalStore::LIST_ITEMS, 'item-b')['is_checked'],
            'Tildar un ítem no puede tildar los demás.',
        );
    }

    public function test_a_relation_the_server_omits_keeps_the_known_value(): void
    {
        $store = app(LocalStore::class);

        // El POST trae el producto; el delta de un servidor viejo lo omite.
        $store->put(LocalStore::LIST_ITEMS, [
            'id' => 'item-1',
            'custom_name' => null,
            'quantity' => 1,
            'product' => ['id' => 'p1', 'name' => 'Pan'],
        ]);

        $store->put(LocalStore::LIST_ITEMS, [
            'id' => 'item-1',
            'custom_name' => null,
            'quantity' => 3,
        ]);

        $row = $store->find(LocalStore::LIST_ITEMS, 'item-1');

        $this->assertSame('Pan', $row['product']['name'] ?? null, 'La tarjeta no puede quedarse sin nombre.');
        $this->assertSame(3, $row['quantity'], 'El resto del payload sí se actualiza.');
    }

    public function test_an_explicit_null_does_remove_the_relation(): void
    {
        $store = app(LocalStore::class);

        $store->put(LocalStore::LIST_ITEMS, ['id' => 'item-1', 'product' => ['id' => 'p1', 'name' => 'Pan']]);
        $store->put(LocalStore::LIST_ITEMS, ['id' => 'item-1', 'product' => null]);

        $this->assertNull(app(LocalStore::class)->find(LocalStore::LIST_ITEMS, 'item-1')['product'] ?? null);
    }

    public function test_entities_do_not_share_rows(): void
    {
        $store = app(LocalStore::class);

        $store->put(LocalStore::LISTS, ['id' => 'same-id', 'name' => 'Compra']);
        $store->put(LocalStore::LIST_ITEMS, ['id' => 'same-id', 'is_checked' => true]);

        $this->assertSame('Compra', $store->find(LocalStore::LISTS, 'same-id')['name'] ?? null);
        $this->assertTrue((bool) $store->find(LocalStore::LIST_ITEMS, 'same-id')['is_checked']);
    }
}

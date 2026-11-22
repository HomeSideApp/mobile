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

    public function test_entities_do_not_share_rows(): void
    {
        $store = app(LocalStore::class);

        $store->put(LocalStore::LISTS, ['id' => 'same-id', 'name' => 'Compra']);
        $store->put(LocalStore::LIST_ITEMS, ['id' => 'same-id', 'is_checked' => true]);

        $this->assertSame('Compra', $store->find(LocalStore::LISTS, 'same-id')['name'] ?? null);
        $this->assertTrue((bool) $store->find(LocalStore::LIST_ITEMS, 'same-id')['is_checked']);
    }
}

<?php

namespace Tests\Feature;

use App\NativeComponents\ExpenseTicket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;
use Tests\Concerns\InteractsWithHomeside;
use Tests\TestCase;

/**
 * Ticket con OCR: subida, sondeo del estado y confirmación.
 */
class ExpenseTicketTest extends TestCase
{
    use InteractsWithHomeside;
    use RefreshDatabase;

    private function fakeHousehold(): void
    {
        Http::fake([
            '*/api/v1/me' => Http::response(['data' => $this->me()]),
            '*/api/v1/households' => Http::response(['data' => [
                ['id' => 'household-1', 'name' => 'Casa de Ana'],
            ]]),
        ]);
    }

    private function ticketFile(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'ticket').'.jpg';
        file_put_contents($path, str_repeat('x', 1024));

        return $path;
    }

    public function test_it_uploads_the_photo_and_starts_the_recognition(): void
    {
        $this->connect();
        $this->fakeHousehold();

        Http::fake([
            '*/api/v1/me' => Http::response(['data' => $this->me()]),
            '*/api/v1/households' => Http::response(['data' => [['id' => 'household-1', 'name' => 'Casa']]]),
            '*/api/v1/households/household-1/economy/imports' => Http::response(['data' => [
                'id' => 'import-1',
                'status' => 'queued',
                'progress' => 0,
                'draft' => [],
            ]], 201),
        ]);

        $screen = Native::test(ExpenseTicket::class)
            ->call('onMediaSelected', true, [['path' => $this->ticketFile()]]);

        $this->assertSame('import-1', $screen->get('importId'));
        $this->assertSame('queued', $screen->get('status'));

        Http::assertSent(fn ($request): bool => $request->method() === 'POST'
            && str_ends_with($request->url(), '/economy/imports'));
    }

    public function test_polling_shows_the_draft_when_it_is_ready(): void
    {
        $this->connect();
        $this->fakeHousehold();

        Http::fake([
            '*/api/v1/me' => Http::response(['data' => $this->me()]),
            '*/api/v1/households' => Http::response(['data' => [['id' => 'household-1', 'name' => 'Casa']]]),
            '*/api/v1/households/household-1/economy/imports' => Http::response(['data' => [
                'id' => 'import-1', 'status' => 'processing', 'progress' => 40, 'draft' => [],
            ]], 201),
            '*/api/v1/households/household-1/economy/imports/import-1' => Http::response(['data' => [
                'id' => 'import-1',
                'status' => 'review_required',
                'progress' => 100,
                'warnings' => ['El total no coincide con los ítems'],
                'draft' => [
                    'amount_minor' => 4235,
                    'currency' => 'EUR',
                    'place' => 'Mercadona',
                    'occurred_at' => '2026-11-12T00:00:00+00:00',
                    'title' => 'Compra',
                ],
            ]]),
        ]);

        $screen = Native::test(ExpenseTicket::class)
            ->call('onMediaSelected', true, [['path' => $this->ticketFile()]])
            ->call('refreshImport');

        $this->assertSame('review_required', $screen->get('status'));
        $this->assertSame('42,35 EUR', $screen->get('total'));
        $this->assertSame('Mercadona', $screen->get('place'));
        $this->assertSame('2026-11-12', $screen->get('date'));
        $this->assertCount(1, $screen->get('warnings'));
    }

    public function test_confirming_creates_the_expense(): void
    {
        $this->connect();
        $this->fakeHousehold();

        Http::fake([
            '*/api/v1/me' => Http::response(['data' => $this->me()]),
            '*/api/v1/households' => Http::response(['data' => [['id' => 'household-1', 'name' => 'Casa']]]),
            '*/api/v1/households/household-1/economy/imports' => Http::response(['data' => [
                'id' => 'import-1', 'status' => 'review_required', 'progress' => 100,
                'draft' => ['amount_minor' => 4235, 'currency' => 'EUR', 'place' => 'Mercadona', 'title' => 'Compra'],
            ]], 201),
            '*/api/v1/households/household-1/economy/imports/import-1/confirm' => Http::response(['data' => ['id' => 'transaction-9']], 201),
        ]);

        Native::test(ExpenseTicket::class)
            ->call('onMediaSelected', true, [['path' => $this->ticketFile()]])
            ->call('confirm');

        Http::assertSent(fn ($request): bool => $request->method() === 'POST'
            && str_ends_with($request->url(), '/imports/import-1/confirm')
            && $request['title'] === 'Compra');
    }

    public function test_discarding_forgets_the_import(): void
    {
        $this->connect();
        $this->fakeHousehold();

        Http::fake([
            '*/api/v1/me' => Http::response(['data' => $this->me()]),
            '*/api/v1/households' => Http::response(['data' => [['id' => 'household-1', 'name' => 'Casa']]]),
            '*/api/v1/households/household-1/economy/imports' => Http::response(['data' => [
                'id' => 'import-1', 'status' => 'review_required', 'progress' => 100, 'draft' => [],
            ]], 201),
            '*/api/v1/households/household-1/economy/imports/import-1' => Http::response(['data' => ['id' => 'import-1']]),
        ]);

        $screen = Native::test(ExpenseTicket::class)
            ->call('onMediaSelected', true, [['path' => $this->ticketFile()]])
            ->call('discard');

        $this->assertNull($screen->get('importId'));
        $this->assertSame('idle', $screen->get('status'));

        Http::assertSent(fn ($request): bool => $request->method() === 'DELETE'
            && str_ends_with($request->url(), '/imports/import-1'));
    }
}

<?php

namespace Tests\Feature;

use App\Services\Homeside\AppUpdateChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AppUpdateCheckerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function manifest(array $overrides = []): array
    {
        return [
            'channel' => 'dev',
            'version' => '0.1.50-dev',
            'version_code' => 50,
            'min_version_code' => 0,
            'apk_url' => 'https://example.test/homeside-dev.apk',
            'sha256' => str_repeat('a', 64),
            'notes' => 'Listas compartidas',
            ...$overrides,
        ];
    }

    private function fakeManifest(array $overrides = []): void
    {
        Http::fake([
            '*/releases/*/latest.json' => Http::response($this->manifest($overrides)),
        ]);
    }

    public function test_the_default_channel_is_dev(): void
    {
        $this->assertSame('dev', (new AppUpdateChecker)->channel());
        $this->assertSame('Desarrollo', (new AppUpdateChecker)->channelLabel());
    }

    public function test_it_reads_the_manifest_of_the_selected_channel(): void
    {
        config(['nativephp.version_code' => 10]);
        $this->fakeManifest();

        (new AppUpdateChecker)->status();

        Http::assertSent(fn ($request): bool => $request->url() === 'https://github.com/HomeSideApp/mobile/releases/download/dev-latest/latest.json');
    }

    public function test_a_newer_version_is_optional(): void
    {
        config(['nativephp.version_code' => 42]);
        $this->fakeManifest();

        $status = (new AppUpdateChecker)->status();

        $this->assertSame(AppUpdateChecker::STATE_OPTIONAL, $status['state']);
        $this->assertSame('0.1.50-dev', $status['latest_version']);
        $this->assertSame('https://example.test/homeside-dev.apk', $status['apk_url']);
    }

    public function test_the_same_version_is_up_to_date(): void
    {
        config(['nativephp.version_code' => 50]);
        $this->fakeManifest();

        $this->assertSame(AppUpdateChecker::STATE_UP_TO_DATE, (new AppUpdateChecker)->status()['state']);
    }

    public function test_a_minimum_above_the_current_version_is_required(): void
    {
        config(['nativephp.version_code' => 40]);
        $this->fakeManifest(['min_version_code' => 45]);

        $status = (new AppUpdateChecker)->status();

        $this->assertSame(AppUpdateChecker::STATE_REQUIRED, $status['state']);
        $this->assertStringContainsString('ya no es compatible', (string) $status['message']);
    }

    public function test_it_never_offers_a_downgrade(): void
    {
        // Usuario en dev con código 60 mirando el canal stable (código 30).
        config(['nativephp.version_code' => 60]);
        (new AppUpdateChecker)->setChannel('stable');
        $this->fakeManifest(['channel' => 'stable', 'version' => '0.2.0', 'version_code' => 30]);

        $this->assertSame(AppUpdateChecker::STATE_UP_TO_DATE, (new AppUpdateChecker)->status()['state']);
    }

    public function test_a_channel_without_releases_is_unavailable(): void
    {
        Http::fake(['*' => Http::response('Not Found', 404)]);

        $status = (new AppUpdateChecker)->status();

        $this->assertSame(AppUpdateChecker::STATE_UNAVAILABLE, $status['state']);
        $this->assertStringContainsString('Todavía no hay versiones', (string) $status['message']);
    }

    public function test_a_network_failure_is_unavailable_and_does_not_throw(): void
    {
        Http::fake(fn () => throw new ConnectionException('timeout'));

        $status = (new AppUpdateChecker)->status();

        $this->assertSame(AppUpdateChecker::STATE_UNAVAILABLE, $status['state']);
        $this->assertStringContainsString('No pudimos consultar', (string) $status['message']);
    }

    public function test_a_broken_manifest_is_unavailable(): void
    {
        Http::fake(['*' => Http::response(['hello' => 'world'])]);

        $this->assertSame(AppUpdateChecker::STATE_UNAVAILABLE, (new AppUpdateChecker)->status()['state']);
    }

    public function test_the_result_is_cached_for_a_day(): void
    {
        config(['nativephp.version_code' => 10]);
        $this->fakeManifest();

        (new AppUpdateChecker)->status();
        (new AppUpdateChecker)->status();

        Http::assertSentCount(1);
    }

    public function test_the_manual_check_bypasses_the_cache(): void
    {
        config(['nativephp.version_code' => 10]);
        $this->fakeManifest();

        $checker = new AppUpdateChecker;
        $checker->status();
        $checker->status(force: true);

        Http::assertSentCount(2);
    }

    public function test_switching_channel_fetches_the_other_manifest(): void
    {
        config(['nativephp.version_code' => 10]);
        Http::fake([
            '*/download/dev-latest/latest.json' => Http::response($this->manifest()),
            '*/releases/latest/download/latest.json' => Http::response($this->manifest([
                'channel' => 'stable',
                'version' => '0.2.0',
                'version_code' => 30,
            ])),
        ]);

        $checker = new AppUpdateChecker;
        $checker->status();
        $checker->setChannel('stable');
        $status = $checker->status();

        $this->assertSame('stable', $status['channel']);
        $this->assertSame('Estable', $status['channel_label']);
        $this->assertSame('0.2.0', $status['latest_version']);
        Http::assertSent(fn ($request): bool => $request->url() === 'https://github.com/HomeSideApp/mobile/releases/latest/download/latest.json');
    }

    public function test_an_unknown_channel_is_rejected(): void
    {
        $this->assertFalse((new AppUpdateChecker)->setChannel('nightly'));
        $this->assertSame('dev', (new AppUpdateChecker)->channel());
    }

    public function test_before_the_first_check_the_state_is_unknown_and_no_request_is_made(): void
    {
        Http::fake();

        $status = (new AppUpdateChecker)->lastKnownStatus();

        $this->assertSame(AppUpdateChecker::STATE_UNKNOWN, $status['state']);
        Http::assertNothingSent();
    }
}

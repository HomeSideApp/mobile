<?php

namespace Tests\Feature;

use App\Services\Settings\AppearanceService;
use App\Services\Settings\SettingsRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Native\Mobile\UI\Theme;
use Tests\TestCase;

class AppearanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Theme::reset();
    }

    protected function tearDown(): void
    {
        Theme::reset();

        parent::tearDown();
    }

    /**
     * Las paletas originales se leen del archivo: `Theme::merge()` reescribe
     * `config('native-ui.theme')`, así que la config ya no sirve como referencia.
     */
    private function palette(string $which, string $key): mixed
    {
        /** @var array<string, mixed> $config */
        $config = require config_path('native-ui.php');

        return $config['theme'][$which][$key] ?? null;
    }

    public function test_the_default_is_the_system_appearance(): void
    {
        $this->assertSame('system', (new AppearanceService)->current());
    }

    public function test_choosing_an_appearance_persists(): void
    {
        $service = new AppearanceService;

        $this->assertTrue($service->set('light'));
        $this->assertSame('light', $service->current());
        $this->assertSame('light', (new SettingsRepository)->get(AppearanceService::SETTING_KEY));
    }

    public function test_an_unknown_appearance_is_rejected(): void
    {
        $this->assertFalse((new AppearanceService)->set('sepia'));
        $this->assertSame('system', (new AppearanceService)->current());
    }

    public function test_forcing_light_paints_the_dark_palette_with_light_tokens(): void
    {
        (new AppearanceService)->apply('light');

        $this->assertSame($this->palette('light', 'primary'), Theme::get('dark.primary'));
        $this->assertSame($this->palette('light', 'background'), Theme::get('dark.background'));
    }

    public function test_forcing_dark_paints_the_light_palette_with_dark_tokens(): void
    {
        (new AppearanceService)->apply('dark');

        $this->assertSame($this->palette('dark', 'primary'), Theme::get('light.primary'));
    }

    public function test_going_back_to_system_restores_both_palettes(): void
    {
        $service = new AppearanceService;

        $service->apply('light');
        $service->apply('system');

        $this->assertSame($this->palette('light', 'primary'), Theme::get('light.primary'));
        $this->assertSame($this->palette('dark', 'primary'), Theme::get('dark.primary'));
    }
}

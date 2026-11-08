<?php

namespace Tests\Feature;

use App\Support\CardColor;
use PHPUnit\Framework\TestCase;

class CardColorTest extends TestCase
{
    public function test_dark_text_on_light_category_colors(): void
    {
        // Amarillo y grises del catálogo: con blanco no se leen.
        $this->assertSame('#153C3A', CardColor::foreground('#FFCA28'));
        $this->assertSame('#153C3A', CardColor::foreground('#BDBDBD'));
        $this->assertSame('#153C3A', CardColor::foreground('#D9E8B6'));
        $this->assertSame('#153C3A', CardColor::foreground('#7CCBA2'));
    }

    public function test_light_text_on_the_dark_brand_colors(): void
    {
        $this->assertSame('#FFFFFF', CardColor::foreground('#B42318'));
        $this->assertSame('#FFFFFF', CardColor::foreground('#1E6B63'));
    }

    public function test_it_picks_whichever_text_wins_on_contrast(): void
    {
        // Azul medio: el texto oscuro contrasta 4.6:1 y el blanco 2.6:1.
        $this->assertSame('#153C3A', CardColor::foreground('#42A5F5'));
    }

    public function test_it_accepts_short_hex_and_alpha(): void
    {
        $this->assertSame('#FFFFFF', CardColor::foreground('#B42318'));
        $this->assertSame('#153C3A', CardColor::foreground('#FFFFFF80'));
    }

    public function test_invalid_input_falls_back_to_light_text_and_brand_color(): void
    {
        $this->assertSame('#FFFFFF', CardColor::foreground(null));
        $this->assertSame('#FFFFFF', CardColor::foreground('not-a-color'));
        $this->assertSame(CardColor::FALLBACK, CardColor::normalize('not-a-color'));
    }

    public function test_normalize_returns_a_six_digit_hex(): void
    {
        $this->assertSame('#B42318', CardColor::normalize('#b42318'));
        $this->assertSame('#FF0000', CardColor::normalize('#f00'));
        $this->assertSame('#B42318', CardColor::normalize('#B42318FF'));
    }
}

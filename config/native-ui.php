<?php

/**
 * Native UI — HomeSide theme.
 *
 * Source of truth: `homeside/DESIGN.md` (design tokens, light + dark).
 * Only the semantic roles that EDGE supports are mapped here; the palette is
 * the canonical one (Forest, Seaglass, Sage, Moss, Sand, Mist, Slate).
 *
 * Contrast note: `on-surface-variant` is #4F5D66 instead of DESIGN's #5B6770
 * because the original lands at ~4.33:1 over `surface-variant` (#E6ECEF),
 * just under WCAG AA. Everything else is verbatim from DESIGN.md.
 *
 * Runtime per-screen colors (shopping-list product cards) come from the API
 * (`category.color`) and are applied as arbitrary classes, not tokens: they
 * are data, not brand.
 */

return [

    'theme' => [

        'light' => [
            'primary' => '#1E6B63',
            'on-primary' => '#FFFFFF',

            'secondary' => '#F4F1E8',
            'on-secondary' => '#1E6B63',

            'surface' => '#FFFFFF',
            'on-surface' => '#153C3A',
            'background' => '#FBFCFA',
            'on-background' => '#153C3A',

            'surface-variant' => '#E6ECEF',
            'on-surface-variant' => '#4F5D66',

            'outline' => '#CBD5D8',
            'outline-variant' => '#E6ECEF',

            'destructive' => '#B42318',
            'on-destructive' => '#FFFFFF',

            'success' => '#1E6B63',
            'on-success' => '#FFFFFF',

            'accent' => '#D9E8B6',
            'on-accent' => '#1E6B63',

            'warning' => '#FFF2C7',
            'on-warning' => '#654A00',

            'info' => '#E9F2FF',
            'on-info' => '#184E88',
        ],

        'dark' => [
            'primary' => '#7CCBA2',
            'on-primary' => '#0B2F2F',

            'secondary' => '#164641',
            'on-secondary' => '#F4F1E8',

            'surface' => '#103B39',
            'on-surface' => '#F4F1E8',
            'background' => '#0B2F2F',
            'on-background' => '#F4F1E8',

            'surface-variant' => '#173F3D',
            'on-surface-variant' => '#B8C9C6',

            'outline' => '#2B5A57',
            'outline-variant' => '#234C49',

            'destructive' => '#EF6A60',
            'on-destructive' => '#0B2F2F',

            'success' => '#7CCBA2',
            'on-success' => '#0B2F2F',

            'accent' => '#1E574F',
            'on-accent' => '#D9E8B6',
        ],

        'radius-sm' => 4,
        'radius-md' => 8,
        'radius-lg' => 12,
        'radius-full' => 9999,

        'font-sm' => 14,
        'font-md' => 16,
        'font-lg' => 20,
        'font-xl' => 24,

    ],

    'fonts' => [
        'default' => 'Inter-Regular',
    ],

];

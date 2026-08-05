<?php

// Branding. Rename the whole product from one place. These defaults can be
// overridden by env, and by DB settings applied at boot (DB-driven config).
return [
    'name' => env('BRAND_NAME', env('APP_NAME', 'GameMGR')),
    'tagline' => env('BRAND_TAGLINE', 'Free Game Server Control Panel'),
    // Accent hex; overrides the brand ramp at runtime. Settable in the UI.
    'accent' => env('BRAND_ACCENT', '#6d28d9'),
    // Logo/favicon glyph (an x-icon name). Distinct per product.
    'icon' => env('BRAND_ICON', 'controller'),
];

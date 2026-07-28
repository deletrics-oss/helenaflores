<?php
// includes/theme_injector.php

$s_file_theme = __DIR__ . '/site_settings.json';
$theme_config = file_exists($s_file_theme) ? json_decode(file_get_contents($s_file_theme), true) : [];

// DEFAULT: DARK MODE (Original Style)
$defaults = [
    'mode' => 'dark',
    'color_bg' => '#05070a',
    'color_card' => '#11161f',
    'color_primary' => '#ffb703',
    'color_text' => '#f8f9fa',
    'color_accent' => '#219ebc'
];

// LIGHT MODE DEFAULTS
$light_defaults = [
    'mode' => 'light',
    'color_bg' => '#f0f2f5',
    'color_card' => '#ffffff',
    'color_primary' => '#007bff', // Blueish for light mode or keep yellow? Blue contrasts better on white usually
    'color_text' => '#212529',
    'color_accent' => '#17a2b8'
];

// Merge Logic
$mode = $theme_config['theme_mode'] ?? 'dark';
$active_defaults = ($mode === 'light') ? $light_defaults : $defaults;

// User Overrides (only if set, otherwise use active defaults)
$bg = $theme_config['color_bg'] ?? $active_defaults['color_bg'];
$card = $theme_config['color_card'] ?? $active_defaults['color_card'];
$prim = $theme_config['color_primary'] ?? $active_defaults['color_primary'];
$txt = $theme_config['color_text'] ?? $active_defaults['color_text'];
$acc = $theme_config['color_accent'] ?? $active_defaults['color_accent'];

// Calculate lighter/darker variations for hover/shadows
// Simple heuristics (not perfect color science but enough for CSS)
?>
<style>
    :root {
        --bg-dark:
            <?php echo $bg; ?>
        ;
        --bg-card:
            <?php echo $card; ?>
        ;
        --primary:
            <?php echo $prim; ?>
        ;
        --text-main:
            <?php echo $txt; ?>
        ;
        --accent:
            <?php echo $acc; ?>
        ;

        /* Derived Colors */
        --border:
            <?php echo $mode === 'light' ? '#dee2e6' : '#2b2d42'; ?>
        ;
        --text-muted:
            <?php echo $mode === 'light' ? '#6c757d' : '#8d99ae'; ?>
        ;

        /* Shadow adjustment for Light Mode */
        --shadow:
            <?php echo $mode === 'light' ? '0 4px 12px rgba(0,0,0,0.1)' : '0 8px 30px rgba(0, 0, 0, 0.5)'; ?>
        ;
    }

    /* Extra Global Overrides */
    <?php if ($mode === 'light'): ?>
        body {
            color: var(--text-main);
            /* Ensure contrast */
        }

        .auth-box,
        form,
        input,
        textarea,
        select {
            background-color: #fff !important;
            border: 1px solid #ced4da !important;
            color: #333 !important;
        }

        /* Reverse logic for dark headers if using light mode? */
        /* Usually admins prefer dark sidebar even in light mode, but let's stick to variables */
    <?php endif; ?>
</style>
<script>
    // 🛡️ Content Protection (No Right Click / No Drag)
    document.addEventListener('contextmenu', function (e) {
        if (e.target.tagName === 'IMG' || e.target.tagName === 'VIDEO') {
            e.preventDefault();
        }
    });
    document.addEventListener('dragstart', function (e) {
        if (e.target.tagName === 'IMG' || e.target.tagName === 'VIDEO') {
            e.preventDefault();
        }
    });
</script>
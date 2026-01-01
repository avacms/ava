<?php
/**
 * Admin Theme Partial
 * 
 * Outputs inline CSS for the configured admin accent color theme.
 * Include this in the <head> of admin views after admin.css.
 * 
 * Required variable:
 * - $adminTheme: Theme name (cyan, pink, purple, green, blue, amber)
 */

$themeColors = [
    'cyan'   => ['dark' => ['#0891b2', '#06b6d4', 'rgba(8, 145, 178, 0.15)'],     'light' => ['#0e7490', '#0891b2', 'rgba(14, 116, 144, 0.08)']],
    'pink'   => ['dark' => ['#db2777', '#ec4899', 'rgba(219, 39, 119, 0.15)'],    'light' => ['#be185d', '#db2777', 'rgba(190, 24, 93, 0.08)']],
    'purple' => ['dark' => ['#7c3aed', '#8b5cf6', 'rgba(124, 58, 237, 0.15)'],    'light' => ['#6d28d9', '#7c3aed', 'rgba(109, 40, 217, 0.08)']],
    'green'  => ['dark' => ['#059669', '#10b981', 'rgba(5, 150, 105, 0.15)'],     'light' => ['#047857', '#059669', 'rgba(4, 120, 87, 0.08)']],
    'blue'   => ['dark' => ['#2563eb', '#3b82f6', 'rgba(37, 99, 235, 0.15)'],     'light' => ['#1d4ed8', '#2563eb', 'rgba(29, 78, 216, 0.08)']],
    'amber'  => ['dark' => ['#d97706', '#f59e0b', 'rgba(217, 119, 6, 0.15)'],     'light' => ['#b45309', '#d97706', 'rgba(180, 83, 9, 0.08)']],
];
$theme = $themeColors[$adminTheme ?? 'cyan'] ?? $themeColors['cyan'];
?>
<style>
    /* Theme accent colors (dark mode) */
    :root, html[data-theme="dark"] {
        --accent: <?= $theme['dark'][0] ?>;
        --accent-hover: <?= $theme['dark'][1] ?>;
        --accent-subtle: <?= $theme['dark'][2] ?>;
    }
    /* Theme accent colors (light mode) */
    @media (prefers-color-scheme: light) {
        :root {
            --accent: <?= $theme['light'][0] ?>;
            --accent-hover: <?= $theme['light'][1] ?>;
            --accent-subtle: <?= $theme['light'][2] ?>;
        }
    }
    html[data-theme="light"] {
        --accent: <?= $theme['light'][0] ?>;
        --accent-hover: <?= $theme['light'][1] ?>;
        --accent-subtle: <?= $theme['light'][2] ?>;
    }
</style>

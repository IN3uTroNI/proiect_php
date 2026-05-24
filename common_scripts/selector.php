<?php
// Language and Theme Selector Component (pure PHP output - no closing tag)
// Include with: include __DIR__ . '/selector.php';

// Build language options
$langOptions = '';
foreach (get_supported_languages() as $code => $name) {
    $selected = get_language() === $code ? ' selected' : '';
    $langOptions .= '<option value="' . h($code) . '"' . $selected . '>' . h($name) . '</option>';
}

// Build theme options
$theme = get_theme();
$themeOptions = '';
$themeOptions .= '<option value="dark"' . ($theme === 'dark' ? ' selected' : '') . '>🌙 ' . t('theme_dark') . '</option>';
$themeOptions .= '<option value="light"' . ($theme === 'light' ? ' selected' : '') . '>☀️ ' . t('theme_light') . '</option>';
$themeOptions .= '<option value="auto"' . ($theme === 'auto' ? ' selected' : '') . '>⚙️ ' . t('theme_auto') . '</option>';

echo '<div style="position:fixed;top:1rem;right:1rem;display:flex;gap:0.5rem;z-index:1000">'
    . '<form method="get" style="margin:0;display:flex;gap:0.3rem">'
    . '<select name="lang" onchange="this.form.submit()" style="padding:0.4rem;border:1px solid rgba(255,255,255,0.2);background:rgba(0,0,0,0.1);color:inherit;border-radius:4px;cursor:pointer">'
    . $langOptions
    . '</select>'
    . '<select name="theme" onchange="this.form.submit()" style="padding:0.4rem;border:1px solid rgba(255,255,255,0.2);background:rgba(0,0,0,0.1);color:inherit;border-radius:4px;cursor:pointer">'
    . $themeOptions
    . '</select>'
    . '</form>'
    . '</div>';


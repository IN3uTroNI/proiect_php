<?php
declare(strict_types=1);

// Secure session parameters
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax'
]);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../data/cintroller.php';
require_once __DIR__ . '/translations.php';

define('DB_FILE', __DIR__ . '/../data/dndDataBase.sqlite');

$controller = new DnDSQLiteController(DB_FILE);

// Initialize language from cookie or request
$language = $_COOKIE['lang'] ?? $_GET['lang'] ?? 'en';
$translator = new Translator($language);

// Initialize theme from cookie or request
$theme = $_COOKIE['theme'] ?? $_GET['theme'] ?? 'dark';
if (!in_array($theme, ['light', 'dark', 'auto'])) {
    $theme = 'dark';
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(string $token): bool
{
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

function h($s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function is_logged_in(): bool
{
    return !empty($_SESSION['user']);
}

function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: /login.php');
        exit;
    }
}

function t(string $key, string $default = ''): string
{
    global $translator;
    return $translator->t($key, $default);
}

function set_language(string $lang): void
{
    global $translator;
    $translator->setLanguage($lang);
    setcookie('lang', $lang, time() + (365 * 24 * 60 * 60), '/', '', false, true);
}

function set_theme(string $t): void
{
    global $theme;
    if (in_array($t, ['light', 'dark', 'auto'])) {
        $theme = $t;
        setcookie('theme', $t, time() + (365 * 24 * 60 * 60), '/', '', false, true);
    }
}

function get_theme(): string
{
    global $theme;
    return $theme;
}

function get_language(): string
{
    global $translator;
    return $translator->getLanguage();
}

function get_supported_languages(): array
{
    global $translator;
    return $translator->getSupportedLanguages();
}


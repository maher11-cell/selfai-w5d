<?php
/**
 * SelfAI — i18n endpoint
 *   GET ?action=get       → returns the active locale + flat translations dictionary
 *   GET ?action=list      → returns all available locales (for the language switcher)
 *   GET ?action=set&lang= → sets the locale cookie and returns the new dict
 *
 * No authentication required: the language switcher must work on the public landing page too.
 */
require __DIR__ . '/../includes/db.php';

session_name('selfai_sid');
session_start();

$action = $_GET['action'] ?? 'get';

if ($action === 'list') {
    $i = selfai_i18n_load();
    selfai_json([
        'ok' => true,
        'active'  => selfai_resolve_locale(),
        'locales' => $i['locales_info'] ?? [],
    ]);
}

if ($action === 'set') {
    $lang = (string)($_GET['lang'] ?? '');
    if (!in_array($lang, selfai_locales(), true)) {
        selfai_json(['error' => 'unsupported_locale', 'available' => selfai_locales()], 400);
    }
    setcookie('selfai_locale', $lang, [
        'expires'  => time() + 60 * 60 * 24 * 365,
        'path'     => '/',
        'samesite' => 'Lax',
        'httponly' => false,
    ]);
    $_COOKIE['selfai_locale'] = $lang;
    // bust the static cache by overriding GET
    $_GET['lang'] = $lang;
    // force re-resolve
    $info = selfai_locale_info($lang);
    selfai_json([
        'ok'       => true,
        'locale'   => $lang,
        'info'     => $info,
        'strings'  => selfai_translations($lang),
    ]);
}

// default: get
$locale = selfai_resolve_locale();
selfai_json([
    'ok'      => true,
    'locale'  => $locale,
    'info'    => selfai_locale_info($locale),
    'strings' => selfai_translations($locale),
]);

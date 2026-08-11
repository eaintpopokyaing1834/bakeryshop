<?php
// includes/lang.php — Language loader and __() helper

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Handle language switch from POST
if (isset($_POST['set_lang'])) {
    $allowed = ['en', 'my'];
    $lang = in_array($_POST['set_lang'], $allowed) ? $_POST['set_lang'] : 'en';
    $_SESSION['lang'] = $lang;
    // Redirect back to same page (removing POST)
    $redirect = $_POST['redirect'] ?? $_SERVER['HTTP_REFERER'] ?? '/sweetheaven/user/index.php';
    header('Location: ' . $redirect);
    exit;
}

// Determine active language
$GLOBALS['_lang_code'] = $_SESSION['lang'] ?? 'en';

// Load translations
$_langFile = __DIR__ . '/lang/' . $GLOBALS['_lang_code'] . '.php';
if (!file_exists($_langFile)) {
    $_langFile = __DIR__ . '/lang/en.php';
}
$GLOBALS['_translations'] = require $_langFile;

// Fallback to English for any missing key
$_enFile = __DIR__ . '/lang/en.php';
$GLOBALS['_translations_en'] = require $_enFile;

/**
 * Translate a key. Supports sprintf-style placeholders.
 * Usage: __('key') or __('key', $val1, $val2)
 */
function __($key, ...$args) {
    $str = $GLOBALS['_translations'][$key]
        ?? $GLOBALS['_translations_en'][$key]
        ?? $key;
    if (!empty($args)) {
        $str = vsprintf($str, $args);
    }
    return $str;
}

/**
 * Return the current language code: 'en' or 'my'
 */
function currentLang(): string {
    return $GLOBALS['_lang_code'];
}

/**
 * Return the localized category name with English fallback
 */
function getLocalizedCategoryName($categoryData, $nameKey = 'name', $nameMyKey = 'name_my'): string {
    if (currentLang() === 'my' && !empty($categoryData[$nameMyKey])) {
        return $categoryData[$nameMyKey];
    }
    return $categoryData[$nameKey] ?? '';
}

/**
 * Return the localized product name with English fallback
 */
function getLocalizedProductName($productData, $nameKey = 'name', $nameMyKey = 'name_my'): string {
    if (currentLang() === 'my' && !empty($productData[$nameMyKey])) {
        return $productData[$nameMyKey];
    }
    return $productData[$nameKey] ?? '';
}

/**
 * Return the localized product description with English fallback
 */
function getLocalizedProductDescription($productData, $descKey = 'description', $descMyKey = 'description_my'): string {
    if (currentLang() === 'my' && !empty($productData[$descMyKey])) {
        return $productData[$descMyKey];
    }
    return $productData[$descKey] ?? '';
}

/**
 * Convert English digits to Myanmar digits
 */
function convertToMyanmarDigits($number): string {
    $en_digits = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
    $my_digits = ['၀', '၁', '၂', '၃', '၄', '၅', '၆', '၇', '၈', '၉'];
    return str_replace($en_digits, $my_digits, (string)$number);
}

/**
 * Format price and append currency dynamically based on current language
 */
function formatPrice($amount): string {
    $formatted = number_format((float)$amount);
    if (currentLang() === 'my') {
        $formatted = convertToMyanmarDigits($formatted);
        return $formatted . ' ကျပ်';
    }
    return $formatted . ' MMK';
}

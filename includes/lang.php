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

/**
 * Localize a number to Myanmar digits if current language is 'my'
 */
function localizeNumber($number): string {
    if (currentLang() === 'my') {
        return convertToMyanmarDigits($number);
    }
    return (string)$number;
}

/**
 * Localize a date/time string. 
 */
function localizeDate($dateString, $format = 'M j, Y g:i A'): string {
    if (!$dateString) return '';
    $timestamp = is_numeric($dateString) ? (int)$dateString : strtotime($dateString);
    $formatted = date($format, $timestamp);
    if (currentLang() === 'my') {
        $en_months_short = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        $my_months_full = ['ဇန်နဝါရီ', 'ဖေဖော်ဝါရီ', 'မတ်', 'ဧပြီ', 'မေ', 'ဇွန်', 'ဇူလိုင်', 'ဩဂုတ်', 'စက်တင်ဘာ', 'အောက်တိုဘာ', 'နိုဝင်ဘာ', 'ဒီဇင်ဘာ'];
        $en_months_long = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
        
        $en_days_short = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        $en_days_long = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        $my_days_long = ['တနင်္လာ', 'အင်္ဂါ', 'ဗုဒ္ဓဟူး', 'ကြာသပတေး', 'သောကြာ', 'စနေ', 'တနင်္ဂနွေ'];

        $en_ampm = ['AM', 'PM', 'am', 'pm'];
        $my_ampm = ['နံနက်', 'ညနေ', 'နံနက်', 'ညနေ'];
        
        $formatted = str_replace($en_months_long, $my_months_full, $formatted);
        $formatted = str_replace($en_months_short, $my_months_full, $formatted);
        $formatted = str_replace($en_days_long, $my_days_long, $formatted);
        $formatted = str_replace($en_days_short, $my_days_long, $formatted);
        $formatted = str_replace($en_ampm, $my_ampm, $formatted);
        $formatted = str_replace(',', '၊', $formatted);
        
        return convertToMyanmarDigits($formatted);
    }
    return $formatted;
}

/**
 * Format a discount label dynamically
 */
function getLocalizedDiscountLabel($product): string {
    if (empty($product['discount_type']) || empty($product['discount_value'])) {
        return $product['discount_name'] ?? '';
    }
    
    if ($product['discount_type'] === 'percentage') {
        $val = currentLang() === 'my' ? convertToMyanmarDigits((int) $product['discount_value']) : (int) $product['discount_value'];
        return sprintf(__('discount_percent_off'), $val);
    } else {
        $val = currentLang() === 'my' ? convertToMyanmarDigits(number_format($product['discount_value'])) : number_format($product['discount_value']);
        return sprintf(__('discount_mmk_off'), $val);
    }
}

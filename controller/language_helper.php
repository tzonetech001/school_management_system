<?php


// Available languages
$available_languages = [
    'en' => 'English',
    'sw' => 'Kiswahili'
];

// Initialize language
if (!isset($_SESSION['lang'])) {
    // Check for saved language in cookie
    if (isset($_COOKIE['muyovozi_lang']) && array_key_exists($_COOKIE['muyovozi_lang'], $available_languages)) {
        $_SESSION['lang'] = $_COOKIE['muyovozi_lang'];
    } else {
        // Auto-detect browser language (optional)
        $browser_lang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);
        $_SESSION['lang'] = array_key_exists($browser_lang, $available_languages) ? $browser_lang : 'en';
    }
}

// Set language from GET parameter
if (isset($_GET['lang']) && array_key_exists($_GET['lang'], $available_languages)) {
    $_SESSION['lang'] = $_GET['lang'];
    // Save to cookie for 1 year
    setcookie('muyovozi_lang', $_GET['lang'], time() + (365 * 24 * 60 * 60), '/');
    // Remove lang parameter and redirect to same page without it
    $redirect_url = str_replace('?lang=' . $_GET['lang'], '', $_SERVER['REQUEST_URI']);
    $redirect_url = str_replace('&lang=' . $_GET['lang'], '', $redirect_url);
    header("Location: $redirect_url");
    exit();
}

$current_lang = $_SESSION['lang'];

// Translation function
function __($key, $lang = null) {
    global $current_lang;
    $lang = $lang ?: $current_lang;
    
    static $translations = null;
    if ($translations === null) {
        $translations = include 'translations.php';
    }
    
    return isset($translations[$lang][$key]) ? $translations[$lang][$key] : $key;
}

// Get language selector HTML
function getLanguageSelector() {
    global $available_languages, $current_lang;
    
    $html = '<div class="language-selector">';
    $html .= '<button class="lang-btn" onclick="toggleLanguageMenu()">';
    $html .= '<i class="fas fa-language"></i> ';
    $html .= '<span>' . ($available_languages[$current_lang] ?? 'English') . '</span>';
    $html .= '<i class="fas fa-chevron-down"></i>';
    $html .= '</button>';
    $html .= '<div class="lang-dropdown" id="langDropdown">';
    
    foreach ($available_languages as $code => $name) {
        $active = ($code == $current_lang) ? 'active' : '';
        $html .= '<a href="?lang=' . $code . '" class="lang-option ' . $active . '">';
        $html .= '<span class="lang-code">' . strtoupper($code) . '</span>';
        $html .= '<span class="lang-name">' . $name . '</span>';
        $html .= '</a>';
    }
    
    $html .= '</div></div>';
    
    // Add CSS and JS for the selector
    $html .= '
    <style>
    .language-selector {
        position: relative;
        display: inline-block;
    }
    .lang-btn {
        background: rgba(255,255,255,0.2);
        border: 1px solid rgba(255,255,255,0.3);
        color: white;
        padding: 8px 16px;
        border-radius: 30px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: all 0.3s ease;
    }
    .lang-btn:hover {
        background: rgba(255,255,255,0.3);
    }
    .lang-dropdown {
        position: absolute;
        top: 100%;
        right: 0;
        margin-top: 10px;
        background: white;
        border-radius: 12px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        min-width: 140px;
        display: none;
        z-index: 1000;
        overflow: hidden;
    }
    .lang-dropdown.show {
        display: block;
        animation: fadeInDown 0.3s ease;
    }
    .lang-option {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 16px;
        text-decoration: none;
        color: #333;
        transition: all 0.3s ease;
        border-bottom: 1px solid #f0f0f0;
    }
    .lang-option:last-child {
        border-bottom: none;
    }
    .lang-option:hover {
        background: #f5f5f5;
    }
    .lang-option.active {
        background: #3B9DB3;
        color: white;
    }
    .lang-code {
        font-weight: 700;
        font-size: 12px;
        width: 30px;
    }
    .lang-name {
        font-size: 14px;
    }
    @keyframes fadeInDown {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    </style>
    <script>
    function toggleLanguageMenu() {
        const dropdown = document.getElementById("langDropdown");
        dropdown.classList.toggle("show");
    }
    document.addEventListener("click", function(e) {
        if (!e.target.closest(".language-selector")) {
            const dropdown = document.getElementById("langDropdown");
            if (dropdown) dropdown.classList.remove("show");
        }
    });
    </script>
    ';
    
    return $html;
}
?>
<?php

namespace FactFinder\FfGptTools\pages;

use rex_addon;
use rex_view;
use rex_i18n;
use rex_csrf_token;
use rex_path;
use rex_be_controller;
use rex_url;

$addon_name = 'ff_gpt_tools';
$addon      = rex_addon::get($addon_name);
require_once rex_path::addon($addon_name, 'vendor/autoload.php');
require_once rex_path::base('vendor/autoload.php');
require_once rex_path::addon($addon_name, 'lib/GptTools.php');

// Check if the GptTools class is loaded correctly
if (!class_exists('GptTools')) {
    echo rex_view::error("Error: The GptTools class could not be loaded. Please check the file path and make sure the class is defined correctly.");
    die();
}

$apiKey = $addon->getConfig('apikey');
if (empty($apiKey)) {
    echo rex_view::error("Error: API key is missing. Please configure it <a href='" . rex_url::backendPage($addon_name . '/config') . "'>here</a>.");
    die();
}

$clangId        = filter_var(rex_be_controller::getCurrentPagePart(3), FILTER_SANITIZE_NUMBER_INT);
$prompt_default = 'Analyze the image and generate a barrier-free ALT description in $prompt_lang. The description should include the main subject(s), primary actions, and any distinguishing details that add context and significance. Ensure it\'s concise and suitable for screen readers. Limit the return to 125 characters or fewer. Here is the image:
$prompt_content
';
$content        = '';

$csrfToken = rex_csrf_token::factory('gpt-tools');
$generate  = rex_post('generate', 'bool');

if ($generate && !$csrfToken->isValid()) {
    $error = rex_i18n::msg('csrf_token_invalid');
} elseif ($generate) {
    $content .= '<strong>Generate Image Descriptions</strong></br>' . PHP_EOL;
}

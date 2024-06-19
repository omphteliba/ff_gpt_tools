<?php

namespace FactFinder\FfGptTools\pages;

use FactFinder\FfGptTools\lib\GptTools;
use rex_addon;
use rex_fragment;
use rex_logger;
use rex_view;
use rex_path;
use rex_exception;
use rex_be_controller;
use rex_url;


$addon_name = 'ff_gpt_tools';
$addon      = rex_addon::get($addon_name);
require_once rex_path::addon($addon_name, 'vendor/autoload.php');
require_once rex_path::base('vendor/autoload.php');
require_once rex_path::addon($addon_name, 'lib/GptTools.php');

$apiKey = $addon->getConfig('apikey');
if (empty($apiKey)) {
    echo rex_view::error("Error: API key is missing. Please configure it <a href='" . rex_url::backendPage($addon_name . '/config') . "'>here</a>.");
    die();
}

$clangId = filter_var(rex_be_controller::getCurrentPagePart(3), FILTER_SANITIZE_NUMBER_INT);

$content = '';

$buttons = [];

// add new button 'test'
$n                        = [];
$n['url']                 = rex_url::backendPage('ff_gpt_tools/api', ['func' => 'test']);
$n['label']               = 'Test';
$n['attributes']['class'] = array('btn-primary');

$buttons[] = $n;

$fragment = new rex_fragment();
$fragment->setVar('buttons', $buttons, false);
try {
    $content = $fragment->parse('core/buttons/button_group.php');
} catch (rex_exception $e) {
    rex_logger::logException($e);
}

$fragment = new rex_fragment();
$fragment->setVar('title', 'Tools', false);
$fragment->setVar('body', $content, false);
try {
    echo $fragment->parse('core/page/section.php');
} catch (rex_exception $e) {
    rex_logger::logException($e);
}

$content = '';

// add new func test
if (rex_get('func', 'string') === 'test') {
// Define the URL to load
//$url = "https://www.fact-finder.com/blog/factfinder-recognized-among-top-commerce-search-product-discovery-solutions-in-the-forrester-wave/";
    $url = 'https://www.fact-finder.de/blog/de/ecommerce-umfrage-55-der-online-shopper-suchen-heute-direkt-beim-haendler-nach-produkten/';

    $content .= '<strong>URL: </strong>' . $url . '</br>' . PHP_EOL;
    try {
        $gptTools = new GptTools($addon_name);
        $gptTools->setModelName('gpt-4');
        $content .= '<strong>Answer: </strong>' . $gptTools->getMetaDescription() . '</br>' . PHP_EOL;
        $content .= '<strong>Update worked? </strong>' . $gptTools->updateRedaxoMetaDescription('Das Leben ist ein Märchen',
                1, 1) . '</br>' . PHP_EOL;
    } catch (rex_exception $e) {
        rex_logger::logException($e);
        echo $e->getMessage();
    }
}

if (rex_get('func', 'string') === 'generate') {
    $content .= '<strong>Generate Meta Descriptions</strong></br>' . PHP_EOL;
    try {
        $gptTools = new GptTools($addon_name);
        $gptTools->setModelName('gpt-4');
        $content .= $gptTools->generateMetaDescriptions();
    } catch (rex_exception $e) {
        rex_logger::logException($e);
        echo $e->getMessage();
    }
}

if ($content) {
    $fragment = new rex_fragment();
    $fragment->setVar('title', 'api-Output', false);
    $fragment->setVar('body', $content, false);
    try {
        echo $fragment->parse('core/page/section.php');
    } catch (rex_exception $e) {
        rex_logger::logException($e);
    }
}

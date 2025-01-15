<?php

namespace FactFinder\FfGptTools\pages;

use FactFinder\FfGptTools\lib\GptTools;
use rex;
use rex_addon;
use rex_fragment;
use rex_logger;
use rex_view;
use rex_i18n;
use rex_path;
use rex_exception;
use rex_be_controller;
use rex_url;
use rex_sql;
use rex_csrf_token;
use rex_select;

$addon_name = 'ff_gpt_tools';
$addon      = rex_addon::get($addon_name);
require_once rex_path::addon($addon_name, 'vendor/autoload.php');
require_once rex_path::addon($addon_name, 'lib/GptTools.php');

$csrfToken = rex_csrf_token::factory('gpt-tools');

$apiKey = $addon->getConfig('apikey');
if (empty($apiKey)) {
    echo rex_view::error("Error: API key is missing. Please configure it <a href='" . rex_url::backendPage($addon_name . '/config') . "'>here</a>.");
    die();
}

$clangId = filter_var(rex_be_controller::getCurrentPagePart(3), FILTER_SANITIZE_NUMBER_INT);

if ($addon->getConfig('meta_prompt')) {
    $prompt_default = $addon->getConfig('organize_prompt');
} else {
    $prompt_default = 'Act as an SEO specialist with ten years of experience. You are summarizing a web article for an SEO-optimized meta-description. Language: $prompt_lang. Include relevant keywords and ensure the summary is concise, engaging, and accurately represents the article content. Limit your summary to 18 words or less.

Article content to summarize:
$prompt_content
';

}
$content = '';

// Get the count of uncategorized media items
$sql = rex_sql::factory();
$sql->setQuery('SELECT COUNT(*) as count FROM ' . rex::getTable('media') . ' WHERE category_id = 0');
$uncategorizedCount = $sql->getValue('count');


$content = '';

$buttons = [];

// add new button 'test'
$n                        = [];
$n['url']                 = rex_url::backendPage('ff_gpt_tools/api', ['func' => 'organize_mediapool']);
$n['label']               = 'Organize Mediapool (' . $uncategorizedCount . ' uncategorized)'; // Add count to button label
$n['attributes']['class'] = ['btn-primary'];
$buttons[]                = $n;


$fragment = new rex_fragment();
$fragment->setVar('buttons', $buttons, false);
$content = $fragment->parse('core/buttons/button_group.php');

$fragment = new rex_fragment();
$fragment->setVar('title', rex_i18n::msg('ff_gpt_tools_organize_mediapool'), false);
$fragment->setVar('body', $content, false);
echo $fragment->parse('core/page/section.php');

$content = '';


if (rex_get('func', 'string') === 'organize_mediapool') {
    $content .= '<strong>' . rex_i18n::msg('ff_gpt_tools_organize_mediapool') . '</strong><br>' . PHP_EOL;
    try {
        $gptTools = new GptTools($addon_name);
        $gptTools->setModelName('o1-preview'); // or whichever model you want to use
        $gptTools->organizeMediaItems();
        $content .= "Mediapool organization complete.";

        // Refresh the count after organizing
        $sql->setQuery('SELECT COUNT(*) as count FROM ' . rex::getTable('media') . ' WHERE category_id = 0');
        $uncategorizedCount = $sql->getValue('count');
        $content            .= "<br>Remaining uncategorized items: " . $uncategorizedCount;


    } catch (rex_exception $e) {
        rex_logger::logException($e);
        $content .= $e->getMessage();
    }
}


if ($content) {
    $fragment = new rex_fragment();
    $fragment->setVar('title', rex_i18n::msg('ff_gpt_tools_organize_mediapool'), false);
    $fragment->setVar('body', $content, false);
    echo $fragment->parse('core/page/section.php');
}

// Info-Box
$content = '';

$fragment = new rex_fragment();
$fragment->setVar('class', 'info', false);
$fragment->setVar('title', rex_i18n::msg('ff_gpt_tools_organize_mediapool_information'), false);
$fragment->setVar('body', '<p>' . rex_i18n::msg('ff_gpt_tools_organize_mediapool_intro') . '</p>', false);
echo $fragment->parse('core/page/section.php');

$content .= '<fieldset>';

$content .= '<legend>' . rex_i18n::msg('ff_gpt_tools_organize_mediapool_config_legend') . '</legend>';

$formElements = [];

$n              = [];
$n['label']     = '<label for="rex-form-prompt">' . rex_i18n::msg('ff_gpt_tools_organize_mediapool_prompt') . '</label>';
$n['field']     = '<textarea class="form-control" rows="6" id="rex-form-prompt" name="prompt">' . $prompt_default . '</textarea>';
$formElements[] = $n;

$n              = [];
$n['label']     = '';
$n['field']     = '<p>' . rex_i18n::msg('ff_gpt_tools_organize_mediapool_prompt_note') . '</p>';
$formElements[] = $n;

$tableSelect = new rex_select();
$tableSelect->setId('rex-form-model');
$tableSelect->setName('model');
$tableSelect->setAttribute('class', 'form-control');
$gptTools = new \FactFinder\FfGptTools\lib\GptTools($addon_name);
// Fetch all available models
$availableModels = $gptTools->getAllAvailableModels();
foreach ($availableModels as $model) {
    $tableSelect->addOption($model, $model);
    if ($model === 'gpt-4') {
        $tableSelect->setSelected($model);
    }
}

$n              = [];
$n['label']     = '<label for="rex-form-exporttables">' . rex_i18n::msg('ff_gpt_tools_organize_mediapool_model_name') . '</label>';
$n['field']     = $tableSelect->get();
$formElements[] = $n;

$n              = [];
$n['label']     = '<label for="rex-form-temp">' . rex_i18n::msg('ff_gpt_tools_organize_mediapool_temperature') . '</label>';
$n['field']     = '<input class="form-control" type="text" id="rex-form-temp" name="temp" value="0.7" />';
$formElements[] = $n;

$n              = [];
$n['label']     = '';
$n['field']     = '<p>' . rex_i18n::msg('ff_gpt_tools_organize_mediapool_temperature_note') . '</p>';
$formElements[] = $n;

$n              = [];
$n['label']     = '<label for="rex-form-temp">' . rex_i18n::msg('ff_gpt_tools_organize_mediapool_max_tokens') . '</label>';
$n['field']     = '<input class="form-control" type="text" id="rex-form-temp" name="max_tokens" value="50" />';
$formElements[] = $n;

$n              = [];
$n['label']     = '';
$n['field']     = '<p>' . rex_i18n::msg('ff_gpt_tools_organize_mediapool_max_tokens_note') . '</p>';
$formElements[] = $n;

$fragment = new rex_fragment();
$fragment->setVar('elements', $formElements, false);
$content .= $fragment->parse('core/form/form.php');

$content .= '</fieldset>';

$formElements   = [];
$n              = [];
$n['field']     = '<button class="btn btn-save rex-form-aligned" type="submit" name="organize_mediapool" value="' . rex_i18n::msg('ff_gpt_tools_backup_db_export') . '">' . rex_i18n::msg('ff_gpt_tools_organize_mediapool_submit_button') . '</button>';
$formElements[] = $n;

$fragment = new rex_fragment();
$fragment->setVar('elements', $formElements, false);
$buttons = $fragment->parse('core/form/submit.php');

$fragment = new rex_fragment();
$fragment->setVar('class', 'edit', false);
$fragment->setVar('title', rex_i18n::msg('ff_gpt_tools_organize_mediapool'), false);
$fragment->setVar('body', $content, false);
$fragment->setVar('buttons', $buttons, false);
$content = $fragment->parse('core/page/section.php');

$content = '
<form action="' . rex_url::currentBackendPage() . '" data-pjax="false" method="post">
    ' . $csrfToken->getHiddenField() . '
    ' . $content . '
</form>';

echo $content;

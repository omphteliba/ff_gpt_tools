<?php

/*
 * This is the main file for the ff_gpt_tools addon.
 * It handles the generation of ALT descriptions for images.
 * It uses the GPT-4 model from OpenAI to generate the descriptions.
 */

namespace FactFinder\FfGptTools\pages;

use FactFinder\FfGptTools\lib\GptTools;
use rex;
use rex_addon;
use rex_article;
use rex_fragment;
use rex_logger;
use rex_view;
use rex_i18n;
use rex_path;
use rex_be_controller;
use rex_url;
use rex_sql;
use rex_sql_exception;
use rex_csrf_token;
use rex_clang;
use rex_select;
use rex_var_media;
use rex_var_medialist;
use rex_media;

$addon_name = 'ff_gpt_tools';
$addon      = rex_addon::get($addon_name);
require_once rex_path::addon($addon_name, 'vendor/autoload.php');
require_once rex_path::addon($addon_name, 'lib/GptTools.php');

$valid_fileextensions = ['jpg', 'jpeg', 'png', 'webp', 'svg'];
$valid_filetypes      = ['image/webp', 'image/svg+xml', 'image/jpeg', 'image/png'];

$table_name = rex::getTable('ff_gpt_tools_tasks');

$apiKey = $addon->getConfig('apikey');
if (empty($apiKey)) {
    echo rex_view::error("Error: API key is missing. Please configure it <a href='" . rex_url::backendPage($addon_name . '/config') . "'>here</a>.");
    die();
}

// Check if Image Meta description field is set and exists in the table
$image_descriptionfield = $addon->getConfig('image_descriptionfield');
if (empty($image_descriptionfield)) {
    echo rex_view::error("Error: Image Meta description field is missing.");
    die();
}

try {
    if (!GptTools::checkImageDescriptionField($image_descriptionfield)) {
        die();
    }
} catch (\rex_sql_exception $e) {
    \rex_logger::logException($e);
}

$clangId = filter_var(rex_be_controller::getCurrentPagePart(3), FILTER_SANITIZE_NUMBER_INT);
//$prompt_default = 'Act as an SEO specialist with ten years of experience. Please summarize this article in $prompt_lang in 18 words or less for the image-description of a website: $prompt_content';

if ($addon->getConfig('image_prompt') !== '') {
    $prompt_default = $addon->getConfig('image_prompt');
} else {
    $prompt_default = 'Act as an SEO specialist with ten years of experience in accessibility. Generate a descriptive ALT tag for the provided image. Ensure the description is clear, concise, and accurately represents the content and context of the image. Include relevant keywords without compromising the clarity needed for accessibility. Language: $prompt_lang. Limit the description to 18 words or less.';
}
$content = '';

$csrfToken = rex_csrf_token::factory('gpt-tools');
$generate  = rex_post('generate', 'bool');

if ($generate && !$csrfToken->isValid()) {
    $error = rex_i18n::msg('ff_gpt_tools_csrf_token_invalid');
} elseif ($generate) {
    $content   .= '<strong>' . rex_i18n::msg('ff_gpt_tools_generate_image_descriptions') . '</strong></br>' . PHP_EOL;
    $result    = [];
    $counter   = 0;
    $languages = rex_post('language', 'array');
    $func      = rex_post('func', 'int');

    // switch for variable func
    switch ($func) {
        case 0: // all pages
            $content  .= rex_i18n::msg('ff_gpt_tools_generate_image_descriptions_pages_select_all') . '</br>' . PHP_EOL;
            $articles = rex_sql::factory();
            $articles->setDebug(false);
            $description_field = $addon->getConfig('descriptionfield');
            $query             = 'SELECT id, filename FROM ' . rex::getTable('media') . ' WHERE filetype IN ("' . implode('", "',
                    $valid_filetypes) . '");';

            try {
                $articles->setQuery($query);
            } catch (rex_sql_exception $e) {
                rex_logger::logException($e);
            }
            $counter = 0;

            foreach ($languages as $language) {
                foreach ($articles as $article) {
                    //$content  .= 'Article ID: ' . $article->getValue('id') . ' Clang ID: ' . $article->getValue('clang_id') . '<br>' . PHP_EOL;
                    $result[] = array(
                        'image_id'  => $article->getValue('id'),
                        'image_url' => rex_url::media($article->getValue('filename')),
                        'clang'     => $language,
                        'prompt'    => rex_post('prompt', 'string'),
                        'model'     => rex_post('model', 'string'),
                        'temp'      => rex_post('temp', 'float'),
                        'max_token' => rex_post('max_tokens', 'int'),
                    );
                    $counter++;
                }
            }
            $content .= 'Count: ' . $counter . '</br>' . PHP_EOL;
            break;
        case 1: // empty image description
            $content  .= rex_i18n::msg('ff_gpt_tools_generate_image_descriptions_pages_select_empty') . '</br>' . PHP_EOL;
            $articles = rex_sql::factory();
            $articles->setDebug(false);
            $description_field = $articles->escapeIdentifier($addon->getConfig('image_descriptionfield'));
            $query             = 'SELECT id, filename FROM ' . rex::getTable('media') . ' WHERE ' . $description_field . ' = "" AND filetype IN ("' . implode('", "',
                    $valid_filetypes) . '")';

            try {
                $articles->setQuery($query);
            } catch (rex_sql_exception $e) {
                rex_logger::logException($e);
            }
            foreach ($languages as $language) {
                foreach ($articles as $article) {
                    //$content  .= 'Article ID: ' . $article->getValue('id') . ' Clang ID: ' . $article->getValue('clang_id') . '<br>' . PHP_EOL;
                    $result[] = array(
                        'image_url' => rex_url::media($article->getValue('filename')),
                        'clang'     => $language,
                        'prompt'    => rex_post('prompt', 'string'),
                        'model'     => rex_post('model', 'string'),
                        'temp'      => rex_post('temp', 'float'),
                        'max_token' => rex_post('max_tokens', 'int'),
                    );
                    $counter++;
                }
            }
            break;
        case 2: // one image
            $content .= rex_i18n::msg('ff_gpt_tools_generate_image_descriptions_pages_select_one') . '</br>' . PHP_EOL;
            foreach ($languages as $language) {
                $result[] = array(
                    'clang'     => $language,
                    'image_url' => rex_url::media(rex_post('single_image', 'string')),
                    'prompt'    => rex_post('prompt', 'string'),
                    'model'     => rex_post('model', 'string'),
                    'temp'      => rex_post('temp', 'float'),
                    'max_token' => rex_post('max_tokens', 'int'),
                );
                $counter++;
            }
            break;
        case 3: // list of images
            $content      .= rex_i18n::msg('ff_gpt_tools_generate_image_descriptions_pages_select_list') . '</br>' . PHP_EOL;
            $article_list = explode(",", rex_post('image_list', 'string'));

            foreach ($languages as $language) {
                foreach ($article_list as $article_id) {
                    $media    = \rex_media::get($article_id);
                    $result[] = array(
                        'image_id'  => $article_id,
                        'clang'     => $language,
                        'image_url' => rex_url::media($media->getFileName()),
                        'prompt'    => rex_post('prompt', 'string'),
                        'model'     => rex_post('model', 'string'),
                        'temp'      => rex_post('temp', 'float'),
                        'max_token' => rex_post('max_tokens', 'int'),
                    );
                    $counter++;
                }
            }
            break;
        case 4: // category
            $content .= rex_i18n::msg('ff_gpt_tools_generate_image_descriptions_pages_select_cat') . '</br>' . PHP_EOL;
            break;
        default: // default
            $content .= 'func = default</br>' . PHP_EOL;
    }

    if ($result !== []) {
        $content .= 'Count: ' . $counter . '</br>' . PHP_EOL;
        $tasks   = rex_sql::factory();
        $tasks->setDebug(false);

        foreach ($result as $task) {
            $tasks->setTable('rex_ff_gpt_tools_tasks');
            foreach ($task as $key => $value) {
                $tasks->setValue($key, $value);
            }
            $tasks->setValue('date', date('Y-m-d H:i:s'));
            try {
                $tasks->insert();
            } catch (rex_sql_exception $e) {
                rex_logger::logException($e);
            }
        }
    }
}

if ($content) {
    $fragment = new rex_fragment();
    $fragment->setVar('title', 'api-Output', false);
    $fragment->setVar('body', $content, false);
    echo $fragment->parse('core/page/section.php');
}


// function to trigger the cronjob by calling lib/FFGptToolsCronjob.php
if (rex_get('func') === 'cronjob') {
//    $cronjob = new \FactFinder\FfGptTools\lib\FfGptToolsCronjob();
//    $cronjob->execute();
    $gpttool               = new GptTools('ff_gpt_tools');
    $processedImageEntries = $gpttool->processImageEntries();
}

// copy function
if (rex_get('func') === 'copy') {
    $id  = rex_get('id', 'int');
    $sql = rex_sql::factory();
    $sql->setDebug(false);
    $sql->setTable($table_name);
    $sql->setWhere('id = :id', ['id' => $id]);
    $sql->select();
    $mediaFolderPath = rex_url::media(); // Get the path to the media folder
    // rex_logger::logError(1, $mediaFolderPath, __FILE__, __LINE__);
    $filename            = $sql->getValue('image_url'); // Get the filename with the media folder path
    $filenameWithoutPath = str_replace($mediaFolderPath, '',
        $filename); // Remove the media folder path from the filename
    // rex_logger::logError(1, $filenameWithoutPath, __FILE__, __LINE__);
    $meta_description  = $sql->getValue('meta_description');
    $description_field = $addon->getConfig('image_descriptionfield');
    $sql->setTable(rex::getTable('media'));
    $sql->setValue($description_field, $meta_description);
    $sql->setWhere('filename = :filename', ['filename' => $filenameWithoutPath]);
    $sql->update();
    $sql->setTable($table_name);
    $sql->setWhere('id = :id', ['id' => $id]);
    $sql->setValue('done', 1);
    $sql->update();
}

// Warteschlangen löschen
if (rex_get('func') === 'delete') {
    $sql = rex_sql::factory();
    $sql->setDebug(false);
    $sql->setQuery('DELETE FROM ' . $table_name . ' WHERE article_id = ""');
}

// Warteschlangen Infos

$table_name = rex::getTable('ff_gpt_tools_tasks');

// fetch the fields id, done, article_id, date, image_description, clang, prompt and error_flag from database $table_name and show it as a html table
$sql = rex_sql::factory();
$sql->setDebug(false);
$sql->setQuery('SELECT id, done, image_url, date, meta_description, clang, prompt, model, error_text FROM ' . $table_name . ' WHERE article_id IS NULL OR article_id = "" ORDER BY date DESC');
if ($sql->getRows() > 0) {
    $content = '';
    $content .= '<table class="table table-striped">';
    $content .= '<thead>';
    $content .= '<tr>';
    $content .= '<th scope="col">' . rex_i18n::msg('ff_gpt_tools_done') . '</th>';
    $content .= '<th scope="col">' . rex_i18n::msg('ff_gpt_tools_image') . '</th>';
    $content .= '<th scope="col">' . rex_i18n::msg('ff_gpt_tools_date') . '</th>';
    $content .= '<th scope="col">' . rex_i18n::msg('ff_gpt_tools_image_description') . '</th>';
    $content .= '<th scope="col">' . rex_i18n::msg('ff_gpt_tools_language') . '</th>';
    $content .= '<th scope="col">' . rex_i18n::msg('ff_gpt_tools_prompt') . '</th>';
    $content .= '<th scope="col">' . rex_i18n::msg('ff_gpt_tools_model') . '</th>';
    $content .= '<th scope="col">' . rex_i18n::msg('ff_gpt_tools_error') . '</th>';
    $content .= '<th scope="col">' . rex_i18n::msg('ff_gpt_tools_functions') . '</th>';
    $content .= '</tr>';
    $content .= '</thead>';
    $content .= '<tbody>';

    foreach ($sql as $row) {
        $mediaFolderPath     = rex_url::media(); // Get the path to the media folder
        $filename            = $sql->getValue('image_url'); // Get the filename with the media folder path
        $filenameWithoutPath = str_replace($mediaFolderPath, '',
            $filename); // Remove the media folder path from the filename
        // ToDO: Abfangen wenn das Ergebnis leer ist. Dann muss ich auch den rest nicht ausgeben, denke ich. Bin mir aber nicht sicher, weil schon so spät und heiss.
        $mediaData = GptTools::getMediaDataByFilename($filenameWithoutPath);
        $content   .= '<tr>';
        $content   .= '<td>' . ($row->getValue('done') === 1 ? rex_i18n::msg("yes") : rex_i18n::msg("no")) . '</td>';
        // Fetch the rex_media object
        $media = rex_media::get($filenameWithoutPath);

        if ($media) { // Check if media object was found
            $fileInfo      = pathinfo($media->getFileName());
            $fileExtension = strtolower($fileInfo['extension']);

            if (in_array($fileExtension, $valid_fileextensions)) {
                $content .= '<td><a href="#" onclick="openMediaDetails(\'\', \'' . $mediaData['file_id'] . '\', \'' . $mediaData['category_id'] . '\');return false;"><img src="' . $media->getUrl() . '" width="100px" alt="Image"></a></td>';
            } else {
                $content .= '<td><a href="#" onclick="openMediaDetails(\'\', \'' . $mediaData['file_id'] . '\', \'' . $mediaData['category_id'] . '\');return false;">' . $media->getFileName() . '</a></td>';
            }
        } else {
            // Handle case where media object was not found (e.g., invalid file_id)
            $content .= '<td>-</td>'; // Or display an error message
        }
        $content .= '<td>' . $row->getValue('date') . '</td>';
        $content .= '<td>' . $row->getValue('meta_description') . '</td>';
        $content .= '<td>' . rex_clang::get($row->getValue('clang'))->getName() . '</td>';
        $content .= '<td>' . $row->getValue('prompt') . '</td>';
        $content .= '<td>' . $row->getValue('model') . '</td>';
        $content .= '<td>' . $row->getValue('error_text') . '</td>';
        // show the copy button only when image_description isn't empty
        if ($row->getValue('meta_description') !== '') {
            $content .= '<td><a href="' . rex_url::currentBackendPage([
                    'func' => 'copy',
                    'id'   => $row->getValue('id'),
                ]) . '">' . rex_i18n::msg('ff_gpt_tools_copy') . '</a></td>';
        } else {
            $content .= '<td></td>';
        }
        $content .= '</tr>';
    }
    $content .= '</tbody>';
    $content .= '</table>';

    $fragment = new rex_fragment();
    $fragment->setVar('title', rex_i18n::msg('ff_gpt_tools_tasks'), false);
    $fragment->setVar('body', $content, false);
    echo $fragment->parse('core/page/section.php');

    $content = '';
    $buttons = [];

    $n                        = [];
    $n['url']                 = rex_url::backendPage('ff_gpt_tools/image_description', ['func' => 'delete']);
    $n['label']               = rex_i18n::msg('ff_gpt_tools_delete');
    $n['attributes']['class'] = array('btn-primary');

    $buttons[] = $n;

    $n                        = [];
    $n['url']                 = rex_url::backendPage('ff_gpt_tools/image_description', ['func' => 'cronjob']);
    $n['label']               = rex_i18n::msg('ff_gpt_tools_run_tasks');
    $n['attributes']['class'] = array('btn-primary');

    $buttons[] = $n;

    $fragment = new rex_fragment();
    $fragment->setVar('buttons', $buttons, false);
    $content = $fragment->parse('core/buttons/button_group.php');

    $fragment = new rex_fragment();
    $fragment->setVar('title', 'Tools', false);
    $fragment->setVar('body', $content, false);
    echo $fragment->parse('core/page/section.php');
}

// Info-Box
$content = '';

$fragment = new rex_fragment();
$fragment->setVar('class', 'info', false);
$fragment->setVar('title', rex_i18n::msg('ff_gpt_tools_generate_image_descriptions_information'), false);
$fragment->setVar('body', '<p>' . rex_i18n::msg('ff_gpt_tools_generate_image_descriptions_intro') . '</p>', false);
echo $fragment->parse('core/page/section.php');

$content .= '<fieldset>';

$content .= '<legend>' . rex_i18n::msg('ff_gpt_tools_generate_image_descriptions_config_legend') . '</legend>';

$formElements = [];

$n              = [];
$n['label']     = '<label for="rex-form-prompt">' . rex_i18n::msg('ff_gpt_tools_generate_image_descriptions_prompt') . '</label>';
$n['field']     = '<textarea class="form-control" rows="6" id="rex-form-prompt" name="prompt">' . $prompt_default . '</textarea>';
$formElements[] = $n;

$n              = [];
$n['label']     = '';
$n['field']     = '<p>' . rex_i18n::msg('ff_gpt_tools_generate_image_descriptions_prompt_note') . '</p>';
$formElements[] = $n;

$tableSelect = new rex_select();
$tableSelect->setId('rex-form-model');
$tableSelect->setName('model');
$tableSelect->setAttribute('class', 'form-control');
$gptTools = new GptTools($addon_name);
// Fetch all available models
$availableModels = $gptTools->getAllAvailableModels();
foreach ($availableModels as $model) {
    $tableSelect->addOption($model, $model);
    if ($model === 'gpt-4o-mini') {
        $tableSelect->setSelected($model);
    }
}

$n              = [];
$n['label']     = '<label for="rex-form-exporttables">' . rex_i18n::msg('ff_gpt_tools_generate_image_descriptions_model_name') . '</label>';
$n['field']     = $tableSelect->get();
$formElements[] = $n;

$n              = [];
$n['label']     = '<label for="rex-form-temp">' . rex_i18n::msg('ff_gpt_tools_generate_image_descriptions_temperature') . '</label>';
$n['field']     = '<input class="form-control" type="text" id="rex-form-temp" name="temp" value="0.7" />';
$formElements[] = $n;

$n              = [];
$n['label']     = '';
$n['field']     = '<p>' . rex_i18n::msg('ff_gpt_tools_generate_image_descriptions_temperature_note') . '</p>';
$formElements[] = $n;

$n              = [];
$n['label']     = '<label for="rex-form-temp">' . rex_i18n::msg('ff_gpt_tools_generate_image_descriptions_max_tokens') . '</label>';
$n['field']     = '<input class="form-control" type="text" id="rex-form-temp" name="max_tokens" value="50" />';
$formElements[] = $n;

$n              = [];
$n['label']     = '';
$n['field']     = '<p>' . rex_i18n::msg('ff_gpt_tools_generate_image_descriptions_max_tokens_note') . '</p>';
$formElements[] = $n;

$fragment = new rex_fragment();
$fragment->setVar('elements', $formElements, false);
$content .= $fragment->parse('core/form/form.php');

$content .= '</fieldset>';

// Formular für die Auswahl der Kategorien und Artikel
$content .= '<fieldset>';

$content .= '<legend>' . rex_i18n::msg('ff_gpt_tools_generate_image_descriptions_pages_legend') . '</legend>';

$formElements   = [];
$n              = [];
$n['label']     = '';
$n['field']     = '<p>' . rex_i18n::msg('ff_gpt_tools_generate_image_descriptions_pages_select_note') . '</p>';
$formElements[] = $n;

$fragment = new rex_fragment();
$fragment->setVar('elements', $formElements, false);
$content .= $fragment->parse('core/form/form.php');

$formElements = [];

$langSelect = new rex_select();
$langSelect->setId('rex-form-model');
$langSelect->setName('language[]');
$langSelect->setSize(2);
$langSelect->setMultiple(true);
$langSelect->setAttribute('class', 'form-control');
// fetch all languages
$languages = rex_clang::getAll();

foreach ($languages as $language) {
    $langSelect->addOption($language->getName(), $language->getId());
    if ($language->getId() === rex_clang::getCurrentId()) {
        $langSelect->setSelected($language->getId());
    }
}

$n              = [];
$n['label']     = '<label for="rex-form-exporttables">' . rex_i18n::msg('ff_gpt_tools_generate_image_descriptions_languages') . '</label>';
$n['field']     = $langSelect->get();
$formElements[] = $n;

$fragment = new rex_fragment();
$fragment->setVar('elements', $formElements, false);
$content .= $fragment->parse('core/form/form.php');

$formElements = [];

$n              = [];
$n['label']     = '<label for="rex-form-select_all">' . rex_i18n::msg('ff_gpt_tools_generate_image_descriptions_pages_select_all') . '</label>';
$n['field']     = '<input type="radio" id="rex-form-select_all" name="func" value="0" />';
$formElements[] = $n;

$n              = [];
$n['label']     = '<label for="rex-form-select_empty">' . rex_i18n::msg('ff_gpt_tools_generate_image_descriptions_pages_select_empty') . '</label>';
$n['field']     = '<input type="radio" id="rex-form-select_empty" name="func" value="1" />';
$formElements[] = $n;

$n              = [];
$n['label']     = '<label for="rex-form-pages_select_one">' . rex_i18n::msg('ff_gpt_tools_generate_image_descriptions_pages_select_one') . '</label>';
$n['field']     = '<input type="radio" id="rex-form-pages_select_one" name="func" value="2" checked="checked" />';
$formElements[] = $n;

$n              = [];
$n['label']     = '<label for="rex-form-pages_select_list">' . rex_i18n::msg('ff_gpt_tools_generate_image_descriptions_pages_select_list') . '</label>';
$n['field']     = '<input type="radio" id="rex-form-pages_select_list" name="func" value="3" />';
$formElements[] = $n;

/*$n              = [];
$n['label']     = '<label for="rex-form-pages_select_cat">' . rex_i18n::msg('ff_gpt_tools_generate_image_descriptions_pages_select_cat') . '</label>';
$n['field']     = '<input type="radio" id="rex-form-pages_select_cat" name="func" value="4" />';
$formElements[] = $n;*/

$fragment = new rex_fragment();
$fragment->setVar('elements', $formElements, false);
$radios = $fragment->parse('core/form/radio.php');

$formElements   = [];
$n              = [];
$n['label']     = 'Options';
$n['field']     = $radios;
$formElements[] = $n;

$fragment = new rex_fragment();
$fragment->setVar('elements', $formElements, false);
$content .= $fragment->parse('core/form/form.php');

// Bestimmte Kategorien und Artikel rekursiv löschen
$formElements   = [];
$n              = [];
$n['label']     = '<label for="REX_LINK_1_NAME">' . rex_i18n::msg('ff_gpt_tools_generate_image_descriptions_pages_article') . '</label>';
$n['field']     = rex_var_media::getWidget(1, 'single_image', '', ['types' => implode(',', $valid_fileextensions)]);
$formElements[] = $n;

$n              = [];
$n['label']     = '<label for="REX_LINK_2_NAME">' . rex_i18n::msg('ff_gpt_tools_generate_image_descriptions_pages_articlelist') . '</label>';
$n['field']     = rex_var_medialist::getWidget(2, 'image_list', '', ['types' => implode(',', $valid_fileextensions)]);
$formElements[] = $n;

$fragment = new rex_fragment();
$fragment->setVar('elements', $formElements, false);
$content .= $fragment->parse('core/form/container.php');

$content .= '</fieldset>';

$formElements   = [];
$n              = [];
$n['field']     = '<button class="btn btn-save rex-form-aligned" type="submit" name="generate" value="' . rex_i18n::msg('ff_gpt_tools_backup_db_export') . '">' . rex_i18n::msg('ff_gpt_tools_generate_image_descriptions_submit_button') . '</button>';
$formElements[] = $n;

$fragment = new rex_fragment();
$fragment->setVar('elements', $formElements, false);
$buttons = $fragment->parse('core/form/submit.php');

$fragment = new rex_fragment();
$fragment->setVar('class', 'edit', false);
$fragment->setVar('title', rex_i18n::msg('ff_gpt_tools_generate_image_descriptions'), false);
$fragment->setVar('body', $content, false);
$fragment->setVar('buttons', $buttons, false);
$content = $fragment->parse('core/page/section.php');

$content = '
<form id="gpt_tools_image_description" action="' . rex_url::currentBackendPage() . '" data-pjax="false" method="post">
    ' . $csrfToken->getHiddenField() . '
    ' . $content . '
</form>';

echo $content;
echo '<script src="' . rex_url::addonAssets('ff_gpt_tools') . 'be_meta_generate.js' . '"></script>';

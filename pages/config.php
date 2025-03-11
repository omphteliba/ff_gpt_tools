<?php

/*
Demo für AddOn-Einstellungen die in der Tabelle `rex_config` gespeichert werden.
Hier mit Verwendung der Klasse `rex_config_form`. Die Einstellungen werden automatisch
beim absenden des Formulars gespeichert.

Die beiden Dateien `config.rex_config_form.php` und `config.classic_form.php`
speichern die gleichen AddOn-Einstellungen.
Anhand der identischen Kommentare können die beiden Dateien "verglichen" werden.

https://redaxo.org/doku/master/konfiguration_form
*/

namespace FactFinder\FfGptTools\pages;

use FactFinder\FfGptTools\lib\GptTools;
use rex_addon;
use rex_config_form;
use rex_view;
use rex_i18n;
use rex_fragment;
use rex_sql;
use REX;

$addon = rex_addon::get('ff_gpt_tools');

$apiKey = $addon->getConfig('apikey');
if (empty($apiKey)) {
    echo rex_view::error("Error: API key is missing.");
}

// Check if Image Meta description field is set and exists in the table
$image_descriptionfield = $addon->getConfig('image_descriptionfield');
if (empty($image_descriptionfield)) {
    echo rex_view::error("Error: Image Meta description field is missing.");
}

try {
    GptTools::checkImageDescriptionField($image_descriptionfield);
} catch (\rex_sql_exception $e) {
    \rex_logger::logException($e);
}

$content = '';

if (rex_post('config-submit', 'boolean')) {
    $addon->setConfig(rex_post('config', [
        ['apikey', 'string'],
    ]));

    $content .= rex_view::info(rex_i18n::msg('ff_gpt_tools_config_saved'));
}

// Instanzieren des Formulars
$form = rex_config_form::factory('ff_gpt_tools');

// Fieldset 1
$form->addFieldset(rex_i18n::msg('ff_gpt_tools_config_legend1'));

// 1.1 Einfaches Textfeld
$field = $form->addInputField('password', 'apikey', $addon->getConfig('apikey'), ['class' => 'form-control']);
$field->setLabel(rex_i18n::msg('ff_gpt_tools_apikey'));

// 1.1 Einfaches Textfeld
$field = $form->addInputField('text', 'descriptionfield', $addon->getConfig('descriptionfield'),
    ['class' => 'form-control']);
$field->setLabel(rex_i18n::msg('ff_gpt_tools_descriptionfield'));

$field = $form->addInputField('text', 'image_descriptionfield', $addon->getConfig('image_descriptionfield'),
    ['class' => 'form-control']);
$field->setLabel(rex_i18n::msg('ff_gpt_tools_image_descriptionfield'));

$field = $form->addInputField('text', 'tasks_per_call', $addon->getConfig('tasks_per_call'),
    ['class' => 'form-control']);
$field->setLabel(rex_i18n::msg('ff_gpt_tools_tasks_per_call'));

$field = $form->addTextAreaField('meta_prompt', $addon->getConfig('meta_prompt'),
    ['class' => 'form-control']);
$field->setLabel(rex_i18n::msg('ff_gpt_tools_meta_prompt'));

$field = $form->addTextAreaField('image_prompt', $addon->getConfig('image_prompt'),
    ['class' => 'form-control']);
$field->setLabel(rex_i18n::msg('ff_gpt_tools_image_prompt'));

$field = $form->addTextAreaField('organize_prompt', $addon->getConfig('organize_prompt'),
    ['class' => 'form-control']);
$field->setLabel(rex_i18n::msg('ff_gpt_tools_organize_prompt'));

// Ausgabe des Formulars
$fragment = new rex_fragment();
$fragment->setVar('class', 'edit', false);
$fragment->setVar('title', rex_i18n::msg('ff_gpt_tools_config_title_rex_config'), false);
$fragment->setVar('body', $form->get(), false);
echo $fragment->parse('core/page/section.php');

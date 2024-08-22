<?php

use FactFinder\FfGptTools\lib\FfGptToolsCronjob;

// Check if the cronjob addon is available
if (rex_addon::get('cronjob')->isAvailable()) {

    // Check if the cronjob already exists
    $sql = rex_sql::factory();
    $sql->setQuery('SELECT * FROM ' . rex::getTable('cronjob') . ' WHERE name = ?', ['ff_gpt_tools_cronjob']);

    if ($sql->getRows() === 0) { // Cronjob doesn't exist, so add it
        $sql->reset();
        $sql->setTable(rex::getTable('cronjob'));
        $sql->setValue('name', 'ff_gpt_tools_cronjob');
        $sql->setValue('type', FfGptToolsCronjob::class);
        $sql->setValue('interval', '{"minutes":"all","hours":"all,"days":"all","weekdays":"all","months":"all"}');
        $sql->setValue('environment', '|script|');
        $sql->setValue('execution_moment', 1); // 0 = before, 1 = after
        $sql->setValue('status', 1); // 1 = active, 0 = inactive

        // Set any parameters your cronjob class might require
        $params = [
            // Your parameters go here
        ];
        $sql->setValue('parameters', json_encode($params));

        // Insert the cronjob
        $sql->insert();

        // Check SQL error
        if ($sql->hasError()) {
            throw new rex_sql_exception('Failed to create cronjob: ' . $sql->getError());
        }
    }
} else {
// Error message if cronjob addon is not available
    throw new rex_functional_exception('Cronjob addon is not available!');
}

rex_sql_table::get(rex::getTable('ff_gpt_tools_tasks'))
    ->ensurePrimaryIdColumn()
    ->ensureColumn(new rex_sql_column('article_id', 'varchar(191)', false, ''))
    ->ensureColumn(new rex_sql_column('image_id', 'varchar(191)'))
    ->ensureColumn(new rex_sql_column('done', 'tinyint(1)', false, '0'))
    ->ensureColumn(new rex_sql_column('date', 'datetime'))
    ->ensureColumn(new rex_sql_column('meta_description', 'varchar(191)', false, ''))
    ->ensureColumn(new rex_sql_column('image_url', 'varchar(191)'))
    ->ensureColumn(new rex_sql_column('clang', 'varchar(191)', false, '1'))
    ->ensureColumn(new rex_sql_column('prompt', 'text'))
    ->ensureColumn(new rex_sql_column('model', 'varchar(191)', false, ''))
    ->ensureColumn(new rex_sql_column('temp', 'varchar(191)', false, ''))
    ->ensureColumn(new rex_sql_column('max_token', 'varchar(191)', false, ''))
    ->ensureColumn(new rex_sql_column('error_flag', 'int(11)', true))
    ->ensureColumn(new rex_sql_column('error_text', 'text', true))
    ->ensureColumn(new rex_sql_column('result', 'text'))
    ->ensure();

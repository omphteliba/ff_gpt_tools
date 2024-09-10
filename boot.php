<?php

if (rex_addon::get('cronjob')->isAvailable()) {
    rex_cronjob_manager::registerType(\FactFinder\FfGptTools\lib\FfGptToolsCronjob::class);
}

rex_extension::register('SLICE_SHOW', static function (rex_extension_point $ep) {
    $content = $ep->getSubject();

    // Use regular expressions to find img tags and add/fix alt attributes
    $content = preg_replace_callback(
        '/<img\s*(?![^>]*\balt\s*=[^>]*"?[^>]*>)([^>]*)>/i', // Matches img tags without alt, with empty alt, or alt without content
        static function ($matches) {
            $imageSrc = null;

            // Extract image source from src attribute
            if (preg_match('/src="([^"]*)"/i', $matches[0], $srcMatches)) {
                $imageSrc = $srcMatches[1];
            }

            $altText = '';

            if ($imageSrc) {
                $filename = basename($imageSrc);
                $sql = rex_sql::factory();
                $sql->setQuery('SELECT med_description FROM ' . rex::getTablePrefix() . 'media WHERE filename = ?', [$filename]);

                if ($sql->getRows() > 0) {
                    $altText = $sql->getValue('med_description');
                }
            }

            return '<img alt="' . $altText . '" ' . $matches[1] . '>';
        },
        $content
    );

    return $content;
});


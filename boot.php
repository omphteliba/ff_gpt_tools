<?php

if (rex_addon::get('cronjob')->isAvailable()) {
    rex_cronjob_manager::registerType(\FactFinder\FfGptTools\lib\FfGptToolsCronjob::class);
}

rex_extension::register('SLICE_SHOW', static function (rex_extension_point $ep) {
    $content = $ep->getSubject();
    $altCache = [];

    // Use regular expressions to find img tags and add/fix alt attributes
    $content = preg_replace_callback(
        '/<img\s*(?![^>]*\balt\s*=[^>]*["\'][^>]*>)([^>]*)>/i', // Matches img tags without alt, with empty alt, or alt without content
        static function ($matches) use (&$altCache) {
            $imageSrc = null;

            // Extract image source from src attribute
            if (preg_match('/src="([^"]*)"/i', $matches[0], $srcMatches)) {
                $imageSrc = $srcMatches[1];
            }

            $altText = 'No description available'; // Default fallback alt text

            if ($imageSrc) {
                // Extract the filename regardless of path prefix (media path, media manager variations)
                $filename = basename($imageSrc);

                // Check cache first
                if (isset($altCache[$filename])) {
                    $altText = $altCache[$filename];
                } else {
                    // Query database for image description
                    $sql = rex_sql::factory();
                    $sql->setQuery('SELECT med_description FROM ' . rex::getTablePrefix() . 'media WHERE filename = ?', [$filename]);

                    // Check if a result is found
                    if ($sql->getRows() > 0) {
                        $altText = $sql->getValue('med_description');
                    }

                    // Store result in cache, even if it's empty to avoid re-querying
                    $altCache[$filename] = $altText;
                }
            }

            // Return the modified img tag with the alt attribute
            return '<img alt="' . htmlspecialchars($altText, ENT_QUOTES) . '" ' . $matches[1] . '>';
        },
        $content
    );

    return $content;
});





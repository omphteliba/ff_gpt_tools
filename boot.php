<?php

if (rex_addon::get('cronjob')->isAvailable()) {
    rex_cronjob_manager::registerType(\FactFinder\FfGptTools\lib\FfGptToolsCronjob::class);
}

if (rex::isFrontend()) {
    rex_extension::register('OUTPUT_FILTER', static function (rex_extension_point $ep) {
        $content = $ep->getSubject();
        $altCache = [];

        // Match all img tags
        $content = preg_replace_callback(
            '/<img\b[^>]*>/i',
            static function ($matches) use (&$altCache) {
                $imgTag = $matches[0];

                // Check if alt attribute exists
                if (preg_match('/\balt\s*=\s*("|\')(.*?)\1/i', $imgTag, $altMatches)) {
                    $altValue = trim($altMatches[2]);
                    if ($altValue !== '') {
                        // Alt attribute exists and is not empty, do not modify
                        return $imgTag;
                    }
                    // Alt attribute exists but is empty, we need to update it
                }

                // Extract image source from src attribute
                $imageSrc = null;
                if (preg_match('/\bsrc\s*=\s*["\']([^"\']+)["\']/i', $imgTag, $srcMatches)) {
                    $imageSrc = $srcMatches[1];
                }

                $altText = 'No description available';

                if ($imageSrc) {
                    $filename = basename($imageSrc);

                    if (isset($altCache[$filename])) {
                        $altText = $altCache[$filename];
                    } else {
                        // Query database for image description
                        $sql = rex_sql::factory();
                        $sql->setQuery(
                            'SELECT med_description FROM ' . rex::getTablePrefix() . 'media WHERE filename = ?',
                            [$filename]
                        );

                        if ($sql->hasError()) {
                            rex_logger::logError(1, 'SQL Error: ' . $sql->getError(), __FILE__, __LINE__);
                        } elseif ($sql->getRows() > 0) {
                            $altText = $sql->getValue('med_description');
                        }

                        $altCache[$filename] = $altText;
                    }
                }

                // Remove existing alt attribute if it's empty
                $imgTag = preg_replace('/\balt\s*=\s*("|\')(.*?)\1/i', '', $imgTag);

                // Add or update the alt attribute
                $imgTag = preg_replace(
                    '/<img\b/i',
                    '<img alt="' . htmlspecialchars($altText, ENT_QUOTES, 'UTF-8') . '"',
                    $imgTag
                );

                return $imgTag;
            },
            $content
        );

        return $content;
    });
}










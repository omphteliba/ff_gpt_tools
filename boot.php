<?php

if (rex_addon::get('cronjob')->isAvailable()) {
    rex_cronjob_manager::registerType(\FactFinder\FfGptTools\lib\FfGptToolsCronjob::class);
}


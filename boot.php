<?php

if (rex_addon::get('cronjob')->isAvailable()) {
    rex_cronjob_manager::registerType(FfGptToolsCronjob::class);
}

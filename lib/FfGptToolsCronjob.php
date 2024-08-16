<?php

namespace FactFinder\FfGptTools\lib;

use Exception;
use rex;
use rex_cronjob;
use rex_sql;
use rex_sql_exception;
use rex_yrewrite;
use rex_clang;
use rex_logger;
use rex_path;
use rex_exception;
use rex_i18n;
use rex_url;

$addon_name = 'ff_gpt_tools';
require_once rex_path::addon($addon_name, 'vendor/autoload.php');
//require_once rex_path::base('vendor/autoload.php');

/**
 * Class FfGptToolsCronjob
 * This class is a cronjob that processes entries from a database table.
 * It uses the GptTools class to interact
 * with the OpenAI API and update the meta description of articles in Redaxo.
 * The class can be extended to include additional functionality as needed.
 *
 * @package FfGptTools
 * @property string $ffgptdatabase
 * @property int    $maxEntriesProcessed
 * @property string $message
 * @property string $prompt
 * @property string $temperature
 */
class FfGptToolsCronjob extends rex_cronjob
{

    /**
     * @var string
     */
    protected string $ffgptdatabase = 'ff_gpt_tools_tasks';
    /**
     * @var int
     */
    protected int $maxEntriesProcessed = 10;  // Maximum entries to be processed

    // Setter method to update $ffgptdatabase
    /**
     * @param $tableName
     *
     * @return void
     */
    public function setFfgptdatabase($tableName): void
    {
        $this->ffgptdatabase = $tableName;
    }

    // Setter method to update $maxEntriesProcessed

    /**
     * @param $maxEntries
     *
     * @return void
     */
    public function setMaxEntriesProcessed($maxEntries): void
    {
        $this->maxEntriesProcessed = $maxEntries;
    }

    /**
     * @return string
     */
    public function getFfGptDatabase(): string
    {
        return $this->ffgptdatabase;
    }

    /**
     * @return int
     */
    public function getMaxEntriesProcessed(): int
    {
        return $this->maxEntriesProcessed;
    }

    /**
     * @return bool
     */
    public function execute(): bool
    {
        $message = "Cronjob executed. " . PHP_EOL;
        $gpttool = new GptTools('ff_gpt_tools');

        try {
            // Meta Description processing
            $processedMetaEntries = $gpttool->processMetaEntries();

            $message .= "{$processedMetaEntries['success']} Meta Description entries processed successfully." . PHP_EOL;
            $message .= "{$processedMetaEntries['failure']} Meta Description entries failed." . PHP_EOL;

            // Image Description processing
            $processedImageEntries = $gpttool->processImageEntries();

            $message .= "{$processedImageEntries['success']} Image Description entries processed successfully." . PHP_EOL;
            $message .= "{$processedImageEntries['failure']} Image Description entries failed." . PHP_EOL;

            // Include additional details if needed
            foreach ($processedMetaEntries['messages'] as $msg) {
                $message .= $msg . PHP_EOL;
            }
            foreach ($processedImageEntries['messages'] as $msg) {
                $message .= $msg . PHP_EOL;
            }

            $this->setMessage($message);

            // You can determine the return value based on your criteria, for example:
            return true;
        } catch (rex_sql_exception $e) {
            $this->logAndSetMessage($e, "An SQL error occurred: ");

            return false;
        } catch (rex_exception $e) {
            $this->logAndSetMessage($e, "An error occurred: ");

            return false;
        }
    }

    /**
     * @param $exception
     * @param $message
     *
     * @return void
     */
    private function logAndSetMessage($exception, $message)
    {
        rex_logger::logException($exception);
        $this->setMessage($message . $exception->getMessage());
    }

    /**
     *
     *
     * @return string
     */
    public function getTypeName(): string
    {
        return rex_i18n::msg('ff_gpt_tools_cronjob');
    }

}

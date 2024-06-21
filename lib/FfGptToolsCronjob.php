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
    public function getFfgptdatabase(): string
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

        try {
            $processedEntries = $this->processEntries();
            $message          .= "{$processedEntries['success']} entries processed successfully." . PHP_EOL;
            $message          .= "{$processedEntries['failure']} entries failed." . PHP_EOL;

            // Include additional details if needed
            foreach ($processedEntries['messages'] as $msg) {
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
     * @return array
     * @throws \rex_exception
     * @throws \rex_sql_exception
     */
    private function processEntries(): array
    {
        $tableName = rex::getTablePrefix() . $this->ffgptdatabase;
        $sql       = "SELECT * FROM $tableName WHERE done = 0 AND (error_flag = 0 OR error_flag IS NULL) ORDER BY date LIMIT " . $this->maxEntriesProcessed;

        $sqlObject = rex_sql::factory();
        $sqlObject->setQuery($sql);

        $gptTools = new GptTools('ff_gpt_tools');
        $result   = ['success' => 0, 'failure' => 0, 'messages' => []];

        while ($sqlObject->hasNext()) {
            $success = $this->processSingleEntry($sqlObject, $gptTools, $tableName);
            if ($success) {
                $result['success']++;
            } else {
                $result['failure']++;
            }
            $sqlObject->next();
        }

        return $result;
    }

    /**
     * @throws \rex_exception
     */
    private function processSingleEntry($sqlObject, $gptTools, $tableName): bool
    {
        $gptTools->setModelName($sqlObject->getValue('model'));
        $clang     = $sqlObject->getValue('clang');
        $clangName = rex_clang::get($clang)?->getName();
        $articleId = $sqlObject->getValue('article_id');
        try {
            $fullUrlByArticleId = rex_yrewrite::getFullUrlByArticleId($articleId, $clang);
            // rex_logger::logError(1, "gptTool: fullUrl " . $fullUrlByArticleId, __FILE__, __LINE__);
            $content = $gptTools->getUrlContent($fullUrlByArticleId);
            if (empty($content)) {
                $message = "Error: No content to summarize for Article ID: $articleId.";
                $this->logError($message);
                $this->updateErrorFlag($sqlObject, $tableName, $articleId, $clang, $message);

                return false;
            }
        } catch (Exception $e) {
            $message = "Error: No content to summarize for Article ID: $articleId.";
            rex_logger::logException($e);
            $this->updateErrorFlag($sqlObject, $tableName, $articleId, $clang, $message);

            return false;
        }
        if ($content !== '') {
            $gptTools->setPrompt($this->parsePrompt($sqlObject->getValue('prompt'), $clangName, $content));
        } else {
            $message1 = "Error getting the content for Article: $articleId.";
            $this->logError($message1);
            $this->updateErrorFlag($sqlObject, $tableName, $articleId, $clang, $message1); // Mark as error to prevent reprocessing

            return false;
        }
        $this->logError($gptTools->getPrompt());
        $gptTools->setTemperature($sqlObject->getValue('temp'));
        $gptTools->setMaxTokens($sqlObject->getValue('max_token'));
        $metaDescription = $this->removeHtmlEntities($gptTools->getMetaDescription());

        if ($gptTools->updateRedaxoMetaDescription($metaDescription, $articleId, $clang)) {

            $this->updateDatabaseEntry($sqlObject, $tableName, $metaDescription);

            return true;
        }

        $this->logError("Error updating the meta description for Article: $articleId.");

        return false;
    }

    /**
     * @param $sqlObject
     * @param $tableName
     *
     * @return void
     * @throws \rex_exception
     */
    private function updateDatabaseEntry($sqlObject, $tableName, $metaDescription): void
    {
        try {
            $sqlUpdateObject = rex_sql::factory();
            $sqlUpdateObject->setTable($tableName);
            $sqlUpdateObject->setValue('done', 1);
            $sqlUpdateObject->setValue('meta_description', $metaDescription);
            $sqlUpdateObject->setWhere('id = ' . $sqlObject->getValue('id'));
            $sqlUpdateObject->update();
        } catch (rex_sql_exception $e) {
            rex_logger::logException($e);
            throw new rex_exception("There was an error updating the database. Please try again later.");
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
     * @param $message
     *
     * @return void
     */
    private function logError($message)
    {
        // This can be expanded if you need more complex error logging.
        rex_logger::logError(1, $message, __FILE__, __LINE__);
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

    /**
     * @param string $template
     * @param string $lang
     * @param string $content
     *
     * @return array|string|string[]
     */
    public function parsePrompt(string $template, string $lang, string $content): array|string
    {
        // Replace placeholders with actual values
        return str_replace(array('$prompt_lang', '$prompt_content'), array($lang, $content), $template);
    }

    /**
     * Remove HTML entities from a string
     *
     * @param $string
     *
     * @return array|string
     */
    public function removeHtmlEntities($string): array|string
    {
        // List of common HTML entities to remove
        $entitiesToRemove = array('&quot;', '&amp;', '&lt;', '&gt;', '&apos;', '"');

        return str_replace($entitiesToRemove, '', $string);
    }

    /**
     * @param $sqlObject
     * @param $tableName
     * @param $articleId
     * @param $clang
     *
     * @return void
     */
    private function updateErrorFlag($sqlObject, $tableName, $articleId, $clang, $message = null): void
    {
        $updateSql = "UPDATE $tableName SET error_flag = 1, error_text = ? WHERE article_id = ? AND clang = ?";
        $sqlObject->setQuery($updateSql, [$message, $articleId, $clang]);
    }
}

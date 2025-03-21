<?php

namespace FactFinder\FfGptTools\lib;

use phpDocumentor\Reflection\Types\Boolean;
use rex;
use rex_clang;
use rex_exception;
use rex_addon;
use rex_path;
use rex_sql;
use rex_sql_exception;
use rex_logger;
use rex_yrewrite;
use DOMDocument;
use DOMNode;
use DOMXPath;
use DOMElement;
use ErrorException;
use Exception;
use OpenAI;

$addon_name = 'ff_gpt_tools';
require_once rex_path::addon($addon_name, 'vendor/autoload.php');
//require_once rex_path::base('vendor/autoload.php');

/**
 * GptTools Class
 *
 * Provides utility methods for extracting text from HTML and generating meta-descriptions.
 * Uses the OpenAI API to generate meta-descriptions based on prompts.
 * The class can be extended to include additional functionality as needed.
 *
 * @package GptTools
 * @property string $addon_name
 * @property string $apiKey
 * @property string $modelName
 * @property string $description_field
 * @property string $image_description_field
 * @property string $prompt
 * @property float  $temperature
 * @property int    $maxTokens
 *
 */
class GptTools
{
    /**
     * @var string
     */
    private string $addon_name = 'ff_gpt_tools';
    /**
     * @var string
     */
    private string $apiKey;
    /**
     * @var string
     */
    private string $modelName = 'gpt-4';

    /**
     * @var string
     */
    private string $description_field;

    /**
     * @var string
     */
    private string $image_description_field;

    /**
     * @var string
     */
    private string $prompt = 'Act as a SEO specialist with 10 years of experience. Please summarize this article in $prompt_lang in 18 words or less for the meta-description of a website: $prompt_content';

    /**
     * @var float
     */
    private float $temperature = 0.7;

    /**
     * @var int
     */
    private int $maxTokens = 18;

    /**
     * @var string
     */
    private $imageUrl;

    /**
     * @var string
     */
    protected string $ffgptdatabase = 'ff_gpt_tools_tasks';

    /**
     * @var int
     */
    protected int $maxEntriesProcessed = 10;

    private string $apiMaxTokenString = 'max_tokens';

    public function getApiMaxTokenString(): string
    {
        return $this->apiMaxTokenString;
    }

    public function setApiMaxTokenString(string $apiMaxTokenString = 'max_tokens'): void
    {
        $this->apiMaxTokenString = $apiMaxTokenString;
        // if $this->modelname has o1 or o3 in it then set it to 'max_completion_tokens'
        if (strpos($this->modelName, 'o1') !== false || strpos($this->modelName, 'o3') !== false) {
            $this->apiMaxTokenString = 'max_completion_tokens';
        }
    }

    /**
     * @param $sqlObject
     * @param $gptTools
     * @param $tableName
     *
     * @return bool
     * @throws \rex_exception
     */
    public static function processSingleMetaEntry($sqlObject, $gptTools, $tableName): bool
    {
        $gptTools->setModelName($sqlObject->getValue('model'));
        $gptTools->setApiMaxTokenString();
        $clang     = $sqlObject->getValue('clang');
        $clangName = rex_clang::get($clang)?->getName();
        $articleId = $sqlObject->getValue('article_id');
        try {
            $fullUrlByArticleId = rex_yrewrite::getFullUrlByArticleId($articleId, $clang);
            // rex_logger::logError(1, "gptTool: fullUrl " . $fullUrlByArticleId, __FILE__, __LINE__);
            $content = $gptTools->getUrlContent($fullUrlByArticleId);
            if (empty($content)) {
                $message = "Error: No content to summarize for Article ID: $articleId.";
                self::logError($message);
                self::updateErrorFlag($sqlObject, $tableName, $articleId, $clang, $message);

                return false;
            }
        } catch (Exception $e) {
            $message = "Error: No content to summarize for Article ID: $articleId.";
            rex_logger::logException($e);
            self::updateErrorFlag($sqlObject, $tableName, $articleId, $clang, $message);

            return false;
        }
        if ($content !== '') {
            $gptTools->setPrompt(self::parsePrompt($sqlObject->getValue('prompt'), $clangName, $content));
            /** @phpstan-ignore else.unreachable */
        } else {
            $message1 = "Error getting the content for Article: $articleId.";
            self::logError($message1);
            self::updateErrorFlag($sqlObject, $tableName, $articleId, $clang,
                $message1); // Mark as error to prevent reprocessing

            return false;
        }
        // GptTools::logError($gptTools->getPrompt());
        if (strpos($gptTools->modelName, 'o1') === false && strpos($gptTools->modelName, 'o3') === false) {
            $gptTools->setTemperature($sqlObject->getValue('temp'));
        }
        $gptTools->setMaxTokens($sqlObject->getValue('max_token'));
        $metaDescription = self::removeHtmlEntities($gptTools->getMetaDescription());

        if ($gptTools->updateRedaxoMetaDescription($metaDescription, $articleId, $clang)) {
            self::updateDatabaseEntry($sqlObject, $tableName, $metaDescription);

            return true;
        }

        self::logError("Error updating the meta description for Article: $articleId.");

        return false;
    }

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
     * @param string $url
     *
     * @return bool
     */
    public static function urlExists(string $url): bool
    {
        $file_headers = @get_headers($url);

        return $file_headers[0] !== 'HTTP/1.1 404 Not Found';
    }

    /**
     * @param      $sqlObject
     * @param      $tableName
     * @param      $articleId
     * @param      $clang
     * @param null $message
     *
     * @return void
     */
    public static function updateErrorFlag($sqlObject, $tableName, $articleId, $clang, $message = null): void
    {
        $updateSql = "UPDATE $tableName SET error_flag = 1, error_text = ? WHERE article_id = ? AND clang = ?";
        $sqlObject->setQuery($updateSql, [$message, $articleId, $clang]);
    }

    /**
     * @param      $sqlObject
     * @param      $tableName
     * @param      $articleId
     * @param      $clang
     * @param null $message
     *
     * @return void
     */
    public static function updateImageErrorFlag($tableName, $imageUrl, $message = null): void
    {
        // remove protocoll and rex_server url from $imageUrl
        $imageUrl  = str_replace(rex::getServer(), '', $imageUrl);
        $imageUrl  = '..' . $imageUrl;
        $sqlObject = rex_sql::factory();
        // Use backticks for table name
        $tableName = '`' . str_replace('`', '``', $tableName) . '`'; //escape backticks, and add backticks.

        $sqlObject->setDebug(false);
        $updateSql = "UPDATE $tableName SET error_flag = 1, error_text = ? WHERE image_url = ? ";
        $sqlObject->setQuery($updateSql, [
            $message,
            $imageUrl,
        ]);
    }

    /**
     * Remove HTML entities from a string
     *
     * @param string $string
     *
     * @return array|string
     */
    public static function removeHtmlEntities(string $string): array|string
    {
        // List of common HTML entities to remove
        $entitiesToRemove = array('&quot;', '&amp;', '&lt;', '&gt;', '&apos;', '"');

        return str_replace($entitiesToRemove, '', $string);
    }

    /**
     * @param $sqlObject
     * @param $tableName
     * @param $metaDescription
     *
     * @return void
     * @throws \rex_exception
     */
    public static function updateDatabaseEntry($sqlObject, $tableName, $metaDescription): void
    {
        try {
            $sqlUpdateObject = rex_sql::factory();
            $sqlUpdateObject->setTable($tableName);
            $sqlUpdateObject->setValue('done', 1);
            $sqlUpdateObject->setValue('meta_description', $metaDescription);
            $sqlUpdateObject->setWhere('id = :id', ['id' => $sqlObject->getValue('id')]);
            $sqlUpdateObject->update();
        } catch (rex_sql_exception $e) {
            rex_logger::logException($e);
            throw new rex_exception("There was an error updating the database. Please try again later.");
        }
    }

    /**
     * @return array
     * @throws \rex_sql_exception
     */
    function processImageEntries(): array
    {
        $tableName = rex::getTable($this->ffgptdatabase);
        $sql       = "SELECT * FROM $tableName WHERE article_id = '' AND done = 0 AND (error_flag = 0 OR error_flag IS NULL) ORDER BY date LIMIT :maxEntriesProcessed";

        $sqlObject = rex_sql::factory();
        $sqlObject->setQuery($sql, ['maxEntriesProcessed' => $this->maxEntriesProcessed]);

        $gptTools = new GptTools('ff_gpt_tools');
        $result   = ['success' => 0, 'failure' => 0, 'messages' => []];

        while ($sqlObject->hasNext()) {
            $success = self::processSingleImageEntry($sqlObject, $gptTools, $tableName);
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
     * @param $sqlObject
     * @param $gptTools
     * @param $tableName
     *
     * @return bool
     * @throws \rex_exception
     */
    public static function processSingleImageEntry($sqlObject, $gptTools, $tableName): bool
    {
        $gptTools->setModelName($sqlObject->getValue('model'));
        $gptTools->setApiMaxTokenString();
        $clang     = $sqlObject->getValue('clang');
        $clangName = rex_clang::get($clang)?->getName();
        $image     = $sqlObject->getValue('image_url');
        $image     = rex::getServer() . substr($image, 2);

        // check if the file $image exists
        if (self::urlExists($image)) {
            $gptTools->setPrompt(self::parsePrompt($sqlObject->getValue('prompt'), $clangName, ''));
            $gptTools->setImageUrl($image);
        } else {
            $message1 = "Error getting the image: $image.";
            self::logError($message1);
            self::updateErrorFlag($sqlObject, $tableName, $sqlObject->getValue('image_url'), $clang,
                $message1); // Mark as error to prevent reprocessing

            return false;
        }
        //GptTools::logError($gptTools->getPrompt());
        if (strpos($gptTools->modelName, 'o1') === false && strpos($gptTools->modelName, 'o3') === false) {
            $gptTools->setTemperature($sqlObject->getValue('temp'));
        }
        $gptTools->setMaxTokens($sqlObject->getValue('max_token'));
        $metaDescription = self::removeHtmlEntities($gptTools->getImageDescription());

        if ($gptTools->updateRedaxoImageDescription($metaDescription, $sqlObject->getValue('image_url'))) {
            self::updateDatabaseEntry($sqlObject, $tableName, $metaDescription);

            return true;
        }

        self::logError("Error updating the meta description for Image: $sqlObject->getValue('image_url').");

        return false;
    }

    /**
     * @param $message
     *
     * @return void
     */
    public static function logError($message)
    {
        // This can be expanded if you need more complex error logging.
        rex_logger::logError(1, $message, __FILE__, __LINE__);
    }

    /**
     * @return mixed
     */
    public function getImageUrl()
    {
        return $this->imageUrl;
    }

    /**
     * @param mixed $imageUrl
     */
    public function setImageUrl($imageUrl): void
    {
        $this->imageUrl = $imageUrl;
    }

    /**
     * @return int
     */
    public function getMaxTokens(): int
    {
        return $this->maxTokens;
    }

    /**
     * @param int $maxTokens
     *
     * @return void
     */
    public function setMaxTokens(int $maxTokens): void
    {
        $this->maxTokens = $maxTokens;
    }

    /**
     * @return float
     */
    public function getTemperature(): float
    {
        return $this->temperature;
    }

    /**
     * @param float $temperature
     *
     * @return void
     */
    public function setTemperature(float $temperature): void
    {
        $this->temperature = $temperature;
    }

    /**
     * @return string
     */
    public function getPrompt(): string
    {
        return $this->prompt;
    }

    /**
     * @param string $prompt
     *
     * @return void
     */
    public function setPrompt(string $prompt): void
    {
        $this->prompt = $prompt;
    }

    /**
     * @return string
     */
    public function getAddonName(): string
    {
        return $this->addon_name;
    }

    /**
     * @return string
     */
    public function getModelName(): string
    {
        return $this->modelName;
    }

    /**
     * @param string $modelName
     *
     * @return void
     */
    public function setModelName(string $modelName): void
    {
        $this->modelName = $modelName;
    }

    /**
     * @param string $addon_name
     *
     * @return void
     */
    public function setAddonName(string $addon_name): void
    {
        $this->addon_name = $addon_name;
    }

    /**
     * @return string
     */
    public function getApiKey(): string
    {
        return $this->apiKey;
    }

    /**
     * @param string $apiKey
     *
     * @return void
     */
    public function setApiKey(string $apiKey): void
    {
        $this->apiKey = $apiKey;
    }

    /**
     * @return string
     */
    public function getDescriptionField(): string
    {
        return $this->description_field;
    }

    /**
     * @param string $description_field
     *
     * @return void
     */
    public function setDescriptionField(string $description_field): void
    {
        $this->description_field = $description_field;
    }

    /**
     * @return string
     */
    public function getImageDescriptionField(): string
    {
        return $this->image_description_field;
    }

    /**
     * @param string $image_description_field
     *
     * @return void
     */
    public function setImageDescriptionField(string $image_description_field): void
    {
        $this->image_description_field = $image_description_field;
    }


    /**
     * Constructor
     *
     * @param string $addon_name Name of the addon.
     *
     * @throws \InvalidArgumentException If $addon_name or $apiKey is empty.
     */
    public function __construct(string $addon_name)
    {
        if (empty($addon_name)) {
            throw new \InvalidArgumentException("addon_name must be provided and cannot be empty.");
        }

        $this->addon_name = $addon_name;
        // Fetching and setting the API key
        $this->apiKey = rex_addon::get($this->addon_name)->getConfig('apikey');
        $maxEntries   = (int)\rex_addon::get($this->addon_name)->getConfig('apikey');

        if (isset($maxEntries) && $maxEntries > 0) {
            $this->setMaxEntriesProcessed($maxEntries);
        }

        if (empty($this->apiKey)) {
            throw new \InvalidArgumentException("API key must be configured and cannot be empty.");
        }

        $this->description_field = rex_addon::get($this->addon_name)->getConfig('descriptionfield');
        // $this->image_description_field = rex_addon::get($this->addon_name)->getConfig('image_descriptionfield');
        $this->image_description_field = rex_addon::get($this->addon_name)->getConfig('image_descriptionfield') ?: 'default_value';

        if (empty($this->description_field)) {
            throw new \InvalidArgumentException("description_field must be configured and cannot be empty.");
        }
    }

    /**
     * @param int $articleId
     * @param int $clang
     *
     * @return bool
     * @throws \rex_exception
     * @throws \rex_sql_exception
     */
    public function saveMetaDescription(int $articleId, int $clang): bool
    {
        $metaDescription = $this->getMetaDescriptionById($articleId, $clang);

        return $this->updateRedaxoMetaDescription($metaDescription, $articleId, $clang);
    }

    /**
     * @param string $metaDescription
     * @param int    $articleId
     * @param int    $clang
     *
     * @return bool
     */
    public function updateRedaxoMetaDescription(string $metaDescription, int $articleId, int $clang): bool
    {
        // SQL query to update meta description in Redaxo
        // Execute and return status
        $article = rex_sql::factory();
        $article->setDebug(false);
        $query = 'UPDATE ' . rex::getTable('article') . '
SET ' . $article->escapeIdentifier($this->description_field) . ' = :metaDescription
WHERE id = :articleId
AND clang_id = :clang';
        try {
            $article->setQuery($query, [
                'metaDescription' => $metaDescription,
                'articleId'       => $articleId,
                'clang'           => $clang,
            ]);

        } catch (rex_sql_exception $e) {
            rex_logger::logException($e);

            return false;
        }

        return true;
    }

    /**
     * @param string $metaDescription
     * @param string $image
     *
     * @return bool
     */
    public function updateRedaxoImageDescription(string $metaDescription, string $image): bool
    {
        // SQL query to update meta description in Redaxo
        // Execute and return status
        $article = rex_sql::factory();
        $article->setDebug(false);
        $query = 'UPDATE ' . rex::getTable('media') . '
SET ' . $article->escapeIdentifier($this->image_description_field) . ' = :metaDescription
WHERE filename = :image';

        try {
            $article->setQuery($query, [
                'metaDescription' => $metaDescription,
                'image'           => $image,
            ]);
        } catch (rex_sql_exception $e) {
            rex_logger::logException($e);

            return false;
        }

        return true;
    }

    /**
     * @throws \rex_exception
     * @throws \rex_sql_exception
     */
    public function generateMetaDescriptions(): bool|string
    {
        $output   = '';
        $articles = rex_sql::factory();
        $articles->setDebug(false);
        $query = 'SELECT id, clang_id FROM ' . rex::getTable('article') . '
        WHERE ' . $articles->escapeIdentifier($this->description_field) . ' = "" LIMIT 1';

        try {
            $articles->setQuery($query);
        } catch (rex_sql_exception $e) {
            return false;
        }

        foreach ($articles as $article) {
            $output .= 'Article ID: ' . $article->getValue('id') . ' Clang ID: ' . $article->getValue('clang_id') . '<br>' . PHP_EOL;
            $this->saveMetaDescription($article->getValue('id'), $article->getValue('clang_id'));
        }

        return $output;
    }

    // Future method for generating whole articles

    /**
     * @param int $articleId
     *
     * @return string
     */
    public function generateArticle(int $articleId): string
    {
        // To be implemented
        return '';
    }

    /**
     * Extracts the text content from the <main> tag of an HTML document.
     * Removes <script> and <style> tags, divs with class 'post-meta', and section with id 'comments'.
     * Removes \n, \t, and \r.
     *
     * @param string $html
     *
     * @return string
     */
    public function extractTextFromWebArticle(string $html): string
    {
        // Check if the input HTML is empty or only whitespace
        if (trim($html) === '') {
            return ''; // Early return for empty or whitespace-only input
        }

        $dom = new DOMDocument();
        // Use libxml_use_internal_errors to handle invalid HTML
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_use_internal_errors(false);

        $xpath = new DOMXPath($dom);

        $main = $dom->getElementsByTagName('main')->item(0);
        if ($main === null) {
            $main = $dom->getElementsByTagName('body')->item(0);
            if ($main === null) {
                return ''; // Return empty string if <main> and <body> are not found
            }
        }

        $this->removeTags($main, 'script');
        $this->removeTags($main, 'style');
        $this->removeTags($main, 'form');
        $this->removeTags($main, 'footer');
        $this->removeTags($main, 'nav');
        $this->removeElementsByClass($xpath, $main, 'post-meta');
        $this->removeElementsByClass($xpath, $main, 'llp_form_section');
        $this->removeElementById($xpath, $main, 'comments');

        $textContent = str_replace(["\n", "\t", "\r"], " ", $main->textContent);

        // Check for placeholder text or minimal content
        if (trim($textContent) === '' || stripos($textContent,
                'Lorem Ipsum') !== false || trim($textContent) === 'test' || strlen(trim($textContent)) < 50) {
            return ''; // Return empty string if content is empty, contains "Lorem Ipsum", is just "test", or is very short
        }

        return $textContent;
    }

    /**
     * Helper method to remove elements by tag name.
     *
     * @param DOMElement $element
     * @param string     $tag
     */
    private function removeTags(DOMNode $element, string $tag): void
    {
        $tags = $element->getElementsByTagName($tag);
        while ($tags->length > 0) {
            $tagElement = $tags->item(0);
            if ($tagElement !== null) {
                $tagElement->parentNode?->removeChild($tagElement);
            }
        }
    }

    /**
     * Helper method to remove elements by class name.
     *
     * @param DOMXPath   $xpath
     * @param DOMElement $element
     * @param string     $class
     */
    private function removeElementsByClass(DOMXPath $xpath, DOMNode $element, string $class): void
    {
        $elements = $xpath->query("//div[contains(@class, '$class')]", $element);
        foreach ($elements as $el) {
            $el->parentNode->removeChild($el);
        }
    }

    /**
     * Helper method to remove elements by id.
     *
     * @param DOMXPath $xpath
     * @param DOMNode  $element
     * @param string   $id
     */
    private function removeElementById(DOMXPath $xpath, DOMNode $element, string $id): void // @phpstan-ignore-line
    {
        $elements = $xpath->query("//*[@id='$id']", $element);
        foreach ($elements as $el) {
            $el->parentNode->removeChild($el);
        }
    }

    /**
     * Returns the meta-description
     *
     * @return string
     *
     * @throws rex_exception
     */
    public function getMetaDescription(): string
    {
        try {
            $client = OpenAI::client($this->apiKey);

            if (strpos($this->modelName, 'o1') === false && strpos($this->modelName, 'o3') === false) {
                $response = $client->chat()->create([
                    'model'                  => $this->modelName,
                    $this->apiMaxTokenString => (int)$this->maxTokens,
                    'temperature'            => $this->temperature,
                    'messages'               => [
                        ['role' => 'user', 'content' => $this->prompt],
                    ],
                ]);
            } else {
                $response = $client->chat()->create([
                    'model'                  => $this->modelName,
                    $this->apiMaxTokenString => (int)$this->maxTokens,
                    'messages'               => [
                        ['role' => 'user', 'content' => $this->prompt],
                    ],
                ]);
            }

            // OpenAI doesn't return an error, so no error handling. Yay!

            return $response['choices'][0]['message']['content'];// Return the meta-description
        } catch (Exception $e) {
            rex_logger::logError(1, 'Failed to fetch meta-description from OpenAI: ' . $e->getMessage(), __FILE__,
                __LINE__);

            return '';
        }
    }

    public /**
     * Converts an image from a given URL to a base64-encoded data URL.
     *
     * @param string $imageUrl The URL of the image.
     *
     * @return string The base64-encoded data URL, or null if an error occurred.
     */
    function imageUrlToBase64DataUrl(
        string $imageUrl
    ): ?string {
        // Fetch the image content from the URL.
        $imageContent = @file_get_contents($imageUrl);

        // Check if fetching the image was successful.
        if ($imageContent === false) {
            // Handle the error, e.g., by logging it or returning a default image.
            self::logError("Failed to fetch image from URL: $imageUrl");

            return null;
        }

        // Determine the image's MIME type. This is necessary for the data URL.
        if (strpos($imageUrl, '.svg') !== false) {
            $imageMimeType = 'image/svg+xml';
        } else {
            $imageInfo     = getimagesizefromstring($imageContent);
            $imageMimeType = $imageInfo['mime'] ?? null;
        }
        // Check if the MIME type could be determined.
        if ($imageMimeType === null) {
            // Handle the error, e.g., by logging it.
            self::logError("Failed to determine MIME type for image: $imageUrl");

            return null;
        }

        // Encode the image content to base64.
        $base64EncodedImage = base64_encode($imageContent);

        // Construct and return the data URL.
        return "data:$imageMimeType;base64,$base64EncodedImage";
    }

    /**
     * Returns the image description
     *
     * @return string
     *
     * @throws rex_exception
     */
    public function getImageDescription(): string
    {
        $imageUrlToBase64DataUrl = $this->imageUrlToBase64DataUrl($this->imageUrl);
        if ($imageUrlToBase64DataUrl === null) {
            rex_logger::logError(1, "imageUrlToBase64DataUrl for " . $this->imageUrl . "is NULL", __FILE__, __LINE__);

            return '';
        }
        try {
            $client = OpenAI::client($this->apiKey);

            if (strpos($this->modelName, 'o1') === false && strpos($this->modelName, 'o3') === false) {
                $response = $client->chat()->create([
                    'model'                  => $this->modelName,
                    $this->apiMaxTokenString => (int)$this->maxTokens,
                    'temperature'            => $this->temperature,
                    'messages'               => [
                        [
                            'role'    => 'user',
                            'content' => [
                                [
                                    'type' => 'text',
                                    'text' => (string)$this->prompt,
                                ],
                                [
                                    'type'      => 'image_url',
                                    'image_url' => [
                                        'url' => $imageUrlToBase64DataUrl,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ]);
            } else {
                $response = $client->chat()->create([
                    'model'                  => $this->modelName,
                    $this->apiMaxTokenString => (int)$this->maxTokens,
                    'messages'               => [
                        [
                            'role'    => 'user',
                            'content' => [
                                [
                                    'type' => 'text',
                                    'text' => (string)$this->prompt,
                                ],
                                [
                                    'type'      => 'image_url',
                                    'image_url' => [
                                        'url' => $imageUrlToBase64DataUrl,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ]);
            }

            return $response['choices'][0]['message']['content'];// Return the meta-description
        } catch (Exception $e) {
            $errstr = 'Failed to fetch meta-description from OpenAI: ' . $e->getMessage();
            rex_logger::logError(1, $errstr, __FILE__, __LINE__);
            self::updateImageErrorFlag(rex::getTable($this->ffgptdatabase), $this->imageUrl,
                $errstr); // Return error message

            return '';
        }
    }

    /**
     * @param int $articleId
     * @param int $clang
     *
     * @return string
     * @throws \rex_exception
     */
    private function getMetaDescriptionById(int $articleId, int $clang): string
    {
        // Get the URL of the article
        $url = rex_yrewrite::getFullUrlByArticleId($articleId, $clang);

        return $this->getMetaDescription();
    }

    /**
     * Fetches all available OpenAI models.
     *
     * @return array<int, string> List of available models.
     */
    public function getAllAvailableModels(): array
    {
        try {
            $client   = OpenAI::client($this->apiKey);
            $response = $client->models()->list();

            // Check if the object type in the response is 'list'
            if ($response->object !== 'list') {
                rex_logger::logError(1, 'Unexpected OpenAI API response.', __FILE__, __LINE__);

                return [];
            }

            $models = [];
            foreach ($response->data as $result) {
                $models[] = $result->id;
            }
            sort($models);

            return $models;
        } catch (Exception $e) {
            rex_logger::logError(1, 'Failed to fetch available models from OpenAI: ' . $e->getMessage(), __FILE__,
                __LINE__);

            return [];
        }
    }

    /**
     * @param string $url
     *
     * @return string
     * @throws \rex_exception
     */
    public function getUrlContent(string $url): string
    {
        // Set a custom error handler to catch warnings
        set_error_handler(function ($severity, $message, $file, $line) {
            throw new ErrorException($message, 0, $severity, $file, $line);
        });

        try {
            $htmlContent = file_get_contents($url);
            if ($htmlContent === false) {
                rex_logger::logError(1, "ff_gpt_tools: Failed to load content: $url.", __FILE__, __LINE__);
                throw new rex_exception('Failed to load content: ' . $url);
            }
            // Your existing content processing logic here...
            if ($htmlContent !== '') {
                $cleanedUpContent = $this->extractTextFromWebArticle($htmlContent);
                if ($cleanedUpContent !== '') {
                    return $cleanedUpContent;
                }
                rex_logger::logError(1, "ff_gpt_tools: Cleaned up content is empty: $url.", __FILE__, __LINE__);
                throw new rex_exception('Cleaned up content is empty: ' . $url);
            }
            rex_logger::logError(1, "ff_gpt_tools: Empty content: $url.", __FILE__, __LINE__);
            throw new rex_exception('Empty content: ' . $url);
        } catch (ErrorException $e) {
            // Handle the error gracefully
            rex_logger::logError(1, "ff_gpt_tools: Error accessing $url: " . $e->getMessage(), __FILE__, __LINE__);
            throw new rex_exception('Error accessing ' . $url . ': ' . $e->getMessage());
        } finally {
            // Restore the previous error handler
            restore_error_handler();
        }
    }

    /**
     * @param string $template
     * @param string $lang
     * @param string $content
     *
     * @return array|string|string[]
     */
    public static function parsePrompt(string $template, string $lang, string $content): array|string
    {
        // Replace placeholders with actual values
        return str_replace(array('$prompt_lang', '$prompt_content'), array($lang, $content), $template);
    }

    /**
     * Generates an image description using the OpenAI API.
     *
     * @param string $imageUrl The URL of the image.
     *
     * @return string The generated image description.
     * @throws Exception If there is an error with the API request.
     */
    public function generateImageDescription(string $imageUrl): string
    {
        // Set the image URL
        $this->setImageUrl($imageUrl);

        // Send a request to the OpenAI API to generate the image description
        // Return the generated image description
        return $this->getImageDescription();
    }

    /**
     * Fetches the media data by filename.
     *
     * @param string $filename The filename of the media.
     *
     * @return array<int, int> The media data if found, false otherwise.
     */
    public static function getMediaDataByFilename(string $filename): array
    {
        $sql = rex_sql::factory();
        $sql->setQuery('
        SELECT id, category_id
        FROM ' . rex::getTablePrefix() . 'media
        WHERE filename = :filename
    ', ['filename' => $filename]); // No need to escape here

        if ($sql->getRows() > 0) {
            $id_value          = (int)$sql->getValue('id');
            $category_id_value = (int)$sql->getValue('category_id');

            return [
                'file_id'     => $id_value,
                'category_id' => $category_id_value,
            ];
        }

        return [
            'file_id'     => 0,
            'category_id' => 0,
        ];
    }

    /**
     * @return array
     * @throws \rex_exception
     * @throws \rex_sql_exception
     */
    public function processMetaEntries(): array
    {
        $tableName = rex::getTable($this->ffgptdatabase);
        $sql       = "SELECT * FROM $tableName WHERE article_id <> '' AND done = 0 AND (error_flag = 0 OR error_flag IS NULL) ORDER BY date LIMIT " . $this->maxEntriesProcessed;

        $sqlObject = rex_sql::factory();
        $sqlObject->setQuery($sql);

        $gptTools = new GptTools('ff_gpt_tools');
        $result   = ['success' => 0, 'failure' => 0, 'messages' => []];

        while ($sqlObject->hasNext()) {
            $success = GptTools::processSingleMetaEntry($sqlObject, $gptTools, $tableName);
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
     * Parses the slice content, adds missing alt tags to images using mediapool descriptions.
     *
     * @param $ep The extension point object
     *
     * @return string The modified slice output
     */
    function add_missing_alt_tags($ep)
    {
        // Get the slice content and store it
        $content = $ep->getSubject();

        // Use a DOMDocument to parse the HTML content
        $dom = new DOMDocument();
        @$dom->loadHTML($content);

        // Get all img tags within the content
        $images = $dom->getElementsByTagName('img');

        // Iterate through each image tag
        foreach ($images as $image) {
            // Check if the alt attribute exists and is not empty
            if (!$image->hasAttribute('alt') || empty($image->getAttribute('alt'))) {
                // Get the image source (src attribute)
                $imageSrc = $image->getAttribute('src');

                // Extract the mediapool filename from the image source
                // Assuming the image source is a URL relative to the mediapool
                $filename = basename($imageSrc);

                // Query the mediapool for the image description
                $sql = rex_sql::factory();
                $sql->setQuery('SELECT med_description FROM ' . rex::getTablePrefix() . 'media WHERE filename = ?',
                    [$filename]);

                // If a description is found in the mediapool
                if ($sql->getRows() > 0) {
                    $description = $sql->getValue('med_description');
                    // Set the alt attribute with the mediapool description
                    $image->setAttribute('alt', $description);
                }
            }
        }

        // Save the modified HTML and return it for output
        return $dom->saveHTML();
    }

    /**
     * @param mixed $image_descriptionfield
     *
     * @return bool
     * @throws \rex_sql_exception
     */
    public static function checkImageDescriptionField(mixed $image_descriptionfield): bool
    {
        // check if the field that is defined in $image_descriptionfield exists in the table rex_media
        $sql = rex_sql::factory();
        // Check if the table exists (Corrected logic)
        $sql->setQuery("SHOW TABLES LIKE '" . REX::getTable('media') . "'");
        $tableExists = $sql->getRows() > 0; // Check if ANY rows were returned

        if ($tableExists) {
            // Check if the field exists
            $query = "SHOW COLUMNS FROM `" . REX::getTable('media') . "` LIKE '$image_descriptionfield'";
            $sql->setQuery($query);
            $fieldExists = $sql->getRows() > 0;
            if (!$fieldExists) {
                echo \rex_view::error("The field '$image_descriptionfield' does NOT exist in the table '" . REX::getTable('media') . "'.");

                return false;
            }
        } else {
            echo \rex_view::error("The table '" . REX::getTable('media') . "' does not exist.");

            return false;
        }

        return true;
    }

    // Organze items in the rex_media database that have 0 in the category_id column. Have a look at the rex_media_category table to find a fitting category or create a new one.

    /**
     * Organizes media items with category_id 0 by assigning them to a suitable category or creating a new one.
     *
     * @return string
     * @throws \rex_sql_exception
     */
    public function organizeMediaItems(): string
    {
        $out = '';
        $sql = rex_sql::factory();
        $sql->setQuery('SELECT filename FROM ' . rex::getTable('media') . ' WHERE category_id = 0');

        while ($sql->hasNext()) {
            $filename   = $sql->getValue('filename');
            $categoryId = $this->findOrCreateCategoryForMedia($filename);

            if ($categoryId) {
                $updateSql = rex_sql::factory();
                $updateSql->setQuery('UPDATE ' . rex::getTable('media') . ' SET category_id = ? WHERE filename = ?',
                    [$categoryId, $filename]);
                $out .= "Media item '$filename' assigned to category ID $categoryId." . PHP_EOL;
            } else {
                $out .= "Could not find or create a category for media item '$filename'." . PHP_EOL;
            }

            $sql->next();
        }

        return $out;
    }

    /**
     * Finds or creates a suitable category for a media item using ChatGPT based on its filename.
     *
     * @param string $filename The filename of the media item.
     *
     * @return int|null The ID of the category, or null if no suitable category could be found or created.
     * @throws \rex_exception
     */
    private function findOrCreateCategoryForMedia(string $filename): ?int
    {
        $suggestedCategoryName = $this->suggestCategoryNameWithChatGPT($filename);

        if ($suggestedCategoryName === null) {
            rex_logger::logError(2, "ChatGPT could not suggest a category name for '$filename'.", __FILE__, __LINE__);

            return null; // or fallback to a default category
        }


        $sql = rex_sql::factory();
        $sql->setQuery('SELECT id FROM ' . rex::getTable('media_category') . ' WHERE name = ?',
            [$suggestedCategoryName]);

        if ($sql->getRows() > 0) {
            return (int)$sql->getValue('id');
        }

        $createSql = rex_sql::factory();
        $createSql->setTable(rex::getTable('media_category'));
        $createSql->setValue('name', $suggestedCategoryName);
        $createSql->insert();

        return (int)$createSql->getLastId();
    }

    /**
     * Suggests a category name using ChatGPT based on the given filename.
     *
     * @param string $filename The filename of the media item.
     *
     * @return string|null The suggested category name, or null if the API call fails.
     * @throws \rex_exception
     */
    private function suggestCategoryNameWithChatGPT(string $filename): ?string
    {
        try {
            $client = OpenAI::client($this->apiKey);

            $prompt = "Suggest a concise and relevant category name for a media file named '$filename'.";

            $response = $client->chat()->create([
                'model'    => $this->modelName,  // Use your preferred model
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

            $suggestedName = trim($response['choices'][0]['message']['content']);
            rex_logger::logError(2, "ChatGPT suggested category: '$suggestedName' for '$filename'.", __FILE__,
                __LINE__);

            return $suggestedName;

        } catch (Exception $e) {
            rex_logger::logException($e);

            return null;
        }
    }


}

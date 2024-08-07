<?php

namespace FactFinder\FfGptTools\lib;

use rex;
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
    private $imageUrl;

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

    public function getImageDescriptionField(): string
    {
        return $this->image_description_field;
    }

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
        SET ' . $this->description_field . ' = "' . $metaDescription . '"
        WHERE id = "' . $articleId . '"
        AND clang_id = "' . $clang . '"';

        try {
            $article->setQuery($query);
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
        SET ' . $this->image_description_field . ' = "' . $metaDescription . '"
        WHERE filename = "' . $image . '"';

        try {
            $article->setQuery($query);
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
        $articles->setDebug(true);
        $query = 'SELECT id, clang_id FROM ' . rex::getTable('article') . '
        WHERE ' . $this->description_field . ' = "" LIMIT 1';

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
            return ''; // Return empty string if <main> is not found
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

            $response = $client->chat()->create([
                'model'       => $this->modelName,
                'max_tokens'  => $this->maxTokens,
                'temperature' => $this->temperature,
                'messages'    => [
                    ['role' => 'user', 'content' => $this->prompt],
                ],
            ]);

            // OpenAI doesn't return an error, so no error handling. Yay!

            return $response['choices'][0]['message']['content'];// Return the meta-description
        } catch (Exception $e) {
            rex_logger::logError(1, 'Failed to fetch meta-description from OpenAI: ' . $e->getMessage(), __FILE__,
                __LINE__);

            return '';
        }
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
        try {
            $client = OpenAI::client($this->apiKey);

            $response = $client->chat()->create([
                'model'       => $this->modelName,
                'max_tokens'  => $this->maxTokens,
                'temperature' => $this->temperature,
                'messages'    => [
                    [
                        'role'    => 'user',
                        'content' => $this->prompt,
                    ],
                    [
                        'role'    => 'system',
                        'content' => 'The image URL is ' . $this->imageUrl,
                    ],
                ],
            ]);

            return $response['choices'][0]['message']['content'];// Return the meta-description
        } catch (Exception $e) {
            rex_logger::logError(1, 'Failed to fetch meta-description from OpenAI: ' . $e->getMessage(), __FILE__,
                __LINE__);

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
    public function parsePrompt(string $template, string $lang, string $content): array|string
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
            return [
                'file_id'     => (int)$sql->getValue('id'),
                'category_id' => (int)$sql->getValue('category_id'),
            ];
        }

        return [
            'file_id' => 0,
            'category_id' => 0,
        ];
    }

}

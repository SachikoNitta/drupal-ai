<?php

namespace Drupal\gemini_provider\Plugin\AiProvider;

use Drupal\ai\Attribute\AiProvider;
use Drupal\ai\Base\AiProviderClientBase;
use Drupal\ai\Exception\AiResponseErrorException;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatInterface;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\ChatOutput;
use Drupal\ai\OperationType\Embeddings\EmbeddingsInput;
use Drupal\ai\OperationType\Embeddings\EmbeddingsInterface;
use Drupal\ai\OperationType\Embeddings\EmbeddingsOutput;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\gemini_provider\GeminiChatMessageIterator;
use Gemini\Data\Blob;
use Gemini\Data\Content;
use Gemini\Data\GenerationConfig;
use Gemini\Enums\Role;
use Symfony\Component\Yaml\Yaml;

/**
 * Plugin implementation of the Google's Gemini.
 */
#[AiProvider(
  id: 'gemini',
  label: new TranslatableMarkup('Gemini')
)]
class GeminiProvider extends AiProviderClientBase implements ChatInterface, EmbeddingsInterface {

  /**
   * The Gemini Client.
   *
   * @var \Gemini\Client|null
   */
  protected $client;

  /**
   * API Key.
   *
   * @var string
   */
  protected string $apiKey = '';

  /**
   * Run moderation call, before a normal call.
   *
   * @var bool
   */
  protected bool $moderation = TRUE;

  /**
   * If system message is presented, we store here.
   *
   * @var \Gemini\Data\Content
   */
  protected Content|null $systemMessage = NULL;

  /**
   * {@inheritdoc}
   */
  public function getConfiguredModels(?string $operation_type = NULL, array $capabilities = []): array {
    $this->loadClient();

    $supported_models = [];
    try {
      $models = $this->client->models()->list()->toArray();

      if (!empty($models['models'])) {
        foreach ($models['models'] as $model) {
          // Separate models by operation type.
          switch ($operation_type) {
            case 'embeddings':
              if (!preg_match('/^(models\/)(.)*(embedding-)/i', trim($model['name']))) {
                continue 2;
              }
              break;

            // @todo We need to add other operation types here later.
            default:
              if (preg_match('/^(models\/)(.)*(embedding-)/i', trim($model['name']))) {
                continue 2;
              }
              break;
          }
          $supported_models[$model['name']] = $model['displayName'];
        }
      }
    }
    catch (\JsonException $e) {
      throw new AiResponseErrorException('Couldn\'t fetch gemini models.');
    }

    return $supported_models;
  }

  /**
   * {@inheritdoc}
   */
  public function isUsable(?string $operation_type = NULL, array $capabilities = []): bool {
    if (!$this->getConfig()->get('api_key')) {
      return FALSE;
    }

    if ($operation_type) {
      return in_array($operation_type, $this->getSupportedOperationTypes());
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedOperationTypes(): array {
    // @todo We need to add other operation types here later.
    return [
      'chat',
      'embeddings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfig(): ImmutableConfig {
    return $this->configFactory->get('gemini_provider.settings');
  }

  /**
   * {@inheritdoc}
   */
  public function getApiDefinition(): array {
    $definition = Yaml::parseFile(
      $this->moduleHandler->getModule('gemini_provider')
        ->getPath() . '/definitions/api_defaults.yml'
    );
    return $definition;
  }

  /**
   * {@inheritdoc}
   */
  public function getModelSettings(string $model_id, array $generalConfig = []): array {
    return $generalConfig;
  }

  /**
   * {@inheritdoc}
   */
  public function getSetupData(): array {
    return [
      'key_config_name' => 'api_key',
      'default_models' => [
        'chat' => 'models/gemini-1.5-pro',
        'chat_with_image_vision' => 'models/gemini-1.5-pro',
        'chat_with_complex_json' => 'models/gemini-1.5-pro',
        'embeddings' => 'models/embedding-001',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function embeddingsVectorSize(string $model_id): int {
    return match ($model_id) {
      'models/embedding-001', 'models/text-embedding-004' => 768,
      default => 0,
    };
  }

  /**
   * {@inheritdoc}
   */
  public function setAuthentication(mixed $authentication): void {
    $this->apiKey = $authentication;
    $this->client = NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function chat(array|string|ChatInput $input, string $model_id, array $tags = []): ChatOutput {
    $this->loadClient();

    // Prepare inputs for gemini.
    $chat_input = $input;
    if ($input instanceof ChatInput) {
      $chat_input = [];

      if ($this->systemMessage) {
        $role = Role::from('model');
        $chat_input[] = Content::parse($this->systemMessage, $role);
      }

      foreach ($input->getMessages() as $message) {
        if ($message->getRole() == 'system') {
          $message->setRole('model');
        }

        if ($message->getRole() == 'assistant') {
          $message->setRole('user');
        }

        if (!in_array($message->getRole(), ['model', 'user'])) {
          $error_message = sprintf('The role %s, is not supported by Gemini Provider.', $message->getRole());
          throw new AiResponseErrorException($error_message);
        }

        // Build the content data.
        $content_parts[] = $message->getText();
        // Check for images and added in the content data.
        if (count($message->getImages())) {
          foreach ($message->getImages() as $image) {
            $content_parts[] = Blob::from([
              'mimeType' => $image->getMimeType(),
              'data' => $image->getAsBase64EncodedString(''),
            ]);
          }
        }

        // Format the chat content data.
        $role = Role::from($message->getRole());
        $chat_input[] = Content::parse($content_parts, $role);
      }
    }

    // Set configuration.
    $config = new GenerationConfig(...$this->getConfiguration());

    // Generate response.
    $response = $this->client->generativeModel($model_id)
      ->withGenerationConfig($config);

    if ($this->streamed) {
      $streamedIterator = $response->streamGenerateContent(...$chat_input);
      $message = new GeminiChatMessageIterator($streamedIterator);
    }
    else {
      $response = $response->generateContent(...$chat_input);
      $text = '';
      if (!empty($response->parts())) {
        $text = $response->text();
      }

      $message = new ChatMessage('', $text);
    }

    return new ChatOutput($message, $response, []);
  }

  /**
   * Enables moderation response, for all next coming responses.
   */
  public function enableModeration(): void {
    $this->moderation = TRUE;
  }

  /**
   * Disables moderation response, for all next coming responses.
   */
  public function disableModeration(): void {
    $this->moderation = FALSE;
  }

  /**
   * Gets the raw client.
   *
   * @param string $api_key
   *   If the API key should be hot swapped.
   *
   * @return \Gemini\Client
   *   The Gemini client.
   */
  public function getClient(string $api_key = '') {
    if ($api_key) {
      $this->setAuthentication($api_key);
    }

    $this->loadClient();
    return $this->client;
  }

  /**
   * Loads the Gemini Client with authentication if not initialized.
   */
  protected function loadClient(): void {
    if (!$this->client) {
      if (!$this->apiKey) {
        $this->setAuthentication($this->loadApiKey());
      }

      $this->client = \Gemini::factory()
        ->withApiKey($this->apiKey)
        ->withHttpClient($this->httpClient)
        ->make();
    }
  }

  /**
   * Load API key from key module.
   *
   * @return string
   *   The API key.
   */
  protected function loadApiKey(): string {
    return $this->keyRepository->getKey($this->getConfig()->get('api_key'))
      ->getKeyValue();
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration): void {
    parent::setConfiguration($configuration);

    // Normalize config for Gemini.
    $this->configuration['stopSequences'] = isset($this->configuration['stopSequences'])
      ? explode(',', $this->configuration['stopSequences'])
      : [];

    // Unset formatting for now TODO: need to implement later.
    unset($this->configuration['responseSchema']);
    unset($this->configuration['responseMimeType']);
  }

  /**
   * {@inheritdoc}
   */
  public function setChatSystemRole(string|null $message): void {
    if (!empty($message)) {
      $role = Role::from('model');
      $this->systemMessage = Content::parse($message, $role);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function embeddings(string|EmbeddingsInput $input, string $model_id, array $tags = []): EmbeddingsOutput {
    $this->loadClient();
    // Normalize the input if needed.
    if ($input instanceof EmbeddingsInput) {
      $input = $input->getPrompt();
    }
    try {
      $response = $this->client->embeddingModel($model_id)->embedContent($input);
    }
    catch (\Exception $e) {
      // @todo Handle the exception properly.
      throw $e;
    }

    return new EmbeddingsOutput($response->embedding->values, $response->toArray(), []);
  }

  /**
   * {@inheritdoc}
   */
  public function maxEmbeddingsInput($model_id = ''): int {
    return 2048;
  }

}

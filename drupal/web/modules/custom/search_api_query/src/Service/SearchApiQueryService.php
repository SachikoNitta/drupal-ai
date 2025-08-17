<?php

namespace Drupal\search_api_query\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Query\QueryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;

/**
 * Service for Search API content search operations.
 */
class SearchApiQueryService {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * The logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Constructs a SearchApiQueryService object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ConfigFactoryInterface $config_factory, LoggerChannelFactoryInterface $logger_factory) {
    $this->entityTypeManager = $entity_type_manager;
    $this->configFactory = $config_factory;
    $this->loggerFactory = $logger_factory;
    $this->logger = $logger_factory->get('search_api_query');
  }

  /**
   * Search Drupal content using Search API.
   *
   * @param string $query
   *   The search query text.
   * @param int $limit
   *   The maximum number of results to return.
   * @param array $filters
   *   Optional filters to apply to the search.
   *
   * @return array
   *   Array of search results with content and metadata.
   */
  public function searchContent($query, $limit = 10, array $filters = []) {
    try {
      $this->logger->info('Starting search for query: @query', ['@query' => $query]);
      
      // Get default search index
      $index = $this->getDefaultSearchIndex();
      if (!$index) {
        $this->logger->error('No Search API index found for content search.');
        return [];
      }

      $this->logger->info('Using search index: @index', ['@index' => $index->id()]);
      
      $results = $this->searchByIndex($index->id(), $query, $limit, $filters);
      
      $this->logger->info('Search completed with @count results', ['@count' => count($results)]);
      
      return $results;

    } catch (\Exception $e) {
      $this->logger->error('Content search error: @message', ['@message' => $e->getMessage()]);
      return [];
    }
  }

  /**
   * Get the default Search API index for content search.
   *
   * @return \Drupal\search_api\IndexInterface|null
   *   The default search index or NULL if none found.
   */
  protected function getDefaultSearchIndex() {
    $index_storage = $this->entityTypeManager->getStorage('search_api_index');
    $indexes = $index_storage->loadMultiple();

    // Look for enabled indexes
    foreach ($indexes as $index) {
      /** @var \Drupal\search_api\IndexInterface $index */
      if ($index->status()) {
        return $index;
      }
    }

    return NULL;
  }

  /**
   * Get all available Search API indexes.
   *
   * @return array
   *   Array of available search indexes.
   */
  public function getAvailableIndexes() {
    $index_storage = $this->entityTypeManager->getStorage('search_api_index');
    $indexes = $index_storage->loadMultiple();
    
    $available_indexes = [];
    foreach ($indexes as $index) {
      /** @var \Drupal\search_api\IndexInterface $index */
      if ($index->status()) {
        $available_indexes[$index->id()] = [
          'id' => $index->id(),
          'label' => $index->label(),
          'description' => $index->getDescription(),
          'server' => $index->getServerId(),
        ];
      }
    }
    
    return $available_indexes;
  }

  /**
   * Search content by specific Search API index.
   *
   * @param string $index_id
   *   The Search API index ID.
   * @param string $query
   *   The search query.
   * @param int $limit
   *   Maximum number of results.
   * @param array $filters
   *   Optional filters to apply.
   *
   * @return array
   *   Search results.
   */
  public function searchByIndex($index_id, $query, $limit = 10, array $filters = []) {
    try {
      // Load the Search API index
      $index = $this->entityTypeManager
        ->getStorage('search_api_index')
        ->load($index_id);

      if (!$index) {
        $this->logger->error('Search API index not found: @index_id', ['@index_id' => $index_id]);
        return [];
      }

      // Create a search query
      /** @var \Drupal\search_api\IndexInterface $index */
      $search_query = $index->query();
      
      // Set the search keywords
      $search_query->keys($query);
      
      // Set the limit
      $search_query->range(0, $limit);
      
      // Apply filters if provided
      foreach ($filters as $field => $value) {
        $search_query->addCondition($field, $value);
      }
      
      // Execute the search
      $results = $search_query->execute();
      
      // Process and format results
      return $this->formatSearchResults($results);

    } catch (\Exception $e) {
      $this->logger->error('Search by index error: @message', ['@message' => $e->getMessage()]);
      return [];
    }
  }

  /**
   * Format Search API results into a consistent structure.
   *
   * @param \Drupal\search_api\Query\ResultSetInterface $results
   *   The Search API results.
   *
   * @return array
   *   Formatted search results.
   */
  protected function formatSearchResults($results) {
    $formatted_results = [];
    
    foreach ($results->getResultItems() as $result_item) {
      try {
        // Get the original entity
        $entity = $result_item->getOriginalObject()->getValue();
        
        if (!$entity) {
          continue;
        }
        
        $formatted_result = [
          'score' => $result_item->getScore() ?? 1.0,
          'entity_type' => $entity->getEntityTypeId(),
          'entity_id' => $entity->id(),
          'title' => $entity->label(),
          'url' => $entity->hasLinkTemplate('canonical') ? $entity->toUrl()->toString() : '',
        ];
        
        // Add content summary if available
        if ($entity->hasField('body') && !$entity->get('body')->isEmpty()) {
          $body_field = $entity->get('body')->first();
          if ($body_field) {
            $body_value = $body_field->get('value')->getValue();
            $formatted_result['summary'] = $this->generateSummary($body_value);
          }
        }
        
        // Add creation date
        if ($entity->hasField('created')) {
          $formatted_result['created'] = $entity->get('created')->value;
        }
        
        // Add content type for nodes
        if ($entity->getEntityTypeId() === 'node') {
          $formatted_result['content_type'] = $entity->bundle();
        }
        
        // Add author information if available
        if ($entity->hasField('uid')) {
          $author = $entity->get('uid')->entity;
          if ($author) {
            $formatted_result['author'] = $author->getDisplayName();
          }
        }
        
        $formatted_results[] = $formatted_result;
        
      } catch (\Exception $e) {
        $this->logger->warning('Error processing search result: @message', ['@message' => $e->getMessage()]);
        continue;
      }
    }
    
    $this->logger->info('Search completed. Found @count results.', [
      '@count' => count($formatted_results),
    ]);
    
    return $formatted_results;
  }

  /**
   * Generate a summary from content text.
   *
   * @param string $text
   *   The full text content.
   * @param int $length
   *   Maximum summary length.
   *
   * @return string
   *   The generated summary.
   */
  protected function generateSummary($text, $length = 200) {
    // Strip HTML tags
    $text = strip_tags($text);
    
    // Truncate to specified length
    if (strlen($text) > $length) {
      $text = substr($text, 0, $length);
      $text = substr($text, 0, strrpos($text, ' ')) . '...';
    }
    
    return $text;
  }

}
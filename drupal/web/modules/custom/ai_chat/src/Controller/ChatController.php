<?php

namespace Drupal\ai_chat\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\search_api_query\Service\SearchApiQueryService;
use GuzzleHttp\ClientInterface;

/**
 * Controller for AI Chat functionality.
 */
class ChatController extends ControllerBase {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The Search API query service.
   *
   * @var \Drupal\search_api_query\Service\SearchApiQueryService
   */
  protected $searchApiQueryService;

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * Constructs a ChatController object.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   * @param \Drupal\search_api_query\Service\SearchApiQueryService $search_api_query_service
   *   The Search API query service.
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   */
  public function __construct(AccountProxyInterface $current_user, SearchApiQueryService $search_api_query_service, ClientInterface $http_client) {
    $this->currentUser = $current_user;
    $this->searchApiQueryService = $search_api_query_service;
    $this->httpClient = $http_client;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_user'),
      $container->get('search_api_query.search_service'),
      $container->get('http_client')
    );
  }

  /**
   * Displays the chat page with React component.
   *
   * @return array
   *   A render array.
   */
  public function chatPage() {
    // Check if user is logged in
    if ($this->currentUser->isAnonymous()) {
      return [
        '#markup' => '<div class="ai-chat-login-required">
          <h2>Please log in to access the AI Chat</h2>
          <p><a href="/user/login?destination=/chat">Log in here</a></p>
        </div>',
      ];
    }

    $user = \Drupal\user\Entity\User::load($this->currentUser->id());
    
    return [
      '#markup' => '<div id="ai-chat-app"></div>',
      '#attached' => [
        'library' => ['ai_chat/react_chat'],
        'drupalSettings' => [
          'aiChat' => [
            'userId' => $this->currentUser->id(),
            'userName' => $user->getDisplayName(),
            'userEmail' => $user->getEmail(),
            'apiEndpoint' => '/api/chat',
          ],
        ],
      ],
    ];
  }

  /**
   * API endpoint for chat responses.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with AI chat reply.
   */
  public function apiResponse(Request $request) {
    // Check authentication
    if ($this->currentUser->isAnonymous()) {
      return new JsonResponse(['error' => 'Authentication required'], 401);
    }

    $data = json_decode($request->getContent(), TRUE);
    $message = $data['message'] ?? '';
    $history = $data['history'] ?? [];

    if (empty($message)) {
      return new JsonResponse(['error' => 'Message is required'], 400);
    }

    // Perform content search for AI context
    $search_context = null;
    if (!empty($message)) {
      $search_results = $this->searchApiQueryService->searchContent($message, 5);
      if (!empty($search_results)) {
        $search_context = $this->formatSearchResultsForAI($search_results);
      }
    }

    // Generate AI response using Python API with search context
    $ai_response = $this->generateAIResponse($message, $history, $search_context);

    $user = \Drupal\user\Entity\User::load($this->currentUser->id());

    $response_data = [
      'response' => $ai_response,
      'timestamp' => date('c'),
      'user' => $user->getDisplayName(),
    ];

    // Add search context if available
    if (!empty($search_context)) {
      $response_data['search_context'] = $search_context;
    }

    return new JsonResponse($response_data);
  }

  /**
   * Generate AI response by calling Python FastAPI service.
   *
   * @param string $message
   *   The user message.
   * @param array $history
   *   Previous conversation history.
   * @param string|null $search_context
   *   Search context from Drupal.
   *
   * @return string
   *   The AI response.
   */
  private function generateAIResponse($message, array $history = [], $search_context = null) {
    try {
      // Use host.docker.internal for DDEV to access host machine
      $python_api_url = 'http://host.docker.internal:8001/chat';
      
      // Prepare chat history for Python API
      $formatted_history = [];
      foreach ($history as $msg) {
        $formatted_history[] = [
          'role' => $msg['role'] ?? 'user',
          'content' => $msg['content'] ?? $msg['message'] ?? '',
          'timestamp' => $msg['timestamp'] ?? date('c'),
        ];
      }
      
      $request_data = [
        'message' => $message,
        'history' => $formatted_history,
        'search_context' => $search_context,
        'user_id' => (string) $this->currentUser->id(),
        'system_prompt' => 'You are a helpful AI assistant that answers questions about content in a Drupal website. Use the provided search context when available to give accurate answers. Respond in a conversational and friendly manner.'
      ];
      
      \Drupal::logger('ai_chat')->info('Sending request to Python API: @data', ['@data' => json_encode($request_data)]);
      
      $response = $this->httpClient->request('POST', $python_api_url, [
        'json' => $request_data,
        'headers' => [
          'Content-Type' => 'application/json',
        ],
        'timeout' => 30,
      ]);
      
      $response_data = json_decode($response->getBody()->getContents(), TRUE);
      
      if ($response_data['status'] === 'success') {
        return $response_data['response'];
      } else {
        \Drupal::logger('ai_chat')->error('Python API returned error: @error', ['@error' => $response_data['error'] ?? 'Unknown error']);
        return "I'm sorry, I encountered an error while processing your request. Please try again.";
      }
      
    } catch (\Exception $e) {
      \Drupal::logger('ai_chat')->error('Error calling Python API: @message', ['@message' => $e->getMessage()]);
      return "I'm sorry, I'm having trouble connecting to the AI service right now. Please try again later.";
    }
  }
  
  /**
   * Format search results for AI context.
   *
   * @param array $results
   *   Search results from Search API.
   *
   * @return string
   *   Formatted search context.
   */
  private function formatSearchResultsForAI(array $results) {
    $context = "Relevant content found:\n\n";
    
    foreach ($results as $index => $result) {
      $title = $result['title'] ?? 'Untitled';
      $summary = $result['summary'] ?? 'No summary available';
      $content_type = $result['content_type'] ?? 'content';
      
      $context .= "[{$content_type}] {$title}\n";
      $context .= "{$summary}\n\n";
    }
    
    return $context;
  }


}
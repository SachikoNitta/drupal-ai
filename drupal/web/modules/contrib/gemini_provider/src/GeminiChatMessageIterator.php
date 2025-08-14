<?php

namespace Drupal\gemini_provider;

use Drupal\ai\OperationType\Chat\StreamedChatMessage;
use Drupal\ai\OperationType\Chat\StreamedChatMessageIterator;

/**
 * Gemini Chat message iterator.
 *
 * @package GeminiProvider
 */
class GeminiChatMessageIterator extends StreamedChatMessageIterator {

  /**
   * {@inheritdoc}
   */
  public function getIterator(): \Generator {

    foreach ($this->iterator->getIterator() as $data) {

      $data_array = $data->toArray();

      if (!empty($data->parts())) {
        $text = $data->text();
        $role = $data_array['candidates'][0]['content']['role'];
        $metadata = $data_array['usageMetadata'];

        yield new StreamedChatMessage(
              $role ?? 'user',
              $text ?? '',
              $metadata ?? []
          );
      }
    }
  }

}

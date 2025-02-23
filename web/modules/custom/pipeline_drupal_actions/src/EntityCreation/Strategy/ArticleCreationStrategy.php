<?php
namespace Drupal\pipeline_drupal_actions\EntityCreation\Strategy;

use Drupal\pipeline_drupal_actions\EntityCreation\EntityCreationStrategyBase;

class ArticleCreationStrategy extends EntityCreationStrategyBase {
  public function supportsBundle(string $entityTypeId, string $bundle): bool {
    return $entityTypeId === 'node' && $bundle === 'article';
  }

  public function createEntity(array $stepResults, array &$context): array {
    // Find article content
    $article_content = null;
    foreach ($stepResults as $step) {
      if ($step['output_type'] === 'article_content') {
        $article_content = $step['data'];
        break;
      }
    }

    if (!$article_content) {
      throw new \Exception("Article content not found in the context.");
    }

    // Process content
    $content = preg_replace('/^```json\s*|\s*```$/s', '', $article_content);
    $data = json_decode(trim($content), true);

    if (json_last_error() !== JSON_ERROR_NONE) {
      throw new \Exception("Invalid JSON format: " . json_last_error_msg());
    }

    if (!isset($data['title']) || !isset($data['body'])) {
      throw new \Exception("JSON must contain 'title' and 'body' fields");
    }

    // Remove first H1 tag
    $data['body'] = preg_replace('/<h1>.*?<\/h1>/s', '', $data['body'], 1);
    $data['body'] = ltrim($data['body']);

    // Process featured image
    $media_id = $this->processFeaturedImageData($stepResults);

    // Process SEO metadata
    $seo_content = $this->processSeoMetadata($stepResults);

    // Process taxonomy terms
    $selected_terms = $this->processTerms($stepResults);

    // Create node
    $node = $this->entityTypeManager->getStorage('node')->create([
      'type' => 'article',
      'title' => $seo_content['title'] ?? substr($data['title'], 0, 255),
      'body' => [
        'value' => $data['body'],
        'format' => 'full_html',
        'summary' => $seo_content['summary'] ?? '',
      ],
      'field_category' => $selected_terms,
      // Set status to unpublished by default
      'status' => 0,  // This ensures human review before publishing
    ]);

    if ($media_id) {
      $node->field_media_image = ['target_id' => $media_id];
    }

    $node->save();

    return [
      'nid' => $node->id(),
      'title' => $node->getTitle(),
      'media_id' => $media_id,
    ];
  }

}

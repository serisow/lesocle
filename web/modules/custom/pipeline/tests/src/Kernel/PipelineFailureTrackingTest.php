<?php
/**
 * @file
 * tests/src/Kernel/PipelineFailureTrackingTest.php
 */
namespace Drupal\Tests\pipeline\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\pipeline\Entity\Pipeline;
use Drupal\pipeline_run\Entity\PipelineRun;

class PipelineFailureTrackingTest extends KernelTestBase {
  protected static $modules = [
    'pipeline',
    'pipeline_run',
    'user',
    'system',
    'file',
    'image',
    'options',
    'text'
  ];

  public function setUp(): void {
    parent::setUp();
    // Install config schema for pipeline (it's a config entity)
    $this->installConfig(['pipeline']);

    // Install entity schema for pipeline_run (it's a content entity)
    $this->installEntitySchema('pipeline_run');
    $this->installEntitySchema('file');

    // Install necessary config
    $this->installConfig(['system', 'file']);

    // Install necessary tables
    $this->installSchema('file', ['file_usage']);

    // Rebuild the container to register services and event subscribers
    $this->container = \Drupal::getContainer();
  }

  public function testPipelineDisablingThroughFailedRuns() {
    // Create a pipeline
    $pipeline = Pipeline::create([
      'id' => 'test_pipeline',
      'label' => 'Test Pipeline',
      'status' => TRUE,
    ]);
    $pipeline->save();

    // Simulate 3 failed runs
    for ($i = 1; $i <= 3; $i++) {
      $run = PipelineRun::create([
        'pipeline_id' => $pipeline->id(),
        'status' => 'failed',
      ]);
      $run->save();

      $pipeline->incrementExecutionFailures();
      $pipeline->save();

      // Refresh pipeline from storage
      $pipeline = Pipeline::load($pipeline->id());

      if ($i < 3) {
        $this->assertTrue($pipeline->isEnabled(), "Pipeline should remain enabled after $i failures");
      } else {
        $this->assertFalse($pipeline->isEnabled(), "Pipeline should be disabled after $i failures");
      }
    }

    // Verify state persists after storage
    $pipeline = Pipeline::load($pipeline->id());
    $this->assertFalse($pipeline->isEnabled(), "Pipeline should remain disabled when reloaded");
    $this->assertEquals(3, $pipeline->getExecutionFailures(), "Failure count should persist");
  }

  public function testSchedulingExcludesDisabledPipelines() {
    $container = \Drupal::getContainer();
    $entity_type_manager = $container->get('entity_type.manager');

    // Create and disable a pipeline through failures
    $pipeline = Pipeline::create([
      'id' => 'test_pipeline',
      'label' => 'Test Pipeline',
      'status' => TRUE,
      'schedule_type' => 'recurring',
      'recurring_frequency' => 'daily',
    ]);
    $pipeline->save();

    for ($i = 0; $i < 3; $i++) {
      $pipeline->incrementExecutionFailures();
    }
    $pipeline->save();

    // Query scheduled pipelines
    $query = $entity_type_manager->getStorage('pipeline')->getQuery()
      ->condition('status', TRUE)
      ->condition('execution_failures', 3, '<')
      ->condition('schedule_type', ['one_time', 'recurring'], 'IN');

    $pipeline_ids = $query->execute();

    // Verify our disabled pipeline is not included
    $this->assertNotContains('test_pipeline', $pipeline_ids,
      "Failed pipeline should not be included in scheduled pipelines");
  }
}

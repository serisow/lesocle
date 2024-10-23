<?php
namespace Drupal\Tests\pipeline\Unit\Controller;

use Drupal\pipeline\Plugin\ModelInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\pipeline\Controller\PipelineApiController;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\pipeline\Entity\Pipeline;
use Drupal\pipeline_run\Entity\PipelineRun;
use Drupal\pipeline\Plugin\ModelManager;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Argument;
use Psr\Log\LoggerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\pipeline\Plugin\StepTypeInterface; // Ensure this is correct
use Drupal\pipeline\Entity\LLMConfig; // Added to resolve prophesize error

/**
 * @coversDefaultClass \Drupal\pipeline\Controller\PipelineApiController
 * @group pipeline
 */
class PipelineApiControllerTest extends UnitTestCase {
  use ProphecyTrait;
  // Removed FunctionalTestSetupTrait as it's not needed for UnitTestCase

  protected $entityTypeManager;
  protected $pipelineStorage;
  protected $llmConfigStorage;
  protected $pipelineRunStorage;
  protected $modelManager;
  /**
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface|\Prophecy\Prophecy\ObjectProphecy
   */
  protected $loggerFactory;

  /**
   * @var \Psr\Log\LoggerInterface|\Prophecy\Prophecy\ObjectProphecy
   */
  protected $logger;

  protected $controller;

  protected function setUp(): void {
    parent::setUp();

    $container = new ContainerBuilder();
    \Drupal::setContainer($container);

    $this->entityTypeManager = $this->prophesize(EntityTypeManagerInterface::class);
    $this->pipelineStorage = $this->prophesize(EntityStorageInterface::class);
    $this->llmConfigStorage = $this->prophesize(EntityStorageInterface::class);
    $this->pipelineRunStorage = $this->prophesize(EntityStorageInterface::class); // Initialized
    $this->modelManager = $this->prophesize(ModelManager::class);
    $this->loggerFactory = $this->prophesize(LoggerChannelFactoryInterface::class);
    $this->logger = $this->prophesize(LoggerInterface::class);

    $this->loggerFactory->get('pipeline')->willReturn($this->logger->reveal());

    $this->entityTypeManager->getStorage('pipeline')->willReturn($this->pipelineStorage->reveal());
    $this->entityTypeManager->getStorage('llm_config')->willReturn($this->llmConfigStorage->reveal());
    $this->entityTypeManager->getStorage('pipeline_run')->willReturn($this->pipelineRunStorage->reveal()); // Configured

    $container->set('entity_type.manager', $this->entityTypeManager->reveal());
    $container->set('plugin.manager.model_manager', $this->modelManager->reveal());
    $container->set('logger.factory', $this->loggerFactory->reveal());

    $this->controller = new PipelineApiController(
      $this->entityTypeManager->reveal(),
      $this->modelManager->reveal(),
      $this->loggerFactory->reveal()
    );
  }

  /**
   * @covers ::getScheduledPipelines
   */
  public function testGetScheduledPipelines() {
    $currentDate = new \DateTime('now', new \DateTimeZone('UTC'));
    $startOfDay = $currentDate->setTime(0, 0, 0)->getTimestamp();
    $startOfNextDay = $currentDate->modify('+1 day')->getTimestamp();

    // Mock the pipeline query
    $query = $this->prophesize(QueryInterface::class);
    $orGroup = $this->prophesize(QueryInterface::class);
    $andGroup = $this->prophesize(QueryInterface::class);

    $query->accessCheck()->willReturn($query);
    $query->condition('status', TRUE)->willReturn($query);
    $query->condition('schedule_type', ['one_time', 'recurring'], 'IN')->willReturn($query);

    $query->orConditionGroup()->willReturn($orGroup->reveal());
    $query->andConditionGroup()->willReturn($andGroup->reveal());

    $orGroup->condition(Argument::cetera())->willReturn($orGroup);
    $andGroup->condition(Argument::cetera())->willReturn($andGroup);

    $query->condition($orGroup)->willReturn($query);
    $query->sort('scheduled_time', 'ASC')->willReturn($query);
    $query->execute()->willReturn(['pipeline1', 'pipeline2']);

    $this->pipelineStorage->getQuery()->willReturn($query->reveal());

    // Mock pipelines
    $pipeline1 = $this->createMockPipeline('pipeline1', 'Pipeline 1', 'one_time', $startOfDay + 3600);
    $pipeline2 = $this->createMockPipeline('pipeline2', 'Pipeline 2', 'recurring', null, 'daily', '12:00');

    $this->pipelineStorage->load('pipeline1')->willReturn($pipeline1);
    $this->pipelineStorage->load('pipeline2')->willReturn($pipeline2);

    // Mock pipeline runs query
    $runQuery = $this->prophesize(QueryInterface::class);
    $runQuery->accessCheck()->willReturn($runQuery);
    $runQuery->condition('pipeline_id', Argument::any())->willReturn($runQuery);
    $runQuery->condition('status', 'completed')->willReturn($runQuery);
    $runQuery->sort('end_time', 'DESC')->willReturn($runQuery);
    $runQuery->range(0, 1)->willReturn($runQuery);
    $runQuery->execute()->willReturn(['run1']);

    $this->pipelineRunStorage->getQuery()->willReturn($runQuery->reveal());

    $pipelineRun = $this->prophesize(PipelineRun::class);
    $pipelineRun->getEndTime()->willReturn($startOfDay - 3600);
    $this->pipelineRunStorage->load('run1')->willReturn($pipelineRun->reveal());

    // Mock logger calls
    $this->logger->debug(Argument::cetera())->shouldBeCalled();

    // Get the result
    $response = $this->controller->getScheduledPipelines();
    $result = json_decode($response->getContent(), TRUE);

    // Assertions
    $this->assertCount(2, $result, 'Only scheduled pipelines should be returned');
    $this->assertEquals('pipeline1', $result[0]['id'], 'One-time pipeline should be included');
    $this->assertEquals('pipeline2', $result[1]['id'], 'Recurring pipeline should be included');

    $this->assertEquals($startOfDay + 3600, $result[0]['scheduled_time'], 'One-time pipeline should have correct scheduled time');
    $this->assertEquals('daily', $result[1]['recurring_frequency'], 'Recurring pipeline should have correct frequency');
    $this->assertEquals('12:00', $result[1]['recurring_time'], 'Recurring pipeline should have correct time');
  }

  /**
   * @covers ::getScheduledPipelines
   */
  public function testGetScheduledPipelinesWithNoPipelines() {
    // Mock the query to return an empty result
    $query = $this->prophesize(QueryInterface::class);
    $query->accessCheck()->willReturn($query);
    $query->condition(Argument::cetera())->willReturn($query);
    $query->orConditionGroup()->willReturn($query);
    $query->andConditionGroup()->willReturn($query);
    $query->sort(Argument::cetera())->willReturn($query);
    $query->execute()->willReturn([]);

    $this->pipelineStorage->getQuery()->willReturn($query->reveal());

    // Mock pipeline runs query
    $runQuery = $this->prophesize(QueryInterface::class);
    $runQuery->accessCheck()->willReturn($runQuery);
    $runQuery->condition(Argument::cetera())->willReturn($runQuery);
    $runQuery->orConditionGroup()->willReturn($runQuery);
    $runQuery->andConditionGroup()->willReturn($runQuery);
    $runQuery->sort(Argument::cetera())->willReturn($runQuery);
    $runQuery->range(Argument::cetera())->willReturn($runQuery);
    $runQuery->execute()->willReturn([]);

    $this->pipelineRunStorage->getQuery()->willReturn($runQuery->reveal());

    // Mock logger calls
    $this->logger->debug(Argument::cetera())->shouldBeCalled();

    // Get the result
    $response = $this->controller->getScheduledPipelines();

    // Assert that the response is a JsonResponse
    $this->assertInstanceOf(JsonResponse::class, $response);

    // Assert that the content is an empty array
    $content = json_decode($response->getContent(), true);
    $this->assertIsArray($content);
    $this->assertEmpty($content, 'The response should be an empty array when no pipelines are available');

    // Verify that the debug log was called with empty pipeline_ids
    $this->logger->debug(
      'Scheduled pipelines query executed with parameters: @params',
      Argument::that(function ($argument) {
        $params = json_decode($argument['@params'], true);
        return empty($params['pipeline_ids']);
      })
    )->shouldHaveBeenCalled();

    $this->logger->debug(
      'Scheduled pipelines result: @result',
      Argument::that(function ($argument) {
        return $argument['@result'] === '[]';
      })
    )->shouldHaveBeenCalled();
  }

  public function testGetScheduledPipelinesWithoutCompletedRuns() {
    $currentDate = new \DateTime('now', new \DateTimeZone('UTC'));
    $startOfDay = $currentDate->setTime(0, 0, 0)->getTimestamp();

    // Mock the pipeline query
    $query = $this->prophesize(QueryInterface::class);
    $query->accessCheck()->willReturn($query);
    $query->condition(Argument::cetera())->willReturn($query);
    $query->orConditionGroup()->willReturn($query);
    $query->andConditionGroup()->willReturn($query);
    $query->sort(Argument::cetera())->willReturn($query);
    $query->execute()->willReturn(['pipeline1', 'pipeline2']);

    $this->pipelineStorage->getQuery()->willReturn($query->reveal());

    // Mock pipelines
    $pipeline1 = $this->createMockPipeline('pipeline1', 'Pipeline 1', 'one_time', $startOfDay + 3600);
    $pipeline2 = $this->createMockPipeline('pipeline2', 'Pipeline 2', 'recurring', null, 'daily', '12:00');

    $this->pipelineStorage->load('pipeline1')->willReturn($pipeline1);
    $this->pipelineStorage->load('pipeline2')->willReturn($pipeline2);

    // Mock pipeline runs query to return empty results for both pipelines
    $runQuery = $this->prophesize(QueryInterface::class);
    $runQuery->accessCheck()->willReturn($runQuery);
    $runQuery->condition('pipeline_id', Argument::type('string'))->willReturn($runQuery);
    $runQuery->condition('status', 'completed')->willReturn($runQuery);
    $runQuery->sort('end_time', 'DESC')->willReturn($runQuery);
    $runQuery->range(0, 1)->willReturn($runQuery);
    $runQuery->execute()->willReturn([]);

    $this->pipelineRunStorage->getQuery()->willReturn($runQuery->reveal());

    // Mock logger calls
    $this->logger->debug(Argument::cetera())->shouldBeCalled();

    // Get the result
    $response = $this->controller->getScheduledPipelines();
    $result = json_decode($response->getContent(), TRUE);

    // Assertions
    $this->assertCount(2, $result, 'Two pipelines should be returned');
    $this->assertEquals('pipeline1', $result[0]['id']);
    $this->assertEquals('pipeline2', $result[1]['id']);
    $this->assertEquals(0, $result[0]['last_run_time'], 'Pipeline without completed runs should have last_run_time of 0');
    $this->assertEquals(0, $result[1]['last_run_time'], 'Pipeline without completed runs should have last_run_time of 0');
    $this->assertEquals($startOfDay + 3600, $result[0]['scheduled_time'], 'One-time pipeline should have correct scheduled time');
    $this->assertEquals('daily', $result[1]['recurring_frequency'], 'Recurring pipeline should have correct frequency');
    $this->assertEquals('12:00', $result[1]['recurring_time'], 'Recurring pipeline should have correct time');
  }

  /**
   * @covers ::getScheduledPipelines
   */
  public function testGetScheduledPipelinesWithDifferentStatuses() {
    $currentDate = new \DateTime('now', new \DateTimeZone('UTC'));
    $startOfDay = $currentDate->setTime(0, 0, 0)->getTimestamp();
    $startOfNextDay = $currentDate->modify('+1 day')->getTimestamp();

    // Mock the pipeline query
    $query = $this->prophesize(QueryInterface::class);
    $orGroup = $this->prophesize(QueryInterface::class);
    $andGroup = $this->prophesize(QueryInterface::class);

    $query->accessCheck()->willReturn($query);
    $query->condition('status', TRUE)->willReturn($query);
    $query->condition('schedule_type', ['one_time', 'recurring'], 'IN')->willReturn($query);

    $query->orConditionGroup()->willReturn($orGroup->reveal());
    $query->andConditionGroup()->willReturn($andGroup->reveal());

    // Correctly mock the orConditionGroup to accept the andConditionGroup
    $orGroup->condition($andGroup->reveal(), Argument::any())->willReturn($orGroup);
    $orGroup->condition('schedule_type', 'recurring')->willReturn($orGroup);
    $andGroup->condition(Argument::cetera())->willReturn($andGroup);

    $query->condition($orGroup)->willReturn($query);
    $query->sort('scheduled_time', 'ASC')->willReturn($query);
    $query->execute()->willReturn(['pipeline1', 'pipeline2']);

    $this->pipelineStorage->getQuery()->willReturn($query->reveal());

    // Mock pipelines with different statuses
    $pipeline1 = $this->createMockPipeline('pipeline1', 'Pipeline 1', 'one_time', $startOfDay + 3600, null, null, TRUE);
    $pipeline2 = $this->createMockPipeline('pipeline2', 'Pipeline 2', 'recurring', null, 'daily', '12:00', TRUE);
    $pipeline3 = $this->createMockPipeline('pipeline3', 'Pipeline 3', 'one_time', $startOfDay + 7200, null, null, FALSE); // Disabled

    $this->pipelineStorage->load('pipeline1')->willReturn($pipeline1);
    $this->pipelineStorage->load('pipeline2')->willReturn($pipeline2);
    $this->pipelineStorage->load('pipeline3')->willReturn($pipeline3);

    // Mock pipeline runs query
    $runQuery = $this->prophesize(QueryInterface::class);
    $runQuery->accessCheck()->willReturn($runQuery);
    $runQuery->condition('pipeline_id', Argument::type('string'))->willReturn($runQuery);
    $runQuery->condition('status', 'completed')->willReturn($runQuery);
    $runQuery->sort('end_time', 'DESC')->willReturn($runQuery);
    $runQuery->range(0, 1)->willReturn($runQuery);

    // **Ensure sequential returns for execute()**
    $runQuery->execute()->willReturn(['run1'], ['run2']);

    $this->pipelineRunStorage->getQuery()->willReturn($runQuery->reveal());

    // Mock pipeline runs
    $pipelineRun1 = $this->prophesize(PipelineRun::class);
    $pipelineRun1->getEndTime()->willReturn($startOfDay - 3600);
    $this->pipelineRunStorage->load('run1')->willReturn($pipelineRun1->reveal());

    $pipelineRun2 = $this->prophesize(PipelineRun::class);
    $pipelineRun2->getEndTime()->willReturn($startOfDay - 1800);
    $this->pipelineRunStorage->load('run2')->willReturn($pipelineRun2->reveal());

    // Mock logger calls
    $this->logger->debug(Argument::cetera())->shouldBeCalled();

    // Get the result
    $response = $this->controller->getScheduledPipelines();
    $result = json_decode($response->getContent(), TRUE);

    // Assertions
    $this->assertCount(2, $result, 'Only enabled pipelines should be returned');

    // Verify pipeline1 (enabled, one_time)
    $this->assertEquals('pipeline1', $result[0]['id'], 'Enabled one-time pipeline should be included');
    $this->assertEquals($startOfDay + 3600, $result[0]['scheduled_time'], 'One-time pipeline should have correct scheduled time');
    $this->assertEquals($startOfDay - 3600, $result[0]['last_run_time'], 'One-time pipeline should have correct last_run_time');

    // Verify pipeline2 (enabled, recurring)
    $this->assertEquals('pipeline2', $result[1]['id'], 'Enabled recurring pipeline should be included');
    $this->assertEquals('daily', $result[1]['recurring_frequency'], 'Recurring pipeline should have correct frequency');
    $this->assertEquals('12:00', $result[1]['recurring_time'], 'Recurring pipeline should have correct time');
    $this->assertEquals($startOfDay - 1800, $result[1]['last_run_time'], 'Recurring pipeline should have correct last_run_time');

    // Ensure pipeline3 (disabled) is not included
    foreach ($result as $pipeline) {
      $this->assertNotEquals('pipeline3', $pipeline['id'], 'Disabled pipeline should not be included');
    }
  }

  public function testGetPipeline() {
    $pipelineId = 'test_pipeline';
    $pipeline = $this->prophesize(Pipeline::class);
    $pipeline->id()->willReturn($pipelineId);
    $pipeline->label()->willReturn('Test Pipeline');
    $pipeline->isEnabled()->willReturn(true);
    $pipeline->getInstructions()->willReturn('Test instructions');
    $pipeline->getCreatedTime()->willReturn(1234567890);
    $pipeline->getChangedTime()->willReturn(1234567891);
    $pipeline->getScheduleType()->willReturn('one_time');
    $pipeline->getScheduledTime()->willReturn(1234567892);

    $stepType = $this->prophesize(StepTypeInterface::class); // Ensure interface is imported
    $stepType->getUuid()->willReturn('step1_uuid');
    $stepType->getPluginId()->willReturn('llm_step');
    $stepType->getWeight()->willReturn(0);
    $stepType->getStepDescription()->willReturn('Step 1 description');
    $stepType->getStepOutputKey()->willReturn('step1_output');
    $stepType->getStepOutputType()->willReturn('generic_content');
    $stepType->getConfiguration()->willReturn([
      'data' => [
        'prompt' => 'Test prompt',
        'llm_config' => 'test_llm_config',
        'required_steps' => 'step0_output',
      ],
    ]);

    $pipeline->getStepTypes()->willReturn([$stepType->reveal()]);

    $llmConfig = $this->prophesize(LLMConfig::class); // Ensure LLMConfig is imported
    $llmConfig->id()->willReturn('test_llm_config');
    $llmConfig->label()->willReturn('Test LLM Config');
    $llmConfig->getApiKey()->willReturn('test_api_key');
    $llmConfig->getModelName()->willReturn('test_model');
    $llmConfig->getApiUrl()->willReturn('https://api.example.com');
    $llmConfig->getParameters()->willReturn(['param1' => 'value1']);

    $this->pipelineStorage->load($pipelineId)->willReturn($pipeline->reveal());
    $this->llmConfigStorage->load('test_llm_config')->willReturn($llmConfig->reveal());

    $modelPlugin = $this->prophesize(ModelInterface::class);
    $modelPlugin->getServiceId()->willReturn('test_service');
    $this->modelManager->createInstanceFromModelName('test_model')->willReturn($modelPlugin->reveal());

    $response = $this->controller->getPipeline($pipelineId);

    $this->assertInstanceOf(JsonResponse::class, $response);
    $content = json_decode($response->getContent(), true);

    $this->assertEquals($pipelineId, $content['id']);
    $this->assertEquals('Test Pipeline', $content['label']);
    $this->assertTrue($content['status']);
    $this->assertEquals('Test instructions', $content['instructions']);
    $this->assertEquals(1234567890, $content['created']);
    $this->assertEquals(1234567891, $content['changed']);
    $this->assertEquals('one_time', $content['schedule_type']);
    $this->assertEquals(1234567892, $content['scheduled_time']);

    $this->assertCount(1, $content['steps']);
    $step = $content['steps'][0];
    $this->assertEquals('step1_uuid', $step['id']);
    $this->assertEquals('llm_step', $step['type']);
    $this->assertEquals(0, $step['weight']);
    $this->assertEquals('Step 1 description', $step['step_description']);
    $this->assertEquals('step1_output', $step['step_output_key']);
    $this->assertEquals('generic_content', $step['output_type']);
    $this->assertEquals('Test prompt', $step['prompt']);
    $this->assertEquals('test_llm_config', $step['llm_config']);
    $this->assertEquals('step0_output', $step['required_steps']);

    $this->assertArrayHasKey('llm_service', $step);
    $llmService = $step['llm_service'];
    $this->assertEquals('test_llm_config', $llmService['id']);
    $this->assertEquals('Test LLM Config', $llmService['label']);
    $this->assertEquals('test_api_key', $llmService['api_key']);
    $this->assertEquals('test_model', $llmService['model_name']);
    $this->assertEquals('https://api.example.com', $llmService['api_url']);
    $this->assertEquals(['param1' => 'value1'], $llmService['parameters']);
    $this->assertEquals('test_service', $llmService['service_name']);
  }

  /**
   * @covers ::getPipeline
   */
  public function testGetPipelineRecurring() {
    // Mock the recurring pipeline entity
    $pipelineId = 'recurring_pipeline';
    $pipeline = $this->prophesize(Pipeline::class);
    $pipeline->id()->willReturn($pipelineId);
    $pipeline->label()->willReturn('Recurring Pipeline');
    $pipeline->isEnabled()->willReturn(true);
    $pipeline->getInstructions()->willReturn('Recurring instructions');
    $pipeline->getCreatedTime()->willReturn(1234567890);
    $pipeline->getChangedTime()->willReturn(1234567891);
    $pipeline->getScheduleType()->willReturn('recurring');
    $pipeline->getRecurringFrequency()->willReturn('weekly');
    $pipeline->getRecurringTime()->willReturn('08:00');

    // Mock step types
    $stepType = $this->prophesize(StepTypeInterface::class);
    $stepType->getUuid()->willReturn('step_recurring_uuid');
    $stepType->getPluginId()->willReturn('recurring_step');
    $stepType->getWeight()->willReturn(1);
    $stepType->getStepDescription()->willReturn('Recurring step description');
    $stepType->getStepOutputKey()->willReturn('recurring_step_output');
    $stepType->getStepOutputType()->willReturn('recurring_content');
    $stepType->getConfiguration()->willReturn([
      'data' => [
        'required_steps' => 'previous_step_output',
      ],
    ]);

    $pipeline->getStepTypes()->willReturn([$stepType->reveal()]);

    // Mock pipeline storage load
    $this->pipelineStorage->load($pipelineId)->willReturn($pipeline->reveal());

    // Mock LLMConfig if applicable
    $llmConfig = $this->prophesize(LLMConfig::class);
    $llmConfig->id()->willReturn('recurring_llm_config');
    $llmConfig->label()->willReturn('Recurring LLM Config');
    $llmConfig->getApiKey()->willReturn('recurring_api_key');
    $llmConfig->getModelName()->willReturn('recurring_model');
    $llmConfig->getApiUrl()->willReturn('https://api.recurring.com');
    $llmConfig->getParameters()->willReturn(['paramA' => 'valueA']);

    $this->llmConfigStorage->load('recurring_llm_config')->willReturn($llmConfig->reveal());

    $modelPlugin = $this->prophesize(ModelInterface::class);
    $modelPlugin->getServiceId()->willReturn('recurring_service');
    $this->modelManager->createInstanceFromModelName('recurring_model')->willReturn($modelPlugin->reveal());

    // Invoke the controller method
    $response = $this->controller->getPipeline($pipelineId);
    $content = json_decode($response->getContent(), true);

    // Assertions
    $this->assertInstanceOf(JsonResponse::class, $response, 'Response should be a JsonResponse.');
    $this->assertEquals($pipelineId, $content['id'], 'Pipeline ID should match.');
    $this->assertEquals('Recurring Pipeline', $content['label'], 'Pipeline label should match.');
    $this->assertTrue($content['status'], 'Pipeline status should be enabled.');
    $this->assertEquals('Recurring instructions', $content['instructions'], 'Pipeline instructions should match.');
    $this->assertEquals(1234567890, $content['created'], 'Pipeline created time should match.');
    $this->assertEquals(1234567891, $content['changed'], 'Pipeline changed time should match.');
    $this->assertEquals('recurring', $content['schedule_type'], 'Pipeline schedule type should be recurring.');

    $this->assertEquals('weekly', $content['recurring_frequency'], 'Recurring frequency should match.');
    $this->assertEquals('08:00', $content['recurring_time'], 'Recurring time should match.');

    $this->assertCount(1, $content['steps'], 'There should be one step.');

    $step = $content['steps'][0];
    $this->assertEquals('step_recurring_uuid', $step['id'], 'Step UUID should match.');
    $this->assertEquals('recurring_step', $step['type'], 'Step type should match.');
    $this->assertEquals(1, $step['weight'], 'Step weight should match.');
    $this->assertEquals('Recurring step description', $step['step_description'], 'Step description should match.');
    $this->assertEquals('recurring_step_output', $step['step_output_key'], 'Step output key should match.');
    $this->assertEquals('recurring_content', $step['output_type'], 'Step output type should match.');
    $this->assertEquals('previous_step_output', $step['required_steps'], 'Step required_steps should match.');
  }

  /**
   * @covers ::getPipeline
   */
  public function testGetPipelineNotFound() {
    $nonExistentPipelineId = 'non_existent_pipeline';

    // Mock pipeline storage to return null for the non-existent pipeline ID
    $this->pipelineStorage->load($nonExistentPipelineId)->willReturn(null);

    // Invoke the controller method
    $response = $this->controller->getPipeline($nonExistentPipelineId);

    // Assert that the response is an instance of JsonResponse
    $this->assertInstanceOf(JsonResponse::class, $response, 'Response should be a JsonResponse.');

    // Decode the JSON response content
    $content = json_decode($response->getContent(), true);

    // Assert that the content contains the correct error message
    $this->assertEquals(['error' => 'Pipeline not found'], $content, 'Error message should indicate that the pipeline was not found.');

    // Assert that the response status code is 404
    $this->assertEquals(404, $response->getStatusCode(), 'Response status code should be 404 for not found.');
  }

  /**
   * @covers ::getPipeline
   */
  public function testGetPipelineWithActionStep() {
    // Mock the action_step pipeline entity
    $pipelineId = 'action_step_pipeline';
    $pipeline = $this->prophesize(Pipeline::class);
    $pipeline->id()->willReturn($pipelineId);
    $pipeline->label()->willReturn('Action Step Pipeline');
    $pipeline->isEnabled()->willReturn(true);
    $pipeline->getInstructions()->willReturn('Action step instructions');
    $pipeline->getCreatedTime()->willReturn(1234567890);
    $pipeline->getChangedTime()->willReturn(1234567891);
    $pipeline->getScheduleType()->willReturn('one_time');
    $pipeline->getScheduledTime()->willReturn(1234567892);

    // Mock the 'action_step' step type
    $stepType = $this->prophesize(StepTypeInterface::class);
    $stepType->getUuid()->willReturn('step_action_uuid');
    $stepType->getPluginId()->willReturn('action_step');
    $stepType->getWeight()->willReturn(2);
    $stepType->getStepDescription()->willReturn('Action step description');
    $stepType->getStepOutputKey()->willReturn('action_step_output');
    $stepType->getStepOutputType()->willReturn('action_content');
    $stepType->getConfiguration()->willReturn([
      'data' => [
        'action_config' => 'execute_action',
        'required_steps' => 'previous_step_output',
      ],
    ]);

    $pipeline->getStepTypes()->willReturn([$stepType->reveal()]);

    // Mock pipeline storage load
    $this->pipelineStorage->load($pipelineId)->willReturn($pipeline->reveal());

    // If 'action_step' involves LLMConfig, mock it accordingly
    // (Assuming 'action_step' does not require LLMConfig. If it does, add similar mocks as in testGetPipeline)

    // Invoke the controller method
    $response = $this->controller->getPipeline($pipelineId);
    $content = json_decode($response->getContent(), true);

    // Assertions
    $this->assertInstanceOf(JsonResponse::class, $response, 'Response should be a JsonResponse.');

    // Assert pipeline details
    $this->assertEquals($pipelineId, $content['id'], 'Pipeline ID should match.');
    $this->assertEquals('Action Step Pipeline', $content['label'], 'Pipeline label should match.');
    $this->assertTrue($content['status'], 'Pipeline status should be enabled.');
    $this->assertEquals('Action step instructions', $content['instructions'], 'Pipeline instructions should match.');
    $this->assertEquals(1234567890, $content['created'], 'Pipeline created time should match.');
    $this->assertEquals(1234567891, $content['changed'], 'Pipeline changed time should match.');
    $this->assertEquals('one_time', $content['schedule_type'], 'Pipeline schedule type should be one_time.');

    // Assert scheduled time
    $this->assertEquals(1234567892, $content['scheduled_time'], 'Pipeline scheduled time should match.');

    // Assert steps
    $this->assertCount(1, $content['steps'], 'There should be one step.');

    $step = $content['steps'][0];
    $this->assertEquals('step_action_uuid', $step['id'], 'Step UUID should match.');
    $this->assertEquals('action_step', $step['type'], 'Step type should match.');
    $this->assertEquals(2, $step['weight'], 'Step weight should match.');
    $this->assertEquals('Action step description', $step['step_description'], 'Step description should match.');
    $this->assertEquals('action_step_output', $step['step_output_key'], 'Step output key should match.');
    $this->assertEquals('action_content', $step['output_type'], 'Step output type should match.');
    $this->assertEquals('execute_action', $step['action_config'], 'Action config should match.');
    $this->assertEquals('previous_step_output', $step['required_steps'], 'Step required_steps should match.');
  }

  /**
   * @covers ::getPipeline
   */
  public function testGetPipelineWithGoogleSearchStep() {
    // Mock the google_search pipeline entity
    $pipelineId = 'google_search_pipeline';
    $pipeline = $this->prophesize(Pipeline::class);
    $pipeline->id()->willReturn($pipelineId);
    $pipeline->label()->willReturn('Google Search Pipeline');
    $pipeline->isEnabled()->willReturn(true);
    $pipeline->getInstructions()->willReturn('Google search instructions');
    $pipeline->getCreatedTime()->willReturn(1234567890);
    $pipeline->getChangedTime()->willReturn(1234567891);
    $pipeline->getScheduleType()->willReturn('one_time');
    $pipeline->getScheduledTime()->willReturn(1234567892);

    // Mock the 'google_search' step type
    $stepType = $this->prophesize(StepTypeInterface::class);
    $stepType->getUuid()->willReturn('step_google_search_uuid');
    $stepType->getPluginId()->willReturn('google_search');
    $stepType->getWeight()->willReturn(3);
    $stepType->getStepDescription()->willReturn('Google Search step description');
    $stepType->getStepOutputKey()->willReturn('google_search_step_output');
    $stepType->getStepOutputType()->willReturn('search_content');
    $stepType->getConfiguration()->willReturn([
      'data' => [
        'query' => 'Drupal testing best practices',
        'category' => 'Education',
        'advanced_params' => [
          'language' => 'en',
          'region' => 'US',
        ],
        'required_steps' => 'previous_step_output',
      ],
    ]);

    $pipeline->getStepTypes()->willReturn([$stepType->reveal()]);

    // Mock pipeline storage load
    $this->pipelineStorage->load($pipelineId)->willReturn($pipeline->reveal());

    // If 'google_search' involves LLMConfig, mock it accordingly
    // (Assuming 'google_search' does not require LLMConfig. If it does, add similar mocks as in testGetPipeline)

    // Invoke the controller method
    $response = $this->controller->getPipeline($pipelineId);
    $content = json_decode($response->getContent(), true);

    // Assertions
    $this->assertInstanceOf(JsonResponse::class, $response, 'Response should be a JsonResponse.');

    // Assert pipeline details
    $this->assertEquals($pipelineId, $content['id'], 'Pipeline ID should match.');
    $this->assertEquals('Google Search Pipeline', $content['label'], 'Pipeline label should match.');
    $this->assertTrue($content['status'], 'Pipeline status should be enabled.');
    $this->assertEquals('Google search instructions', $content['instructions'], 'Pipeline instructions should match.');
    $this->assertEquals(1234567890, $content['created'], 'Pipeline created time should match.');
    $this->assertEquals(1234567891, $content['changed'], 'Pipeline changed time should match.');
    $this->assertEquals('one_time', $content['schedule_type'], 'Pipeline schedule type should be one_time.');

    // Assert scheduled time
    $this->assertEquals(1234567892, $content['scheduled_time'], 'Pipeline scheduled time should match.');

    // Assert steps
    $this->assertCount(1, $content['steps'], 'There should be one step.');

    $step = $content['steps'][0];
    $this->assertEquals('step_google_search_uuid', $step['id'], 'Step UUID should match.');
    $this->assertEquals('google_search', $step['type'], 'Step type should match.');
    $this->assertEquals(3, $step['weight'], 'Step weight should match.');
    $this->assertEquals('Google Search step description', $step['step_description'], 'Step description should match.');
    $this->assertEquals('google_search_step_output', $step['step_output_key'], 'Step output key should match.');
    $this->assertEquals('search_content', $step['output_type'], 'Step output type should match.');
    $this->assertEquals([
      'query' => 'Drupal testing best practices',
      'category' => 'Education',
      'advanced_params' => [
        'language' => 'en',
        'region' => 'US',
      ],
    ], $step['google_search_config'], 'Google Search config should match.');
    $this->assertEquals('previous_step_output', $step['required_steps'], 'Step required_steps should match.');
  }

  /**
   * @covers ::create
   */
  public function testCreateMethod() {
    // Mock the ContainerInterface
    $container = $this->prophesize(ContainerInterface::class);

    // Configure the container to return the mocked services
    $container->get('entity_type.manager')->willReturn($this->entityTypeManager->reveal());
    $container->get('plugin.manager.model_manager')->willReturn($this->modelManager->reveal());
    $container->get('logger.factory')->willReturn($this->loggerFactory->reveal());

    // Invoke the create method
    $controller = PipelineApiController::create($container->reveal());

    // Assert that the controller is an instance of PipelineApiController
    $this->assertInstanceOf(PipelineApiController::class, $controller, 'create() should return an instance of PipelineApiController.');
  }


  /**
   * Helper method to create mock pipelines with status.
   *
   * @param string $id
   *   The pipeline ID.
   * @param string $label
   *   The pipeline label.
   * @param string $scheduleType
   *   The schedule type ('one_time' or 'recurring').
   * @param int|null $scheduledTime
   *   The scheduled time for one-time pipelines.
   * @param string|null $recurringFrequency
   *   The recurring frequency for recurring pipelines.
   * @param string|null $recurringTime
   *   The recurring time for recurring pipelines.
   * @param bool $status
   *   The status of the pipeline (TRUE for enabled, FALSE for disabled).
   *
   * @return \Drupal\pipeline\Entity\Pipeline
   *   The mocked pipeline entity.
   */
  private function createMockPipeline($id, $label, $scheduleType, $scheduledTime = null, $recurringFrequency = null, $recurringTime = null, $status = TRUE) {
    $pipeline = $this->prophesize(Pipeline::class);
    $pipeline->id()->willReturn($id);
    $pipeline->label()->willReturn($label);
    $pipeline->getScheduleType()->willReturn($scheduleType);
    $pipeline->getScheduledTime()->willReturn($scheduledTime);
    $pipeline->getRecurringFrequency()->willReturn($recurringFrequency);
    $pipeline->getRecurringTime()->willReturn($recurringTime);
    $pipeline->isEnabled()->willReturn($status);
    return $pipeline->reveal();
  }

}

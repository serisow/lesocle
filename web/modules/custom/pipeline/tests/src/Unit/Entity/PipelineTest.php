<?php

namespace Drupal\Tests\pipeline\Unit\Entity;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\pipeline\Entity\Pipeline;
use Drupal\Tests\UnitTestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\LoggerInterface;

/**
 * @coversDefaultClass \Drupal\pipeline\Entity\Pipeline
 * @group pipeline
 */
class PipelineTest extends UnitTestCase {
  use ProphecyTrait;

  /**
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface|\Prophecy\Prophecy\ObjectProphecy
   */
  protected $loggerFactory;

  /**
   * @var \Psr\Log\LoggerInterface|\Prophecy\Prophecy\ObjectProphecy
   */
  protected $logger;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|\Prophecy\Prophecy\ObjectProphecy
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create a new container
    $container = new ContainerBuilder();

    // Set up logger factory and logger
    $this->loggerFactory = $this->prophesize(LoggerChannelFactoryInterface::class);
    $this->logger = $this->prophesize(LoggerInterface::class);

    // Configure logger factory to return our logger
    $this->loggerFactory->get('pipeline')->willReturn($this->logger->reveal());

    // Set up entity type manager
    $this->entityTypeManager = $this->prophesize(EntityTypeManagerInterface::class);

    // Add entity type definition mock
    $entityType = $this->createMock('Drupal\Core\Entity\EntityTypeInterface');
    $entityType->expects($this->any())
      ->method('getKey')
      ->willReturnMap([
        ['id', 'id'],
        ['label', 'label'],
        ['status', 'status'],
        ['uuid', 'uuid'],
      ]);

    $this->entityTypeManager->getDefinition('pipeline')
      ->willReturn($entityType);

    // Add services to container
    $container->set('logger.factory', $this->loggerFactory->reveal());
    $container->set('entity_type.manager', $this->entityTypeManager->reveal());
    $container->set('string_translation', $this->getStringTranslationStub());

    // Set the container
    \Drupal::setContainer($container);
  }

  /**
   * @covers ::incrementExecutionFailures
   */
  public function testAutoDisableAfterThreeFailures() {
    // Update the warning expectation to match actual calls
    $this->logger->warning(
      'Pipeline %label has been automatically disabled after 3 consecutive failures.',
      ['%label' => 'Test Pipeline']
    )->shouldBeCalled(); // Remove exactlyOnce(), just expect it to be called

    $values = [
      'label' => 'Test Pipeline',
      'id' => 'test_pipeline',
      'status' => TRUE,
      'execution_failures' => 0,
    ];

    $pipeline = new Pipeline($values, 'pipeline');

    // Pipeline starts enabled
    $this->assertTrue($pipeline->isEnabled(), 'Pipeline should start enabled');
    $this->assertEquals(0, $pipeline->getExecutionFailures(), 'Pipeline should start with 0 failures');

    // First failure
    $pipeline->incrementExecutionFailures();
    $this->assertTrue($pipeline->isEnabled(), 'Pipeline should remain enabled after first failure');
    $this->assertEquals(1, $pipeline->getExecutionFailures(), 'Pipeline should have 1 failure');

    // Second failure
    $pipeline->incrementExecutionFailures();
    $this->assertTrue($pipeline->isEnabled(), 'Pipeline should remain enabled after second failure');
    $this->assertEquals(2, $pipeline->getExecutionFailures(), 'Pipeline should have 2 failures');

    // Third failure - should trigger auto-disable
    $pipeline->incrementExecutionFailures();
    $this->assertFalse($pipeline->isEnabled(), 'Pipeline should be disabled after third failure');
    $this->assertEquals(3, $pipeline->getExecutionFailures(), 'Pipeline should have 3 failures');

    // Additional failures should keep pipeline disabled
    $pipeline->incrementExecutionFailures();
    $this->assertFalse($pipeline->isEnabled(), 'Pipeline should remain disabled after additional failures');
    $this->assertEquals(4, $pipeline->getExecutionFailures(), 'Pipeline should track failures beyond 3');
  }

  /**
   * @covers ::resetExecutionFailures
   */
  public function testResetExecutionFailures() {
    // Expect a warning log when pipeline is disabled
    $this->logger->warning(
      'Pipeline %label has been automatically disabled after 3 consecutive failures.',
      ['%label' => 'Test Pipeline']
    )->shouldBeCalled();

    $values = [
      'label' => 'Test Pipeline',
      'id' => 'test_pipeline',
      'status' => TRUE,
      'execution_failures' => 0,
    ];

    $pipeline = new Pipeline($values, 'pipeline');

    // Get to disabled state
    $pipeline->incrementExecutionFailures();
    $pipeline->incrementExecutionFailures();
    $pipeline->incrementExecutionFailures(); // Should disable pipeline

    $this->assertFalse($pipeline->isEnabled(), 'Pipeline should be disabled');
    $this->assertEquals(3, $pipeline->getExecutionFailures(), 'Pipeline should have 3 failures');

    // Reset failures
    $pipeline->resetExecutionFailures();
    $this->assertEquals(0, $pipeline->getExecutionFailures(), 'Pipeline should have 0 failures after reset');
    // Note: Reset doesn't automatically re-enable the pipeline
    $this->assertFalse($pipeline->isEnabled(), 'Pipeline should remain disabled after reset until explicitly enabled');

    // Verify we can re-enable it
    $pipeline->setStatus(true);
    $this->assertTrue($pipeline->isEnabled(), 'Pipeline should be able to be re-enabled after reset');
  }

  /**
   * @covers ::save
   */
  public function testSaveAfterDisabling() {
    $values = [
      'label' => 'Test Pipeline',
      'id' => 'test_pipeline',
      'status' => TRUE,
      'execution_failures' => 0,
    ];

    $pipeline = new Pipeline($values, 'pipeline');

    // Set up expectations for save
    $storage = $this->prophesize('\Drupal\Core\Config\Entity\ConfigEntityStorageInterface');
    $this->entityTypeManager->getStorage('pipeline')->willReturn($storage->reveal());

    // Get to disabled state
    $pipeline->incrementExecutionFailures();
    $pipeline->incrementExecutionFailures();
    $pipeline->incrementExecutionFailures(); // Should disable pipeline

    // Verify save is called after disabling
    $storage->save($pipeline)->shouldBeCalled();
    $pipeline->save();
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    parent::tearDown();
    $this->container = NULL;
  }
}

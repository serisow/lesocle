<?php
namespace Drupal\pipeline\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

class PipelineDashboardController extends ControllerBase
{

  protected $dateFormatter;

  public function __construct(DateFormatterInterface $date_formatter)
  {
    $this->dateFormatter = $date_formatter;
  }

  public static function create(ContainerInterface $container)
  {
    return new static(
      $container->get('date.formatter')
    );
  }

  public function dashboard()
  {
    // Get pipeline statistics
    $stats = $this->getPipelineStats();

    // Get recent pipelines
    $recent_pipelines = $this->getRecentPipelines();

    // Quick actions
    $quick_actions = $this->getQuickActions();

    // System status
    $system_status = $this->getSystemStatus();

    return [
      '#theme' => 'pipeline_dashboard',
      '#stats' => $stats,
      '#recent_pipelines' => $recent_pipelines,
      '#quick_actions' => $quick_actions,
      '#system_status' => $system_status,
    ];
  }

  protected function getPipelineStats()
  {
    // Implement statistics gathering
    return [
      [
        'title' => $this->t('Total Pipelines'),
        'value' => '12',
        'subtitle' => $this->t('4 active'),
      ],
      // Add other stats...
    ];
  }

  protected function getRecentPipelines()
  {
    $pipeline_storage = $this->entityTypeManager()->getStorage('pipeline');
    $pipelines = $pipeline_storage->loadMultiple();
    $recent = [];

    foreach ($pipelines as $pipeline) {
      $recent[] = [
        'label' => $pipeline->label(),
        'steps' => count($pipeline->getStepTypes()),
        'status' => $pipeline->isEnabled() ? 'active' : 'disabled',
        'last_run' => $this->dateFormatter->formatTimeDiffSince($pipeline->getChangedTime()),
        'success_rate' => '95', // Implement actual calculation
        'edit_url' => $pipeline->toUrl('edit-form')->toString(),
      ];
    }

    return $recent;
  }

  protected function getQuickActions()
  {
    return [
      [
        'label' => $this->t('Create Pipeline'),
        'url' => Url::fromRoute('entity.pipeline.add_form')->toString(),
        'icon' => 'plus',
      ],
      // Add other actions...
    ];
  }

  protected function getSystemStatus()
  {
    return [
      [
        'label' => $this->t('API Services'),
        'status' => 'operational',
      ],
      // Add other statuses...
    ];
  }
}

<?php

use Drupal\pipeline_run\Entity\PipelineRun;
use Drupal\pipeline_run\Entity\PipelineStepRun;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\file\Entity\File;

// Function to create a sample log file
function create_sample_log_file($content) {
  $directory = 'public://pipeline_run_logs';
  \Drupal::service('file_system')->prepareDirectory($directory, \Drupal\Core\File\FileSystemInterface::CREATE_DIRECTORY);

  $filename = 'pipeline_run_log_' . \Drupal::time()->getCurrentTime() . '.log';
  $uri = $directory . '/' . $filename;

  $file = File::create([
    'uri' => $uri,
    'filename' => $filename,
  ]);
  file_put_contents($uri, $content);
  $file->save();

  return $file;
}

// Create a PipelineRun entity
$pipeline_run = PipelineRun::create([
  'pipeline_id' => 'create_article_with_image',
  'status' => 'completed',
  'start_time' => (new DrupalDateTime('now - 1 hour'))->getTimestamp(),
  'end_time' => (new DrupalDateTime('now'))->getTimestamp(),
  'created_by' => 1, // Assuming user ID 1 exists (admin)
  'context_data' => json_encode([
    'article_title' => 'Sample Article Title',
    'image_prompt' => 'A beautiful landscape with mountains and a lake',
  ]),
  'triggered_by' => 'manual',
]);

// Create and attach a sample log file
$log_content = "Sample log content for PipelineRun\nStatus: completed";
$log_file = create_sample_log_file($log_content);
$pipeline_run->set('log_file', $log_file);

$pipeline_run->save();

\Drupal::messenger()->addMessage("Created PipelineRun entity with ID: " . $pipeline_run->id());

// Create three PipelineStepRun entities
$step_types = ['llm_step', 'action_step', 'google_search'];
$step_statuses = ['success', 'success', 'failed'];

for ($i = 0; $i < 3; $i++) {
  $start_time = (new DrupalDateTime('now - ' . (60 - $i * 20) . ' minutes'))->getTimestamp();
  $end_time = (new DrupalDateTime('now - ' . (40 - $i * 20) . ' minutes'))->getTimestamp();

  $pipeline_step_run = PipelineStepRun::create([
    'pipeline_run_id' => $pipeline_run->id(),
    'step_uuid' => \Drupal::service('uuid')->generate(),
    'status' => $step_statuses[$i],
    'output' => "Sample output for step " . ($i + 1),
    'error_message' => $step_statuses[$i] === 'failed' ? 'Sample error message for step ' . ($i + 1) : '',
    'sequence' => $i + 1,
    'start_time' => $start_time,
    'end_time' => $end_time,
    'step_type' => $step_types[$i],
  ]);

  $pipeline_step_run->save();

  \Drupal::messenger()->addMessage("Created PipelineStepRun entity with ID: " . $pipeline_step_run->id());
}

\Drupal::messenger()->addMessage("Finished creating PipelineRun and PipelineStepRun entities.");

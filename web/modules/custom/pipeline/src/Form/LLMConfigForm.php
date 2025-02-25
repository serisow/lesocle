<?php
namespace Drupal\pipeline\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Ajax\MessageCommand;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\File\FileExists;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\Url;
use Drupal\pipeline\Plugin\ModelManager;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form controller for LLM Config add/edit forms.
 */
class LLMConfigForm extends EntityForm {
  /**
   * The model manager.
   *
   * @var \Drupal\pipeline\Plugin\ModelManager
   */
  protected $modelManager;

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * The tempstore factory.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected $tempStoreFactory;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructs a new LLMConfigForm.
   */
  public function __construct(
    ModelManager $model_manager,
    ClientInterface $http_client,
    PrivateTempStoreFactory $temp_store_factory,
    LoggerInterface $logger
  ) {
    $this->modelManager = $model_manager;
    $this->httpClient = $http_client;
    $this->tempStoreFactory = $temp_store_factory;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.model_manager'),
      $container->get('http_client'),
      $container->get('tempstore.private'),
      $container->get('logger.factory')->get('pipeline')
    );
  }
  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    /** @var \Drupal\pipeline\Entity\LLMConfig $llm_config */
    $llm_config = $this->entity;

    // Name field for the LLM Config entity.
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('LLM Config Name'),
      '#maxlength' => 255,
      '#default_value' => $llm_config->label(),
      '#description' => $this->t('The name of the LLM configuration.'),
      '#required' => TRUE,
    ];

    // Machine name field (used internally).
    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $llm_config->id(),
      '#machine_name' => [
        'exists' => '\Drupal\pipeline\Entity\LLMConfig::load',
      ],
      '#disabled' => !$llm_config->isNew(),
    ];

    // API Key field.
    $form['api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API Key'),
      '#maxlength' => 255,
      '#default_value' => $llm_config->getApiKey(),
      '#description' => $this->t('The API key used for authentication.'),
      '#required' => TRUE,
    ];

    // THIS IS NOT A TRUE MODEL, BUT CHEAP ENOUGH  FOR DEV ENVIRONMENT
    if ($llm_config->get('model_name') === 'aws_polly_standard') {
      $form['api_secret'] = [
        '#type' => 'textfield',
        '#title' => $this->t('AWS Secret Key'),
        '#default_value' => $llm_config->getApiSecret() ?? '',
        '#required' => TRUE,
        '#description' => $this->t('The AWS Secret Access Key associated with your account.'),
      ];
    }

    $form['model_name'] = [
      '#type' => 'select',
      '#title' => $this->t('Model'),
      '#options' => $this->getModelOptions(),
      '#default_value' => $llm_config->get('model_name'),
      '#required' => TRUE,
      '#ajax' => [
        'callback' => '::updateModelParameters',
        'wrapper' => 'model-parameters',
      ],
    ];



    $form['parameters'] = [
      '#type' => 'container',
      '#tree' => TRUE,
      '#prefix' => '<div id="model-parameters">',
      '#suffix' => '</div>',
    ];

    $this->buildParametersForm($form, $form_state);

    return $form;
  }

  /**
   * Gets the available model options.
   *
   * @return array
   *   An array of model options.
   */
  protected function getModelOptions() {
    $options = [];
    foreach ($this->modelManager->getDefinitions() as $plugin_id => $definition) {
      $options[$definition['model_name']] = $definition['label'];
    }
    return $options;
  }

  /**
   * Builds the parameters form based on the selected model.
   */
  protected function buildParametersForm(array &$form, FormStateInterface $form_state) {
    $model_name = $form_state->getValue('model_name') ?: $this->entity->get('model_name');
    if ($model_name) {
      $plugin = $this->modelManager->createInstanceFromModelName($model_name);
      $default_params = $plugin->getDefaultParameters();
      $current_params = $this->entity->get('parameters') ?: [];

      // Add voice selection for ElevenLabs models : @TODO:SSOW move into a function
      if ($plugin->getServiceId() === 'elevenlabs') {
        $form['parameters']['voice_selection'] = [
          '#type' => 'details',
          '#title' => $this->t('Voice Settings'),
          '#open' => TRUE,
        ];

        $form['parameters']['voice_selection']['voice_id'] = [
          '#type' => 'select',
          '#title' => $this->t('Voice'),
          '#options' => $this->getElevenLabsVoices($this->entity->getApiKey()),
          '#default_value' => $current_params['voice_id'] ?? '',
          '#required' => TRUE,
          '#description' => $this->t('Select the voice to use for text-to-speech generation.'),
        ];

        $form['parameters']['voice_selection']['stability'] = [
          '#type' => 'range',
          '#title' => $this->t('Stability'),
          '#min' => 0,
          '#max' => 100,
          '#step' => 1,
          '#default_value' => ($current_params['stability'] ?? 0.5) * 100,
          '#description' => $this->t('Controls the stability of the voice. Higher values make the voice more consistent.'),
        ];

        $form['parameters']['voice_selection']['similarity_boost'] = [
          '#type' => 'range',
          '#title' => $this->t('Similarity Boost'),
          '#min' => 0,
          '#max' => 100,
          '#step' => 1,
          '#default_value' => ($current_params['similarity_boost'] ?? 0.75) * 100,
          '#description' => $this->t('Controls how much the voice tries to match the original voice.'),
        ];

        // Add preview container for AJAX updates
        $form['parameters']['voice_selection']['preview_container'] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['voice-preview-wrapper']],
        ];

        $form['parameters']['voice_selection']['preview'] = [
          '#type' => 'button',
          '#value' => $this->t('Preview Voice'),
          '#ajax' => [
            'callback' => '::previewVoice',
            'wrapper' => 'voice-preview-wrapper',
            'event' => 'click',
            'progress' => [
              'type' => 'throbber',
              'message' => $this->t('Generating preview...'),
            ],
          ],
          '#states' => [
            'disabled' => [
              ':input[name="parameters[voice_selection][voice_id]"]' => ['value' => ''],
            ],
          ],
        ];
      }

      // Add AWS Polly specific fields: @TODO:SSOW move into a function
      if ($plugin->getServiceId() === 'aws_polly') {
        $form['parameters']['region'] = [
          '#type' => 'select',
          '#title' => $this->t('AWS Region'),
          '#options' => [
            'us-east-1' => 'US East (N. Virginia)',
            'us-east-2' => 'US East (Ohio)',
            'us-west-1' => 'US West (N. California)',
            'us-west-2' => 'US West (Oregon)',
            'eu-west-1' => 'EU (Ireland)',
            'eu-central-1' => 'EU (Frankfurt)',
            'eu-north-1'   =>  'EU (Stockholm)',
            // Add other AWS regions as needed
          ],
          '#default_value' => $current_params['region'] ?? 'us-west-2',
          '#required' => TRUE,
        ];

        $form['parameters']['voice_selection'] = [
          '#type' => 'details',
          '#title' => $this->t('Voice Settings'),
          '#open' => TRUE,
        ];

        $form['parameters']['voice_selection']['voice_id'] = [
          '#type' => 'select',
          '#title' => $this->t('Voice'),
          '#options' => $this->getAWSPollyVoices($current_params),
          '#default_value' => $current_params['voice_id'] ?? 'Joanna',
          '#required' => TRUE,
        ];

        $form['parameters']['voice_selection']['engine'] = [
          '#type' => 'select',
          '#title' => $this->t('Voice Engine'),
          '#options' => [
            'standard' => $this->t('Standard'),
            'neural' => $this->t('Neural (higher quality)'),
          ],
          '#default_value' => $current_params['engine'] ?? 'standard',
          '#description' => $this->t('Neural voices provide higher quality but are only available in certain regions and languages.'),
        ];

        $form['parameters']['voice_selection']['output_format'] = [
          '#type' => 'select',
          '#title' => $this->t('Output Format'),
          '#options' => [
            'mp3' => 'MP3',
            'ogg_vorbis' => 'OGG',
            'pcm' => 'PCM',
          ],
          '#default_value' => $current_params['output_format'] ?? 'mp3',
        ];

        $form['parameters']['voice_selection']['sample_rate'] = [
          '#type' => 'select',
          '#title' => $this->t('Sample Rate'),
          '#options' => [
            '8000' => '8000 Hz',
            '16000' => '16000 Hz',
            '22050' => '22050 Hz',
            '24000' => '24000 Hz',
          ],
          '#default_value' => $current_params['sample_rate'] ?? '22050',
          '#description' => $this->t('The audio frequency specified in Hz.'),
        ];

        // Add preview container for AJAX updates
        $form['parameters']['voice_selection']['preview_container'] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['voice-preview-wrapper']],
        ];

        $form['parameters']['voice_selection']['preview'] = [
          '#type' => 'button',
          '#value' => $this->t('Preview Voice'),
          '#ajax' => [
            'callback' => '::previewAWSPollyVoice',
            'wrapper' => 'voice-preview-wrapper',
            'event' => 'click',
            'progress' => [
              'type' => 'throbber',
              'message' => $this->t('Generating preview...'),
            ],
          ],
        ];
      }

      foreach ($default_params as $key => $default_value) {
        $form['parameters'][$key] = [
          '#type' => 'textfield',
          '#title' => $this->t('@key', ['@key' => ucfirst(str_replace('_', ' ', $key))]),
          '#default_value' => $current_params[$key] ?? $default_value,
        ];
      }
    }
  }

  /**
   * Ajax callback to update model parameters.
   */
  public function updateModelParameters(array $form, FormStateInterface $form_state) {
    return $form['parameters'];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state)
  {
    parent::validateForm($form, $form_state);
    // Custom validation logic can go here if needed.
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $llm_config = $this->entity;
    $status = $llm_config->save();

    if ($status) {
      $this->messenger()->addMessage($this->t('Saved the %label LLM Config.', [
        '%label' => $llm_config->label(),
      ]));
    }
    else {
      $this->messenger()->addMessage($this->t('The %label LLM Config was not saved.', [
        '%label' => $llm_config->label(),
      ]), 'error');
    }

    $form_state->setRedirectUrl($llm_config->toUrl('collection'));
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    /** @var \Drupal\pipeline\Entity\LLMConfig $llm_config */
    $llm_config = $this->entity;
    $model_name = $form_state->getValue('model_name');
    $plugin = $this->modelManager->createInstanceFromModelName($model_name);

    // Process voice settings if they exist
    $parameters = $form_state->getValue('parameters');
    if ($model_name == 'eleven_multilingual_v2' && isset($parameters['voice_selection'])) {
      $parameters = [
        'voice_id' => $parameters['voice_selection']['voice_id'],
        'stability' => $parameters['voice_selection']['stability'] / 100,
        'similarity_boost' => $parameters['voice_selection']['similarity_boost'] / 100,
        'style' => 0,
        'use_speaker_boost' => true
      ];
    }

    $llm_config->setModelName($model_name);
    $llm_config->setApiKey($form_state->getValue('api_key'));
    $llm_config->setParameters($parameters);
    $llm_config->setApiUrl($plugin->getApiUrl());

    if ($model_name == 'aws_polly_standard') {
      $llm_config->setApiSecret($form_state->getValue('api_secret'));
    }
  }


  /**
   * Gets available ElevenLabs voices.
   */
  protected function getElevenLabsVoices($api_key) {
    try {
      $response = $this->httpClient->get('https://api.elevenlabs.io/v1/voices', [
        'headers' => [
          'xi-api-key' => $api_key,
        ],
      ]);

      $data = json_decode($response->getBody(), TRUE);
      $voices = [];
      foreach ($data['voices'] as $voice) {
        $voices[$voice['voice_id']] = $voice['name'];
      }
      return $voices;
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to fetch ElevenLabs voices: @error', ['@error' => $e->getMessage()]);
      return ['' => $this->t('- Failed to load voices -')];
    }
  }

  /**
   * Ajax callback to preview voice.
   */
  public function previewVoice(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();

    try {
      $voice_id = $form_state->getValue(['parameters', 'voice_selection', 'voice_id']);
      $api_key = $form_state->getValue('api_key');

      $stability = $form_state->getValue(['parameters', 'voice_selection', 'stability']) / 100;
      $similarity_boost = $form_state->getValue(['parameters', 'voice_selection', 'similarity_boost']) / 100;

      $preview_text = $this->t('This is a preview of the selected voice.');

      // Call ElevenLabs API to generate preview
      $result = $this->httpClient->post("https://api.elevenlabs.io/v1/text-to-speech/{$voice_id}", [
        'headers' => [
          'xi-api-key' => $api_key,
          'Content-Type' => 'application/json',
        ],
        'json' => [
          'text' => $preview_text,
          'voice_settings' => [
            'stability' => $stability,
            'similarity_boost' => $similarity_boost,
          ],
        ],
      ]);

      if ($result->getStatusCode() === 200) {
        // Save preview file temporarily
        $directory = 'private://voice-previews';
        \Drupal::service('file_system')->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);

        $filename = 'preview_' . $voice_id . '_' . uniqid() . '.mp3';
        $uri = $directory . '/' . $filename;

        $file = \Drupal::service('file.repository')->writeData(
          $result->getBody()->getContents(),
          $uri,
          FileExists::Replace
        );

        if ($file) {
          // Store file ID in tempstore for cleanup
          $tempstore = $this->tempStoreFactory->get('pipeline_voice_previews');
          $fids = $tempstore->get('voice_previews') ?? [];
          $fids[] = $file->id(); // Add the new file ID
          $tempstore->set('voice_previews', $fids); // Save the updated array

          $url = Url::fromRoute('pipeline.voice_preview', ['file' => $filename])
            ->setAbsolute()
            ->toString();

          // Create container with unique timestamp to force audio reload
          $timestamp = time();
          $container_id = 'voice-preview-wrapper-' . $timestamp;
          $markup = '<div class="voice-preview-wrapper" id="' . $container_id . '">';
          $markup .= '<div class="voice-preview-messages"></div>';
          $markup .= '<audio controls><source src="' . $url . '?t=' . $timestamp . '" type="audio/mpeg"></audio>';
          $markup .= '</div>';

          // Replace the entire preview container
          $response->addCommand(new ReplaceCommand('.voice-preview-wrapper', $markup));
          $response->addCommand(new MessageCommand(
            $this->t('Preview generated successfully.'),
            '.voice-preview-messages',
            ['type' => 'status']
          ));
        }
      }
    }
    catch (\Exception $e) {
      $response->addCommand(new MessageCommand(
        $this->t('Failed to generate preview: @error', ['@error' => $e->getMessage()]),
        '.voice-preview-messages',
        ['type' => 'error']
      ));
    }

    return $response;
  }

  /**
   * Gets available AWS Polly voices.
   */
  protected function getAWSPollyVoices($current_params) {
    try {
      // If api_key and api_secret are not yet set, return a placeholder
      $api_key = $this->entity->getApiKey();
      $api_secret = $this->entity->getApiSecret();
      $region = $current_params['region'] ?? 'us-west-2';

      if (empty($api_key) || empty($api_secret)) {
        return ['Joanna' => 'Joanna (Default - configure AWS credentials for more)'];
      }

      // Create Polly client
      $client = new \Aws\Polly\PollyClient([
        'version' => 'latest',
        'region' => $region,
        'credentials' => [
          'key' => $api_key,
          'secret' => $api_secret,
        ],
      ]);

      // Fetch available voices
      $result = $client->describeVoices();
      $voices = [];

      foreach ($result['Voices'] as $voice) {
        $engine_type = isset($voice['SupportedEngines']) ?
          ' (' . implode(', ', $voice['SupportedEngines']) . ')' : '';
        $voices[$voice['Id']] = $voice['Name'] . ' - ' . $voice['LanguageCode'] . $engine_type;
      }

      return $voices;
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to fetch AWS Polly voices: @error', ['@error' => $e->getMessage()]);
      return ['Joanna' => 'Joanna (Default - error fetching voices)'];
    }
  }

  /**
   * Ajax callback to preview AWS Polly voice.
   */
  public function previewAWSPollyVoice(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();

    try {
      $voice_id = $form_state->getValue(['parameters', 'voice_selection', 'voice_id']);
      $api_key = $form_state->getValue('api_key');
      $api_secret = $form_state->getValue('api_secret');;
      $region = $form_state->getValue(['parameters', 'region']) ?? 'us-west-2';
      $engine = $form_state->getValue(['parameters', 'voice_selection', 'engine']) ?? 'standard';
      $output_format = $form_state->getValue(['parameters', 'voice_selection', 'output_format']) ?? 'mp3';
      $sample_rate = $form_state->getValue(['parameters', 'voice_selection', 'sample_rate']) ?? '22050';

      $preview_text = $this->t('This is a preview of the selected AWS Polly voice.');

      // Create Polly client
      $client = new \Aws\Polly\PollyClient([
        'version' => 'latest',
        'region' => $region,
        'credentials' => [
          'key' => $api_key,
          'secret' => $api_secret,
        ],
      ]);

      // Set up parameters for speech synthesis
      $params = [
        'Text' => $preview_text,
        'VoiceId' => $voice_id,
        'OutputFormat' => $output_format,
        'SampleRate' => $sample_rate,
        'Engine' => $engine,
      ];

      // Synthesize speech
      $result = $client->synthesizeSpeech($params);

      // Save preview file temporarily
      $directory = 'private://voice-previews';
      \Drupal::service('file_system')->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);

      $filename = 'preview_' . $voice_id . '_' . uniqid() . '.mp3';
      $uri = $directory . '/' . $filename;

      $file = \Drupal::service('file.repository')->writeData(
        $result->get('AudioStream')->getContents(),
        $uri,
        FileExists::Replace
      );

      if ($file) {
        // Store file ID in tempstore for cleanup
        $tempstore = $this->tempStoreFactory->get('pipeline_voice_previews');
        $fids = $tempstore->get('voice_previews') ?? [];
        $fids[] = $file->id();
        $tempstore->set('voice_previews', $fids);

        $url = Url::fromRoute('pipeline.voice_preview', ['file' => $filename])
          ->setAbsolute()
          ->toString();

        // Create container with unique timestamp to force audio reload
        $timestamp = time();
        $container_id = 'voice-preview-wrapper-' . $timestamp;
        $markup = '<div class="voice-preview-wrapper" id="' . $container_id . '">';
        $markup .= '<div class="voice-preview-messages"></div>';
        $markup .= '<audio controls><source src="' . $url . '?t=' . $timestamp . '" type="audio/mpeg"></audio>';
        $markup .= '</div>';

        // Replace the entire preview container
        $response->addCommand(new ReplaceCommand('.voice-preview-wrapper', $markup));
        $response->addCommand(new MessageCommand(
          $this->t('Preview generated successfully.'),
          '.voice-preview-messages',
          ['type' => 'status']
        ));
      }
    }
    catch (\Exception $e) {
      $response->addCommand(new MessageCommand(
        $this->t('Failed to generate preview: @error', ['@error' => $e->getMessage()]),
        '.voice-preview-messages',
        ['type' => 'error']
      ));
    }

    return $response;
  }

}


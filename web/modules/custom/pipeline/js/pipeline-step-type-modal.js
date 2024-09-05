(function ($, Drupal, once) {

  // Ensure DrupalDialogEvent is only declared once
  if (typeof DrupalDialogEvent === 'undefined') {
    window.DrupalDialogEvent = function DrupalDialogEvent() {};
  }

  $.ajaxSetup({
    timeout: 300000 // 5 minutes in milliseconds
  });

  Drupal.behaviors.promptFormatterStepModal = {
    attach: function (context, settings) {
      once('promptFormatterStepModal', 'body', context).forEach(function (element) {
        $(element).on('submit', '.ui-dialog[class*="pipeline-step-type-"] form[data-dialog-form="true"]', function (e) {
          e.preventDefault();
          var $form = $(this);
          var formData = new FormData(this);
          var pipelineId = drupalSettings.pipelineId;
          var stepType = $form.find('input[name="step_type"]').val();
          var uuid = $form.find('input[name="uuid"]').val();

          var formAction = drupalSettings.path.baseUrl + 'admin/structure/pipelines/' + pipelineId + '/step-type-ajax/' + (stepType || '');
          // Determine if this is an update or add operation
          var method = uuid ? 'PUT' : 'POST';
          formData.append('_method', method);
          formData.append('step_type', stepType);
          formData.append('uuid', uuid);

          // For Prompt Formatter steps, add variables to formData
          if (stepType === 'prompt_formatter') {
            var variables = Drupal.promptFormatterStep.getVariables($form);
            formData.append('variables', JSON.stringify(variables));
          }

          $.ajax({
            url: formAction,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function (response) {
              if (Array.isArray(response)) {
                response.forEach(function(command) {
                  switch (command.command) {
                    case 'closeDialog':
                      $form.closest('.ui-dialog-content').dialog('close');
                      break;
                    case 'redirect':
                      window.location.href = command.url;
                      break;
                    case 'invoke':
                      if (command.method === 'showMessage') {
                        Drupal.behaviors.promptFormatterStepModal.showMessage(command.args[0], command.args[1]);
                      } else if (command.method === 'showFormErrors') {
                        Drupal.behaviors.promptFormatterStepModal.showFormErrors(command.args[0]);
                      }
                      break;
                    case 'insert':
                      if (command.method === 'replaceWith' && command.selector === '.ui-dialog-content') {
                        $('.ui-dialog-content').html(command.data);
                        Drupal.attachBehaviors($('.ui-dialog-content')[0]);
                      }
                      if (command.method === 'replaceWith' && command.selector === '#prompt-formatter-variables-wrapper') {
                        $('#prompt-formatter-variables-wrapper').html(command.data);
                        Drupal.attachBehaviors($('#prompt-formatter-variables-wrapper')[0]);
                      }
                      break;
                  }
                });
                // For Prompt Formatter steps, handle specific response
                if (stepType === 'prompt_formatter') {
                  Drupal.promptFormatterStep.handleResponse(response);
                }
              }

              // Keep these checks for backwards compatibility or if the response structure changes
              if (response.closeModal) {
                $form.closest('.ui-dialog-content').dialog('close');
              }
              if (response.message) {
                Drupal.behaviors.promptFormatterStepModal.showMessage(response.message, response.messageType);
              }
            },
            error: function (jqXHR, textStatus, errorThrown) {
              console.error('AJAX error:', textStatus, errorThrown);
              Drupal.behaviors.promptFormatterStepModal.showMessage('An error occurred. Please try again.', 'error');
            }
          });
        });

        // Handle add variable button click for Prompt Formatter steps
        $(element).on('click', '.ui-dialog[class*="pipeline-step-type-add-modal"] .add-variable', function (e) {
          e.preventDefault();
          var $button = $(this);
          var $form = $button.closest('form');
          var pipelineId = $button.data('pipeline-id') || drupalSettings.pipelineId;
          var stepType = $button.data('step-type') || $form.find('input[name="step_type"]').val();
          var formId = $form.attr('id');

          var variables = Drupal.promptFormatterStep.getVariables($form);

          $.ajax({
            url: Drupal.url('admin/structure/pipelines/' + pipelineId + '/add-prompt-formatter-variable'),
            type: 'POST',
            data: {
              step_type: stepType,
              variables: JSON.stringify(variables),
              form_id: formId
            },
            dataType: 'json',
            success: function (response) {
              Drupal.promptFormatterStep.handleResponse(response);
            }
          });
        });
      });
    },

    showFormErrors: function(errors) {
      $('.ui-dialog-content .form-item--error-message').remove();
      $('.ui-dialog-content .form-item--error').removeClass('form-item--error');

      $.each(errors, function(key, value) {
        var fieldName = key.replace('data][', '');
        var $field = $('[name="data[' + fieldName + ']"]');

        if ($field.length) {
          var $wrapper;

          // Special handling for radio buttons
          if ($field.attr('type') === 'radio') {
            $wrapper = $field.closest('.form-radios');
            // Remove any existing error messages
            $wrapper.find('.form-item--error-message').remove();
            // Add error message before the radio group
            $('<div class="form-item--error-message">' + value + '</div>').insertBefore($wrapper);
          } else {
            $wrapper = $field.closest('.form-item');
            $wrapper.addClass('form-item--error');
            $('<div class="form-item--error-message">' + value + '</div>').insertAfter($field);
          }

          // Scroll to the first error
          if (Object.keys(errors)[0] === key) {
            $wrapper[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
          }
        }
      });

      // Adjust dialog height if needed
      var $dialog = $('.ui-dialog-content');
      $dialog.dialog('option', 'position', { my: 'center', at: 'center', of: window });
    },

    showMessage: function(message, type) {
      var $messages = $('.messages');
      if (!$messages.length) {
        $messages = $('<div class="messages"></div>').insertBefore('.ui-dialog-content');
      }
      $messages.html('<div class="' + type + '">' + message + '</div>');
    }
  };

  Drupal.promptFormatterStep = {
    getVariables: function($form) {
      var variables = {};
      $form.find('.variable-container').each(function(index) {
        var key = $(this).data('variable-key');
        variables[key] = {name: $(this).find('input[type="text"]').val()};
      });
      return variables;
    },

    handleResponse: function(response) {
      if (response.data) {
        $('#prompt-formatter-variables-wrapper').replaceWith(response.data);
        Drupal.attachBehaviors($('#prompt-formatter-variables-wrapper')[0]);
      }
    }
  };

  Drupal.AjaxCommands.prototype.updatePromptFormatterVariables = function(ajax, response, status) {
    if (response.data) {
      $('#prompt-formatter-variables-wrapper').replaceWith(response.data);
      Drupal.attachBehaviors($('#prompt-formatter-variables-wrapper')[0]);
    }
  };
})(jQuery, Drupal, once);

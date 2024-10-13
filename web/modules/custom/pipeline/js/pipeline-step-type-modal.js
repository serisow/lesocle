(function ($, Drupal, once) {

  // Ensure DrupalDialogEvent is only declared once
  if (typeof DrupalDialogEvent === 'undefined') {
    window.DrupalDialogEvent = function DrupalDialogEvent() {};
  }
  $.ajaxSetup({
    timeout: 300000 // 5 minutes in milliseconds
  });
  Drupal.behaviors.pipelineStepTypeModal = {
    attach: function (context, settings) {
      once('pipelineStepTypeModal', 'body', context).forEach(function (element) {
        $(element).on('submit', '.ui-dialog[class*="pipeline-step-type-"] form[data-dialog-form="true"]', function (e) {
          e.preventDefault();
          var $form = $(this);
          var formData = new FormData(this);
          var pipelineId = drupalSettings.pipelineId;
          var stepType = $form.find('input[name="step_type"]').val();
          var uuid = $form.find('input[name="uuid"]').val();

          var formAction = drupalSettings.path.baseUrl + 'admin/structure/pipelines/' + pipelineId + '/step-type-ajax/' + (stepType|| '');
          // Determine if this is an update or add operation
          var method = uuid ? 'PUT' : 'POST';
          formData.append('_method', method);
          formData.append('step_type', stepType); // Add this line
          formData.append('uuid', uuid); // Add this line

          // For Multiple Choice steps, add options to formData
          if (stepType === 'multiple_choice') {
            var options = Drupal.multipleChoiceStep.getOptions($form);
            formData.append('options', JSON.stringify(options));
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
                        Drupal.behaviors.pipelineStepTypeModal.showMessage(command.args[0], command.args[1]);
                      } else if (command.method === 'showFormErrors') {
                        Drupal.behaviors.pipelineStepTypeModal.showFormErrors(command.args[0]);
                      }
                      break;
                    case 'insert':
                      if (command.method === 'replaceWith' && command.selector === '.ui-dialog-content') {
                        $('.ui-dialog-content').html(command.data);
                        Drupal.attachBehaviors($('.ui-dialog-content')[0]);
                      }
                      if (command.method === 'replaceWith' && command.selector === '#multiple-choice-options-wrapper') {
                        $('#multiple-choice-options-wrapper').html(command.data);
                        Drupal.attachBehaviors($('#multiple-choice-options-wrapper')[0]);
                      }
                      break;
                  }
                });
                // For Multiple Choice steps, handle specific response
                if (stepType === 'multiple_choice') {
                  Drupal.multipleChoiceStep.handleResponse(response);
                }
              }

              // Keep these checks for backwards compatibility or if the response structure changes
              if (response.closeModal) {
                $form.closest('.ui-dialog-content').dialog('close');
              }
              if (response.message) {
                Drupal.behaviors.pipelineStepTypeModal.showMessage(response.message, response.messageType);
              }
            },
            error: function (jqXHR, textStatus, errorThrown) {
              console.error('AJAX error:', textStatus, errorThrown);
              Drupal.behaviors.pipelineStepTypeModal.showMessage('An error occurred. Please try again.', 'error');
            }
          });
        });

        // Handle add option button click for Multiple Choice steps
        $(element).on('click', '.ui-dialog[class*="pipeline-step-type-add-modal"] .add-option', function (e) {
          e.preventDefault();
          var $button = $(this);
          var $form = $button.closest('form');
          var pipelineId = $button.data('pipeline-id') || drupalSettings.pipelineId;
          var stepType = $button.data('step-type') || $form.find('input[name="step_type"]').val();
          var formId = $form.attr('id');

          var options = Drupal.multipleChoiceStep.getOptions($form);

          $.ajax({
            url: Drupal.url('admin/structure/pipelines/' + pipelineId + '/add-multiple-choice-option'),
            type: 'POST',
            data: {
              step_type: stepType,
              options: JSON.stringify(options),
              form_id: formId
            },
            dataType: 'json',
            success: function (response) {
              Drupal.multipleChoiceStep.handleResponse(response);
            }
          });
        });

        // Handle prompt template selection
        $(element).on('autocompleteclose', 'input[name="data[prompt_template]"]', function (event) {
          var $input = $(this);
          var $form = $input.closest('form');
          var pipelineId = drupalSettings.pipelineId;
          var stepType = $form.find('input[name="step_type"]').val();
          var inputValue = $input.val();

          // Extract the entity ID from the input value
          var promptTemplateId = inputValue.match(/\((.*?)\)$/);
          promptTemplateId = promptTemplateId ? promptTemplateId[1] : inputValue;

          if (promptTemplateId) {
            $.ajax({
              url: drupalSettings.path.baseUrl + 'admin/structure/pipelines/' + pipelineId + '/update-prompt/' + stepType,
              type: 'POST',
              data: {
                prompt_template_id: promptTemplateId
              },
              dataType: 'json',
              success: function (response) {
                if (response.prompt) {
                  $form.find('textarea[name="data[prompt]"]').val(response.prompt);
                }
              },
              error: function (jqXHR, textStatus, errorThrown) {
                console.error('AJAX error:', textStatus, errorThrown);
              }
            });
          }
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

  Drupal.AjaxCommands.prototype.updateRemainingOptions = function(ajax, response, status) {
    if (response.data) {
      $('#multiple-choice-options-wrapper').replaceWith(response.data);
      Drupal.attachBehaviors($('#multiple-choice-options-wrapper')[0]);
    }
  };
})(jQuery, Drupal, once);

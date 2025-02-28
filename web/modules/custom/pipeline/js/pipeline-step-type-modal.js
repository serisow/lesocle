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
                      break;
                  }
                });
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

})(jQuery, Drupal, once);

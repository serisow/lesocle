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

        // Handle file uploads for image and audio fields
        $(element).on('change', 'input[type="file"][name^="files[data_"]', function(event) {
          var $fileInput = $(this);
          var $form = $fileInput.closest('form');
          var pipelineId = drupalSettings.pipelineId;
          var stepType = $form.find('input[name="step_type"]').val();
          var uuid = $form.find('input[name="uuid"]').val();
          var fieldName = $fileInput.attr('name').match(/files\[(.*?)\]/)[1]; // Extract field name

          // Don't proceed if no file selected
          if (!this.files || !this.files[0]) {
            return;
          }

          // Show upload in progress message
          var $wrapper = $fileInput.closest('.form-managed-file');
          if ($wrapper.find('.file-upload-processing').length === 0) {
            $wrapper.append('<div class="file-upload-processing">Uploading file...</div>');
          }

          // Create FormData for the file upload
          var formData = new FormData();
          formData.append('files[' + fieldName + ']', this.files[0]);
          formData.append('pipeline_id', pipelineId);
          formData.append('step_type', stepType);
          formData.append('field_name', fieldName);
          if (uuid) {
            formData.append('uuid', uuid);
          }

          // Send AJAX request to upload the file
          $.ajax({
            url: drupalSettings.path.baseUrl + 'admin/structure/pipelines/file-upload',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
              // Remove processing message
              $wrapper.find('.file-upload-processing').remove();

              if (response.status === 'success') {
                // Update the form with the file information
                Drupal.behaviors.pipelineStepTypeModal.updateFileField($wrapper, response);
              } else {
                console.error('File upload error:', response.message);
                Drupal.behaviors.pipelineStepTypeModal.showMessage(response.message, 'error');
              }
            },
            error: function(jqXHR, textStatus, errorThrown) {
              $wrapper.find('.file-upload-processing').remove();
              console.error('AJAX error:', textStatus, errorThrown);
              Drupal.behaviors.pipelineStepTypeModal.showMessage('An error occurred during file upload.', 'error');
            }
          });
        });

        // Handle remove file button clicks
        $(element).on('click', '.form-managed-file .remove-file', function(event) {
          event.preventDefault();
          var $button = $(this);
          var $wrapper = $button.closest('.form-managed-file');

          // Reset the file input and remove the file display
          $wrapper.find('input[type="file"]').val('');
          $wrapper.find('.file-display').remove();
          $wrapper.find('input[name$="[fids]"]').val('');

          // Show the file upload input again
          $wrapper.find('input[type="file"]').show();
        });

      });
    },

    updateFileField: function($wrapper, response) {
      // Hide the file input
      $wrapper.find('input[type="file"]').hide();

      // Add or update the hidden input for the file ID
      var fieldName = response.field_name;
      var inputName = fieldName + '[fids]';

      if ($wrapper.find('input[name="' + inputName + '"]').length === 0) {
        $wrapper.append('<input type="hidden" name="' + inputName + '" value="' + response.fid + '">');
      } else {
        $wrapper.find('input[name="' + inputName + '"]').val(response.fid);
      }

      // Determine file type class based on mime type
      var mimeClass = 'file--mime-' + response.mime.replace('/', '-');
      var fileTypeClass = '';

      // Add general file type class
      if (response.mime.indexOf('image/') === 0) {
        fileTypeClass = 'file--image';
      } else if (response.mime.indexOf('audio/') === 0) {
        fileTypeClass = 'file--audio';
      } else if (response.mime.indexOf('video/') === 0) {
        fileTypeClass = 'file--video';
      } else {
        fileTypeClass = 'file--general';
      }

      // Create or update the file display to match Drupal's managed_file style exactly
      var fileUrl = response.url;

      var fileDisplay =
        '<div class="js-form-managed-file form-managed-file is-single no-upload has-value no-meta">' +
        '<div class="form-managed-file__main">' +
        '<span data-drupal-selector="edit-' + fieldName.replace(/_/g, '-') + '-file-' + response.fid + '-filename" class="file ' + mimeClass + ' ' + fileTypeClass + '">' +
        '<a href="' + fileUrl + '" type="' + response.mime + '">' + response.filename + '</a>' +
        ' <span class="file__size">(' + response.filesize + ')</span>' +
        '</span>' +
        '<input data-drupal-selector="edit-' + fieldName.replace(/_/g, '-') + '-file-' + response.fid + '-remove-button" formnovalidate="formnovalidate" ' +
        'class="button--extrasmall remove-button button js-form-submit form-submit" ' +
        'type="submit" name="' + fieldName + '_remove_button" value="Remove">' +
        '</div>' +
        '</div>';

      // Check if we need to replace an existing display or create a new one
      if ($wrapper.find('.form-managed-file').length === 0) {
        $wrapper.append(fileDisplay);
      } else {
        $wrapper.find('.form-managed-file').replaceWith(fileDisplay);
      }

      // Reattach the click handler for the remove button
      $wrapper.find('.remove-button').on('click', function(event) {
        event.preventDefault();
        $wrapper.find('input[type="file"]').val('').show();
        $wrapper.find('.form-managed-file').remove();
        $wrapper.find('input[name="' + inputName + '"]').val('');
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

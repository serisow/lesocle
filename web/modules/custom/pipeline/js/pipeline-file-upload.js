/**
 * @file
 * Provides file upload functionality for pipeline step type forms.
 */
(function ($, Drupal, once) {

  'use strict';

  /**
   * Pipeline file upload behavior.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.pipelineFileUpload = {
    attach: function (context, settings) {
      once('pipelineFileUpload', 'body', context).forEach(function (element) {

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
                Drupal.behaviors.pipelineFileUpload.updateFileField($wrapper, response);
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

    /**
     * Updates the file field with uploaded file information.
     *
     * @param {jQuery} $wrapper
     *   The form wrapper element.
     * @param {Object} response
     *   The server response containing file information.
     */
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
    }
  };

})(jQuery, Drupal, once);

(function ($, Drupal) {
  'use strict';

  Drupal.multipleChoiceStep = Drupal.multipleChoiceStep || {};

  Drupal.multipleChoiceStep.getOptions = function($form) {
    var options = {};
    $form.find('.option-container').each(function(index) {
      var key = 'option_' + (index + 1);
      options[key] = {text: $(this).find('input[type="text"]').val()};
    });
    return options;
  };


  Drupal.multipleChoiceStep.updateOptions = function(element, isAddOption) {
    var $element = $(element);
    var $form = $element.closest('form');
    var pipelineId = $element.data('pipeline-id') || drupalSettings.pipelineId;
    var stepType = $element.data('step-type') || $form.find('input[name="step_type"]').val();
    var formId = $form.attr('id');
    var uuid = $form.find('input[name="uuid"]').val();

    var options = Drupal.multipleChoiceStep.getOptions($form);

    var ajaxUrl = uuid && !isAddOption
      ? Drupal.url('admin/structure/pipelines/' + pipelineId + '/edit-multiple-choice-option/' + uuid)
      : Drupal.url('admin/structure/pipelines/' + pipelineId + '/add-multiple-choice-option');

    $.ajax({
      url: ajaxUrl,
      type: 'POST',
      data: {
        step_type: stepType,
        options: JSON.stringify(options),
        form_id: formId,
        uuid: uuid
      },
      dataType: 'json',
      success: function (response) {
        Drupal.multipleChoiceStep.handleResponse(response);
      }
    });
  };

  Drupal.multipleChoiceStep.handleResponse = function(response) {
    if (Array.isArray(response)) {
      response.forEach(function(command) {
        switch (command.command) {
          case 'insert':
            if (command.method === 'replaceWith' && command.selector === '#multiple-choice-options-wrapper') {
              $('#multiple-choice-options-wrapper').replaceWith(command.data);
              Drupal.attachBehaviors($('#multiple-choice-options-wrapper')[0]);
            }
            break;
        }
      });
    }
  };


})(jQuery, Drupal);

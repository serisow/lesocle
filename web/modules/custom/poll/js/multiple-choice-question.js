(function ($, Drupal) {
  'use strict';

  Drupal.multipleChoiceQuestion = Drupal.multipleChoiceQuestion || {};

  Drupal.multipleChoiceQuestion.getOptions = function($form) {
    var options = {};
    $form.find('.option-container').each(function(index) {
      var key = 'option_' + (index + 1);
      options[key] = {text: $(this).find('input[type="text"]').val()};
    });
    return options;
  };


  Drupal.multipleChoiceQuestion.updateOptions = function(element, isAddOption) {
    var $element = $(element);
    var $form = $element.closest('form');
    var pollId = $element.data('poll-id') || drupalSettings.pollId;
    var questionType = $element.data('question-type') || $form.find('input[name="question_type"]').val();
    var formId = $form.attr('id');
    var uuid = $form.find('input[name="uuid"]').val();

    var options = Drupal.multipleChoiceQuestion.getOptions($form);

    var ajaxUrl = uuid && !isAddOption
      ? Drupal.url('admin/structure/polls/' + pollId + '/edit-multiple-choice-option/' + uuid)
      : Drupal.url('admin/structure/polls/' + pollId + '/add-multiple-choice-option');

    $.ajax({
      url: ajaxUrl,
      type: 'POST',
      data: {
        question_type: questionType,
        options: JSON.stringify(options),
        form_id: formId,
        uuid: uuid
      },
      dataType: 'json',
      success: function (response) {
        Drupal.multipleChoiceQuestion.handleResponse(response);
      }
    });
  };

  Drupal.multipleChoiceQuestion.handleResponse = function(response) {
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

  Drupal.behaviors.multipleChoiceQuestion = {
    attach: function (context, settings) {
      once('multipleChoiceQuestion', 'body', context).forEach(function (element) {

        // @TODO MOVE HERE THE LOGIC FROM poll-question-type-modal
        // Handle add option button click for Multiple Choice questions
        /*$(element).on('click', '.ui-dialog[class*="poll-question-type-add-modal"] .add-option', function (e) {
          e.preventDefault();
          Drupal.multipleChoiceQuestion.updateOptions(this, true);
        });*/
        // Handle update correct answers when we enter text in a new option
       /* $(element).on('change', '.option-container input[type="text"]', function(e) {
          var $element = $(element);
          var $form = $element.find('form[data-dialog-form="true"]');
          var formData = new FormData($form[0]);

          var pollId = $element.data('poll-id') || drupalSettings.pollId;
          var questionType = $element.data('question-type') || $form.find('input[name="question_type"]').val();
          var formId = $form.attr('id');
          var uuid = $form.find('input[name="uuid"]').val();

          var formAction = drupalSettings.path.baseUrl + 'admin/structure/polls/' + pollId + '/question-type-onchange-input';
          // Determine if this is an update or add operation
          var method = uuid ? 'PUT' : 'POST';
          formData.append('_method', method);
          formData.append('question_type', questionType); // Add this line
          formData.append('uuid', uuid); // Add this line
          // For Multiple Choice questions, add options to formData
          if (questionType === 'multiple_choice') {
            var options = Drupal.multipleChoiceQuestion.getOptions($form);
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
                    case 'invoke':
                      if (command.method === 'showMessage') {
                        Drupal.behaviors.pollQuestionTypeModal.showMessage(command.args[0], command.args[1]);
                      } else if (command.method === 'showFormErrors') {
                        Drupal.behaviors.pollQuestionTypeModal.showFormErrors(command.args[0]);
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
                // For Multiple Choice questions, handle specific response
                if (questionType === 'multiple_choice') {
                  Drupal.multipleChoiceQuestion.handleResponse(response);
                }
              }

              if (response.message) {
                Drupal.behaviors.pollQuestionTypeModal.showMessage(response.message, response.messageType);
              }
            },
            error: function (jqXHR, textStatus, errorThrown) {
              console.error('AJAX error:', textStatus, errorThrown);
              Drupal.behaviors.pollQuestionTypeModal.showMessage('An error occurred. Please try again.', 'error');
            }
          });


        });*/
      });
    }
  };

})(jQuery, Drupal);

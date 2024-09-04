(function ($, Drupal) {
  Drupal.behaviors.pollParticipant = {
    attach: function (context, settings) {

      /*once('poll-participant', 'a.use-ajax', context).forEach(function (element) {
        $(element).on('click', function (event) {
          event.preventDefault();
          var $this = $(this);
          alert($this.attr('href'));

          var dialogOptions = {
            width: 'auto',
            dialogClass: 'participant-edit-modal',
          };
          Drupal.ajax({
            url: $this.attr('href'),
            dialogType: 'modal',
            dialog: dialogOptions,
          }).execute();
        });
      });*/
    }
  };

})(jQuery, Drupal);

(function ($, Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.chatFormStream = {
    attach: (context) => {
      let streamElements = $('[data-response]', context);
      once('data-streamed', streamElements).forEach((item) => {
        const element = $(item);
        const form = element.closest('form');
        // Use plain javascript with capture, so we can run first.
        item.addEventListener('mousedown', (event) => {
          // Get the loading message within the form context.
          const loadingMessage = form.find('#ai-loading-message-chat');
          
          // Show the loader only if streaming is checked.
          if (form.find('#edit-streamed').prop('checked') && loadingMessage.length) {
            loadingMessage.show();
          }

          // If streaming is not checked return.
          if (!form.find('#edit-streamed').prop('checked')) {
            return;
          }
          // Check all click events on the button.
          event.preventDefault();
          // Stop the default event.
          event.stopImmediatePropagation();
          event.stopPropagation();
          const clickedElement = $(event.currentTarget);
          const responseField = $('#' + clickedElement.attr('data-response'));
          let data = form.serializeArray();

          // Push an event for the current submission.
          data.push({
            name: event.currentTarget.name,
            value: event.currentTarget.value
          });

          $.ajax({
            url: form.attr('action'),
            method: 'POST',
            data: data,
            xhrFields: {
              onprogress: function (event) {
                responseField.html(event.currentTarget.response.replaceAll("\n", "<br />"));
                responseField.scrollTop(responseField[0].scrollHeight);
              },
            },
            complete: function () {
              // Hide the loading message when the streamed response completes.
              if (loadingMessage.length) {
                loadingMessage.hide();
              }
            }
          });
        }, true);
      });

    // Hide the loading message for non-streamed AJAX responses.
    $(document).ajaxComplete(function (event, xhr, settings) {
      if (
        settings.extraData &&
        settings.extraData._triggering_element_name === 'op'
      ) {
        $('#ai-loading-message-chat').hide();
      }
    });
  },
};
})(jQuery, Drupal, drupalSettings);

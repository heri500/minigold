/**
 * @file
 * JavaScript behaviors for the Data Product module.
 */

(function ($, Drupal) {
  'use strict';

  /**
   * Behavior for Data Product DataTable.
   */
  Drupal.behaviors.dataProductDataTable = {
    attach: function (context, settings) {
      // Initialize DataTable only once
      let baseUrl = drupalSettings.path.baseUrl || '/';
      once('data-product-table', 'table.datatable', context).forEach(function (element) {
        // Initialize DataTable with the options from Drupal settings
        var table = $(element).DataTable();
        console.log('Test');
        // Initialize jeditable for the Stock column (assuming it's the last column)
        table.on('draw', function () {
          // Target the last column (Stock) cells specifically
          $('.stock-editable').editable( function(value, settings) {
            var urlUpdate = baseUrl + 'data_product/update-stock';
            var productId = $(this).attr('id');
            $.ajax({
              url: urlUpdate,
              type: 'POST',
              dataType: 'json',
              data: {
                id_product: productId,
                stock: value
              },
              success: function(response) {
                if (response.success) {
                  Drupal.behaviors.dataProductDataTable.showMessage('Stock updated successfully.', 'success');
                } else {
                  Drupal.behaviors.dataProductDataTable.showMessage('Failed to update stock: ' + response.message, 'error');
                  // Refresh the table to show original values
                  table.ajax.reload(null, false);
                }
              },
              error: function() {
                Drupal.behaviors.dataProductDataTable.showMessage('An error occurred while updating stock.', 'error');
                // Refresh the table to show original values
                table.ajax.reload(null, false);
              }
            });
            // Return the value to display while waiting for server response
            return value;
          },{
            type: 'text',
            placeholder: 'Click to edit',
            style: 'display: inline;',
            width: '80px',
            callback: function(value, settings) {
              // Optional callback after edit is completed
            },
          });

        });
      });
    },

    /**
     * Helper function to display messages.
     */
    showMessage: function(message, type) {
      var messageList = $('.messages');
      if (messageList.length === 0) {
        messageList = $('<div class="messages"></div>');
        $('main').prepend(messageList);
      }

      var messageClass = 'messages--' + (type || 'status');
      var messageElement = $('<div class="' + messageClass + '">' + message + '</div>');
      messageList.append(messageElement);

      // Remove message after 5 seconds
      setTimeout(function() {
        messageElement.fadeOut(function() {
          $(this).remove();
        });
      }, 3000);
    }
  };

})(jQuery, Drupal);

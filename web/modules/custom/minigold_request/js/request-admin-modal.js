/**
 * @file
 * JavaScript for request admin modal and autocomplete functionality.
 */
(function ($, Drupal, once) {
  'use strict';

  /**
   * Attach behaviors to request admin forms and modals.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.requestAdminModal = {
    attach: function (context, settings) {
      // Handle product autocomplete selection.
      $(once('product-autocomplete', 'input[name="product"]', context)).each(function () {
        var $input = $(this);
        var $idField = $('input[name="product_id"]');

        $input.on('autocompleteselect', function (event, ui) {
          // Extract the ID from the value (format is "Product Name (ID)")
          var matches = ui.item.value.match(/\(([^)]+)\)$/);
          if (matches && matches[1]) {
            $idField.val(matches[1]);
          }
        });

        // Clear product_id when the product field is cleared
        $input.on('change', function () {
          if (!$(this).val()) {
            $idField.val('');
          }
        });
      });

      // Set modal size for Bootstrap modal
      $(document).on('dialog:aftercreate', function (event, dialog, $element) {
        // Add Bootstrap 5 classes to the modal
        if ($element.find('form#request-admin-form').length > 0) {
          $element.closest('.ui-dialog').addClass('modal-dialog');
          $element.addClass('modal-content');
          $element.find('.ui-dialog-titlebar').addClass('modal-header');
          $element.find('.ui-dialog-content').addClass('modal-body');

          // Add form-control class to form inputs
          $element.find('input[type="text"], input[type="date"], textarea, select').addClass('form-control');
          $element.find('input[type="submit"], button').addClass('btn');
          $element.find('input[type="submit"]').addClass('btn-primary');
          $element.find('button[value="Cancel"]').addClass('btn-secondary');
        }
      });
    }
  };

})(jQuery, Drupal, once);

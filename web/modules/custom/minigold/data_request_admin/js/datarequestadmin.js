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
      const productTable = $("#selected-products-body", context);
      const selectedProductsData = $("#selected-products-data", context);
      let selectedProducts = [];
      let currentSelectedProduct = [];

      try {
        selectedProducts = JSON.parse(selectedProductsData.val() || "[]");
      } catch (e) {
        selectedProducts = [];
      }

      function updateProductTable() {
        productTable.empty();
        selectedProducts.forEach((product, index) => {
          const row = $("<tr></tr>");
          row.append(`<td>${index + 1}</td>`);
          row.append(`<td>${product.product_code || "-"}</td>`);
          row.append(`<td>${product.product_name}</td>`);
          row.append(`<td>${product.qty}</td>`);
          row.append(`<td class="btn-col"><button type="button" class="btn btn-xs btn-danger delete-product" data-index="${index}"><i class="fa-solid fa-trash"></i></button></td>`);
          productTable.append(row);
        });

        // Update the hidden field
        selectedProductsData.val(JSON.stringify(selectedProducts));
      }

      // Handle product autocomplete selection.
      $(once('product-autocomplete', 'input[name="product"]', context)).each(function (element) {
        var $input = $(this);
        var $idField = $('input[name="product_id"]');
        $input.on('autocompleteselect', function (event, ui) {
          // Extract the ID from the value (format is "Product Name (ID)")
          const selectedProduct = ui.item;
          currentSelectedProduct = selectedProduct;
          $idField.val(selectedProduct.id);
          // Focus on quantity field after selecting a product
          setTimeout(function() {
            $("#product-qty").select();
          }, 100);
        });

        // Clear product_id when the product field is cleared
        $input.on('change', function () {
          if (!$(this).val()) {
            $idField.val('');
          }
        });
      });

      once('search-product', '#button-addon1', context).forEach(function(element) {
        $(element).on("click", function() {
          $("#product-search").focus();
        });
      });

      // Handle add product button
      once('product-add', '#add-product-btn', context).forEach(function(element) {
        $(element).on("click", function() {
          const productId = $("#product-id").val();
          const productSearch = $("#product-search");
          const productName = productSearch.val();
          const productQty = parseInt($("#product-qty").val()) || 1;
          const productCode = currentSelectedProduct.code || '-';

          if (!productId || !productName) {
            alert("Silahkan pilih produk terlebih dahulu");
            return;
          }

          // Check if product already exists in the table
          const existingProductIndex = selectedProducts.findIndex(p => p.product_id === productId);

          if (existingProductIndex >= 0) {
            // Update quantity if product already exists
            selectedProducts[existingProductIndex].qty += productQty;
          } else {
            // Add new product to array
            selectedProducts.push({
              product_id: productId,
              product_code: productCode,
              product_name: productName,
              qty: productQty
            });
          }
          // Clear currentSelectedProduct
          currentSelectedProduct = [];
          // Clear the input fields
          $("#product-id").val("");
          productSearch.val("").data('product-code', '');
          $("#product-qty").val("1");
          productSearch.focus();

          // Update the table
          updateProductTable();
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
      // once qty enter press
      once('qty-keypress', '#product-qty', context).forEach(function(element) {
        $(element).on("keypress", function(e) {
          if (e.which === 13) { // Enter key
            e.preventDefault();
            $("#add-product-btn").click();
          }
        });
      });

      // Handle delete button clicks (using delegation)
      $(document).on("click", ".delete-product", function() {
        const index = $(this).data("index");
        selectedProducts.splice(index, 1);
        updateProductTable();
      });

      $(document).off('click.deleteRequestIcon').on('click.deleteRequestIcon', '.delete-icon', function (e) {
        e.preventDefault();

        let idRequest = $(this).data('id');
        console.log('Delete icon clicked for ID:', idRequest);

        if (!idRequest) {
          alert('Error: Missing Request ID.');
          return;
        }

        // Construct the URL using drupalSettings
        let baseUrl = drupalSettings.path.baseUrl || '/';
        let deleteUrl = baseUrl + 'data-request-admin/delete/' + idRequest;
        const deleteConfirmation = confirm('Yakin ingin menghapus request ini...??!');
        if (deleteConfirmation) {
          window.location.href = deleteUrl;
        }
      });

      // Handle edit icon clicks
      $(document).off('click.editRequesticon').on('click.editRequesticon', '.edit-icon', function (e) {
        e.preventDefault();

        let requestId = $(this).data('id');
        console.log('Edit icon clicked for ID:', requestId);

        if (!requestId) {
          alert('Error: Missing Request ID.');
          return;
        }

        // Create a direct AJAX request to open the modal with the correct ID
        let baseUrl = drupalSettings.path.baseUrl || '/';
        let modalUrl = baseUrl + 'data-request-admin/add/' + requestId;

        // Use Drupal's Ajax framework directly
        Drupal.ajax({
          url: modalUrl,
          dialogType: 'modal',
          dialog: {
            width: 800,
            title: 'Edit Request Admin'
          }
        }).execute();
      });

      // Initialize the table
      updateProductTable();
    }
  };
})(jQuery, Drupal, once);

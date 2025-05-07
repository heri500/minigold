/**
 * @file
 * JavaScript behaviors for the Data Request Admin module.
 */

(function ($, Drupal, once) {
  'use strict';

  /**
   * Behavior for handling product selection and management in the request form.
   */
  Drupal.behaviors.dataRequestAdminProduct = {
    attach: function (context, settings) {
      const productTable = $("#selected-products-body", context);
      const selectedProductsData = $("#selected-products-data", context);
      let selectedProducts = [];

      try {
        selectedProducts = JSON.parse(selectedProductsData.val() || "[]");
      } catch (e) {
        selectedProducts = [];
      }

      // Function to update the table
      function updateProductTable() {
        productTable.empty();
        selectedProducts.forEach((product, index) => {
          const row = $("<tr></tr>");
          row.append(`<td>${index + 1}</td>`);
          row.append(`<td>${product.product_code || "-"}</td>`);
          row.append(`<td>${product.product_name}</td>`);
          row.append(`<td>${product.qty}</td>`);
          row.append(`<td><button type="button" class="btn btn-sm btn-danger delete-product" data-index="${index}"><i class="fa-solid fa-trash"></i></button></td>`);
          productTable.append(row);
        });

        // Update the hidden field
        selectedProductsData.val(JSON.stringify(selectedProducts));
      }

      // Handle autocomplete selection
      once('product-autocomplete', '#product-search', context).forEach(function(element) {
        $(element).on("autocompleteselect", function(event, ui) {
          const selectedProduct = ui.item;
          alert(selectedProduct.id);
          $("#product-id").val(selectedProduct.id);

          // Store product code for later use
          $(element).data('product-code', selectedProduct.code || '-');
          console.log(element);
          // Focus on quantity field after selecting a product
          setTimeout(function() {
            $("#product-qty").select();
          }, 100);
        });
      });

      // Search button handler
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
          const productCode = productSearch.data('product-code') || '-';

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

          // Clear the input fields
          $("#product-id").val("");
          productSearch.val("").data('product-code', '');
          $("#product-qty").val("1");
          productSearch.focus();

          // Update the table
          updateProductTable();
        });
      });

      // Handle delete button clicks (using delegation)
      $(document).on("click", ".delete-product", function() {
        const index = $(this).data("index");
        selectedProducts.splice(index, 1);
        updateProductTable();
      });

      // Initialize the table
      updateProductTable();

      // Add keyboard support
      once('qty-keypress', '#product-qty', context).forEach(function(element) {
        $(element).on("keypress", function(e) {
          if (e.which === 13) { // Enter key
            e.preventDefault();
            $("#add-product-btn").click();
          }
        });
      });

      /** Form submission validation
       * temporary disable because admin no need to add detail request
      once('form-validate', 'form#data-request-admin-add-request-admin', context).forEach(function(element) {
        $(element).on("submit", function(e) {
          if (selectedProducts.length === 0) {
            e.preventDefault();
            alert("Silahkan tambahkan minimal satu produk");
            return false;
          }
        });
      });
      **/
    }
  };
})(jQuery, Drupal, once);

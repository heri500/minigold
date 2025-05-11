/**
 * @file
 * JavaScript for request admin modal and autocomplete functionality.
 */
(function ($, Drupal, once) {
  'use strict';

  // Store the dataTable instance globally within this closure
  let dataTableInstance = null;
  let selectedIds = [];
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

      // Get the table ID from drupalSettings
      const tableId = settings.dataRequestAdmin?.tableId || 'data-request-admin-table';

      // Function to get DataTable instance
      function getDataTableInstance() {
        if ($.fn.DataTable.isDataTable('#' + tableId)) {
          dataTableInstance = $('#' + tableId).DataTable();
          return true;
        }
        return false;
      }

      // Try to get the DataTable instance - use once() to only try once per page load
      once('datatable-instance', 'body', context).forEach(function() {
        // First attempt immediately
        if (getDataTableInstance()) {
          setupDataTableEvents();
        } else {
          // If not found, try again after a delay
          setTimeout(function() {
            if (getDataTableInstance()) {
              setupDataTableEvents();
            }
          }, 300);
        }
      });

      function setupDataTableEvents() {
        if (!dataTableInstance) return;

        // Function to add dt-control class to first column cells
        function addDtControlClass() {
          $('#' + tableId + ' tbody tr td:first-child').addClass('dt-control');
          $('#' + tableId + ' tbody tr').each(function() {
            var $tds = $(this).find('td');

            // Get text from 3rd column
            var dataId = $tds.eq(drupalSettings.dataRequestAdmin.colIdIdx).text().trim();

            // Get data-status from <div> inside 10th column
            var dataStatus = $tds.eq(drupalSettings.dataRequestAdmin.colIdIdx + 6).find('div').data('status');

            // Set attributes on <tr>
            $(this).attr('data-id', dataId);
            if (dataStatus !== undefined) {
              $(this).attr('data-status', dataStatus);
            }
          });
        }

        // Call the function immediately
        addDtControlClass();

        // Format function for child rows - customize this based on your data structure
        function formatChildRow(rowData, rowId) {
          // Return a placeholder while loading data
          return '<div class="child-row-details p-3">' +
            '<div class="text-center child-row-loading">' +
            '<div class="spinner-border text-primary" role="status">' +
            '<span class="visually-hidden">Loading...</span>' +
            '</div>' +
            '<p class="mt-2">Loading request details...</p>' +
            '</div>' +
            '</div>';
        }

        // Load child row data via AJAX
        function loadChildRowData(row, tr, rowId) {
          // Get base URL from drupalSettings
          const baseUrl = drupalSettings.path.baseUrl || '/';
          const detailUrl = baseUrl + 'data-request-admin/detailrequest/' + rowId;

          // Show loading placeholder
          row.child(formatChildRow(null, rowId)).show();
          tr.addClass('shown');

          // Make AJAX request to get detailed data
          $.ajax({
            url: detailUrl,
            type: 'GET',
            dataType: 'json',
            success: function(response) {
              // Format the AJAX response into HTML
              let detailHtml = '<div class="child-row-details p-1">';

              if (response && response.status === 'success') {
                const data = response.data || {};

                // Create a more detailed view with the response data
                detailHtml += '<div class="row">';

                // Add products section if available
                if (data && data.length > 0) {
                  detailHtml += '<div class="row">' +
                    '<div class="col-12">' +
                    '<h5>Requested Products</h5>' +
                    '<table class="table table-sm table-striped">' +
                    '<thead>' +
                    '<tr>' +
                    '<th>#</th>' +
                    '<th>Product Code</th>' +
                    '<th>Product Name</th>' +
                    '<th>Quantity</th>' +
                    '</tr>' +
                    '</thead>' +
                    '<tbody>';

                  data.forEach(function(product, index) {
                    detailHtml += '<tr>' +
                      '<td>' + (index + 1) + '</td>' +
                      '<td>' + (product.brand || '-') + '</td>' +
                      '<td>' + (product.product_name || 'N/A') + '</td>' +
                      '<td>' + (product.qty_request || '0') + '</td>' +
                      '</tr>';
                  });

                  detailHtml += '</tbody></table></div></div>';
                }
              } else {
                // Show error message if response is not successful
                detailHtml += '<div class="alert alert-warning">Could not load request details. Please try again.</div>';
              }

              detailHtml += '</div>';

              // Update the child row with the actual content
              row.child(detailHtml).show();
              tr.addClass('shown');
            },
            error: function() {
              // Handle error
              const errorHtml = '<div class="child-row-details p-3">' +
                '<div class="alert alert-danger">Error loading request details. Please try again later.</div>' +
                '</div>';

              row.child(errorHtml).show();
              tr.addClass('shown');
            }
          });
        }

        // Add click handler for child rows
        $('#' + tableId + ' tbody').off('click', 'td.dt-control').on('click', 'td.dt-control', function() {
          const tr = $(this).closest('tr');
          const row = dataTableInstance.row(tr);

          if (row.child.isShown()) {
            // This row is already open - close it
            row.child.hide();
            tr.removeClass('shown');
          } else {
            // Open this row
            // Get the request ID from the 4th column (index 3)
            const rowData = row.data();
            let requestId;

            if (rowData) {
              // If API data is available, use it
              requestId = rowData[3]; // 4th column (index 3)
            } else {
              // If row data is not available via the API, get it from the DOM
              requestId = tr.find('td:eq(3)').text().trim();
            }

            // Validate requestId
            if (!requestId || requestId === 'N/A' || requestId === '') {
              // If ID is missing, show error message
              row.child('<div class="child-row-details p-3"><div class="alert alert-danger">Error: Could not identify request ID.</div></div>').show();
              tr.addClass('shown');
              return;
            }

            // Load child row data via AJAX
            loadChildRowData(row, tr, requestId);
          }
        });

        // Add event listeners for the dataTable
        dataTableInstance.on('draw.dt', function() {
          // Add dt-control class to first column cells after redraw
          addDtControlClass();
          // Reattach event handlers after table redraw
          attachEventHandlers();
        });

        // Handle AJAX data loading
        dataTableInstance.on('xhr.dt', function() {
          setTimeout(function() {
            addDtControlClass();
          }, 100);
        });

        // Also add class on page length change
        dataTableInstance.on('length.dt', function() {
          setTimeout(function() {
            addDtControlClass();
          }, 100);
        });

        // Add row selection functionality if needed
        enableRowSelection();

        // Add search functionality enhancement
        enhanceTableSearch();

        // Add refresh button functionality
        addRefreshButton();
      }

      // Function to update export buttons state based on row selection
      function updateExportButtonsState() {
        const selectedRows = $('#' + tableId + ' tbody tr.selected');
        const hasSelectedRows = selectedRows.length > 0;
        // Collect ID values from cell no 4 (index 3)
        selectedIds = [];
        selectedRows.each(function () {
          const id = $(this).find('td:eq(3)').text().trim();
          if (id) {
            selectedIds.push(id);
          }
        });
        // Enable/disable all export buttons based on selection state
        $('.export-btn')
          .prop('disabled', !hasSelectedRows)
          .toggleClass('disabled', !hasSelectedRows); // add/remove 'disable' class
      }

      function enableRowSelection() {
        // Add click handler for rows to toggle selection
        $(document).on('click', '#' + tableId + ' tbody tr', function() {
          var status = $(this).data('status');
          if (status === 0) {
            $(this).toggleClass('selected');
            // Update export buttons state whenever row selection changes
            updateExportButtonsState();
          }
        });
      }

      function enhanceTableSearch() {
        // Add debounce function for search input to prevent too many searches
        const searchInput = $('div.dataTables_filter input');
        let searchTimeout;

        searchInput.off('keyup.datatables').on('keyup.datatables', function() {
          clearTimeout(searchTimeout);
          const self = this;

          searchTimeout = setTimeout(function() {
            dataTableInstance.search($(self).val()).draw();
          }, 400);
        });
      }

      function addRefreshButton() {
        // Add a refresh button next to the search box
        const filterDiv = $('div.dt-search');

        if (filterDiv.length && !filterDiv.find('#refresh-datatable').length) {
          const refreshButton = $('<button id="refresh-datatable" class="btn btn-sm btn-outline-secondary ms-2"><i class="fa-solid fa-sync"></i></button>');
          filterDiv.append(refreshButton);

          refreshButton.on('click', function() {
            dataTableInstance.ajax.reload();
          });
        }
      }

      function attachEventHandlers() {
        // Reattach your event handlers for dynamically created elements
        $('.delete-icon').off('click.deleteRequestIcon').on('click.deleteRequestIcon', function(e) {
          e.preventDefault();

          let idRequest = $(this).data('id');

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

        $('.edit-icon').off('click.editRequesticon').on('click.editRequesticon', function(e) {
          e.preventDefault();

          let requestId = $(this).data('id');

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

        $('#add-request-production').off('click.requestProduction').on('click.requestProduction', function(e) {
          e.preventDefault();
          if (!selectedIds) {
            alert('Error: Missing Request ID.');
            return;
          }
          const productionId = selectedIds.join('_');
          // Create a direct AJAX request to open the modal with the correct ID
          let baseUrl = drupalSettings.path.baseUrl || '/';
          let modalUrl = baseUrl + 'data-request-admin/requestproduction/' + productionId;
          // Use Drupal's Ajax framework directly
          Drupal.ajax({
            url: modalUrl,
            dialogType: 'modal',
            dialog: {
              width: 800,
              title: 'Create Request Production'
            }
          }).execute();
        });

      }

      // Add event handler for check selected button
      once('check-selected', '#check-select-row', context).forEach(function(element) {
        $(element).on('click', function() {
          if (!dataTableInstance) {
            // Try to get instance one more time
            getDataTableInstance();
          }

          if (dataTableInstance) {
            // Get all rows with 'selected' class
            const selectedRows = $('#' + tableId + ' tbody tr.selected');

            if (selectedRows.length > 0) {
              // Get the IDs of selected rows
              const selectedIds = [];
              selectedRows.each(function() {
                // Assuming the ID is in the 3rd column (index 2)
                // Adjust this index based on your actual table structure
                const id = $(this).find('td:eq(3)').text();
                if (id) {
                  selectedIds.push(id);
                }
              });

              if (selectedIds.length > 0) {
                alert('Selected ' + selectedIds.length + ' row(s): ' + selectedIds.join(', '));
                // You can process the selected IDs here or send them to the server
              } else {
                alert('Selected rows do not contain valid IDs');
              }
            } else {
              alert('No rows selected. Click on rows to select them.');
            }
          } else {
            alert('DataTable instance not available');
          }
        });
      });

      // Function to export selected rows
      function exportSelectedRows(format) {
        if (!dataTableInstance) return;

        const selectedRows = $('#' + tableId + ' tbody tr.selected');
        if (selectedRows.length === 0) {
          alert('No rows selected for export');
          return;
        }

        // Get data from selected rows
        const exportData = [];
        const headers = [];

        // Get headers
        $('#' + tableId + ' thead th').each(function() {
          headers.push($(this).text().trim());
        });

        // Skip first two columns if they're action buttons
        const startCol = headers[0] === '' && headers[1] === '' ? 2 : 0;
        const exportHeaders = headers.slice(startCol);

        // Get data from each selected row
        selectedRows.each(function() {
          const rowData = {};
          $(this).find('td').each(function(index) {
            if (index >= startCol) {
              rowData[exportHeaders[index - startCol]] = $(this).text().trim();
            }
          });
          exportData.push(rowData);
        });

        // Export based on format
        if (format === 'json') {
          const jsonString = JSON.stringify(exportData, null, 2);
          downloadFile(jsonString, 'request_admin_export.json', 'application/json');
        } else if (format === 'csv') {
          const csvContent = convertToCSV(exportData, exportHeaders);
          downloadFile(csvContent, 'request_admin_export.csv', 'text/csv');
        }
      }

      // Helper function to convert data to CSV
      function convertToCSV(objArray, headers) {
        const array = typeof objArray !== 'object' ? JSON.parse(objArray) : objArray;
        let str = headers.join(',') + '\r\n';

        for (let i = 0; i < array.length; i++) {
          let line = '';
          for (let index in headers) {
            if (line !== '') line += ',';

            // Handle fields with commas by quoting them
            let field = array[i][headers[index]] || '';
            if (field.includes(',')) {
              field = '"' + field + '"';
            }

            line += field;
          }
          str += line + '\r\n';
        }
        return str;
      }

      // Helper function to download file
      function downloadFile(content, fileName, mimeType) {
        const a = document.createElement('a');
        const blob = new Blob([content], {type: mimeType});
        a.href = window.URL.createObjectURL(blob);
        a.download = fileName;
        a.click();
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

      // Add export buttons if needed
      /*once('export-buttons', '#add-request-production', context).forEach(function(element) {
        // Create export buttons
        const exportCsvBtn = $('<button class="btn btn-success me-2 export-btn" disabled>Export CSV</button>');
        const exportJsonBtn = $('<button class="btn btn-info me-2 export-btn" disabled>Export JSON</button>');

        // Add buttons after the check selected button
        $(element).after(exportJsonBtn).after(exportCsvBtn);

        // Add click handlers
        exportCsvBtn.on('click', function() {
          exportSelectedRows('csv');
        });

        exportJsonBtn.on('click', function() {
          exportSelectedRows('json');
        });

        // Initialize buttons state
        updateExportButtonsState();
      });*/

      // Initialize event handlers for the first time
      attachEventHandlers();

      // Handle cancel button click to close dialog
      once('cancel-request-btn', '#cancel-request', context).forEach(function(element) {
        $(element).on('click', function(e) {
          e.preventDefault();

          // Close the modal dialog
          if (Drupal.dialog) {
            // Find the closest dialog container and close it
            const $dialog = $(this).closest('.ui-dialog-content');
            if ($dialog.length) {
              $dialog.dialog('close');
            } else {
              // Fallback to closing all dialogs
              $('.ui-dialog-content').dialog('close');
            }
          }
        });
      });

      // Initialize the table
      updateProductTable();
    }
  };
})(jQuery, Drupal, once);

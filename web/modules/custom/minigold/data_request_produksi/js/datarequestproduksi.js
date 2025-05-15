(function ($, Drupal, once) {
  'use strict';

  // Store the dataTable instance globally within this closure
  let dataTableInstance = null;
  Drupal.behaviors.requestAdminModal = {
    attach: function (context, settings) {
      // Get the table ID from drupalSettings
      const tableId = settings.dataRequestAdmin?.tableId || 'data-request-production-table';
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
          $('#' + tableId + ' tbody tr').each(function () {
            var $tds = $(this).find('td');

            // Get text from 3rd column
            var dataId = $tds.eq(drupalSettings.dataRequestProduksi.colIdIdx).text().trim();

            // Get data-status from <div> inside 10th column
            var dataStatus = $tds.eq(drupalSettings.dataRequestProduksi.colIdIdx + 4).find('div').data('status');

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
          const detailUrl = baseUrl + 'data-request-produksi/detailrequest/' + rowId;

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
                    '<h5>Requested Production</h5>' +
                    '<table class="table table-sm table-striped">' +
                    '<thead>' +
                    '<tr>' +
                    '<th>#</th>' +
                    '<th>Kepingan</th>' +
                    '<th>Gramasi</th>' +
                    '<th>Jumlah Request</th>' +
                    '<th>Jumlah Produksi</th>' +
                    '</tr>' +
                    '</thead>' +
                    '<tbody>';

                  data.forEach(function(product, index) {
                    detailHtml += '<tr>' +
                      '<td>' + (index + 1) + '</td>' +
                      '<td>' + (product.produk_produksi || 'N/A') + '</td>' +
                      '<td>' + (product.gramasi || 'N/A') + '</td>' +
                      '<td>' + (product.total_qty_actual || '0') + '</td>' +
                      '<td>' + (product.total_qty || '0') + '</td>' +
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
                '<div class="alert alert-danger">Error loading request production details. Please try again later.</div>' +
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
          // attachEventHandlers(); // open when function already applied
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

      enhanceTableSearch();
      addRefreshButton();

      function attachEventHandlers() {
        $('.edit-icon').off('click.editRequesticon').on('click.editRequesticon', function(e) {
          e.preventDefault();

          let requestId = $(this).data('id');

          if (!requestId) {
            alert('Error: Missing Request Production ID.');
            return;
          }

          // Create a direct AJAX request to open the modal with the correct ID
          let baseUrl = drupalSettings.path.baseUrl || '/';
          let modalUrl = baseUrl + 'data-request-produksi/add/' + requestId;

          // Use Drupal's Ajax framework directly
          Drupal.ajax({
            url: modalUrl,
            dialogType: 'modal',
            dialog: {
              width: 800,
              title: 'Edit Request Produksi'
            }
          }).execute();
        });
      }

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
        let modalUrl = baseUrl + 'data-request-produksi/add/' + requestId;

        // Use Drupal's Ajax framework directly
        Drupal.ajax({
          url: modalUrl,
          dialogType: 'modal',
          dialog: {
            width: 800,
            title: 'Edit Request Produksi'
          }
        }).execute();
      });
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
    }
  }
})(jQuery, Drupal, once);

(function ($, Drupal, once) {
  // Check if the behavior has already been defined to prevent duplicate declarations
  if (Drupal.behaviors.dataRequestAdmin) {
    return;
  }

  // Define any constants in a namespaced object to avoid global conflicts
  if (!window.DataRequestAdmin) {
    window.DataRequestAdmin = {
      Constants: {
        POPOVER_OPEN_DELAY: 300 // Put your actual value here
      },
      // Add a flag to track if modal is currently open to prevent multiple instances
      isModalOpen: false
    };
  }

  Drupal.behaviors.dataRequestAdmin = {
    attach: function (context, settings) {
      // Use once() for all elements to prevent duplicate attachments
      once('data-request-add', '#add-new-request', context).forEach(function(element) {
        $(element).on('click', function (e) {
          e.preventDefault();
          openRequestModal(0);
        });
      });

      // Use event delegation for edit buttons, with a tracking system to prevent multiple executions
      // This works better for elements in DataTables or dynamically created elements
      $(document).off('click.editRequestIcon', '.edit-icon').on('click.editRequestIcon', '.edit-icon', function(e) {
        e.preventDefault();
        let idRequest = $(this).data('id');
        console.log('Edit icon clicked for ID:', idRequest);

        if (!idRequest) {
          alert('Error: Missing Request ID.');
          return;
        }
        openRequestModal(idRequest);
      });

      function openRequestModal(idRequest) {
        // Prevent multiple modals from opening simultaneously
        if (window.DataRequestAdmin.isModalOpen) {
          $('#requestModal').remove();
        }

        console.log('Opening modal for ID:', idRequest);
        window.DataRequestAdmin.isModalOpen = true;

        // Remove any existing modals first
        $('#requestModal').remove();

        const modalHtml = `
          <div class="modal fade" id="requestModal" tabindex="-1" aria-labelledby="requestModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-md-custom">
              <div class="modal-content">
                <div class="modal-header">
                  <h5 class="modal-title">${idRequest ? 'Edit' : 'Tambah'} Request Admin</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                  <div id="request-form-container"></div>
                </div>
              </div>
            </div>
          </div>
        `;

        $('body').append(modalHtml);
        $('#requestModal').modal('show');

        let url = idRequest ? `data-request-admin/add/${idRequest}` : `data-request-admin/add/0`;
        console.log('Loading URL:', Drupal.url(url));

        // Load content via AJAX, but handle script evaluation carefully
        $.ajax({
          url: Drupal.url(url),
          dataType: 'html',
          success: function(response) {
            $('#request-form-container').html(response);

            // Manually attach behaviors to the new content
            Drupal.attachBehaviors($('#request-form-container')[0]);
          },
          error: function(xhr, status, error) {
            console.error('Error loading form:', error);
            $('#request-form-container').html('<div class="alert alert-danger">Error loading form. Please try again.</div>');
          }
        });

        $('#requestModal').on('hidden.bs.modal', function () {
          window.DataRequestAdmin.isModalOpen = false;
          $(this).remove();
        });
      }
    }
  };
})(jQuery, Drupal, once);

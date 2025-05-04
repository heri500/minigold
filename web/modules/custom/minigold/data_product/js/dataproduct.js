(function ($, Drupal) {
  Drupal.behaviors.dataCabang = {
    attach: function (context, settings) {
      console.log('Drupal behavior attached'); // Debugging

      // Add cabang modal
      $(once('data-cabang-add', '#add-new-cabang', context)).on('click', function (e) {
        e.preventDefault();
        openCabangModal(0);
      });

      // Use event delegation for edit buttons inside DataTables
      $(document).on('click', '.edit-icon', function (e) {
        e.preventDefault();
        let idCabang = $(this).data('id');

        console.log('Edit icon clicked for ID:', idCabang); // Debugging

        if (!idCabang) {
          alert('Error: Missing cabang ID.');
          return;
        }

        openCabangModal(idCabang);
      });

      function openCabangModal(idCabang) {
        console.log('Opening modal for ID:', idCabang); // Debugging

        const modalHtml = `
          <div class="modal fade" id="cabangModal" tabindex="-1" aria-labelledby="cabangModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-md-custom">
              <div class="modal-content">
                <div class="modal-header">
                  <h5 class="modal-title">${idCabang ? 'Edit' : 'Tambah'} Cabang</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                  <div id="cabang-form-container"></div>
                </div>
              </div>
            </div>
          </div>
        `;

        $('body').append(modalHtml);
        $('#cabangModal').modal('show');

        let url = idCabang ? `datacabang/add/${idCabang}` : `datacabang/add/0`;
        console.log('Loading URL:', Drupal.url(url)); // Debugging

        $('#cabang-form-container').load(Drupal.url(url));

        $('#cabangModal').on('hidden.bs.modal', function () {
          $(this).remove();
        });
      }
    }
  };
})(jQuery, Drupal);

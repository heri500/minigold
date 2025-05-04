<?php

namespace Drupal\data_product\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Response;
/**
 * Provides route responses for the Data Cabang module.
 */
class DataProductController extends ControllerBase {

  /**
   * Returns the DataTables table.
   */
  public function table() {
    $header = [
      ['data' => '', 'datatable_options' => ['data-orderable' => 'false', 'class' => 'no-sort', 'searchable' => 'false']],
      ['data' => t('ID'), 'datatable_options' => ['data-orderable' => 'true', 'searchable' => 'false']],
      ['data' => t('Brand'), 'datatable_options' => ['data-orderable' => 'true', 'searchable' => 'true']],
      ['data' => t('Finest'), 'datatable_options' => ['data-orderable' => 'true', 'searchable' => 'false']],
      ['data' => t('Series'), 'datatable_options' => ['data-orderable' => 'true', 'searchable' => 'true']],
      ['data' => t('Tahun Release'), 'datatable_options' => ['data-orderable' => 'true', 'searchable' => 'false']],
      ['data' => t('Product Name'), 'datatable_options' => ['data-orderable' => 'true', 'searchable' => 'true']],
      ['data' => t('Gramasi'), 'datatable_options' => ['data-orderable' => 'true', 'searchable' => 'false']],
      ['data' => t('Ukuran'), 'datatable_options' => ['data-orderable' => 'true', 'searchable' => 'false']],
      ['data' => t('Finishing'), 'datatable_options' => ['data-orderable' => 'true', 'searchable' => 'true']],
      ['data' => t('Kategori'), 'datatable_options' => ['data-orderable' => 'true', 'searchable' => 'false']],
    ];

    $rows = [];
    $rowData = array_fill(0, count($header), '');
    $rows[] = $rowData;

    $Prefix = '<div class="col"><a id="add-new-cabang" href="#" class="btn btn-primary btn-sm">TAMBAH PRODUK</a></div>';
    $Prefix .= '<div class="col">&nbsp;</div>';
    return [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#theme' => 'datatable',
      '#attributes' => [
        'class' => ['table', 'table-striped', 'table-hover', 'table-sm'],
        'style' => 'width: 100%',
      ],
      '#datatable_options' => $this->getDataTableOptions(),
      '#prefix' => $Prefix,
      '#attached' => [
        'library' => [
          'data_product/dataproduct_js', // Define the library in the module's *.libraries.yml file.
        ],
      ],
    ];
  }

  /**
   * Returns the DataTable options.
   */
  private function getDataTableOptions() {
    return [
      'info' => TRUE,
      'stateSave' => TRUE,
      'ajax' => base_path() . 'datasource/getdata/product?editable=1',
      'processing' => TRUE,
      'serverSide' => TRUE,
      'paginationType' => 'full_numbers',
      'pageLength' => 50,
      'lengthMenu' => [[50, 100, 250, 500, -1], [50, 100, 250, 500, "All"]],
      'order' => [
        [1, 'desc'],
      ],
    ];
  }
  /**
   * Returns the AddCabangForm as plain HTML for a modal dialog.
   */
  public function addProductForm($id = NULL) {
    // Pass $id to the form for editing mode
    $form = \Drupal::formBuilder()->getForm(\Drupal\data_cabang\Form\AddCabangForm::class, $id);

    // Render the form as plain HTML
    $rendered_form = \Drupal::service('renderer')->renderPlain($form);

    // Return the form HTML in a Response
    return new Response($rendered_form);
  }
}

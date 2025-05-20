<?php

namespace Drupal\data_product\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\RendererInterface;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\data_request_admin\Form\AddRequestAdmin;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Url;
use Drupal\data_source\Service\DataSourceService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides route responses for the Data Cabang module.
 */
class DataProductController extends ControllerBase {
  protected $requestStack;
  /**
   * The data source service.
   *
   * @var \Drupal\data_source\Service\DataSourceService
   */
  protected $dataSourceService;

  public function __construct(
    AccountInterface $current_user,
    DataSourceService $data_source_service, RequestStack $request_stack
  ) {
    $this->currentUser = $current_user;
    $this->dataSourceService = $data_source_service;
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_user'),
      $container->get('data_source.service'),
      $container->get('request_stack')
    );
  }
  /**
   * Returns the DataTables table.
   */
  public function table() {
    $header = [
      ['data' => t('ID'), 'data-orderable' => 'true', 'data-dt-order' => 'enable', 'data-searchable' => 'true'],
      ['data' => t('Brand'), 'data-orderable' => 'true', 'data-dt-order' => 'enable', 'data-searchable' => 'true'],
      ['data' => t('Finest'), 'data-orderable' => 'true', 'data-dt-order' => 'enable', 'data-searchable' => 'true'],
      ['data' => t('Series'), 'data-orderable' => 'true', 'data-dt-order' => 'enable', 'data-searchable' => 'true'],
      ['data' => t('Tahun'), 'data-orderable' => 'true', 'data-dt-order' => 'enable', 'data-searchable' => 'true'],
      ['data' => t('Product Name'), 'data-orderable' => 'true', 'data-dt-order' => 'enable', 'data-searchable' => 'true'],
      ['data' => t('Gramasi'), 'data-orderable' => 'true', 'data-dt-order' => 'enable', 'data-searchable' => 'true'],
      ['data' => t('Ukuran'), 'data-orderable' => 'true', 'data-dt-order' => 'enable', 'data-searchable' => 'true'],
      ['data' => t('Finishing'), 'data-orderable' => 'true', 'data-dt-order' => 'enable', 'data-searchable' => 'true'],
      ['data' => t('Stock'), 'data-orderable' => 'true', 'data-dt-order' => 'enable', 'data-searchable' => 'false'],
    ];

    $rows = [];
    $rowData = array_fill(0, count($header), '');
    $rows[] = $rowData;

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
      '#attached' => [
        'library' => [
          'data_product/dataproduct_js', // Define the library in the module's *.libraries.yml file.
          'data_product/jeditable',
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
      'destroy' => TRUE,
      'ajax' => base_path() . 'datasource/getdata/product?editable=0&view_detail=0&deletable=0',
      'processing' => TRUE,
      'serverSide' => TRUE,
      'paginationType' => 'full_numbers',
      'pageLength' => 50,
      'lengthMenu' => [[50, 100, 250, 500, -1], [50, 100, 250, 500, "All"]],
      'order' => [
        [1, 'desc'],
      ],
      'buttons' => ['copy', 'csv', 'excel', 'pdf', 'print'],
      'layout' => ['topStart' => 'buttons'],
    ];
  }

  /**
   * Updates the product stock.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   A JSON response.
   */
  public function updateStock() {
    // Get the product ID and stock value from the request
    $request = $this->requestStack->getCurrentRequest();
    $request_data = $request->request->all();
    $id_product = $request_data['id_product'];
    $stock = $request_data['stock'];
    // Validate input
    if (empty($id_product) || !is_numeric($stock)) {
      return new JsonResponse([
        'success' => FALSE,
        'message' => 'Invalid product ID or stock value.',
      ]);
    }

    try {
      // Check if a record already exists for this product
      $exists = $this->dataSourceService->recordExists('product_stock', 'id_product', $id_product);
      if ($exists) {
        // Update existing record
        $fieldsid_data = ['field' => 'id_product', 'value' => $id_product];
        $field_update = [
          'stock' => $stock,
          'uid_changed' => $this->currentUser->id(),
          'changed' => date('Y-m-d H:i:s'),
        ];
        $result = $this->dataSourceService->updateTable(
          'product_stock', $field_update, $fieldsid_data
        );
      } else {
        // Insert new record
        $result = $this->dataSourceService->insertTable(
          'product_stock',
          [
            'id_product' => $id_product,
            'stock' => $stock,
            'uid_created' => $this->currentUser->id(),
          ]
        );
      }

      if ($result !== FALSE) {
        return new JsonResponse([
          'success' => TRUE,
          'message' => 'Stock updated successfully.',
        ]);
      } else {
        return new JsonResponse([
          'success' => FALSE,
          'message' => 'Failed to update stock in the database.',
        ]);
      }
    }
    catch (\Exception $e) {
      return new JsonResponse([
        'success' => FALSE,
        'message' => 'An error occurred: ' . $e->getMessage(),
      ]);
    }
  }
}

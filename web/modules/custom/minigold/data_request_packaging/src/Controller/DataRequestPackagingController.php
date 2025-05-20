<?php

declare(strict_types=1);

namespace Drupal\data_request_packaging\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\data_source\Service\DataSourceService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Returns responses for Data request packaging routes.
 */
final class DataRequestPackagingController extends ControllerBase
{
  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The data source service.
   *
   * @var \Drupal\data_source\Service\DataSourceService
   */
  protected $dataSourceService;

  public function __construct(
    FormBuilderInterface $form_builder, RendererInterface $renderer,
    AccountInterface     $current_user, DataSourceService $data_source_service
  )
  {
    $this->formBuilder = $form_builder;
    $this->renderer = $renderer;
    $this->currentUser = $current_user;
    $this->dataSourceService = $data_source_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container)
  {
    return new static(
      $container->get('form_builder'),
      $container->get('renderer'),
      $container->get('current_user'),
      $container->get('data_source.service'),
    );
  }

  /**
   * Builds the response.
   */
  public function __invoke(): array
  {
    $header = [
      ['data' => '', 'class' => 'no-sort dt-orderable-none', 'data-orderable' => 'false', 'data-dt-order' => 'disable', 'data-searchable' => 'false'],
    ];
    $ColIdIdx = 1;
    if ($this->currentUser->hasPermission('administer request packaging')) {
      $header = [...$header,
        ['data' => '', 'class' => 'no-sort dt-orderable-none', 'data-orderable' => 'false', 'data-dt-order' => 'disable', 'data-searchable' => 'false'],
        ['data' => '', 'class' => 'no-sort dt-orderable-none', 'data-orderable' => 'false', 'data-dt-order' => 'disable', 'data-searchable' => 'false'],
      ];
      $ColIdIdx = 3;
    }
    $header = [...$header,
      // -- set only have time ['data' => '', 'datatable_options' => ['data-orderable' => 'false', 'class' => 'no-sort', 'searchable' => 'false']],
      ['data' => t('ID'), 'class' => 'column-id', 'data-orderable' => 'true', 'data-dt-order' => 'enable', 'data-searchable' => 'false'],
      ['data' => t('Tgl Request Kemasan'), 'data-orderable' => 'true', 'data-dt-order' => 'enable', 'data-searchable' => 'true'],
      ['data' => t('Tgl Request Produksi'), 'data-orderable' => 'true', 'data-dt-order' => 'enable', 'data-searchable' => 'true'],
      ['data' => t('Created By'), 'data-orderable' => 'true', 'data-dt-order' => 'enable', 'data-searchable' => 'false'],
      ['data' => t('Update By'), 'data-orderable' => 'true', 'data-dt-order' => 'enable', 'data-searchable' => 'false'],
      ['data' => t('Status'), 'data-orderable' => 'true', 'data-dt-order' => 'enable', 'data-searchable' => 'false'],
    ];
    $rows = [];
    $rowData = array_fill(0, count($header), '');
    $rows[] = $rowData;
    // Default route is with ID 0 for adding new requests
    $add_button = Link::fromTextAndUrl(
      $this->t('Add Request'),
      Url::fromRoute('data_request_packaging.modal_form', ['id' => 0])
    )->toRenderable();

    $add_button['#attributes'] = [
      'class' => ['btn', 'btn-primary', 'use-ajax', 'me-2'],
      'id' => 'add-request-button',
      'data-dialog-type' => 'modal',
      'data-dialog-options' => json_encode([
        'width' => '800',
      ]),
    ];
    // Generate a unique ID for the DataTable
    $table_id = 'data-request-packaging-table';
    return [
      'buttons' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['table-buttons', 'mb-3', 'hidden-obj']],
        'add_button' => $add_button,
      ],
      'table' => [
        '#type' => 'table',
        '#header' => $header,
        '#rows' => $rows,
        '#theme' => 'datatable',
        '#attributes' => [
          'class' => ['table', 'table-striped', 'table-hover', 'table-sm'],
          'style' => 'width: 100%',
          'id' => $table_id, // Add a unique ID to the table
        ],
        '#datatable_options' => $this->getDataTableOptions(),
        //'#prefix' => $rendered_button.' '.$rendered_prod_button,
        '#attached' => [
          'library' => [
            'data_request_packaging/datarequestpackaging_js',
            'core/drupal.dialog.ajax',// Define the library in the module's *.libraries.yml file.
          ],
          'drupalSettings' => [
            'dataRequestPackaging' => [
              'modalFormUrl' => Url::fromRoute('data_request_packaging.modal_form', ['id' => 0])->toString(),
              'tableId' => $table_id,
              'colIdIdx' => $ColIdIdx,
            ],
          ],
        ],
      ]
    ];
  }

  /**
   * Returns the DataTable options.
   */
  private function getDataTableOptions()
  {
    if ($this->currentUser->hasPermission('administer request packaging')) {
      $AjaxUrl = base_path() . 'datasource/getdata/request_packaging?editable=1&deletable=1&hasdetail=1';
      $orderedColumn = 3;
    } else {
      $AjaxUrl = base_path() . 'datasource/getdata/request_packaging?hasdetail=1';
      $orderedColumn = 0;
    }
    return [
      'info' => TRUE,
      'destroy' => TRUE,
      'stateSave' => TRUE,
      'ajax' => $AjaxUrl,
      'processing' => TRUE,
      'serverSide' => TRUE,
      'paginationType' => 'full_numbers',
      'pageLength' => 50,
      'lengthMenu' => [[50, 100, 250, 500, -1], [50, 100, 250, 500, "All"]],
      'order' => [
        [$orderedColumn, 'desc'],
      ],
      'buttons' => ['copy', 'csv', 'excel', 'pdf', 'print'],
      'layout' => ['topStart' => 'buttons'],
    ];
  }

  public function modalForm($id = 0) {
    $response = new AjaxResponse();

    // Ensure ID is properly handled - convert to int for numeric comparison
    $id = (int)$id;

    // Build the form
    $form = $this->formBuilder->getForm('Drupal\data_request_packaging\Form\EditRequestPackaging', $id);

    // Add the form to a modal dialog
    $title = ($id > 0) ? $this->t('Edit Request Packaging') : $this->t('Add Request Packaging');
    $response->addCommand(new OpenModalDialogCommand($title, $form, ['width' => '1000']));

    return $response;
  }

  /**
   * Returns JSON detail of the request.
   */
  public function detailRequest($id) {
    $data = [];
    if (!empty($id)) {
      $table_name = 'request_packaging_detail';
      $field_data = $this->dataSourceService->getTableFields($table_name);
      $field_value[] = ['id_request_packaging' => $id];
      $left_join = [
        'alias' => 'p',
        'table_name' => 'product',
        'target_field' => 'id_product',
        'source_field' => 'product_id',
        'field_name' => ['product_id', 'brand', 'product_name'],
      ];
      $result = $this->dataSourceService->fetchRecordsByField($table_name, $field_data, $field_value, $left_join, [], []);
      // Example static data – replace with DB query or service logic as needed.
      $data = [
        'data' => $result,
        'status' => 'success',
      ];
    }
    return new JsonResponse($data);
  }

  public function deleteRequestPackaging($id = NULL) {
    if (!empty($id)) {
      $fieldsid_data = ['field' => 'id_request_packaging', 'value' => $id];
      // Delete all existing detail records for this request
      $query = $this->dataSourceService->fetchRecordsById('request_packaging', ['id_request_packaging','id_production_process','id_request_produksi','id_request_kemasan'], $id);
      if (!empty($query)) {
        // Delete all existing detail records for this request
        $this->dataSourceService->deleteTableById('request_packaging_detail',$fieldsid_data);

        // Get Request Kemasan dan Produksi ID related to Request Packaging
        if ($query->id_request_kemasan) {
          $query = $this->dataSourceService->fetchRecordsById('request_kemasan', ['id_request_kemasan'], $query->id_request_kemasan);
          if (!empty($query)) {
            // Update Request Admin Status
            $fieldsid_data2 = ['field' => 'id_request_kemasan', 'value' => $query->id_request_kemasan];
            $request_data = ['status_kemasan' => 1];
            $this->dataSourceService->updateTable('request_kemasan', $request_data, $fieldsid_data2);
          }
        }

        if ($query->id_request_produksi) {
          $query = $this->dataSourceService->fetchRecordsById('request_produksi', ['id_request_produksi'], $query->id_request_kemasan);
          if (!empty($query)) {
            // Update Request Admin Status
            $fieldsid_data2 = ['field' => 'id_request_produksi', 'value' => $query->id_request_produksi];
            $request_data = ['status_produksi' => 1];
            $this->dataSourceService->updateTable('request_produksi', $request_data, $fieldsid_data2);
          }
        }

        // Delete Request Admin Produksi
        $this->dataSourceService->deleteTableById('request_packaging',$fieldsid_data);
      }
      // Set drupal message if delete success
      $this->messenger()->addStatus($this->t('Request has been successfully deleted.'));
    }
    // Redirect to the route after deletion
    return new RedirectResponse(Url::fromRoute('data_request_kemasan.table')->toString());
  }

}

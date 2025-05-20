<?php

declare(strict_types=1);

namespace Drupal\data_request_admin\Controller;

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
use Drupal\data_request_admin\StatusRequest;

/**
 * Returns responses for Data request admin routes.
 */
final class DataRequestAdminController extends ControllerBase {

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
    AccountInterface $current_user, DataSourceService $data_source_service
  ) {
    $this->formBuilder = $form_builder;
    $this->renderer = $renderer;
    $this->currentUser = $current_user;
    $this->dataSourceService = $data_source_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
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
    if ($this->currentUser->hasPermission('administer request admin')) {
      $header = [...$header,
        ['data' => '', 'class' => 'no-sort dt-orderable-none', 'data-orderable' => 'false', 'data-dt-order' => 'disable', 'data-searchable' => 'false'],
        ['data' => '', 'class' => 'no-sort dt-orderable-none', 'data-orderable' => 'false', 'data-dt-order' => 'disable', 'data-searchable' => 'false'],
      ];
      $ColIdIdx = 3;
    }
    $header = [...$header,
      // -- set only have time ['data' => '', 'datatable_options' => ['data-orderable' => 'false', 'class' => 'no-sort', 'searchable' => 'false']],
      ['data' => t('ID'), 'data-orderable' => 'true', 'data-dt-order' => 'enable', 'data-searchable' => 'false'],
      ['data' => t('No. Req'), 'data-orderable' => 'true', 'data-dt-order' => 'enable', 'data-searchable' => 'true'],
      ['data' => t('Tgl Request'), 'data-orderable' => 'true', 'data-dt-order' => 'enable', 'data-searchable' => 'true'],
      ['data' => t('Request By'), 'data-orderable' => 'true', 'data-dt-order' => 'enable', 'data-searchable' => 'false'],
      ['data' => t('Nama Pemesan'), 'data-orderable' => 'true', 'data-dt-order' => 'enable', 'data-searchable' => 'true'],
      ['data' => t('Keterangan'), 'data-orderable' => 'true', 'data-dt-order' => 'enable', 'data-searchable' => 'true'],
      ['data' => t('Status'), 'data-orderable' => 'true', 'data-dt-order' => 'enable', 'data-searchable' => 'false'],
      ['data' => t('File Attach'), 'data-orderable' => 'true', 'data-dt-order' => 'enable', 'data-searchable' => 'false'],
    ];

    $rowData = array_fill(0, count($header), '');
    $rows[] = $rowData;

    // Default route is with ID 0 for adding new requests
    $add_button = Link::fromTextAndUrl(
      $this->t('Add Request'),
      Url::fromRoute('data_request_admin.modal_form', ['id' => 0])
    )->toRenderable();

    $add_button['#attributes'] = [
      'class' => ['btn', 'btn-primary', 'use-ajax', 'me-2'],
      'id' => 'add-request-button',
      'data-dialog-type' => 'modal',
      'data-dialog-options' => json_encode([
        'width' => '800',
      ]),
    ];
    //$rendered_button = \Drupal::service('renderer')->render($add_button);

    // Default route is with ID 0 for adding new production requests
    $add_production_button = Link::fromTextAndUrl(
      $this->t('Request Production'),
      Url::fromRoute('data_request_admin.request_production', ['id' => ''])
    )->toRenderable();

    $add_production_button['#attributes'] = [
      'class' => ['btn', 'btn-primary', 'use-ajax', 'me-2', 'export-btn', 'disabled'],
      'id' => 'add-request-production',
      'data-dialog-type' => 'modal',
      'data-dialog-options' => json_encode([
        'width' => '800',
      ]),
      'disabled' => 'disabled'
    ];
    //$rendered_prod_button = \Drupal::service('renderer')->render($add_production_button);

    // Generate a unique ID for the DataTable
    $table_id = 'data-request-admin-table';
    // Get status array and color
    $statuses = StatusRequest::STATUS;
    $statusesColor = StatusRequest::STATUSCOLOR;
    return [
      'buttons' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['table-buttons', 'mb-3']],
        'add_button' => $add_button,
        'production_button' => $add_production_button,
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
            'data_request_admin/datarequestadmin_js',
            'core/drupal.dialog.ajax',// Define the library in the module's *.libraries.yml file.
          ],
          'drupalSettings' => [
            'dataRequestAdmin' => [
              'modalFormUrl' => Url::fromRoute('data_request_admin.modal_form', ['id' => 0])->toString(),
              'tableId' => $table_id,
              'colIdIdx' => $ColIdIdx,
              'status' => $statuses,
              'status_color' => $statusesColor,
            ],
          ],
        ],
      ]
    ];
  }
  /**
   * Returns the DataTable options.
   */
  private function getDataTableOptions() {
    if ($this->currentUser->hasPermission('administer request admin')) {
      $AjaxUrl = base_path() . 'datasource/getdata/request_admin?editable=1&deletable=1&hasdetail=1';
      $orderedColumn = 3;
    }else{
      $AjaxUrl = base_path() . 'datasource/getdata/request_admin?hasdetail=1';
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

  public function addRequestForm($id = NULL) { //// Pass $id to the form for editing mode
    $form = \Drupal::formBuilder()->getForm(AddRequestAdmin::class, $id);
    return $form;
  }

  public function modalForm($id = 0) {
    $response = new AjaxResponse();

    // Ensure ID is properly handled - convert to int for numeric comparison
    $id = (int)$id;

    // Build the form
    $form = $this->formBuilder->getForm('Drupal\data_request_admin\Form\AddRequestAdmin', $id);

    // Add the form to a modal dialog
    $title = ($id > 0) ? $this->t('Edit Request Admin') : $this->t('Add Request Admin');
    $response->addCommand(new OpenModalDialogCommand($title, $form, ['width' => '700']));

    return $response;
  }

  public function modalRequestProduksi($id = '') {
    $response = new AjaxResponse();

    if ($id != '') {
      // Build the form
      $form = $this->formBuilder->getForm('Drupal\data_request_admin\Form\FormRequestProduksi', $id);
      // Add the form to a modal dialog
      $title = $this->t('Create Request Produksi');
      $response->addCommand(new OpenModalDialogCommand($title, $form, [
        'width' => '700',
        'dialogClass' => 'request-produksi-modal',
      ]));

      return $response;
    }else{
      return [];
    }
  }

  public function deleteRequestAdmin($id = NULL) {
    if (!empty($id)) {
      $fieldsid_data = ['field' => 'id_request_admin', 'value' => $id];
      // Delete all existing detail records for this request
      $query = $this->dataSourceService->fetchRecordsById('request_admin', ['id_request_admin'], $id);
      if (!empty($query)) {
        // Delete all existing detail records for this request
        $this->dataSourceService->deleteTableById('request_admin_detail',$fieldsid_data);

        // Delete the main request
        $this->dataSourceService->deleteTableById('request_admin',$fieldsid_data);
      }
      // Set drupal message if delete success
      $this->messenger()->addStatus($this->t('Request has been successfully deleted.'));
    }
    // Redirect to the route after deletion
    return new RedirectResponse(Url::fromRoute('data_request_admin.table')->toString());
  }

  /**
   * Returns JSON detail of the request.
   */
  public function detailRequest($id) {
    $data = [];
    if (!empty($id)) {
      $field_data1 = $this->dataSourceService->getTableFields('request_admin');
      $table_name = 'request_admin_detail';
      $field_data2 = $this->dataSourceService->getTableFields($table_name);
      $field_value[] = ['id_request_admin' => $id];
      $left_join = [
        'alias' => 'p',
        'table_name' => 'product',
        'target_field' => 'id_product',
        'source_field' => 'product_id',
        'field_name' => ['product_id', 'brand', 'product_name'],
      ];
      $result = [];
      $result['info'] = $this->dataSourceService->fetchRecordsById('request_admin', $field_data1, $id);
      if (!empty($result['info']->uid_changed)){
        $user = User::load($result['info']->uid_changed);
        $username = $user->getAccountName();
        $result['info']->uid_changed = $username;
        $date = (new \DateTime($result['info']->changed))->format('d-m-Y H:i');
        $result['info']->changed = $date;
      } else {
        $result['info']->uid_changed = '-';
        $result['info']->changed = '-';
      }

      $result['detail'] = $this->dataSourceService->fetchRecordsByField($table_name, $field_data2, $field_value, $left_join,[],[]);
      // Example static data – replace with DB query or service logic as needed.
      $data = [
        'data' => $result,
        'status' => 'success',
      ];
    }
    return new JsonResponse($data);
  }

}

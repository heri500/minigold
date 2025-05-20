<?php

declare(strict_types=1);

namespace Drupal\data_request_packaging\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\MessageCommand;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\data_source\Service\DataSourceService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\data_request_admin\StatusRequest;

/**
 * Provides a Data request packaging form.
 */
final class EditRequestPackaging extends FormBase {

  /**
   * The data source service.
   *
   * @var \Drupal\data_source\Service\DataSourceService
   */
  protected $dataSourceService;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Constructs a DatasourceController object.
   *
   * @param \Drupal\data_source\Service\DataSourceService $data_source_service
   *   The data source service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   */
  public function __construct(DataSourceService $data_source_service, RequestStack $request_stack) {
    $this->dataSourceService = $data_source_service;
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('data_source.service'),
      $container->get('request_stack')
    );
  }
  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'data_request_packaging_edit_request_packaging';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $id_request = NULL): array {

    $resultKemasan = [];
    $request_data = [];
    $requestDate = null;
    if ($id_request) {
      $table_name = 'request_packaging';
      $field_data = $this->dataSourceService->getTableFields($table_name);
      $query = $this->dataSourceService->fetchRecordsById($table_name, $field_data, $id_request);
      if ($query) {
        $request_data = $query;
        $form['id'] = [
          '#type' => 'hidden',
          '#value' => $request_data->id_request_packaging ?? '',
        ];
        $form['id_production_process'] = [
          '#type' => 'hidden',
          '#value' => $request_data->id_production_process ?? '',
        ];
        $requestFromKemasanDate = new \DateTime($query->tgl_request_from_kemasan);
        $requestFromProduksiDate = new \DateTime($query->tgl_request_from_produksi);
        $field_data = $this->dataSourceService->getTableFields('request_packaging_detail');
        $field_value[] = ['id_request_packaging' => $id_request];
        $left_join = [
          'alias' => 'p',
          'table_name' => 'product',
          'target_field' => 'id_product',
          'source_field' => 'product_id',
          'field_name' => ['product_id', 'brand', 'product_name'],
        ];
        $resultPackaging = $this->dataSourceService->fetchRecordsByField('request_packaging_detail', $field_data, $field_value, $left_join, [],[]);
      }
    } else {
      \Drupal::messenger()->addWarning($this->t('Invalid request ID.'));
      // Perform redirect to the route.
      $response = new RedirectResponse(Url::fromRoute('data_request_produksi.table')->toString());
      $response->send();
      return [];
    }
    $form['tgl_request_from_kemasan'] = [
      '#type' => 'date',
      '#attributes' => [
        'class' => ['form-control'],
      ],
      '#prefix' => '<div class="input-group mt-2 mb-2"><span class="input-group-text">Tgl From Kemasan</span>',
      '#suffix' => '</div>',
      '#default_value' => !empty($requestFromKemasanDate) ? $requestFromKemasanDate->format('Y-m-d') : date('Y-m-d'),
      '#disabled' => TRUE,
    ];
    $form['tgl_request_from_produksi'] = [
      '#type' => 'date',
      '#attributes' => [
        'class' => ['form-control'],
      ],
      '#prefix' => '<div class="input-group mt-2 mb-2"><span class="input-group-text">Tgl From Produksi</span>',
      '#suffix' => '</div>',
      '#default_value' => !empty($requestFromProduksiDate) ? $requestFromProduksiDate->format('Y-m-d') : date('Y-m-d'),
      '#disabled' => TRUE,
    ];
    $form['request_produksi_summary_title'] = [
      '#markup' => '<h5 class="mt-0 mb-1 request-title">' . $this->t('Dari Produksi') . '</h5>',
      '#prefix' => '<div class="row"><div class="col-4">',
    ];
    // Build table headers for request produksi
    $header = [
      ['data' => '', 'class' => 'hidden-column'],
      ['data' => 'Kepingan'],
      ['data' => 'Qty Produksi'],
    ];    // Table header
    $form['from_request_produksi_table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#prefix' => '<div class="from-request-produksi-wrapper">',
      '#suffix' => '</div></div>',
      '#attributes' => [ 'id' => 'from-request-produksi-table', ],
    ];
    // Build table rows.
    foreach ($resultPackaging as $index => $row) {
      if (!empty($row->produk_produksi)) {
        $form['from_request_produksi_table'][$index]['id_request_detail'] = [
          '#type' => 'hidden',
          '#default_value' => $row->id_request_packaging_detail,
        ];
        $form['from_request_produksi_table'][$index]['produk_produksi'] = [
          '#markup' => $row->produk_produksi,
        ];
        $form['from_request_produksi_table'][$index]['qty_keping'] = [
          '#type' => 'textfield',
          '#default_value' => $row->qty_keping,
          '#size' => 3,
          '#attributes' => ['class' => ['total-qty-input'], 'disabled' => 'diabled'],
          '#disabled' => TRUE,
        ];
      }
    }

    $form['request_packaging_summary_title'] = [
      '#markup' => '<h5 class="mt-0 mb-1 request-title">' . $this->t('Hasil Packaging') . '</h5>',
      '#prefix' => '<div class="col-8">',
    ];
    // Build table headers for request produksi
    $header = [
      ['data' => '', 'class' => 'hidden-column'],
      ['data' => '', 'class' => 'hidden-column'],
      ['data' => 'Kepingan'],
      ['data' => 'Produksi'],
      ['data' => 'Packaging'],
    ];    // Table header
    $form['edit_request_packaging_table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#prefix' => '<div class="edit-request-packaging-wrapper">',
      '#suffix' => '</div></div></div>',
      '#attributes' => [ 'id' => 'edit-request-packaging-table', ],
    ];
    // Build table rows.
    foreach ($resultPackaging as $index => $row) {
      if (!empty($row->id_product)) {
        $form['edit_request_packaging_table'][$index]['id_request_detail'] = [
          '#type' => 'hidden',
          '#default_value' => $row->id_request_packaging_detail,
        ];
        $form['edit_request_packaging_table'][$index]['id_product'] = [
          '#type' => 'hidden',
          '#default_value' => $row->id_product,
        ];
        $form['edit_request_packaging_table'][$index]['product_name'] = [
          '#markup' => $row->product_name,
        ];
        $form['edit_request_packaging_table'][$index]['qty_product'] = [
          '#type' => 'textfield',
          '#default_value' => $row->qty_product,
          '#size' => 5,
          '#attributes' => ['class' => ['total-qty-input'], 'disabled' => 'diabled'],
          '#disabled' => TRUE,
        ];
        $form['edit_request_packaging_table'][$index]['qty_packaging'] = [
          '#type' => 'textfield',
          '#default_value' => $row->qty_product,
          '#size' => 5,
          '#attributes' => ['class' => ['total-qty-input'],],
        ];
      }
    }

    $form['keterangan'] = [
      '#type' => 'textarea',
      '#attributes' => [
        'class' => ['form-control'],
        'placeholder' => 'Keterangan Packaging',
        'cols' => 106,
        'rows' => 3,
      ],
      '#prefix' => '<div class="input-group mt-3 mb-2"><span class="input-group-text">Keterangan</span>',
      '#suffix' => '</div>',
      '#required' => FALSE,
      '#default_value' => !empty($request_data->keterangan) ? $request_data->keterangan : '',
    ];

    $statuses = StatusRequest::STATUS;
    $form['status_packaging'] = [
      '#type' => 'select',
      '#options' => $statuses,
      '#default_value' => $request_data->status_packaging,
      '#prefix' => '<div class="input-group mt-1 mb-2"><span class="input-group-text">Status Packaging</span>',
      '#suffix' => '</div>',
      '#required' => TRUE,
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Simpan Packaging'),
      '#attributes' => [
        'class' => ['btn btn-primary'],
        'title' => t('Klik untuk simpan packaging'),
      ],
      '#ajax' => [
        'callback' => '::ajaxSubmit',
        'wrapper' => 'request-produksi-form-wrapper',
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Saving & Start Kemasan...'),
        ],
      ],
    ];
    $form['actions']['cancel'] = [
      '#type' => 'button',
      '#value' => $this->t('Batal'),
      '#attributes' => [
        'class' => ['btn btn-danger'],
        'id' => 'cancel-request',
      ],
      // Remove the AJAX configuration for the cancel button
    ];

    // Give the form a unique ID in the DOM for Ajax targeting
    $form['#prefix'] = '<div id="request-packaging-form-wrapper">';
    $form['#suffix'] = '</div>';
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    // @todo Validate the form here.
    // Example:
    // @code
    //   if (mb_strlen($form_state->getValue('message')) < 10) {
    //     $form_state->setErrorByName(
    //       'message',
    //       $this->t('Message should be at least 10 characters.'),
    //     );
    //   }
    // @endcode
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {

  }

  public function ajaxSubmit(array &$form, FormStateInterface $form_state)
  {
    $response = new AjaxResponse();

    if ($form_state->hasAnyErrors()) {
      // Return the form with errors
      return $form;
    }
    $values['id_request'] = json_decode($form_state->getValue('id'));
    $values['id_production_process'] = $form_state->getValue('id_production_process');
    $values['detail_packaging'] = $form_state->getValue('edit_request_packaging_table');
    $values['keterangan_packaging'] = $form_state->getValue('keterangan_packaging');
    $values['status_packaging'] = $form_state->getValue('status_packaging');
    $result = $this->dataSourceService->updateRequestPackaging($values);

    if ($result) {
      // Success
      $response->addCommand(new MessageCommand($this->t('Request Kemasan & Packaging saved successfully.'), NULL, ['type' => 'status'], TRUE));
      $response->addCommand(new CloseModalDialogCommand());
      $response->addCommand(new RedirectCommand(Url::fromRoute('data_request_packaging.table')->toString()));
    }
    else {
      // Error
      $response->addCommand(new MessageCommand($this->t('Error saving request.'), NULL, ['type' => 'error'], TRUE));
    }

    return $response;
  }

}

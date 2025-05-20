<?php

declare(strict_types=1);

namespace Drupal\data_request_kemasan\Form;

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
 * Provides a Data request produksi form.
 */
final class EditRequestKemasan extends FormBase {
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
    return 'data_request_produksi_edit_request_production';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $id_request = NULL): array {
    $resultKemasan = [];
    $request_data = [];
    $requestDate = null;
    if ($id_request) {
      $table_name = 'request_kemasan';
      $field_data = $this->dataSourceService->getTableFields($table_name);
      $query = $this->dataSourceService->fetchRecordsById($table_name, $field_data, $id_request);
      if ($query) {
        $request_data = $query;
        $form['id'] = [
          '#type' => 'hidden',
          '#value' => $request_data->id_request_kemasan ?? '',
        ];
        $form['id_production_process'] = [
          '#type' => 'hidden',
          '#value' => $request_data->id_production_process ?? '',
        ];
        $requestDate = new \DateTime($query->tgl_request_kemasan);
        $field_data = $this->dataSourceService->getTableFields('request_kemasan_detail');
        $field_value[] = ['id_request_kemasan' => $id_request];
        $left_join = [
          'alias' => 'p',
          'table_name' => 'product',
          'target_field' => 'id_product',
          'source_field' => 'product_id',
          'field_name' => ['product_id', 'brand', 'product_name'],
        ];
        $resultKemasan = $this->dataSourceService->fetchRecordsByField('request_kemasan_detail', $field_data, $field_value, $left_join, [],[]);
      }
    } else {
      \Drupal::messenger()->addWarning($this->t('Invalid request ID.'));
      // Perform redirect to the route.
      $response = new RedirectResponse(Url::fromRoute('data_request_produksi.table')->toString());
      $response->send();
      return [];
    }
    $form['tgl_request'] = [
      '#type' => 'date',
      '#attributes' => [
        'class' => ['form-control'],
      ],
      '#prefix' => '<div class="input-group mt-2 mb-2"><span class="input-group-text">Tanggal</span>',
      '#suffix' => '</div>',
      '#default_value' => !empty($requestDate) ? $requestDate->format('Y-m-d') : date('Y-m-d'),
      '#disabled' => TRUE,
    ];
    $form['request_admin_summary_title'] = [
      '#markup' => '<h5 class="mt-0 mb-1 request-title">' . $this->t('Request Kemasan') . '</h5>',
    ];
    // Build table headers for request produksi
    $header = [
      ['data' => '', 'class' => 'hidden-column'],
      ['data' => '', 'class' => 'hidden-column'],
      ['data' => 'Kepingan'],
      ['data' => 'Qty Produksi'],
      ['data' => 'Qty Requested'],
    ];    // Table header
    $form['edit_request_kemasan_table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#prefix' => '<div class="edit-request-kemasan-wrapper">',
      '#suffix' => '</div>',
      '#attributes' => [ 'id' => 'edit-request-produksi-table', ],
    ];
    // Build table rows.
    foreach ($resultKemasan as $index => $row) {
      $form['edit_request_kemasan_table'][$index]['id_request_detail'] = [
        '#type' => 'hidden',
        '#default_value' => $row->id_request_kemasan_detail,
      ];
      $form['edit_request_kemasan_table'][$index]['id_product'] = [
        '#type' => 'hidden',
        '#default_value' => $row->product_id,
      ];
      $form['edit_request_kemasan_table'][$index]['product_name'] = [
        '#markup' => $row->product_name,
      ];
      $form['edit_request_kemasan_table'][$index]['total_qty'] = [
        '#type' => 'textfield',
        '#default_value' => $row->total_qty,
        '#size' => 5,
        '#attributes' => ['class' => ['total-qty-input']],
      ];
      $form['edit_request_kemasan_table'][$index]['requested_qty_keping'] = [
        '#type' => 'textfield',
        '#default_value' => $row->total_qty_actual,
        '#size' => 5,
        '#attributes' => ['class' => ['total-qty-input'], 'disabled' => 'diabled'],
        '#disabled' => TRUE,
      ];
    }

    $form['keterangan_kemasan'] = [
      '#type' => 'textarea',
      '#attributes' => [
        'class' => ['form-control'],
        'placeholder' => 'Keterangan Kemasan',
        'cols' => 62,
        'rows' => 3,
      ],
      '#prefix' => '<div class="input-group mt-1 mb-2"><span class="input-group-text">Keterangan</span>',
      '#suffix' => '</div>',
      '#required' => FALSE,
      '#default_value' => !empty($request_data->keterangan) ? $request_data->keterangan : '',
    ];
    $statuses = StatusRequest::STATUS;
    $form['status_kemasan'] = [
      '#type' => 'select',
      '#options' => $statuses,
      '#default_value' => $request_data->status_kemasan,
      '#prefix' => '<div class="input-group mt-1 mb-2"><span class="input-group-text">Status Kemasan</span>',
      '#suffix' => '</div>',
      '#required' => TRUE,
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Simpan Request Kemasan'),
      '#attributes' => [
        'class' => ['btn btn-primary'],
        'title' => t('Klik untuk simpan request produksi'),
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
    $form['#prefix'] = '<div id="request-produksi-form-wrapper">';
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
    $tgl_request = $form_state->getValue('tgl_request');
    $date = new \DateTime($tgl_request);
    $values['id_request'] = json_decode($form_state->getValue('id'));
    $values['id_production_process'] = $form_state->getValue('id_production_process');
    $values['tgl_request'] = $date->format('Y-m-d H:i:s');;
    $values['detail_kemasan'] = $form_state->getValue('edit_request_kemasan_table');
    $values['keterangan_kemasan'] = $form_state->getValue('keterangan_kemasan');
    $values['status_kemasan'] = $form_state->getValue('status_kemasan');
    $result = $this->dataSourceService->updateRequestKemasan($values);

    if ($result) {
      // Success
      $response->addCommand(new MessageCommand($this->t('Request Kemasan & Packaging saved successfully.'), NULL, ['type' => 'status'], TRUE));
      $response->addCommand(new CloseModalDialogCommand());
      $response->addCommand(new RedirectCommand(Url::fromRoute('data_request_kemasan.table')->toString()));
    }
    else {
      // Error
      $response->addCommand(new MessageCommand($this->t('Error saving request.'), NULL, ['type' => 'error'], TRUE));
    }

    return $response;
  }
}

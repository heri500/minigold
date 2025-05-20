<?php

declare(strict_types=1);

namespace Drupal\data_request_produksi\Form;

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
final class EditRequestProduction extends FormBase {
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
    $resultProduction = [];
    $request_data = [];
    $requestDate = null;
    if ($id_request) {
      $table_name = 'request_produksi';
      $field_data = $this->dataSourceService->getTableFields($table_name);
      $query = $this->dataSourceService->fetchRecordsById($table_name, $field_data, $id_request);
      if ($query) {
        $request_data = $query;
        $form['id'] = [
          '#type' => 'hidden',
          '#value' => $request_data->id_request_produksi ?? '',
        ];
        $form['id_production_process'] = [
          '#type' => 'hidden',
          '#value' => $request_data->id_production_process ?? '',
        ];
        $requestDate = new \DateTime($query->tgl_request_produksi);
        $table_name = 'request_produksi_detail';
        $field_data = $this->dataSourceService->getTableFields($table_name);
        $field_value[] = ['id_request_produksi' => $id_request];
        $resultProduction = $this->dataSourceService->fetchRecordsByField($table_name, $field_data, $field_value, [],[],[]);
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
      '#markup' => '<h5 class="mt-0 mb-1 request-title">' . $this->t('Request Produksi') . '</h5>',
    ];
    // Build table headers for request produksi
    $header = [
      ['data' => '', 'class' => 'hidden-column'],
      ['data' => '', 'class' => 'hidden-column'],
      ['data' => 'Kepingan'],
      ['data' => 'Total Qty Produksi'],
      ['data' => 'Total Qty Requested'],
    ];    // Table header
    $form['edit_request_produksi_table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#prefix' => '<div class="edit-request-produksi-wrapper">',
      '#suffix' => '</div>',
      '#attributes' => [ 'id' => 'edit-request-produksi-table', ],
    ];
    // Build table rows.
    foreach ($resultProduction as $index => $row) {
      $form['edit_request_produksi_table'][$index]['id_request_detail'] = [
        '#type' => 'hidden',
        '#default_value' => $row->id_request_produksi_detail,
      ];
      $form['edit_request_produksi_table'][$index]['gramasi'] = [
        '#type' => 'hidden',
        '#default_value' => $row->gramasi,
      ];
      $form['edit_request_produksi_table'][$index]['kepingan'] = [
        '#markup' => 'Kepingan ' . $row->gramasi,
      ];
      $form['edit_request_produksi_table'][$index]['total_qty'] = [
        '#type' => 'textfield',
        '#default_value' => $row->total_qty,
        '#size' => 5,
        '#attributes' => ['class' => ['total-qty-input']],
      ];
      $form['edit_request_produksi_table'][$index]['requested_qty_keping'] = [
        '#type' => 'textfield',
        '#default_value' => $row->total_qty_actual,
        '#size' => 5,
        '#attributes' => ['class' => ['total-qty-input'], 'disabled' => 'diabled'],
        '#disabled' => TRUE,
      ];
    }
    $form['keterangan_produksi'] = [
      '#type' => 'textfield',
      '#attributes' => [
        'class' => ['form-control'],
        'placeholder' => 'Keterangan Produksi',
      ],
      '#prefix' => '<div class="input-group mt-1 mb-2"><span class="input-group-text">Keterangan</span>',
      '#suffix' => '</div>',
      '#required' => FALSE,
      '#default_value' => !empty($request_data->keterangan) ? $request_data->keterangan : '',
    ];
    $statuses = StatusRequest::STATUS;
    $form['status_produksi'] = [
      '#type' => 'select',
      '#options' => $statuses,
      '#default_value' => $request_data->status_produksi,
      '#prefix' => '<div class="input-group mt-1 mb-2"><span class="input-group-text">Status Produksi</span>',
      '#suffix' => '</div>',
      '#required' => TRUE,
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Simpan Request Produksi'),
      '#attributes' => [
        'class' => ['btn btn-primary'],
        'title' => t('Klik untuk simpan request produksi'),
      ],
      '#ajax' => [
        'callback' => '::ajaxSubmit',
        'wrapper' => 'request-produksi-form-wrapper',
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Saving & Start Production...'),
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
    $values['detail_produksi'] = $form_state->getValue('edit_request_produksi_table');
    $values['keterangan_produksi'] = $form_state->getValue('keterangan_produksi');
    $values['status_produksi'] = $form_state->getValue('status_produksi');
    $result = $this->dataSourceService->updateRequestProduction($values);

    if ($result) {
      // Success
      $response->addCommand(new MessageCommand($this->t('Request Production & Packaging saved successfully.'), NULL, ['type' => 'status'], TRUE));
      $response->addCommand(new CloseModalDialogCommand());
      $response->addCommand(new RedirectCommand(Url::fromRoute('data_request_produksi.table')->toString()));
    }
    else {
      // Error
      $response->addCommand(new MessageCommand($this->t('Error saving request.'), NULL, ['type' => 'error'], TRUE));
    }

    return $response;
  }
}

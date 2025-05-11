<?php

declare(strict_types=1);

namespace Drupal\data_request_admin\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\MessageCommand;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\data_source\Service\DataSourceService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\RedirectResponse;



/**
 * Provides a Data Request Admin form.
 */
final class FormRequestProduksi extends FormBase {
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
    return 'data_request_admin_form_request_produksi';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $id_request = NULL): array
  {
    $splitId = explode('_', $id_request);
    if (empty($splitId)) {
      \Drupal::messenger()->addWarning($this->t('Invalid request ID.'));
      // Perform redirect to the route.
      $response = new RedirectResponse(Url::fromRoute('data_request_admin.table')->toString());
      $response->send();
      return [];
    }

    $table_name = 'request_admin';
    $field_data = $this->dataSourceService->getTableFields($table_name);
    $requestData = $this->dataSourceService->fetchRecordsByIds($table_name, $field_data, $splitId);
    //proceed check request detail
    $table_name = 'request_admin_detail';
    $field_data = ['id_product'];
    $field_value[] = ['id_request_admin' => $splitId];
    $left_join = [
      'alias' => 'p',
      'table_name' => 'product',
      'target_field' => 'id_product',
      'source_field' => 'product_id',
      'field_name' => ['brand', 'product_name'],
    ];
    // get request admin detail group by product
    $expression_data = [
      ['expression' => 'SUM(ta.qty_request)', 'alias' => 'total_qty'],
    ];
    $grouping_data = [
      ['field' => 'ta.id_product'],
      ['field' => 'p.product_name'],
      ['field' => 'p.brand'],
    ];
    $resultPackaging = $this->dataSourceService->fetchRecordsByField(
     $table_name, $field_data, $field_value, $left_join, $expression_data, $grouping_data
    );
    $field_data = [];
    $left_join = [
      'alias' => 'p',
      'table_name' => 'product',
      'target_field' => 'id_product',
      'source_field' => 'product_id',
      'field_name' => ['gramasi'],
    ];
    $grouping_data = [
      ['field' => 'p.gramasi'],
    ];
    // get request admin detail group by gramasi
    $resultProduction = $this->dataSourceService->fetchRecordsByField(
      $table_name, $field_data, $field_value, $left_join, $expression_data, $grouping_data
    );
    $rendered_button = [];
    foreach ($requestData as $RequestAdmin){
      // Default route is with ID 0 for adding new requests
      $requestDate = substr($RequestAdmin->tgl_request,0,10);
      $linkTitle = $RequestAdmin->no_request.'-'.$RequestAdmin->nama_pemesan.'-'.$requestDate;
      $add_button = Link::fromTextAndUrl(
        $RequestAdmin->no_request,
        Url::fromUri('internal:#')
      )->toRenderable();

      $add_button['#attributes'] = [
        'class' => ['btn', 'btn-primary', 'use-ajax', 'me-2', 'disabled', 'text-muted', 'pe-none'],
        'id' => 'request'.$RequestAdmin->id_request_admin,
        'title' => $linkTitle,
        'aria-disabled' => 'true',
      ];
      $rendered_button[] = \Drupal::service('renderer')->render($add_button);
    }
    $form['selected_request_admin'] = [
      '#markup' => '<h5 class="mb-1 request-title">' . $this->t('Selected Request Admin') . '</h5>',
    ];
    $selectedRequest = implode('', $rendered_button);
    $form['selected_request'] = [
      '#markup' => $selectedRequest,
    ];
    $form['selected_request_id'] = [
      '#type' => 'hidden',
      '#value' => json_encode($splitId), // Replace with dynamic value if needed.
    ];
    $form['tgl_request'] = [
      '#type' => 'date',
      '#attributes' => [
        'class' => ['form-control'],
      ],
      '#prefix' => '<div class="input-group mt-2 mb-2"><span class="input-group-text">Tanggal</span>',
      '#suffix' => '</div>',
      '#default_value' => date('Y-m-d'),
    ];
    $form['request_admin_summary_title'] = [
      '#markup' => '<h5 class="mt-0 mb-1 request-title">' . $this->t('Request Produksi') . '</h5>',
    ];
    // Build table headers for request produksi
    $header = [
      ['data' => '', 'class' => 'hidden-column'],
      ['data' => 'Kepingan'],
      ['data' => 'Total Qty Requested'],
    ];    // Table header
    $form['request_admin_summary'] = [
      '#type' => 'table',
      '#header' => $header,
      '#prefix' => '<div class="request-admin-summary">',
      '#suffix' => '</div>',
      '#attributes' => [ 'id' => 'request-produksi-table', ],
    ];
    // Build table rows.
    foreach ($resultProduction as $index => $row) {
      $form['request_admin_summary'][$index]['gramasi'] = [
        '#type' => 'hidden',
        '#default_value' => $row->gramasi,
      ];
      $form['request_admin_summary'][$index]['kepingan'] = [
        '#markup' => 'Kepingan ' . $row->gramasi,
      ];
      $form['request_admin_summary'][$index]['total_qty'] = [
        '#type' => 'textfield',
        '#default_value' => $row->total_qty,
        '#size' => 5,
        '#attributes' => ['class' => ['total-qty-input']],
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
    ];
    $form['request_packaging_title'] = [
      '#markup' => '<h5 class="mt-0 mb-1 request-title">' . $this->t('Request Kemasan') . '</h5>',
    ];
    // Build table headers for request produksi
    $header = [
      ['data' => '', 'class' => 'hidden-column'],
      ['data' => 'Kemasan'],
      ['data' => 'Total Qty Requested'],
    ];    // Table header
    $form['request_kemasan_summary'] = [
      '#type' => 'table',
      '#header' => $header,
      '#prefix' => '<div class="request-kemasan-summary">',
      '#suffix' => '</div>',
      '#attributes' => [ 'id' => 'request-kemasan-summary-table', ],
    ];
    // Build table rows.
    foreach ($resultPackaging as $index => $row) {
      $form['request_kemasan_summary'][$index]['id_product'] = [
        '#type' => 'hidden',
        '#default_value' => $row->id_product,
      ];
      $form['request_kemasan_summary'][$index]['product_name'] = [
        '#markup' => $row->product_name,
      ];
      $form['request_kemasan_summary'][$index]['total_qty'] = [
        '#type' => 'textfield',
        '#default_value' => $row->total_qty,
        '#size' => 5,
        '#attributes' => ['class' => ['total-qty-input']],
      ];
    }
    $form['keterangan_kemasan'] = [
      '#type' => 'textfield',
      '#attributes' => [
        'class' => ['form-control'],
        'placeholder' => 'Keterangan Kemasan',
      ],
      '#prefix' => '<div class="input-group mt-2 mb-2"><span class="input-group-text">Keterangan</span>',
      '#suffix' => '</div>',
      '#required' => FALSE,
    ];
    $form['actions'] = [
      '#type' => 'submit',
      '#value' => $this->t('Ajukan Request Produksi'),
      '#attributes' => [
        'class' => ['btn btn-primary'],
      ],
      '#ajax' => [
        'callback' => '::ajaxSubmit',
        'wrapper' => 'request-produksi-form-wrapper',
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Saving...'),
        ],
      ],
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
    $values['ids_request'] = json_decode($form_state->getValue('selected_request_id'));
    $values['tgl_request'] = $date->format('Y-m-d H:i:s');;
    $values['detail_produksi'] = $form_state->getValue('request_admin_summary');
    $values['keterangan_produksi'] = $form_state->getValue('keterangan_produksi');
    $values['detail_kemasan'] = $form_state->getValue('request_kemasan_summary');
    $values['keterangan_kemasan'] = $form_state->getValue('keterangan_produksi');
    dpm($values);
    $result = $this->dataSourceService->saveRequestProduction($values);

    if ($result) {
      // Success
      $response->addCommand(new MessageCommand($this->t('Request Production & Packaging saved successfully.'), NULL, ['type' => 'status'], TRUE));
      $response->addCommand(new CloseModalDialogCommand());
      $response->addCommand(new RedirectCommand(Url::fromRoute('data_request_admin.table')->toString()));
    }
    else {
      // Error
      $response->addCommand(new MessageCommand($this->t('Error saving request.'), NULL, ['type' => 'error'], TRUE));
    }

    return $response;
  }
}

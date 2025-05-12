<?php

declare(strict_types=1);

namespace Drupal\data_request_admin\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\MessageCommand;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\data_source\Service\DataSourceService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\Constraints\File as SymfonyFile;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\Entity\File;

/**
 * Provides a Data request admin form.
 */
final class AddRequestAdmin extends FormBase {
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
    return 'data_request_admin_add_request_admin';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $id_request = NULL): array {
    $request_data = [];
    $SelectedData = null;
    $current_file_id = null;

    if ($id_request) {
      $table_name = 'request_admin';
      $field_data = $this->dataSourceService->getTableFields($table_name);
      $query = $this->dataSourceService->fetchRecordsById($table_name, $field_data, $id_request);
      if ($query) {
        $request_data = $query;
        $form['id'] = [
          '#type' => 'hidden',
          '#value' => $request_data->id_request_admin ?? '',
        ];

        // Store the current file ID if it exists
        if (!empty($request_data->file_id)) {
          $current_file_id = $request_data->file_id;
          $form['existing_file_id'] = [
            '#type' => 'hidden',
            '#value' => $current_file_id,
          ];
        }

        $table_name = 'request_admin_detail';
        $field_data = $this->dataSourceService->getTableFields($table_name);
        $field_value[] = ['id_request_admin' => $request_data->id_request_admin];
        $left_join = [
          'alias' => 'p',
          'table_name' => 'product',
          'target_field' => 'id_product',
          'source_field' => 'product_id',
          'field_name' => ['product_id','brand','product_name'],
        ];
        $result = $this->dataSourceService->fetchRecordsByField($table_name, $field_data, $field_value, $left_join,[],[]);
        $ArrData = [];
        foreach ($result as $rowData){
          $newRow = new \stdClass();
          $newRow->product_id = $rowData->product_id;
          $newRow->product_code = $rowData->brand;
          $newRow->product_name = $rowData->product_name;
          $newRow->qty = $rowData->qty_request;
          $ArrData[] = $newRow;
        }
        $SelectedData = json_encode($ArrData);
      }
    }
    $form['no_request'] = [
      '#type' => 'textfield',
      '#attributes' => [
        'class' => ['form-control'],
        'placeholder' => 'Nomor Request',
      ],
      '#prefix' => '<div class="input-group mb-3"><span class="input-group-text">No. Request</span>',
      '#suffix' => '</div>',
      '#required' => TRUE,
      '#default_value' => $request_data->no_request ?? '',
    ];
    if (!empty($request_data->tgl_request)) {
      $datetime = new \DateTime($request_data->tgl_request);
      $unix_timestamp = $datetime->getTimestamp();
      $form['tgl_request'] = [
        '#type' => 'date',
        '#attributes' => [
          'class' => ['form-control'],
        ],
        '#prefix' => '<div class="input-group mb-3"><span class="input-group-text">Tanggal</span>',
        '#suffix' => '</div>',
        '#default_value' => date('Y-m-d',$unix_timestamp) ?? '',
      ];
    }else{
      $form['tgl_request'] = [
        '#type' => 'date',
        '#attributes' => [
          'class' => ['form-control'],
        ],
        '#prefix' => '<div class="input-group mb-3"><span class="input-group-text">Tanggal</span>',
        '#suffix' => '</div>',
        '#default_value' => date('Y-m-d'),
      ];
    }
    $form['nama_pemesan'] = [
      '#type' => 'textfield',
      '#attributes' => [
        'class' => ['form-control'],
        'placeholder' => 'Nama Pemesan',
      ],
      '#prefix' => '<div class="input-group mb-3"><span class="input-group-text">Nama Pemesan</span>',
      '#suffix' => '</div>',
      '#required' => TRUE,
      '#default_value' => $request_data->nama_pemesan ?? '',
    ];
    $form['keterangan'] = [
      '#type' => 'textarea',
      '#attributes' => [
        'class' => ['form-control'],
        'placeholder' => 'Keterangan',
        'cols' => 62,
      ],
      '#prefix' => '<div class="input-group mb-3"><span class="input-group-text">Keterangan</span>',
      '#suffix' => '</div>',
      '#required' => FALSE,
      '#default_value' => $request_data->keterangan ?? '',
    ];

    $directory = 'public://requestfile';
    \Drupal::service('file_system')->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

    // Set up file attachment field with default value if editing
    $file_field_options = [
      '#type' => 'managed_file',
      '#upload_location' => 'public://requestfile/',
      '#constraints' => [
        new SymfonyFile([
          'maxSize' => '25M',
          'mimeTypes' => [
            'application/pdf',
            'image/jpeg',
            'image/png',
          ],
          'mimeTypesMessage' => $this->t('Please upload a valid file (pdf, jpg, png).'),
        ]),
      ],
      '#prefix' => '<div class="input-group mb-3"><span class="input-group-text">File Attachment</span><div class="form-control">',
      '#suffix' => '</div></div>',
    ];

    // Make file required only for new records
    if (!$id_request) {
      $file_field_options['#required'] = TRUE;
    } else {
      $file_field_options['#required'] = FALSE;

      // Set default value if there's an existing file
      if ($current_file_id) {
        $file_field_options['#default_value'] = [$current_file_id];
        // Add description about existing file
        $file = File::load($current_file_id);
        if ($file) {
          $file_field_options['#description'] = $this->t('Current file: @filename. Upload a new file to replace it',
            ['@filename' => $file->getFilename()]);
        }
      }
    }

    $form['file_attachment'] = $file_field_options;

    // Create a container to hold both fields
    $form['product_container'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['form-group'],
      ],
    ];

    // Create a wrapper DIV for the input group using #prefix
    $form['product_container']['product_wrapper'] = [
      '#type' => 'markup',
      '#markup' => '<div class="input-group mb-3">',
      '#weight' => 0,
    ];

    // Add the input group label
    $form['product_container']['product_label'] = [
      '#type' => 'markup',
      '#markup' => '<span class="input-group-text">Pilih Produk</span>',
      '#weight' => 1,
    ];
    $form['product_container']['search_product_button'] = [
      '#type' => 'markup',
      '#markup' => '<a title="click to search product" class="btn btn-outline-secondary" type="button" id="button-addon1"><i class="fa-solid fa-magnifying-glass"></i></a>',
      '#weight' => 2,
    ];
    // Add the first input field with autocomplete
    $form['product_container']['product'] = [
      '#type' => 'textfield',
      '#attributes' => [
        'class' => ['form-control', 'input-product-group'],
        'placeholder' => 'Pilih Produk',
        'id' => 'product-search',
      ],
      '#autocomplete_route_name' => 'data_request_admin.product_autocomplete',
      '#autocomplete_route_parameters' => [],
      '#required' => FALSE, // Changed to FALSE since we'll validate the presence of products in the table instead
      '#weight' => 3,
    ];

    // Hidden field to store the product ID
    $form['product_container']['product_id'] = [
      '#type' => 'hidden',
      '#attributes' => [
        'id' => 'product-id',
      ],
      '#weight' => 3.5,
    ];

    // Add the Qty label between the fields
    $form['product_container']['qty_label'] = [
      '#type' => 'markup',
      '#markup' => '<span class="input-group-text qty-label">Qty</span>',
      '#weight' => 4,
    ];

    // Add the second input field (quantity)
    $form['product_container']['product_qty'] = [
      '#type' => 'textfield',
      '#attributes' => [
        'class' => ['form-control', 'input-qty-group'],
        'placeholder' => 'Qty Product',
        'id' => 'product-qty',
      ],
      '#default_value' => '1',
      '#weight' => 5,
    ];
    $form['product_container']['add_product_button'] = [
      '#type' => 'markup',
      '#markup' => '<a title="click to add product as requested product" class="btn btn-success" id="add-product-btn" type="button"><i class="fa-solid fa-circle-plus"></i></a>',
      '#weight' => 6,
    ];
    // Close the input group div
    $form['product_container']['product_wrapper_close'] = [
      '#type' => 'markup',
      '#markup' => '</div>',
      '#weight' => 7,
    ];

    // Remove theme wrappers to prevent extra divs
    $form['product_container']['#theme_wrappers'] = [];
    $form['product_container']['product']['#theme_wrappers'] = [];
    $form['product_container']['product_qty']['#theme_wrappers'] = [];

    // Add the table for selected products
    $form['selected_products_container'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['mt-3', 'mb-1'],
      ],
    ];

    $form['selected_products_container']['selected_products_table'] = [
      '#type' => 'markup',
      '#markup' => '<div class="table-responsive">
        <table id="selected-products-table" class="table table-striped table-bordered">
          <thead class="table-light">
            <tr>
              <th>No</th>
              <th>Brand</th>
              <th>Nama Produk</th>
              <th>Qty</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody id="selected-products-body">
            <!-- Table rows will be inserted here dynamically -->
          </tbody>
        </table>
      </div>',
    ];
    if (!empty($SelectedData)){
      // Hidden field to store the selected products as JSON
      $form['selected_products'] = [
        '#type' => 'hidden',
        '#attributes' => [
          'id' => 'selected-products-data',
        ],
        '#default_value' => $SelectedData,
      ];
    }else {
      // Hidden field to store the selected products as JSON
      $form['selected_products'] = [
        '#type' => 'hidden',
        '#attributes' => [
          'id' => 'selected-products-data',
        ],
        '#default_value' => '[]',
      ];
    }
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Simpan'),
      '#attributes' => [
        'class' => ['btn btn-primary'],
      ],
      '#ajax' => [
        'callback' => '::ajaxSubmit',
        'wrapper' => 'request-admin-form-wrapper',
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Saving...'),
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
    $form['#prefix'] = '<div id="request-admin-form-wrapper">';
    $form['#suffix'] = '</div>';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $attachment = $form_state->getValue('file_attachment');
    $id = $form_state->getValue('id');
    // Only validate the file attachment if this is a new record or if a new file was uploaded
    if (empty($id) && empty($attachment)) {
      $form_state->setErrorByName('file_attachment', $this->t('Please upload a file attachment.'));
    }

    // Validate the nama_pemesan field
    $nama_pemesan = $form_state->getValue('nama_pemesan');
    if (empty($nama_pemesan)) {
      $form_state->setErrorByName('nama_pemesan', $this->t('Please enter nama pemesan.'));
    }

    // Validate that there are selected products
    $selected_products = json_decode($form_state->getValue('selected_products'), TRUE);
    if (empty($selected_products)) {
      $form_state->setErrorByName('selected_products', $this->t('Please select at least one product.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Empty implementation as we're using Ajax for form submission
  }

  /**
   * Ajax submit handler.
   */
  public function ajaxSubmit(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();

    if ($form_state->hasAnyErrors()) {
      // Return the form with errors
      return $form;
    }

    // Get the form values
    $id = !empty($form_state->getValue('id')) ? $form_state->getValue('id') : null;
    $no_request = $form_state->getValue('no_request');
    $tgl_request = $form_state->getValue('tgl_request');
    $nama_pemesan = $form_state->getValue('nama_pemesan');
    $date = new \DateTime($tgl_request);
    // Format it as a PostgreSQL timestamp string
    $tgl_request = $date->format('Y-m-d H:i:s');
    $keterangan = $form_state->getValue('keterangan');
    $selected_products = json_decode($form_state->getValue('selected_products'), TRUE);

    // Handle file attachment
    $file_upload = $form_state->getValue('file_attachment');
    $existing_file_id = $form_state->getValue('existing_file_id');
    // If editing and no new file was uploaded, use the existing file ID
    if ($id && empty($file_upload) && !empty($existing_file_id)) {
      $file_upload = $existing_file_id;
    }

    $fields_data = [
      'no_request' => $no_request,
      'tgl_request' => $tgl_request,
      'keterangan' => $keterangan,
      'nama_pemesan' => $nama_pemesan,
      'file_upload' => $file_upload,
      'detail_data' => $selected_products,
    ];
    // Save the request using the service
    $result = $this->dataSourceService->saveRequest($id, $fields_data);
    if ($result) {
      // Success
      $response->addCommand(new MessageCommand($this->t('Request saved successfully.'), NULL, ['type' => 'status'], TRUE));
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

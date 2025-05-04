<?php

declare(strict_types=1);

namespace Drupal\data_request_admin\Form;

use Drupal\Core\Database\Database;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Data request admin form.
 */
final class AddRequestAdmin extends FormBase {

  protected Connection $database;
  protected $targetDatabase = 'minigold_master';
  public function __construct(Connection $database) {
    $this->database = Database::getConnection('default', $this->targetDatabase);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database')
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
    // Attach custom CSS and JavaScript
    $form['#attached']['library'][] = 'data_request_admin/form_request_admin';

    $request_data = [];
    $SelectedData = null;
    if ($id_request) {
      $query = $this->database->select('request_admin', 'c')
        ->fields('c', ['id_request_admin', 'no_request', 'tgl_request', 'uid_request',
          'keterangan', 'status_request', 'uid_changed', 'created', 'changed'])
        ->condition('id_request_admin', $id_request)
        ->execute()
        ->fetchAssoc();

      if ($query) {
        $request_data = $query;
        $form['id'] = [
          '#type' => 'hidden',
          '#value' => $request_data['id_request_admin'] ?? '',
        ];
        $query2 = $this->database->select('request_admin_detail', 'rad')
          ->fields('rad', ['id_product', 'qty_request'])
          ->condition('rad.id_request_admin', $id_request);
        $query2->leftJoin('product', 'p', 'rad.id_product = p.product_id');
        // Add fields from the product table
        $query2->addField('p', 'product_id');
        $query2->addField('p', 'brand');
        $query2->addField('p', 'product_name');
        $result = $query2->execute()->fetchAll();
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
      '#default_value' => $request_data['no_request'] ?? '',
    ];
    if (!empty($request_data['tgl_request'])) {
      $datetime = new \DateTime($request_data['tgl_request']);
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
      '#default_value' => $request_data['keterangan'] ?? '',
    ];

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
      '#default_value' => $request_data['no_request'] ?? '',
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
        'class' => ['mt-3', 'mb-4'],
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
    ];
    $form['actions']['cancel'] = [
      '#type' => 'markup',
      '#markup' => '&nbsp;<a type="button" class="btn btn-danger" data-bs-dismiss="modal">Batal</a>',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    // Validate that at least one product has been selected
    $selected_products = json_decode($form_state->getValue('selected_products'), TRUE);

    if (empty($selected_products)) {
      $form_state->setErrorByName('selected_products', $this->t('Please add at least one product to the request.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Get the form values
    $no_request = $form_state->getValue('no_request');
    $tgl_request = $form_state->getValue('tgl_request');
    $date = new \DateTime($tgl_request);
    // Format it as a PostgreSQL timestamp string
    $tgl_request = $date->format('Y-m-d H:i:s');
    $keterangan = $form_state->getValue('keterangan');
    $selected_products = json_decode($form_state->getValue('selected_products'), TRUE);
    $id = $form_state->getValue('id');

    // Use database transaction to ensure data integrity
    $transaction = $this->database->startTransaction();

    try {
      if (!empty($id)) {
        // Update existing record
        $this->database->update('request_admin')
          ->fields([
            'no_request' => $no_request,
            'tgl_request' => $tgl_request,
            'keterangan' => $keterangan,
            'uid_changed' => \Drupal::currentUser()->id(),
            'changed' => date('Y-m-d H:i:s'),
          ])
          ->condition('id_request_admin', $id)
          ->execute();

        // Delete all existing detail records for this request
        $this->database->delete('request_admin_detail')
          ->condition('id_request_admin', $id)
          ->execute();

        // Insert new detail records
        foreach ($selected_products as $product) {
          $this->database->insert('request_admin_detail')
            ->fields([
              'id_request_admin' => $id,
              'id_product' => $product['product_id'],
              'qty_request' => $product['qty'],
            ])
            ->execute();
        }

        $id_request = $id;
      } else {
        // Insert new record
        $id_request = $this->database->insert('request_admin')
          ->fields([
            'no_request' => $no_request,
            'tgl_request' => $tgl_request,
            'uid_request' => \Drupal::currentUser()->id(),
            'keterangan' => $keterangan,
            'status_request' => 0,
          ])
          ->execute();

        // Insert the product items
        foreach ($selected_products as $product) {
          $this->database->insert('request_admin_detail')
            ->fields([
              'id_request_admin' => $id_request,
              'id_product' => $product['product_id'],
              'qty_request' => $product['qty'],
            ])
            ->execute();
        }
      }

      $this->messenger()->addStatus($this->t('Request has been saved successfully.'));
      $form_state->setRedirect('data_request_admin.table');
    }
    catch (\Exception $e) {
      // Rollback the transaction if something went wrong
      $transaction->rollBack();
      $this->messenger()->addError($this->t('An error occurred while saving your request. Please try again.'));
      \Drupal::logger('data_request_admin')->error('Error saving request: @message', ['@message' => $e->getMessage()]);
    }
  }

}

<?php

namespace Drupal\minigold_request\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\minigold_request\Service\RequestAdminService;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Ajax\MessageCommand;

/**
 * Form for adding/editing Request Admin records.
 */
class RequestAdminForm extends FormBase {

  /**
   * The request admin service.
   *
   * @var \Drupal\minigold_request\Service\RequestAdminService
   */
  protected $requestAdminService;

  /**
   * Constructs a RequestAdminForm object.
   *
   * @param \Drupal\minigold_request\Service\RequestAdminService $request_admin_service
   *   The request admin service.
   */
  public function __construct(RequestAdminService $request_admin_service) {
    $this->requestAdminService = $request_admin_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('minigold_request.request_admin_service')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'request_admin_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $id = 0) {
    // Store the ID for use in submit handler
    $form_state->set('id', $id);

    // If editing, load the existing record
    $request_data = [];
    if ($id > 0) {
      $request_data = $this->requestAdminService->getRequestById($id);
      if (empty($request_data)) {
        $this->messenger()->addError($this->t('Request not found.'));
        return $form;
      }
    }

    // Build the form
    $form['#prefix'] = '<div id="request-admin-form-wrapper">';
    $form['#suffix'] = '</div>';

    $form['no_request'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Request Number'),
      '#required' => TRUE,
      '#default_value' => $request_data['no_request'] ?? '',
    ];

    $form['tgl_request'] = [
      '#type' => 'date',
      '#title' => $this->t('Request Date'),
      '#required' => TRUE,
      '#default_value' => $request_data['tgl_request'] ?? date('Y-m-d'),
    ];

    $form['nama_pemesan'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Requestor Name'),
      '#required' => TRUE,
      '#default_value' => $request_data['nama_pemesan'] ?? '',
    ];

    $form['keterangan'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $request_data['keterangan'] ?? '',
    ];

    $form['file_upload'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Attachment'),
      '#upload_location' => 'public://requestfile/',
      '#description' => $this->t('Allowed file types: PDF, DOC, DOCX, XLS, XLSX, JPG, JPEG, PNG. Maximum size: 25MB.'),
      '#default_value' => !empty($request_data['file_id']) ? [$request_data['file_id']] : NULL,
    ];

    // Product selection with autocomplete
    $form['product'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Product'),
      '#required' => TRUE,
      '#autocomplete_route_name' => 'minigold_request.product_autocomplete',
      '#description' => $this->t('Start typing to search for products.'),
    ];

    // Hidden field to store selected product ID
    $form['product_id'] = [
      '#type' => 'hidden',
      '#attributes' => ['id' => 'product-id'],
    ];

    // Submit buttons
    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#button_type' => 'primary',
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
      '#value' => $this->t('Cancel'),
      '#ajax' => [
        'callback' => '::ajaxCancel',
        'wrapper' => 'request-admin-form-wrapper',
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Validate that a product was selected
    if (empty($form_state->getValue('product_id'))) {
      $form_state->setErrorByName('product', $this->t('Please select a valid product from the autocomplete options.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // This is not used because we're using ajax submit
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

    // Get form values
    $id = $form_state->get('id');
    $values = [
      'no_request' => $form_state->getValue('no_request'),
      'tgl_request' => $form_state->getValue('tgl_request'),
      'nama_pemesan' => $form_state->getValue('nama_pemesan'),
      'keterangan' => $form_state->getValue('keterangan'),
      'file_upload' => $form_state->getValue('file_upload'),
      'product_id' => $form_state->getValue('product_id'),
    ];

    // Save the request using the service
    $result = $this->requestAdminService->saveRequest($id, $values);

    if ($result) {
      // Success
      $response->addCommand(new MessageCommand($this->t('Request saved successfully.'), NULL, ['type' => 'status'], TRUE));
      $response->addCommand(new CloseModalDialogCommand());
      $response->addCommand(new RedirectCommand(Url::fromRoute('minigold_request.admin_list')->toString()));
    }
    else {
      // Error
      $response->addCommand(new MessageCommand($this->t('Error saving request.'), NULL, ['type' => 'error'], TRUE));
    }

    return $response;
  }

  /**
   * Ajax cancel handler.
   */
  public function ajaxCancel(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    $response->addCommand(new CloseModalDialogCommand());
    return $response;
  }
}

<?php

namespace Drupal\minigold_request\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\minigold_request\Service\RequestAdminService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Form\FormBuilder;

/**
 * Controller for the Request Admin functionality.
 */
class RequestAdminController extends ControllerBase {

  /**
   * The request admin service.
   *
   * @var \Drupal\minigold_request\Service\RequestAdminService
   */
  protected $requestAdminService;

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilder
   */
  protected $formBuilder;

  /**
   * Constructs a RequestAdminController object.
   *
   * @param \Drupal\minigold_request\Service\RequestAdminService $request_admin_service
   *   The request admin service.
   * @param \Drupal\Core\Form\FormBuilder $form_builder
   *   The form builder.
   */
  public function __construct(RequestAdminService $request_admin_service, FormBuilder $form_builder) {
    $this->requestAdminService = $request_admin_service;
    $this->formBuilder = $form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('minigold_request.request_admin_service'),
      $container->get('form_builder')
    );
  }

  /**
   * Lists all request admin records.
   *
   * @return array
   *   A render array.
   */
  public function listRequests() {
    // Get all requests from the service
    $requests = $this->requestAdminService->getAllRequests();

    // Define table headers
    $header = [
      'actions' => $this->t('Actions'),
      'id_request_admin' => $this->t('ID'),
      'no_request' => $this->t('Request Number'),
      'tgl_request' => $this->t('Request Date'),
      'nama_pemesan' => $this->t('Requestor Name'),
      'keterangan' => $this->t('Description'),
      'file_attachment' => $this->t('Attachment'),
    ];

    // Create add button with modal class
    $add_button = Link::fromTextAndUrl(
      $this->t('Add Request'),
      Url::fromRoute('minigold_request.admin_modal', ['id' => 0])
    )->toRenderable();

    $add_button['#attributes'] = [
      'class' => ['btn', 'btn-primary', 'use-ajax'],
      'data-dialog-type' => 'modal',
      'data-dialog-options' => json_encode([
        'width' => '800',
      ]),
    ];

    // Return the themed list
    return [
      '#theme' => 'request_admin_list',
      '#requests' => $requests,
      '#add_button' => $add_button,
      '#header' => $header,
      '#attached' => [
        'library' => [
          'core/drupal.dialog.ajax',
        ],
      ],
    ];
  }

  /**
   * Displays a modal form for adding/editing request admin records.
   *
   * @param int $id
   *   The ID of the request admin record to edit, or 0 for a new record.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   An AJAX response to open the modal dialog.
   */
  public function modalForm($id = 0) {
    $response = new AjaxResponse();

    // Build the form
    $form = $this->formBuilder->getForm('Drupal\minigold_request\Form\RequestAdminForm', $id);

    // Add the form to a modal dialog
    $title = ($id > 0) ? $this->t('Edit Request Admin') : $this->t('Add Request Admin');
    $response->addCommand(new OpenModalDialogCommand($title, $form, ['width' => '650']));

    return $response;
  }

  /**
   * Autocomplete callback for product search.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   A JSON response containing the matched products.
   */
  public function productAutocomplete(Request $request) {
    $query = $request->query->get('q');

    // Get matching products from the service
    $matches = $this->requestAdminService->getProductMatches($query);

    return new JsonResponse($matches);
  }
}

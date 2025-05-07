<?php

declare(strict_types=1);

namespace Drupal\data_request_admin\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\RendererInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\data_request_admin\Form\AddRequestAdmin;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Database\Connection;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Url;

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

  protected Connection $database;
  protected $targetDatabase = 'minigold_master';

  public function __construct(FormBuilderInterface $form_builder, RendererInterface $renderer, AccountInterface $current_user, Connection $database) {
    $this->formBuilder = $form_builder;
    $this->renderer = $renderer;
    $this->currentUser = $current_user;
    $this->database = Database::getConnection('default', $this->targetDatabase);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('form_builder'),
      $container->get('renderer'),
      $container->get('current_user'),
      $container->get('database')
    );
  }


  /**
   * Builds the response.
   */
  public function __invoke(): array {
    if ($this->currentUser->hasPermission('administer request admin')) {
      $header = [
        ['data' => '', 'datatable_options' => ['data-orderable' => 'false', 'class' => 'no-sort', 'searchable' => 'false']],
        ['data' => '', 'datatable_options' => ['data-orderable' => 'false', 'class' => 'no-sort', 'searchable' => 'false']],
      ];
    }
    $header = [ ...$header,
      // -- set only have time ['data' => '', 'datatable_options' => ['data-orderable' => 'false', 'class' => 'no-sort', 'searchable' => 'false']],
      ['data' => t('ID'), 'datatable_options' => ['data-orderable' => 'true', 'searchable' => 'false']],
      ['data' => t('No, Req'), 'datatable_options' => ['data-orderable' => 'true', 'searchable' => 'true']],
      ['data' => t('Tgl Request'), 'datatable_options' => ['data-orderable' => 'true', 'searchable' => 'true']],
      ['data' => t('Request By'), 'datatable_options' => ['data-orderable' => 'true', 'searchable' => 'false']],
      ['data' => t('Keterangan'), 'datatable_options' => ['data-orderable' => 'true', 'searchable' => 'true']],
      ['data' => t('Status'), 'datatable_options' => ['data-orderable' => 'true', 'searchable' => 'false']],
      ['data' => t('Change By'), 'datatable_options' => ['data-orderable' => 'true', 'searchable' => 'false']],
      //['data' => t('Created'), 'datatable_options' => ['data-orderable' => 'true', 'searchable' => 'false']],
      //['data' => t('Changed'), 'datatable_options' => ['data-orderable' => 'true', 'searchable' => 'false']],
    ];
    $rows = [];
    $rowData = array_fill(0, count($header), '');
    $rows[] = $rowData;

    // Default route is with ID 0 for adding new requests
    $add_button = Link::fromTextAndUrl(
      $this->t('Add Request'),
      Url::fromRoute('data_request_admin.modal_form', ['id' => 0])
    )->toRenderable();

    $add_button['#attributes'] = [
      'class' => ['btn', 'btn-primary', 'use-ajax'],
      'id' => 'add-request-button',
      'data-dialog-type' => 'modal',
      'data-dialog-options' => json_encode([
        'width' => '800',
      ]),
    ];
    $rendered_button = \Drupal::service('renderer')->render($add_button);

    return [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#theme' => 'datatable',
      '#attributes' => [
        'class' => ['table', 'table-striped', 'table-hover', 'table-sm'],
        'style' => 'width: 100%',
      ],
      '#datatable_options' => $this->getDataTableOptions(),
      '#prefix' => $rendered_button,
      '#attached' => [
        'library' => [
          'data_request_admin/datarequestadmin_js',
          'core/drupal.dialog.ajax',// Define the library in the module's *.libraries.yml file.
        ],
        'drupalSettings' => [
          'dataRequestAdmin' => [
            'modalFormUrl' => Url::fromRoute('data_request_admin.modal_form', ['id' => 0])->toString(),
          ],
        ],
      ],
    ];
  }
  /**
   * Returns the DataTable options.
   */
  private function getDataTableOptions() {
    if ($this->currentUser->hasPermission('administer request admin')) {
      $AjaxUrl = base_path() . 'datasource/getdata/request_admin?editable=1&deletable=1';
      $orderedColumn = 2;
    }else{
      $AjaxUrl = base_path() . 'datasource/getdata/request_admin';
      $orderedColumn = 0;
    }
    return [
      'info' => TRUE,
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

  public function deleteRequestAdmin($id = NULL) {
    if (!empty($id)) {
      $query = $this->database->select('request_admin', 'c')
        ->fields('c', ['id_request_admin'])
        ->condition('id_request_admin', $id)
        ->execute()
        ->fetchObject();

      if (!empty($query)) {
        // Delete all existing detail records for this request
        $this->database->delete('request_admin_detail')
          ->condition('id_request_admin', $id)
          ->execute();

        // Delete the main request
        $this->database->delete('request_admin')
          ->condition('id_request_admin', $id)
          ->execute();
      }
      // Set drupal message if delete success
      $this->messenger()->addStatus($this->t('Request has been successfully deleted.'));
    }
    // Redirect to the route after deletion
    return new RedirectResponse(Url::fromRoute('data_request_admin.table')->toString());
  }
}

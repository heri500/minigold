<?php

declare(strict_types=1);

namespace Drupal\data_request_admin\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Render\RendererInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\data_request_admin\Form\AddRequestAdmin;

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

  public function __construct(FormBuilderInterface $form_builder, RendererInterface $renderer) {
    $this->formBuilder = $form_builder;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('form_builder'),
      $container->get('renderer')
    );
  }


  /**
   * Builds the response.
   */
  public function __invoke(): array {
    $header = [
      ['data' => '', 'datatable_options' => ['data-orderable' => 'false', 'class' => 'no-sort', 'searchable' => 'false']],
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

    $Prefix = '<div class="col"><a id="add-new-request" href="#" class="btn btn-primary btn-sm">BUAT REQUEST</a></div>';
    $Prefix .= '<div class="col">&nbsp;</div>';
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
      '#prefix' => $Prefix,
      '#attached' => [
        'library' => [
          'data_request_admin/datarequestadmin_js', // Define the library in the module's *.libraries.yml file.
        ],
      ],
    ];
  }
  /**
   * Returns the DataTable options.
   */
  private function getDataTableOptions() {
    return [
      'info' => TRUE,
      'stateSave' => TRUE,
      'ajax' => base_path() . 'datasource/getdata/request_admin?editable=1',
      'processing' => TRUE,
      'serverSide' => TRUE,
      'paginationType' => 'full_numbers',
      'pageLength' => 50,
      'lengthMenu' => [[50, 100, 250, 500, -1], [50, 100, 250, 500, "All"]],
      'order' => [
        [1, 'desc'],
      ],
    ];
  }

  public function addRequestForm($id = NULL) { //// Pass $id to the form for editing mode
    $form = \Drupal::formBuilder()->getForm(AddRequestAdmin::class, $id);
    return $form;
  }
}

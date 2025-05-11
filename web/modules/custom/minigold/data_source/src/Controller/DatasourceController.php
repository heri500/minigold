<?php

namespace Drupal\data_source\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\data_source\Service\DataSourceService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\data_request_admin\StatusRequest;
use Drupal\user\Entity\User;
use Drupal\data_source\Service\FileLinkGenerator;
/**
 * Provides route responses for the Data Source module.
 */
class DatasourceController extends ControllerBase {

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
  protected FileLinkGenerator $fileLinkGenerator;
  public function __construct(DataSourceService $data_source_service, RequestStack $request_stack, FileLinkGenerator $file_link_generator) {
    $this->dataSourceService = $data_source_service;
    $this->requestStack = $request_stack;
    $this->fileLinkGenerator = $file_link_generator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('data_source.service'),
      $container->get('request_stack'),
      $container->get('data_source.file_link_generator')
    );
  }

  /**
   * Returns data from the specified table in DataTables format.
   *
   * @param string|null $table_name
   *   The name of the table to query.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   A JSON response containing the data in DataTables format.
   */
  public function getData($table_name = NULL) {
    if (empty($table_name)) {
      return new JsonResponse([
        'draw' => 0,
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => [],
      ]);
    }
    $statuses = StatusRequest::STATUS;
    $statusesColor = StatusRequest::STATUSCOLOR;
    $request = $this->requestStack->getCurrentRequest();
    $dt_params = $request->query->all();

    // Get table fields and validate table existence
    $field_id = $this->dataSourceService->getTableFieldsId($table_name);
    $field_data = $this->dataSourceService->getTableFields($table_name);
    if (empty($field_data)) {
      return new JsonResponse(['error' => 'No field definitions found for table ' . $table_name], 404);
    }

    // Check if table exists
    if (!$this->dataSourceService->tableExists($table_name)) {
      return new JsonResponse(['error' => 'Table ' . $table_name . ' does not exist.'], 404);
    }
    $dt_params['table_name'] = $table_name;
    // Map DataTables parameters to our service parameters
    $params = $this->mapDataTablesParams($dt_params, $field_data);
    // Get data using the service
    $result = $this->dataSourceService->fetchRecords($table_name, $field_data, $params);

    // Prepare response in DataTables format
    $output = [
      'draw' => isset($dt_params['draw']) ? (int) $dt_params['draw'] : 1,
      'recordsTotal' => $result['total'],
      'recordsFiltered' => $result['filtered_total'],
      'data' => [],
    ];

    // Format data rows
    $editable = !empty($dt_params['editable']) && $dt_params['editable'] == 1;
    $view_detail = !empty($dt_params['view_detail']) && $dt_params['view_detail'] == 1;
    $deletable = !empty($dt_params['deletable']) && $dt_params['deletable'] == 1;
    if ($editable){
      $canEdit = 1;
    }else{
      $canEdit = 0;
    }
    if ($deletable){
      $canDelete = 1;
    }else{
      $canDelete = 0;
    }
    foreach ($result['records'] as $record) {
      foreach ($field_data as $field) {
        if (str_starts_with($field, 'status')) {
          if ($record->{$field} > 0){
            $canEdit = 0;
            $canDelete = 0;
          }else{
            $canEdit = 1;
            $canDelete = 1;
          }
        }
      }
      $row = [];
      $row[] = '';
      // Add edit button if requested
      if ($editable) {
        if ($canEdit) {
          $row[] = '<div class="icon-edit"><a title="click to edit record" data-id="' . $record->{$field_id} . '" class="edit-icon" href="#"><i class="fa-solid fa-pen-to-square"></i></a></div>';
        }else{
          $row[] = '<div class="disable-icon-edit"><a title="record lock" data-id="' . $record->{$field_id} . '" class="lock-icon icon-danger" href="#"><i class="fa-solid fa-lock"></i></a></div>';
        }
      }
      if ($deletable) {
        if ($canDelete) {
          $row[] = '<div class="icon-edit"><a title="click to delete request" data-id="' . $record->{$field_id} . '" class="delete-icon icon-danger" href="#"><i class="fa-solid fa-trash-can"></i></a></div>';
        }else{
          $row[] = '<div class="disable-icon-edit"><a title="record lock" data-id="' . $record->{$field_id} . '" class="lock-icon icon-danger" href="#"><i class="fa-solid fa-lock"></i></a></div>';
        }
      }
      if ($view_detail){
        $row[] = '<div class="icon-edit"><a title="click to view detail request" data-id="' . $record->{$field_id} . '" class="detail-icon" href="#"><i class="fa-solid fa-play"></i></a></div>';
      }
      // Add all fields to the row
      foreach ($field_data as $field) {
        if (str_starts_with($field, 'status')) {
          $row[] = '<div data-status="'.$record->{$field}.'" class="d-grid status-cell"><a class="btn btn-block btn-xs-text btn-'.$statusesColor[$record->{$field}].'">'.$statuses[$record->{$field}].'</a></div>';
        } else if (str_starts_with($field, 'uid')) {
          if (!empty($record->{$field})) {
            $user = User::load($record->{$field});
            $username = $user->getAccountName();
            $row[] = $username;
          } else {
            $row[] = '-';
          }
        } else if (str_starts_with($field, 'tgl')) {
          if (!empty($record->{$field})) {
            $date = (new \DateTime($record->{$field}))->format('d-m-Y');
          }else{
            $date = '-';
          }
          $row[] = $date;
        } else if (str_starts_with($field, 'created') || str_starts_with($field, 'changed')) {
          if (!empty($record->{$field})) {
            $date = (new \DateTime($record->{$field}))->format('d-m-Y H:i');
          }else{
            $date = '-';
          }
          $row[] = $date;
        } else if (str_starts_with($field, 'file_id')) {
          $file_link = $this->fileLinkGenerator->renderLink($record->{$field});
          $row[] = $file_link;
        } else {
          $row[] = $record->{$field};
        }
      }

      $output['data'][] = $row;
    }

    return new JsonResponse($output);
  }

  /**
   * Maps DataTables request parameters to our service parameters.
   *
   * @param array $dt_params
   *   The DataTables parameters.
   * @param array $fields
   *   Available fields.
   *
   * @return array
   *   Mapped parameters for our service.
   */
  protected function mapDataTablesParams(array $dt_params, array $fields) {
    $params = [];
    // Search value
    $params['search_value'] = !empty($dt_params['search']['value']) ? $dt_params['search']['value'] :
      (!empty($dt_params['sSearch']) ? $dt_params['sSearch'] : NULL);

    // Search fields
    $params['search_fields'] = $this->dataSourceService->getSearchFields($dt_params['table_name'] ?? NULL);
    // Sorting (supporting both new and legacy DataTables parameters)
    if (!empty($dt_params['order']) && is_array($dt_params['order'])) {
      // New DataTables format
      $sort_index = isset($dt_params['order'][0]['column']) ?
        (int) $dt_params['order'][0]['column'] : 0;

      // Adjust for editable column if present
      if (!empty($dt_params['hasdetail']) && $dt_params['hasdetail'] == 1) {
        if (!empty($dt_params['editable']) && $dt_params['editable'] == 1) {
          $sort_index = max(0, $sort_index - 3);
        }else{
          $sort_index = max(0, $sort_index - 1);
        }
      }else{
        if (!empty($dt_params['editable']) && $dt_params['editable'] == 1) {
          $sort_index = max(0, $sort_index - 2);
        }else{
          $sort_index = max(0, $sort_index);
        }
      }
      $params['order_by'] = isset($fields[$sort_index]) ? $fields[$sort_index] : $fields[0];
      $params['order_direction'] = isset($dt_params['order'][0]['dir']) ?
        strtoupper($dt_params['order'][0]['dir']) : 'ASC';
    }
    else {
      // Legacy DataTables format
      $sort_index = isset($dt_params['iSortCol_0']) ?
        (int) $dt_params['iSortCol_0'] : 0;

      // Adjust for editable column if present
      if (!empty($dt_params['editable']) && $dt_params['editable'] == 1) {
        $sort_index = max(0, $sort_index - 1);
      }

      $params['order_by'] = isset($fields[$sort_index]) ? $fields[$sort_index] : $fields[0];
      $params['order_direction'] = isset($dt_params['sSortDir_0']) ?
        strtoupper($dt_params['sSortDir_0']) : 'ASC';
    }
    // Pagination (supporting both new and legacy DataTables parameters)
    if (isset($dt_params['start']) && isset($dt_params['length'])) {
      // New DataTables format
      $params['range'] = [
        'start' => (int) $dt_params['start'],
        'length' => (int) $dt_params['length'],
      ];
    }
    elseif (isset($dt_params['iDisplayStart']) && isset($dt_params['iDisplayLength'])) {
      // Legacy DataTables format
      $params['range'] = [
        'start' => (int) $dt_params['iDisplayStart'],
        'length' => (int) $dt_params['iDisplayLength'],
      ];
    }

    return $params;
  }

}

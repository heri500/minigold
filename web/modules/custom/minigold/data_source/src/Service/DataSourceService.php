<?php

namespace Drupal\data_source\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\file\FileInterface;

/**
 * Service for data source operations.
 */
class DataSourceService {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Table name prefix used in all database operations.
   *
   * @var string
   */
  protected $tablePrefix = '';

  /**
   * The target database key.
   *
   * @var string
   */
  protected $targetDatabase = 'minigold_master';

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a RequestAdminService object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(
    Connection $database,
    AccountProxyInterface $current_user,
    FileSystemInterface $file_system,
    EntityTypeManagerInterface $entity_type_manager
  ) {
    // Use the minigold_master database connection instead of the default one
    $this->database = Database::getConnection('default', $this->targetDatabase);
    $this->currentUser = $current_user;
    $this->fileSystem = $file_system;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Checks if a table exists.
   *
   * @param string $table_name
   *   The name of the table to check.
   *
   * @return bool
   *   TRUE if the table exists, FALSE otherwise.
   */
  public function tableExists($table_name) {
    $schema = $this->database->schema();
    $full_table_name = $this->tablePrefix . $table_name;

    // PostgreSQL is case-sensitive with table names, check if the table exists
    // by querying the information_schema
    $query = $this->database->query("SELECT to_regclass('public.{$full_table_name}')");
    $result = $query->fetchField();

    return !empty($result);
  }

  /**
   * Gets the full table name with prefix.
   *
   * @param string $table_name
   *   Base table name.
   *
   * @return string
   *   Full table name with prefix.
   */
  public function getFullTableName($table_name) {
    return $this->tablePrefix . $table_name;
  }

  /**
   * Fetches records from the specified table with DataTables server-side processing support.
   *
   * @param string $table_name
   *   Table name without prefix.
   * @param array $fields
   *   Fields to select.
   * @param array $params
   *   Query parameters including:
   *   - order_by: Field to order by.
   *   - order_direction: Sort direction (ASC/DESC).
   *   - search_value: Search string.
   *   - search_fields: Fields to search in.
   *   - range: Array with 'start' and 'length' keys for pagination.
   *
   * @return array
   *   Array containing:
   *   - records: The database records.
   *   - filtered_total: Total records after filtering.
   *   - total: Total records before filtering.
   */
  public function fetchRecords($table_name, array $fields, array $params = []) {
    $full_table_name = $this->getFullTableName($table_name);

    // First, get the total count of records in the table
    $total_query = $this->database->select($full_table_name, 'ta');
    $total_query->addExpression('COUNT(*)', 'count');
    $total = $total_query->execute()->fetchField();

    // Build the base query object
    $query = $this->database->select($full_table_name, 'ta')
      ->fields('ta', $fields);

    // Create a query clone for getting filtered count
    $filtered_count_query = clone $query;
    $filtered_count_query->countQuery();

    // Add search conditions with PostgreSQL ILIKE for case-insensitive search
    if (!empty($params['search_value']) && !empty($params['search_fields'])) {
      $db_or = $query->orConditionGroup();
      foreach ($params['search_fields'] as $field) {
        // Use ILIKE for PostgreSQL case-insensitive search
        $db_or->condition($field, '%' . $this->database->escapeLike($params['search_value']) . '%', 'ILIKE');
      }
      $query->condition($db_or);

      // Apply same conditions to count query
      $filtered_count_query_or = $filtered_count_query->orConditionGroup();
      foreach ($params['search_fields'] as $field) {
        $filtered_count_query_or->condition($field, '%' . $this->database->escapeLike($params['search_value']) . '%', 'ILIKE');
      }
      $filtered_count_query->condition($filtered_count_query_or);
    }

    // Get filtered count
    $filtered_total = !empty($params['search_value'])
      ? $filtered_count_query->execute()->fetchField()
      : $total;

    // Handle ordering (DataTables may send multiple sort columns)
    if (!empty($params['order_by'])) {
      $order_direction = !empty($params['order_direction']) ? $params['order_direction'] : 'ASC';
      $query->orderBy($params['order_by'], $order_direction);
    }

    // Add pagination - PostgreSQL uses LIMIT and OFFSET
    if (!empty($params['range']) && isset($params['range']['start']) && isset($params['range']['length'])) {
      $query->range($params['range']['start'], $params['range']['length']);
    }
    //TBD : add expression if table has expression need to execute
    $table_expression = $this->getTableExpression($table_name);
    if (!empty($table_expression) && is_array($table_expression)){
      foreach ($table_expression as $idx => $Expression){
        $query->addExpression($Expression['expression'], $Expression['alias']);
      }
    }

    // Execute and get records
    $records = $query->execute()->fetchAll();

    return [
      'records' => $records,
      'filtered_total' => (int) $filtered_total,
      'total' => (int) $total,
    ];
  }

  public function fetchRecordsById($table_name, array $fields, $id_value) {
    $full_table_name = $this->getFullTableName($table_name);
    if (empty($fields) || !is_array($fields)){
      $fields = $this->getTableFields($table_name);
    }
    $query = $this->database->select($full_table_name, 'ta')
      ->fields('ta', $fields);
    $field_id = $this->getTableFieldsId($table_name);
    if (!empty($field_id) && !empty($id_value)) {
      $db_and = $query->andConditionGroup();
      $db_and->condition($field_id, $id_value);
      $query->condition($db_and);
    }
    // Get table expression if any
    $has_expressions = $this->getTableExpression($table_name);
    if (!empty($has_expressions)){
      foreach ($has_expressions as $Expression){
        $query->addExpression($Expression['expression'], $Expression['alias']);
      }
    }
    // Execute and get records
    return $query->execute()->fetchObject();
  }

  public function fetchRecordsByIds($table_name, array $fields, array $id_value) {
    $full_table_name = $this->getFullTableName($table_name);
    if (empty($fields) || !is_array($fields)){
      $fields = $this->getTableFields($table_name);
    }
    $query = $this->database->select($full_table_name, 'ta')
      ->fields('ta', $fields);
    $field_id = $this->getTableFieldsId($table_name);
    if (!empty($field_id) && !empty($id_value)) {
      $db_and = $query->andConditionGroup();
      $db_and->condition($field_id, $id_value, 'IN');
      $query->condition($db_and);
    }
    // Execute and get records
    return $query->execute()->fetchAll();
  }

  /**
   * @param $table_name
   * @param array $fields
   * @param array $field_value
   * @param array $left_join
   * @param array $add_expression
   * @param array $group_by
   * @return array
   */
  public function fetchRecordsByField(
    $table_name, array $fields, array $field_value,
    array $left_join, array $add_expression, array $group_by
  ): array
  {
    $full_table_name = $this->getFullTableName($table_name);
    $query = $this->database->select($full_table_name, 'ta');
    if (!empty($fields)) {
      $query->fields('ta', $fields);
    }
    if (is_array($field_value) && !empty($field_value)) {
      $db_and = $query->andConditionGroup();
      foreach ($field_value as $field_cond) {
        // Use ILIKE for PostgreSQL case-insensitive search
        foreach ($field_cond as $key => $value){
          if (is_array($value) && !empty($value)){
            $db_and->condition($key, $value, 'IN');
          }else {
            $db_and->condition($key, $value);
          }
        }
      }
      $query->condition($db_and);
    }
    // check left join array and execute left join
    if (is_array($left_join) && !empty($left_join)) {
      $AliasTable = !empty($left_join['alias']) ? $left_join['alias'] : 'al';
      $query->leftJoin($left_join['table_name'], $AliasTable, 'ta.'.$left_join['target_field'].' = '.$AliasTable.'.'.$left_join['source_field']);
      if (is_array($left_join['field_name']) && !empty($left_join['field_name'])){
        foreach ($left_join['field_name'] as $field_name){
          $query->addField($AliasTable, $field_name);
        }
      }
    }

    // check if there is any expression and execute it
    if (is_array($add_expression) && !empty($add_expression)) {
      foreach ($add_expression as $expression_data) {
        $query->addExpression($expression_data['expression'], $expression_data['alias']);
      }
    }

    // check if there is any group by and execute it
    if (is_array($group_by) && !empty($group_by)) {
      foreach ($group_by as $groupby_data) {
        $query->groupBy($groupby_data['field']);
      }
    }

    // Execute and get records
    $records = $query->execute()->fetchAll();
    return $records;
  }

  public function createOptions($table_name, $field_id, $field_value = []){
    $optionSelect = [];
    $field_data = $this->getTableFields($table_name);
    if (empty($field_id)){
      $field_id = $this->getTableFieldsId($table_name);
    }
    if (!empty($table_name) && !empty($field_id) && !empty($field_value) && !empty($field_data)){
      if (is_array($field_value)){
        $new_field_value = [];
        $expression_data = [];
        foreach ($field_value as $fieldName){
          if (str_starts_with($fieldName, 'tgl')){
            $expression_data[] = ['expression' => 'DATE('.$fieldName.')', 'alias' => 'transform_date'];
            $new_field_value[] = 'transform_date';
          }else{
            $new_field_value[] = $fieldName;
          }
        }
        $field_value = $new_field_value;
      }
      $records = $this->fetchRecordsByField($table_name, $field_data,[],[],$expression_data,[]);
      foreach ($records as $optionData){
        if (is_array($field_value)){
          $valueData = [];
          foreach ($field_value as $fieldName){
            $valueData[] = $optionData->{$fieldName};
          }
          $valueData = implode('-',$valueData);
        }
        $optionSelect[$optionData->{$field_id}] = $valueData;
      }
    }
    return $optionSelect;
  }

  /**
   * @return void
   */
  public function saveRequest($id, array $values){
    $transaction = $this->database->startTransaction();
    try {
      $request_data = [
        'no_request' => $values['no_request'],
        'tgl_request' => $values['tgl_request'],
        'nama_pemesan' => $values['nama_pemesan'],
        'keterangan' => $values['keterangan'],
        'uid_request' => $this->currentUser->id(),
      ];
      $file_id = NULL;
      $file_name = NULL;
      if (!empty($values['file_upload'])) {
        $file = $this->entityTypeManager->getStorage('file')->load(reset($values['file_upload']));
        if ($file instanceof FileInterface) {
          // Make the file permanent
          $file->setPermanent();
          $file->save();
          $file_id = $file->id();
          $file_name = $file->getFilename();
          if ($file_id) {
            $request_data['file_id'] = $file_id;
            $request_data['file_attachment'] = $file_name;
          }
        }
      }
      if (empty($id)) {
        //Process Insert Data
        $id = $this->insertTable('request_admin', $request_data);
      }else{
        //Process Update Data
        $fieldsid_data = ['field' => 'id_request_admin', 'value' => $id];
        $this->updateTable('request_admin', $request_data, $fieldsid_data);
        // Delete all existing detail records for this request
        $this->deleteTableById('request_admin_detail', $fieldsid_data);
      }
      if (!empty($id) && !empty($values['detail_data'])){
        foreach ($values['detail_data'] as $product) {
          $detail_insert = [
            'id_request_admin' => $id,
            'id_product' => $product['product_id'],
            'qty_request' => $product['qty'],
          ];
          $this->insertTable('request_admin_detail', $detail_insert);
        }
      }
      return $id;
    }catch (\Exception $e) {
      // Roll back the transaction if something went wrong
      if (isset($transaction)) {
        $transaction->rollBack();
      }
      \Drupal::logger('data_admin_request')->error('Error saving request: @message', ['@message' => $e->getMessage()]);
      return FALSE;
    }
  }

  public function saveRequestProduction(array $values){
    $transaction = $this->database->startTransaction();
    try {
      //Save Production Process Data
      $production_process = [
        'tgl_start' => $values['tgl_request'],
        'uid_created' => $this->currentUser->id(),
      ];
      $id_production_process = $this->insertTable('request_production_process', $production_process);
      if (!empty($id_production_process)) {
        //Save Request Production
        $request_produksi = [
          'tgl_request_produksi' => $values['tgl_request'],
          'keterangan' => $values['keterangan_produksi'],
          'uid_request' => $this->currentUser->id(),
          'id_production_process' => $id_production_process,
        ];
        $id_request_produksi = $this->insertTable('request_produksi', $request_produksi);
        if (!empty($id_request_produksi)) {
          if (!empty($values['detail_produksi'])) {
            foreach ($values['detail_produksi'] as $dataDetailProduksi) {
              $DetailProduksi = [
                'id_request_produksi' => $id_request_produksi,
                'produk_produksi' => 'Kepingan ' . $dataDetailProduksi['gramasi'],
                'gramasi' => $dataDetailProduksi['gramasi'],
                'total_qty' => $dataDetailProduksi['total_qty'],
                'total_qty_actual' => $dataDetailProduksi['requested_qty_keping'],
              ];
              $this->insertTable('request_produksi_detail', $DetailProduksi);
            }
          }
          //Save Request Admin Production
          if (!empty($values['ids_request'])) {
            foreach ($values['ids_request'] as $idRequestAdmin) {
              $RequestAdminProduksi = [
                'id_request_admin' => $idRequestAdmin,
                'id_request_produksi' => $id_request_produksi,
              ];
              $this->insertTable('request_admin_produksi', $RequestAdminProduksi);
            }
          }
        }
        //End Save Request Production

        //Save Request Kemasan
        $request_kemasan = [
          'tgl_request_kemasan' => $values['tgl_request'],
          'keterangan' => $values['keterangan_kemasan'],
          'uid_request' => $this->currentUser->id(),
          'id_production_process' => $id_production_process,
        ];
        $id_request_kemasan = $this->insertTable('request_kemasan', $request_kemasan);
        if (!empty($id_request_kemasan)) {
          if (!empty($values['detail_kemasan'])) {
            foreach ($values['detail_kemasan'] as $dataDetailKemasan) {
              $DetailKemasan = [
                'id_request_kemasan' => $id_request_kemasan,
                'id_product' => $dataDetailKemasan['id_product'],
                'total_qty' => $dataDetailKemasan['total_qty'],
                'total_qty_actual' => $dataDetailKemasan['requested_qty_kemasan'],
              ];
              $this->insertTable('request_kemasan_detail', $DetailKemasan);
            }
          }
          //Save Request Admin Kemasan
          if (!empty($values['ids_request'])) {
            foreach ($values['ids_request'] as $idRequestAdmin) {
              $RequestAdminKemasan = [
                'id_request_admin' => $idRequestAdmin,
                'id_request_kemasan' => $id_request_kemasan,
              ];
              $this->insertTable('request_admin_kemasan', $RequestAdminKemasan);
            }
          }
        }
        //End Save Request Kemasan

        //Update Request Admin
        if (!empty($values['ids_request'])) {
          //Update Request Admin id_production_process and status
          foreach ($values['ids_request'] as $idRequestAdmin) {
            $RequestAdmin = [
              'status_request' => 1,
              'id_production_process' => $id_production_process,
              'changed' => date('Y-m-d H:i:s'),
              'uid_changed' => $this->currentUser->id(),
            ];
            $fieldsid_data = [
              'field' => 'id_request_admin',
              'value' => $idRequestAdmin,
            ];
            $this->updateTable('request_admin', $RequestAdmin, $fieldsid_data);
          }
        }
      }
      return $values['ids_request'];
    }catch (\Exception $e) {
      // Roll back the transaction if something went wrong
      if (isset($transaction)) {
        $transaction->rollBack();
      }
      \Drupal::logger('data_request_admin')->error('Error saving request production: @message', ['@message' => $e->getMessage()]);
      return FALSE;
    }
  }

  /**
   * @param array $values
   * @return void
   */
  public function updateRequestProduction(array $values){
    if (!empty($values['id_request'])) {
      $transaction = $this->database->startTransaction();
      try {
        //Update Request Production
        $request_produksi = [
          'keterangan' => $values['keterangan_produksi'],
          'uid_changed' => $this->currentUser->id(),
          'changed' => date('Y-m-d H:i:s'),
          'status_produksi' => $values['status_produksi']
        ];
        $fieldsid_data = [
          'field' => 'id_request_produksi',
          'value' => $values['id_request'],
        ];
        $this->updateTable('request_produksi', $request_produksi, $fieldsid_data);
        // Check if request_packaging is exists or not
        $CreatePackaging = false;
        $field_data = $this->getTableFields('request_packaging');
        $field_value[] = ['id_production_process' => $values['id_production_process']];
        $query = $this->fetchRecordsByField('request_packaging', $field_data, $field_value,[],[],[]);
        $idRequestPackaging  = 0;
        if ($values['status_produksi'] == 4) {
          if (empty($query)) {
            $CreatePackaging = true;
          } else {
            if (!empty($query[0]->id_request_packaging)) {
              $idRequestPackaging = $query[0]->id_request_packaging;
            } else {
              $CreatePackaging = true;
            }
          }
        }
        if ($CreatePackaging){
          // If status 4 (On Packaging) then auto create request packaging data
          $data_packaging = [
            'id_request_produksi' => $values['id_request'],
            'tgl_request_from_produksi' => date('Y-m-d H:i:s'),
            'uid_created' => $this->currentUser->id(),
            'id_production_process' => $values['id_production_process'],
          ];
          $idPackaging = $this->insertTable('request_packaging', $data_packaging);
        }else{
          //if request packing exists then update
          if (!empty($idRequestPackaging)) {
            $data_packaging = [
              'id_request_produksi' => $values['id_request'],
              'tgl_request_from_produksi' => date('Y-m-d H:i:s'),
              'uid_changed' => $this->currentUser->id(),
              'changed' => date('Y-m-d H:i:s'),
            ];
            $fieldsid_data = [
              'field' => 'id_request_packaging',
              'value' => $idRequestPackaging,
            ];
            $this->updateTable('request_packaging', $data_packaging, $fieldsid_data);
          }
        }
        foreach ($values['detail_produksi'] as $DetailProduksi){
          $fieldsid_data = [
            'field' => 'id_request_produksi_detail',
            'value' => $DetailProduksi['id_request_detail'],
          ];
          $request_detail_produksi = [
            'total_qty' => $DetailProduksi['total_qty'],
            'uid_changed' => $this->currentUser->id(),
            'changed' => date('Y-m-d H:i:s'),
          ];
          $this->updateTable('request_produksi_detail', $request_detail_produksi, $fieldsid_data);
          if ($CreatePackaging && !empty($idPackaging)){
            // If status 4 (On Packaging) then auto create request packaging detail data
            $data_detail_packaging = [
              'id_request_packaging' => $idPackaging,
              'produk_produksi' => 'Kepingan ' .$DetailProduksi['gramasi'],
              'qty_keping' => $DetailProduksi['total_qty'],
              'final_qty_product' => $DetailProduksi['total_qty'],
              'uid_created' => $this->currentUser->id(),
            ];
            $this->insertTable('request_packaging_detail', $data_detail_packaging);
          }else{
            if ($values['status_produksi'] == 4 && !empty($idRequestPackaging)) {
              $data_detail_packaging = [
                'id_request_packaging' => $idRequestPackaging,
                'produk_produksi' => 'Kepingan ' .$DetailProduksi['id_product'],
                'qty_keping' => $DetailProduksi['total_qty'],
                'final_qty_product' => $DetailProduksi['total_qty'],
                'uid_created' => $this->currentUser->id(),
              ];
              $this->insertTable('request_packaging_detail', $data_detail_packaging);
            }
          }
        }
        //End Save Request Production
        return $values['id_request'];
      } catch (\Exception $e) {
        // Roll back the transaction if something went wrong
        if (isset($transaction)) {
          $transaction->rollBack();
        }
        \Drupal::logger('data_request_admin')->error('Error saving request production: @message', ['@message' => $e->getMessage()]);
        return FALSE;
      }
    }else{
      $messages = t('Request Production ID is empty');
      \Drupal::logger('data_request_admin')->error('Error saving request production: @message', ['@message' => $messages]);
      return FALSE;
    }
  }

  /**
   * @param array $values
   * @return void
   */
  public function updateRequestKemasan(array $values){
    if (!empty($values['id_request'])) {
      $transaction = $this->database->startTransaction();
      try {
        //Update Request Production
        $request_kemasan = [
          'keterangan' => $values['keterangan_kemasan'],
          'uid_changed' => $this->currentUser->id(),
          'changed' => date('Y-m-d H:i:s'),
          'status_kemasan' => $values['status_kemasan']
        ];
        $fieldsid_data = [
          'field' => 'id_request_kemasan',
          'value' => $values['id_request'],
        ];
        $this->updateTable('request_kemasan', $request_kemasan, $fieldsid_data);
        $CreatePackaging = false;
        // Check if request_packaging is exists or not
        $field_data = $this->getTableFields('request_packaging');
        $field_value[] = ['id_production_process' => $values['id_production_process']];
        $query = $this->fetchRecordsByField('request_packaging', $field_data, $field_value,[],[],[]);
        $idRequestPackaging  = 0;
        if ($values['status_kemasan'] == 4) {
          if (empty($query)) {
            $CreatePackaging = true;
          } else {
            if (!empty($query[0]->id_request_packaging)) {
              $idRequestPackaging = $query[0]->id_request_packaging;
            } else {
              $CreatePackaging = true;
            }
          }
        }
        if ($CreatePackaging){
          // If status 4 (On Packaging) then auto create request packaging data
          $data_packaging = [
            'id_request_kemasan' => $values['id_request'],
            'tgl_request_from_kemasan' => date('Y-m-d H:i:s'),
            'uid_created' => $this->currentUser->id(),
            'id_production_process' => $values['id_production_process'],
          ];
          $idPackaging = $this->insertTable('request_packaging', $data_packaging);
        }else{
          //if request packing exists then update
          if (!empty($idRequestPackaging)) {
            $data_packaging = [
              'id_request_kemasan' => $values['id_request'],
              'tgl_request_from_kemasan' => date('Y-m-d H:i:s'),
              'uid_changed' => $this->currentUser->id(),
              'changed' => date('Y-m-d H:i:s'),
            ];
            $fieldsid_data = [
              'field' => 'id_request_packaging',
              'value' => $idRequestPackaging,
            ];
            $this->updateTable('request_packaging', $data_packaging, $fieldsid_data);
          }
        }
        foreach ($values['detail_kemasan'] as $DetailKemasan){
          $fieldsid_data = [
            'field' => 'id_request_kemasan_detail',
            'value' => $DetailKemasan['id_request_detail'],
          ];
          $request_detail_kemasan = [
            'total_qty' => $DetailKemasan['total_qty'],
            'uid_changed' => $this->currentUser->id(),
            'changed' => date('Y-m-d H:i:s'),
          ];
          $this->updateTable('request_kemasan_detail', $request_detail_kemasan, $fieldsid_data);
          if ($CreatePackaging && !empty($idPackaging)){
            // If status 4 (On Packaging) then auto create request packaging detail data
            $data_detail_packaging = [
              'id_request_packaging' => $idPackaging,
              'id_product' => $DetailKemasan['id_product'],
              'qty_product' => $DetailKemasan['total_qty'],
              'final_qty_product' => $DetailKemasan['total_qty'],
              'uid_created' => $this->currentUser->id(),
            ];
            $this->insertTable('request_packaging_detail', $data_detail_packaging);
          }else{
            if ($values['status_kemasan'] == 4 && !empty($idRequestPackaging)) {
              $data_detail_packaging = [
                'id_request_packaging' => $idRequestPackaging,
                'id_product' => $DetailKemasan['id_product'],
                'qty_product' => $DetailKemasan['total_qty'],
                'final_qty_product' => $DetailKemasan['total_qty'],
                'uid_created' => $this->currentUser->id(),
              ];
              $this->insertTable('request_packaging_detail', $data_detail_packaging);
            }
          }
        }
        //End Save Request Production
        return $values['id_request'];
      } catch (\Exception $e) {
        // Roll back the transaction if something went wrong
        if (isset($transaction)) {
          $transaction->rollBack();
        }
        \Drupal::logger('data_request_kemasan')->error('Error saving request kemasan: @message', ['@message' => $e->getMessage()]);
        return FALSE;
      }
    }else{
      $messages = t('Request Kemasan ID is empty');
      \Drupal::logger('data_request_kemasan')->error('Error saving request kemasan: @message', ['@message' => $messages]);
      return FALSE;
    }
  }

  public function updateRequestPackaging(array $values){
    if (!empty($values['id_request'])) {
      $transaction = $this->database->startTransaction();
      try {
        //Update Request Production
        $request_packaging = [
          'keterangan' => $values['keterangan_packaging'],
          'uid_changed' => $this->currentUser->id(),
          'changed' => date('Y-m-d H:i:s'),
          'status_packaging' => $values['status_packaging']
        ];
        $fieldsid_data = [
          'field' => 'id_request_packaging',
          'value' => $values['id_request'],
        ];
        $this->updateTable('request_packaging', $request_packaging, $fieldsid_data);
        $UpdateStock = false;
        // Check if status update is delivered to stock (5)
        if ($values['status_packaging'] == 5) {
          $UpdateStock = true;
        }
        foreach ($values['detail_packaging'] as $DetailPackaging){
          $fieldsid_data = [
            'field' => 'id_request_packaging_detail',
            'value' => $DetailPackaging['id_request_detail'],
          ];
          $request_detail_packaging = [
            'final_qty_product' => $DetailPackaging['qty_packaging'],
            'uid_changed' => $this->currentUser->id(),
            'changed' => date('Y-m-d H:i:s'),
          ];
          $this->updateTable('request_packaging_detail', $request_detail_packaging, $fieldsid_data);
          if ($UpdateStock && !empty($DetailPackaging['id_product'])){
            // Check if id_product exists in product_stock, if not create new record, if exists just update stock
            $field_data = $this->getTableFields('product_stock');
            $field_value = [];
            $field_value[] = ['id_product' => $DetailPackaging['id_product']];
            $query = $this->fetchRecordsByField('product_stock', $field_data, $field_value,[],[],[]);
            // If status 5 (Delivered to stock) then update or insert stock product
            if (!empty($query) && !empty($query[0]->id_product)){
              $fieldsid_stock = [
                'field' => 'id_product',
                'value' => $DetailPackaging['id_product'],
              ];
              $data_stock = [
                'stock' => (int)$query[0]->stock + (int)$DetailPackaging['qty_packaging'],
                'uid_changed' => $this->currentUser->id(),
                'changed' => date('Y-m-d H:i:s'),
              ];
              $this->updateTable('product_stock', $data_stock, $fieldsid_stock);
            }else {
              $data_stock = [
                'id_product' => $DetailPackaging['id_product'],
                'stock' => $DetailPackaging['qty_packaging'],
                'uid_created' => $this->currentUser->id(),
              ];
              $this->insertTable('product_stock', $data_stock);
            }
            // Update Request Admin, Request Production, Request Kemasan status
            $fieldsid_stock = [
              'field' => 'id_production_process',
              'value' => $values['id_production_process'],
            ];
            $data_update = [
              'status_request' => $values['status_packaging'],
              'changed' => date('Y-m-d H:i:s'),
              'uid_changed' => $this->currentUser->id(),
            ];
            $this->updateTable('request_admin', $data_update, $fieldsid_stock);
            $data_update = [
              'status_kemasan' => $values['status_packaging'],
              'changed' => date('Y-m-d H:i:s'),
              'uid_changed' => $this->currentUser->id(),
            ];
            $this->updateTable('request_kemasan', $data_update, $fieldsid_stock);
            $data_update = [
              'status_produksi' => $values['status_packaging'],
              'changed' => date('Y-m-d H:i:s'),
              'uid_changed' => $this->currentUser->id(),
            ];
            $this->updateTable('request_produksi', $data_update, $fieldsid_stock);
            // Update production process to complete
            $data_update = [
              'tgl_end' => date('Y-m-d H:i:s'),
              'changed' => date('Y-m-d H:i:s'),
              'uid_changed' => $this->currentUser->id(),
            ];
            $this->updateTable('request_production_process', $data_update, $fieldsid_stock);
          }
        }
        //End Save Request Production
        return $values['id_request'];
      } catch (\Exception $e) {
        // Roll back the transaction if something went wrong
        if (isset($transaction)) {
          $transaction->rollBack();
        }
        \Drupal::logger('data_request_kemasan')->error('Error saving request kemasan: @message', ['@message' => $e->getMessage()]);
        return FALSE;
      }
    }else{
      $messages = t('Request Kemasan ID is empty');
      \Drupal::logger('data_request_kemasan')->error('Error saving request kemasan: @message', ['@message' => $messages]);
      return FALSE;
    }
  }

  public function insertTable($table_name, array $fields_data)
  {
    $query = null;
    if (!empty($table_name)) {
      $query = $this->database->insert($table_name)
        ->fields($fields_data)
        ->execute();
    }
    return $query;
  }
  public function updateTable($table_name, array $fields_data, array $fieldsid_data){
    $query = null;
    if (!empty($table_name)) {
      $query = $this->database->update($table_name)
        ->fields($fields_data)
        ->condition($fieldsid_data['field'], $fieldsid_data['value'])
        ->execute();
    }
    return $query;
  }

  public function deleteTableById($table_name, array $fieldsid_data){
    $query = null;
    if (!empty($table_name) && !empty($fieldsid_data) && is_array($fieldsid_data)) {
      $query = $this->database->delete($table_name)
        ->condition($fieldsid_data['field'], $fieldsid_data['value'])
        ->execute();
    }
    return $query;
  }

  /**
   * Checks if a record exists in a table.
   *
   * @param string $table_name
   *   The name of the table.
   * @param string $field
   *   The field name to check.
   * @param mixed $value
   *   The value to check for.
   *
   * @return bool
   *   TRUE if the record exists, FALSE otherwise.
   */
  public function recordExists($table_name, $field, $value) {
    try {
      $query = $this->database->select($table_name, 't');
      $query->fields('t', [$field]);
      $query->condition('t.' . $field, $value);
      $query->range(0, 1);

      $result = $query->execute()->fetchField();
      return !empty($result);
    }
    catch (\Exception $e) {
      return FALSE;
    }
  }

  /**
   * Updates a record in a table.
   *
   * @param string $table_name
   *   The name of the table.
   * @param array $fields
   *   An array of field values to update, keyed by field name.
   * @param array $conditions
   *   An array of field conditions, keyed by field name.
   *
   * @return bool
   *   TRUE if the record was updated, FALSE otherwise.
   */
  public function updateRecord($table_name, array $fields, array $conditions) {
    try {
      $query = $this->database->update($table_name);
      $query->fields($fields);

      foreach ($conditions as $field => $value) {
        $query->condition($field, $value);
      }

      // Add updated timestamp if field exists
      if (in_array('changed', $this->getTableFields($table_name))) {
        $query->fields(['changed' => date('Y-m-d H:i:s')]);
      }

      return $query->execute() ? TRUE : FALSE;
    }
    catch (\Exception $e) {
      return FALSE;
    }
  }

  /**
   * Inserts a record into a table.
   *
   * @param string $table_name
   *   The name of the table.
   * @param array $fields
   *   An array of field values to insert, keyed by field name.
   *
   * @return bool
   *   TRUE if the record was inserted, FALSE otherwise.
   */
  public function insertRecord($table_name, array $fields) {
    try {
      $table_fields = $this->getTableFields($table_name);

      // Add created timestamp if field exists
      if (in_array('created', $table_fields)) {
        $fields['created'] = date('Y-m-d H:i:s');
      }

      // Add changed timestamp if field exists
      if (in_array('changed', $table_fields)) {
        $fields['changed'] = date('Y-m-d H:i:s');
      }

      // Add user ID if field exists
      if (in_array('uid', $table_fields) && !isset($fields['uid'])) {
        $fields['uid'] = $this->currentUser->id();
      }

      $query = $this->database->insert($table_name);
      $query->fields($fields);

      return $query->execute() ? TRUE : FALSE;
    }
    catch (\Exception $e) {
      return FALSE;
    }
  }

  /**
   * Returns the fields for a given table.
   *
   * @param string $table_name
   *   The name of the table.
   *
   * @return array
   *   Array of field names.
   */
  public function getTableFields($table_name) {
    $field_data = [];
    switch ($table_name) {
      case 'product':
        $field_data = [
          'product_id', 'brand', 'finest', 'series', 'tahun_release',
          'product_name', 'gramasi',
          'ukuran', 'finishing'
        ];
        break;
      case 'request_admin':
        $field_data = [
          'id_request_admin', 'no_request', 'tgl_request', 'uid_request',
          'nama_pemesan','keterangan', 'status_request', 'file_id',
          'uid_changed', 'created', 'changed',
        ];
        break;
      case 'request_admin_detail':
        $field_data = [
          'id_request_admin_detail', 'id_request_admin', 'id_product', 'qty_request', 'status_detail',
          'uid_created', 'uid_changed', 'created', 'changed',
        ];
        break;
      case 'request_produksi':
        $field_data = [
          'id_request_produksi', 'tgl_request_produksi', 'uid_request', 'uid_changed',
          'keterangan', 'status_produksi', 'created', 'changed', 'id_production_process',
        ];
        break;
      case 'request_produksi_detail':
        $field_data = [
          'id_request_produksi_detail', 'id_request_produksi', 'produk_produksi', 'gramasi',
          'total_qty', 'total_qty_actual', 'status_produksi_produk', 'uid_created', 'uid_changed'
          , 'created', 'changed'
        ];
        break;
      case 'request_kemasan':
        $field_data = [
          'id_request_kemasan', 'tgl_request_kemasan', 'uid_request', 'uid_changed',
          'keterangan', 'status_kemasan', 'created', 'changed', 'id_production_process',
        ];
        break;
      case 'request_kemasan_detail':
        $field_data = [
          'id_request_kemasan_detail', 'id_request_kemasan', 'id_product',
          'total_qty', 'total_qty_actual', 'status_kemasan_produk', 'uid_created', 'uid_changed'
          , 'created', 'changed'
        ];
        break;
      case 'request_admin_produksi':
        $field_data = [
          'id_request_admin', 'id_request_produksi'
        ];
        break;
      case 'request_admin_kemasan':
        $field_data = [
          'id_request_admin', 'id_request_kemasan'
        ];
        break;
      case 'request_production_process':
        $field_data = [
          'id_production_process', 'tgl_start', 'tgl_end', 'uid_created',
          'uid_changed', 'created', 'changed',
        ];
        break;
      case 'request_packaging':
        $field_data = [
          'id_request_packaging', 'tgl_request_from_kemasan', 'tgl_request_from_produksi',
          'uid_created', 'uid_changed', 'status_packaging', 'created', 'changed', 'id_production_process',
          'id_request_produksi', 'id_request_kemasan'
        ];
        break;
      case 'request_packaging_detail':
        $field_data = [
          'id_request_packaging_detail', 'id_request_packaging', 'id_product', 'produk_produksi',
          'qty_product', 'qty_keping', 'final_qty_product', 'uid_created', 'uid_changed'
          , 'created', 'changed'
        ];
        break;
      case 'product_stock':
        $field_data = [
          'id_product', 'stock', 'uid_created', 'uid_changed', 'created', 'changed'
        ];
        break;
      // Add cases for other tables here

    }
    return $field_data;
  }
  public function getTableFieldsId($table_name) {
    $field_id = '';

    switch ($table_name) {
      case 'product':
        $field_id = 'product_id';
        break;
      case 'request_admin':
        $field_id = 'id_request_admin';
        break;
      case 'request_admin_detail':
        $field_id = 'id_request_admin_detail';
        break;
      case 'request_produksi':
        $field_id = 'id_request_produksi';
        break;
      case 'request_kemasan':
        $field_id = 'id_request_kemasan';
        break;
      case 'request_production_process':
        $field_id = 'id_production_process';
        break;
      case 'request_packaging':
        $field_id = 'id_request_packaging';
        break;
      case 'request_packaging_detail':
        $field_id = 'id_request_packaging_detail';
        break;
      case 'product_stock':
        $field_id = 'id_product';
        break;
      // Add cases for other tables here

    }

    return $field_id;
  }
  /**
   * Returns the searchable fields for a given table.
   *
   * @param string $table_name
   *   The name of the table.
   *
   * @return array
   *   Array of searchable field names.
   */
  public function getSearchFields($table_name) {
    $field_data = [];

    switch ($table_name) {
      case 'product':
        $field_data = [
          'product_id', 'gramasi', 'brand', 'series', 'product_name',
          'finishing', 'ukuran', 'tahun_release'
        ];
        break;
      case 'request_admin':
        $field_data = [
          'no_request', 'tgl_request', 'keterangan', 'nama_pemesan',
        ];
        break;
      case 'request_admin_detail':
        $field_data = [
          'id_product', 'qty_request', 'status_detail',
        ];
        break;
      case 'request_produksi':
        $field_data = [
          'tgl_request_produksi', 'keterangan'
        ];
        break;
      case 'request_kemasan':
        $field_data = [
          'tgl_request_kemasan', 'keterangan'
        ];
        break;
      case 'request_production_process':
        $field_data = [
          'tgl_start', 'tgl_end'
        ];
        break;
      // Add cases for other tables here
    }

    return $field_data;
  }

  public function allowEditOnprocess($table_name) {
    $allow_edit = 0;

    switch ($table_name) {
      case 'request_admin':
      case 'request_admin_detail':
      case 'product':
        break;
      case 'request_produksi':
      case 'request_kemasan':
        $allow_edit = 3;
        break;
      case 'request_packaging':
        $allow_edit = 4;
        break;
      // Add cases for other tables here
    }
    return $allow_edit;
  }
  public function getTableExpression($table_name) : array {
    $expressions = [];
    switch ($table_name) {
      case 'request_admin':
        $expressions[] = [
          'expression' => '(SELECT id_request_produksi FROM request_produksi WHERE id_production_process = ta.id_production_process)',
          'alias' => 'related_production'
        ];
        $expressions[] = [
          'expression' => '(SELECT status_produksi FROM request_produksi WHERE id_production_process = ta.id_production_process)',
          'alias' => 'production_status'
        ];
        $expressions[] = [
          'expression' => '(SELECT id_request_kemasan FROM request_kemasan WHERE id_production_process = ta.id_production_process)',
          'alias' => 'related_kemasan'
        ];
        $expressions[] = [
          'expression' => '(SELECT status_kemasan FROM request_kemasan WHERE id_production_process = ta.id_production_process)',
          'alias' => 'kemasan_status'
        ];
        break;
      case 'product':
        $expressions[] = [
          'expression' => '(SELECT stock FROM product_stock WHERE id_product = ta.product_id)',
          'alias' => 'stock'
        ];
        break;
      // Add cases for other tables here
    }
    return $expressions;
  }
}

<?php

namespace Drupal\data_source\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\file\FileInterface;
use Drupal\Core\Url;
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
      //Save Request Production
      $request_produksi = [
        'tgl_request_produksi' => $values['tgl_request'],
        'keterangan' => $values['keterangan_produksi'],
        'uid_request' => $this->currentUser->id(),
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
      ];
      $id_request_kemasan = $this->insertTable('request_kemasan', $request_kemasan);
      if (!empty($id_request_kemasan)) {
        if (!empty($values['detail_kemasan'])) {
          foreach ($values['detail_kemasan'] as $dataDetailKemasan) {
            $DetailKemasan = [
              'id_request_kemasan' => $id_request_kemasan,
              'id_product' => $dataDetailKemasan['id_product'],
              'total_qty' => $dataDetailKemasan['total_qty'],
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
        foreach ($values['ids_request'] as $idRequestAdmin) {
          $RequestAdmin = [
            'status_request' => 1,
          ];
          $fieldsid_data = [
            'field' => 'id_request_admin',
            'value' => $idRequestAdmin,
          ];
          $this->updateTable('request_admin', $RequestAdmin,$fieldsid_data);
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
          'ukuran', 'finishing', 'kategori_produk'
        ];
        break;
      case 'request_admin':
        $field_data = [
          'id_request_admin', 'no_request', 'tgl_request', 'uid_request',
          'nama_pemesan','keterangan', 'status_request', 'file_id', 'file_attachment',
          'uid_changed', 'created', 'changed',
        ];
        break;
      case 'request_admin_detail':
        $field_data = [
          'id_request_admin_detail', 'id_request_admin', 'id_product', 'qty_request', 'status_detail',
          'uid_created', 'uid_changed', 'created', 'changed'
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
          'brand', 'series', 'product_name', 'finishing',
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
      // Add cases for other tables here
    }

    return $field_data;
  }

}

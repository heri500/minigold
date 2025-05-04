<?php

namespace Drupal\data_source\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;

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
   * Constructs a DataSourceService object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection (not used directly, just for service instantiation).
   */
  public function __construct(Connection $database) {
    // Use the minigold_master database connection instead of the default one
    $this->database = Database::getConnection('default', $this->targetDatabase);
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
          'keterangan', 'status_request', 'uid_changed', 'created', 'changed'
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
          'no_request', 'tgl_request', 'keterangan'
        ];
        break;
      // Add cases for other tables here
    }

    return $field_data;
  }

}

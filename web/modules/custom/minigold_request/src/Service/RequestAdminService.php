<?php

namespace Drupal\minigold_request\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\file\FileInterface;
use Drupal\Core\Url;
use Drupal\file\Entity\File;

/**
 * Service class for Request Admin functionality.
 */
class RequestAdminService {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The minigold_master database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $minigoldMasterDb;

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
    $this->database = $database;
    $this->currentUser = $current_user;
    $this->fileSystem = $file_system;
    $this->entityTypeManager = $entity_type_manager;
    // Get the minigold_master database connection
    //$this->minigoldMasterDb = \Drupal::database('minigold_master');
    $this->minigoldMasterDb = \Drupal\Core\Database\Database::getConnection('default', 'minigold_master');
  }

  /**
   * Get all request admin records.
   *
   * @return array
   *   Array of request admin records.
   */
  public function getAllRequests() {
    $query = $this->minigoldMasterDb
      ->select('request_admin', 'ra')
      ->fields('ra', [
        'id_request_admin',
        'no_request',
        'tgl_request',
        'uid_request',
        'keterangan',
        'nama_pemesan',
        'file_attachment',
        'file_id',
      ])
      ->orderBy('tgl_request', 'DESC');

    $result = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);

    // Format the results
    foreach ($result as &$record) {
      // Format the date
      if (!empty($record['tgl_request'])) {
        $record['tgl_request_formatted'] = date('d/m/Y', strtotime($record['tgl_request']));
      }

      // Create edit link with FontAwesome icon
      $record['edit_link'] = [
        '#type' => 'link',
        '#title' => [
          '#markup' => '<i class="fas fa-edit"></i>',
        ],
        '#url' => \Drupal\Core\Url::fromRoute('minigold_request.admin_modal', ['id' => $record['id_request_admin']]),
        '#attributes' => [
          'class' => ['use-ajax', 'btn', 'btn-sm', 'btn-info'],
          'data-dialog-type' => 'modal',
          'data-dialog-options' => json_encode(['width' => '800']),
          'title' => t('Edit'),
        ],
      ];

      // Create file link if attachment exists
      if (!empty($record['file_id'])) {
        try {
          $file = $this->entityTypeManager->getStorage('file')->load($record['file_id']);
          if ($file instanceof FileInterface) {
            // Use proper URL generation for file in Drupal 9
            $file_url = $file->createFileUrl();

            $record['file_link'] = [
              '#type' => 'link',
              '#title' => $record['file_attachment'] ?? t('Download'),
              '#url' => Url::fromUri($file_url),
              '#attributes' => [
                'target' => '_blank',
                'class' => ['btn', 'btn-sm', 'btn-outline-primary'],
              ],
            ];
          }
        }
        catch (\Exception $e) {
          \Drupal::logger('minigold_request')->error('Error loading file: @message', ['@message' => $e->getMessage()]);
        }
      }
    }

    return $result;
  }

  /**
   * Get a single request admin record by ID.
   *
   * @param int $id
   *   The request admin ID.
   *
   * @return array
   *   The request admin record.
   */
  public function getRequestById($id) {
    $result = $this->minigoldMasterDb
      ->select('request_admin', 'ra')
      ->fields('ra')
      ->condition('id_request_admin', $id)
      ->execute()
      ->fetchAssoc();

    // Also fetch the product information from request_admin_detail
    if ($result) {
      $details = $this->minigoldMasterDb
        ->select('request_admin_detail', 'rad')
        ->fields('rad', ['id_product'])
        ->condition('id_request_admin', $id)
        ->execute()
        ->fetchCol();

      if (!empty($details)) {
        $result['product_ids'] = $details;

        // Get product names
        $products = $this->minigoldMasterDb
          ->select('product', 'p')
          ->fields('p', ['product_id', 'product_name'])
          ->condition('product_id', $details, 'IN')
          ->execute()
          ->fetchAllKeyed();

        $result['products'] = $products;
      }
    }

    return $result ?? [];
  }

  /**
   * Save a request admin record.
   *
   * @param int $id
   *   The request admin ID (0 for new record).
   * @param array $values
   *   The form values.
   *
   * @return int|bool
   *   The ID of the saved record, or FALSE on failure.
   */
  public function saveRequest($id, array $values) {
    // Begin transaction
    $transaction = $this->minigoldMasterDb->startTransaction();

    try {
      $file_id = NULL;
      $file_name = NULL;

      // Handle file upload if provided
      if (!empty($values['file_upload'])) {
        $file = $this->entityTypeManager->getStorage('file')->load(reset($values['file_upload']));

        if ($file instanceof FileInterface) {
          // Make the file permanent
          $file->setPermanent();
          $file->save();

          $file_id = $file->id();
          $file_name = $file->getFilename();
        }
      }

      // Prepare request data
      $request_data = [
        'no_request' => $values['no_request'],
        'tgl_request' => $values['tgl_request'],
        'nama_pemesan' => $values['nama_pemesan'],
        'keterangan' => $values['keterangan'],
        'uid_request' => $this->currentUser->id(),
      ];

      // Add file info if available
      if ($file_id) {
        $request_data['file_attachment'] = $file_name;
        $request_data['file_id'] = $file_id;
      }

      // Insert or update the request_admin record
      if (empty($id)) {
        // Insert new record
        $id = $this->minigoldMasterDb->insert('request_admin')
          ->fields($request_data)
          ->execute();
      }
      else {
        // Update existing record
        $this->minigoldMasterDb->update('request_admin')
          ->fields($request_data)
          ->condition('id_request_admin', $id)
          ->execute();
      }

      // If we have a product ID, save it to the request_admin_detail table
      if (!empty($values['product_id'])) {
        // First delete any existing details
        $this->minigoldMasterDb->delete('request_admin_detail')
          ->condition('id_request_admin', $id)
          ->execute();

        // Insert new product relation
        $this->minigoldMasterDb->insert('request_admin_detail')
          ->fields([
            'id_request_admin' => $id,
            'id_product' => $values['product_id'],
          ])
          ->execute();
      }

      return $id;
    }
    catch (\Exception $e) {
      // Roll back the transaction if something went wrong
      if (isset($transaction)) {
        $transaction->rollBack();
      }
      \Drupal::logger('minigold_request')->error('Error saving request: @message', ['@message' => $e->getMessage()]);
      return FALSE;
    }
  }

  /**
   * Get product matches for autocomplete.
   *
   * @param string $query
   *   The search query.
   *
   * @return array
   *   Array of matching products.
   */
  public function getProductMatches($query) {
    $matches = [];

    if (empty($query)) {
      return $matches;
    }

    // Query the product table
    $result = $this->minigoldMasterDb
      ->select('product', 'p')
      ->fields('p', ['product_id', 'product_name'])
      ->condition('product_name', '%' . $this->minigoldMasterDb->escapeLike($query) . '%', 'LIKE')
      ->range(0, 10)
      ->execute();

    foreach ($result as $row) {
      $matches[] = [
        'value' => $row->product_name . ' (' . $row->product_id . ')',
        'label' => $row->product_name,
        'id' => $row->product_id,
      ];
    }

    return $matches;
  }
}

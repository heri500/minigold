<?php

namespace Drupal\data_request_admin\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Database\Connection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Returns autocomplete responses for products.
 */
class ProductAutocompleteController extends ControllerBase {

  /**
   * The database connection service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Constructs a ProductAutocompleteController object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection service.
   */
  public function __construct(Connection $database) {
    $this->database = $database;
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
   * Handler for autocomplete request.
   */
  public function handleAutocomplete(Request $request) {
    $results = [];
    $input = $request->query->get('q');

    if ($input) {
      // Get the master database connection
      $connection = \Drupal\Core\Database\Database::getConnection('default', 'minigold_master');

      // Search for products in the PostgreSQL database
      $query = $connection->select('product', 'p')
        ->fields('p', ['product_id', 'brand', 'product_name'])
        ->condition('p.product_name', '%' . $connection->escapeLike($input) . '%', 'ILIKE')  // ILIKE for case-insensitive search in PostgreSQL
        ->range(0, 20)
        ->execute();

      // Format results for autocomplete
      foreach ($query as $row) {
        $results[] = [
          'value' => $row->product_name,
          'label' => $row->product_name,
          'id' => $row->product_id,
          'code' => $row->brand,
        ];
      }
    }

    return new JsonResponse($results);
  }
}

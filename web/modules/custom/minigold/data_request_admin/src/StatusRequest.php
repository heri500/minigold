<?php

namespace Drupal\data_request_admin;

/**
 * Defines status constants for requests.
 */
final class StatusRequest {
  public const STATUS = [
    0 => 'New or Pending',
    1 => 'Accepted',
    2 => 'On Process',
    3 => 'On Packaging',
    4 => 'Partially Complete',
    5 => 'Complete',
  ];
}

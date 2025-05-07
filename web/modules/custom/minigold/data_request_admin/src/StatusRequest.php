<?php

namespace Drupal\data_request_admin;

/**
 * Defines status constants for requests.
 */
final class StatusRequest {
  public const STATUS = [
    0 => 'New or Pending',
    1 => 'On Process',
    2 => 'On Packaging',
    3 => 'Partially Complete',
    4 => 'Complete',
    5 => 'Delivered',
  ];
}

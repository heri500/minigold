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
  public const STATUSCOLOR = [
    0 => 'danger',
    1 => 'warning',
    2 => 'success',
    3 => 'info',
    4 => 'secondary',
    5 => 'primary',
  ];
}

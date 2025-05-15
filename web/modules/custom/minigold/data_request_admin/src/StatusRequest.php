<?php

namespace Drupal\data_request_admin;

/**
 * Defines status constants for requests.
 */
final class StatusRequest {
  public const STATUS = [
    0 => 'New or Pending',
    1 => 'On Process',
    2 => 'Partially Complete',
    3 => 'Complete',
    4 => 'On Packaging',
    5 => 'Delivered',
  ];
  public const STATUSCOLOR = [
    0 => 'danger',
    1 => 'warning',
    2 => 'secondary',
    3 => 'success',
    4 => 'info',
    5 => 'primary',
  ];
}

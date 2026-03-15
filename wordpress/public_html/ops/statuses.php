<?php

function ops_status_aliases(): array {
  return [
    'ready_for_pickup' => 'ready_for_customer',
    'complete' => 'delivered',
    'completed' => 'delivered',
  ];
}

function ops_normalize_status(?string $status): string {
  $status = trim((string)$status);
  if ($status === '') {
    return 'received';
  }

  return ops_status_aliases()[$status] ?? $status;
}

function ops_statuses(): array {
  return [
    'received' => 'Received',
    'supplies_ordered' => 'Supplies Ordered',
    'awaiting_supplies' => 'Awaiting Supplies',
    'supplies_received' => 'Supplies Received',
    'in_production' => 'In Production',
    'ready_for_customer' => 'Ready for Customer',
    'shipped' => 'Shipped',
    'delivered' => 'Order Complete',
  ];
}

function ops_status_label(?string $status): string {
  $statuses = ops_statuses();
  $normalized = ops_normalize_status($status);
  return $statuses[$normalized] ?? ($status !== null && $status !== '' ? (string)$status : 'Received');
}

function ops_board_statuses(): array {
  $statuses = ops_statuses();
  unset($statuses['delivered']);
  return $statuses;
}

function ops_final_statuses(): array {
  return ['delivered', 'complete', 'completed'];
}

function ops_is_final_status(?string $status): bool {
  return in_array(ops_normalize_status($status), ['delivered'], true);
}

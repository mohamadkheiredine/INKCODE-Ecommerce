/**
 * Every permission a Role can hold, mirroring `App\Enums\Permission`. The SPA only uses
 * them to hide what the User's Role cannot use; the API checks every request itself.
 */
export const PERMISSIONS = [
  'dashboard.view',
  'catalog.view',
  'catalog.manage',
  'inventory.view',
  'inventory.receive',
  'inventory.adjust',
  'inventory.transfer',
  'warehouses.manage',
  'customers.view',
  'customers.manage',
  'orders.view',
  'orders.create',
  'orders.update',
  'orders.transition',
  'orders.cancel',
  'orders.assign',
  'shipments.manage',
  'shipping.manage',
  'payments.view',
  'payments.record',
  'refunds.issue',
  'coupons.view',
  'coupons.manage',
  'returns.view',
  'returns.request',
  'returns.approve',
  'returns.receive',
  'reports.view',
  'reports.export',
  'users.manage',
  'settings.manage',
  'audit.view',
] as const

export type Permission = (typeof PERMISSIONS)[number]

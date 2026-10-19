<?php

namespace App\Enums;

/**
 * Every permission a Role can hold, named `<area>.<action>`.
 */
enum Permission: string
{
    case DashboardView = 'dashboard.view';

    case CatalogView = 'catalog.view';
    case CatalogManage = 'catalog.manage';

    case InventoryView = 'inventory.view';
    case InventoryReceive = 'inventory.receive';
    case InventoryAdjust = 'inventory.adjust';
    case InventoryTransfer = 'inventory.transfer';
    case WarehousesManage = 'warehouses.manage';

    case CustomersView = 'customers.view';
    case CustomersManage = 'customers.manage';

    case OrdersView = 'orders.view';
    case OrdersCreate = 'orders.create';
    case OrdersUpdate = 'orders.update';
    case OrdersTransition = 'orders.transition';
    case OrdersCancel = 'orders.cancel';
    case OrdersAssign = 'orders.assign';

    case ShipmentsManage = 'shipments.manage';
    case ShippingManage = 'shipping.manage';

    case PaymentsView = 'payments.view';
    case PaymentsRecord = 'payments.record';
    case RefundsIssue = 'refunds.issue';

    case CouponsView = 'coupons.view';
    case CouponsManage = 'coupons.manage';

    case ReturnsView = 'returns.view';
    case ReturnsRequest = 'returns.request';
    case ReturnsApprove = 'returns.approve';
    case ReturnsReceive = 'returns.receive';

    case ReportsView = 'reports.view';
    case ReportsExport = 'reports.export';

    case UsersManage = 'users.manage';
    case SettingsManage = 'settings.manage';
    case AuditView = 'audit.view';

    public function isViewOnly(): bool
    {
        return str_ends_with($this->value, '.view');
    }
}

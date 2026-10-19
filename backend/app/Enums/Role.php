<?php

namespace App\Enums;

/**
 * The seven fixed Roles. Every User has exactly one; this enum is the single source of the permission matrix.
 */
enum Role: string
{
    case Admin = 'admin';
    case Operations = 'operations';
    case Inventory = 'inventory';
    case Sales = 'sales';
    case Support = 'support';
    case Finance = 'finance';
    case Viewer = 'viewer';

    /**
     * The Role's place in the fixed display order (Admin first, Viewer last).
     */
    public function position(): int
    {
        return (int) array_search($this, self::cases(), true);
    }

    /**
     * @return list<Permission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Admin => Permission::cases(),
            self::Operations => [
                Permission::DashboardView,
                Permission::CatalogView, Permission::CatalogManage,
                Permission::InventoryView,
                Permission::CustomersView,
                Permission::OrdersView, Permission::OrdersCreate, Permission::OrdersUpdate,
                Permission::OrdersTransition, Permission::OrdersCancel, Permission::OrdersAssign,
                Permission::ShipmentsManage, Permission::ShippingManage,
                Permission::PaymentsView,
                Permission::CouponsView,
                Permission::ReturnsView,
                Permission::ReportsView,
            ],
            self::Inventory => [
                Permission::DashboardView,
                Permission::CatalogView,
                Permission::InventoryView, Permission::InventoryReceive, Permission::InventoryAdjust,
                Permission::InventoryTransfer, Permission::WarehousesManage,
                Permission::OrdersView,
                Permission::ShipmentsManage,
                Permission::ReturnsView, Permission::ReturnsReceive,
                Permission::ReportsView,
            ],
            self::Sales => [
                Permission::DashboardView,
                Permission::CatalogView,
                Permission::InventoryView,
                Permission::CustomersView, Permission::CustomersManage,
                Permission::OrdersView, Permission::OrdersCreate, Permission::OrdersUpdate,
                Permission::OrdersCancel, Permission::OrdersAssign,
                Permission::PaymentsView,
                Permission::CouponsView, Permission::CouponsManage,
                Permission::ReturnsView,
                Permission::ReportsView,
            ],
            self::Support => [
                Permission::DashboardView,
                Permission::CatalogView,
                Permission::CustomersView, Permission::CustomersManage,
                Permission::OrdersView,
                Permission::PaymentsView,
                Permission::ReturnsView, Permission::ReturnsRequest, Permission::ReturnsApprove,
            ],
            self::Finance => [
                Permission::DashboardView,
                Permission::CatalogView,
                Permission::InventoryView,
                Permission::CustomersView,
                Permission::OrdersView,
                Permission::PaymentsView, Permission::PaymentsRecord, Permission::RefundsIssue,
                Permission::CouponsView,
                Permission::ReturnsView,
                Permission::ReportsView, Permission::ReportsExport,
            ],
            self::Viewer => array_values(array_filter(
                Permission::cases(),
                fn (Permission $permission): bool => $permission->isViewOnly() && $permission !== Permission::AuditView,
            )),
        };
    }
}

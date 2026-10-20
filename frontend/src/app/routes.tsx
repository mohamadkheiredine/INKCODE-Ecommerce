import type { RouteObject } from 'react-router'
import { PageSkeleton } from '@/components/feedback/page-skeleton'
import { RequireAuth } from '@/features/auth/components/require-auth'
import { SIGN_IN_PATH } from '@/features/auth/redirect'
import type { RouteHandle } from '@/lib/navigation'
import { withPermission } from './with-permission'

const crumb = (page: NonNullable<RouteHandle['crumb']>): RouteHandle => ({ crumb: page })

/**
 * Route modules load lazily, so each page only ships when it is first visited. Each
 * page names itself in `handle.crumb`; the shell builds breadcrumbs and titles from it.
 * A page that needs a permission is wrapped in `withPermission` (the API still checks).
 */
export const routes: RouteObject[] = [
  {
    path: SIGN_IN_PATH,
    HydrateFallback: () => <div className="min-h-svh bg-background" />,
    lazy: async () => ({
      Component: (await import('@/features/auth/routes/sign-in-page')).SignInPage,
    }),
  },
  {
    Component: RequireAuth,
    HydrateFallback: PageSkeleton,
    children: [
      {
        handle: crumb('home'),
        lazy: async () => ({ Component: (await import('./shell/app-shell')).AppShell }),
        children: [
          {
            index: true,
            lazy: async () => ({
              Component: (await import('@/features/home/routes/home-page')).HomePage,
            }),
          },
          {
            path: 'profile',
            handle: crumb('profile'),
            lazy: async () => ({
              Component: (await import('@/features/profile/routes/profile-page')).ProfilePage,
            }),
          },
          {
            path: 'catalog/products',
            handle: crumb('products'),
            children: [
              {
                index: true,
                lazy: async () => ({
                  Component: withPermission(
                    'catalog.view',
                    (await import('@/features/catalog/routes/products-page')).ProductsPage,
                  ),
                }),
              },
              {
                path: 'new',
                handle: crumb('newProduct'),
                lazy: async () => ({
                  Component: withPermission(
                    'catalog.manage',
                    (await import('@/features/catalog/routes/product-editor-page')).NewProductPage,
                  ),
                }),
              },
              {
                path: ':productId',
                handle: crumb('editProduct'),
                lazy: async () => ({
                  Component: withPermission(
                    'catalog.view',
                    (await import('@/features/catalog/routes/product-editor-page')).EditProductPage,
                  ),
                }),
              },
            ],
          },
          {
            path: 'catalog/attributes',
            handle: crumb('attributes'),
            lazy: async () => ({
              Component: withPermission(
                'catalog.view',
                (await import('@/features/catalog/routes/attributes-page')).AttributesPage,
              ),
            }),
          },
          {
            path: 'catalog/categories',
            handle: crumb('categories'),
            lazy: async () => ({
              Component: withPermission(
                'catalog.view',
                (await import('@/features/catalog/routes/categories-page')).CategoriesPage,
              ),
            }),
          },
          {
            path: 'catalog/brands',
            handle: crumb('brands'),
            lazy: async () => ({
              Component: withPermission(
                'catalog.view',
                (await import('@/features/catalog/routes/brands-page')).BrandsPage,
              ),
            }),
          },
          {
            path: 'customers',
            handle: crumb('customers'),
            children: [
              {
                index: true,
                lazy: async () => ({
                  Component: withPermission(
                    'customers.view',
                    (await import('@/features/customers/routes/customers-page')).CustomersPage,
                  ),
                }),
              },
              {
                path: ':customerId',
                handle: crumb('customer'),
                lazy: async () => ({
                  Component: withPermission(
                    'customers.view',
                    (await import('@/features/customers/routes/customer-page')).CustomerPage,
                  ),
                }),
              },
            ],
          },
          {
            path: 'inventory/stock',
            handle: crumb('stockLevels'),
            lazy: async () => ({
              Component: withPermission(
                'inventory.view',
                (await import('@/features/inventory/routes/stock-levels-page')).StockLevelsPage,
              ),
            }),
          },
          {
            path: 'inventory/movements',
            handle: crumb('stockMovements'),
            lazy: async () => ({
              Component: withPermission(
                'inventory.view',
                (await import('@/features/inventory/routes/stock-movements-page'))
                  .StockMovementsPage,
              ),
            }),
          },
          {
            path: 'inventory/warehouses',
            handle: crumb('warehouses'),
            lazy: async () => ({
              Component: withPermission(
                'inventory.view',
                (await import('@/features/inventory/routes/warehouses-page')).WarehousesPage,
              ),
            }),
          },
          {
            path: 'inventory/transfers',
            handle: crumb('transfers'),
            lazy: async () => ({
              Component: withPermission(
                'inventory.view',
                (await import('@/features/inventory/routes/transfers-page')).TransfersPage,
              ),
            }),
          },
          {
            path: 'inventory/receipts',
            handle: crumb('receipts'),
            children: [
              {
                index: true,
                lazy: async () => ({
                  Component: withPermission(
                    'inventory.view',
                    (await import('@/features/inventory/routes/receipts-page')).ReceiptsPage,
                  ),
                }),
              },
              {
                path: 'new',
                handle: crumb('newReceipt'),
                lazy: async () => ({
                  Component: withPermission(
                    'inventory.receive',
                    (await import('@/features/inventory/routes/new-receipt-page')).NewReceiptPage,
                  ),
                }),
              },
              {
                path: ':receiptId',
                handle: crumb('receipt'),
                lazy: async () => ({
                  Component: withPermission(
                    'inventory.view',
                    (await import('@/features/inventory/routes/receipt-page')).ReceiptPage,
                  ),
                }),
              },
            ],
          },
          {
            path: 'shipping/zones',
            handle: crumb('shippingZones'),
            children: [
              {
                index: true,
                lazy: async () => ({
                  Component: withPermission(
                    'orders.view',
                    (await import('@/features/shipping/routes/shipping-zones-page'))
                      .ShippingZonesPage,
                  ),
                }),
              },
              {
                path: ':zoneId',
                handle: crumb('shippingZone'),
                lazy: async () => ({
                  Component: withPermission(
                    'orders.view',
                    (await import('@/features/shipping/routes/shipping-zone-page'))
                      .ShippingZonePage,
                  ),
                }),
              },
            ],
          },
          {
            path: 'shipping/carriers',
            handle: crumb('carriers'),
            lazy: async () => ({
              Component: withPermission(
                'orders.view',
                (await import('@/features/shipping/routes/carriers-page')).CarriersPage,
              ),
            }),
          },
          {
            path: 'settings',
            handle: crumb('settings'),
            lazy: async () => ({
              Component: withPermission(
                'settings.manage',
                (await import('@/features/settings/routes/settings-page')).SettingsPage,
              ),
            }),
          },
          {
            path: 'users',
            handle: crumb('users'),
            lazy: async () => ({
              Component: withPermission(
                'users.manage',
                (await import('@/features/users/routes/users-page')).UsersPage,
              ),
            }),
          },
          {
            path: 'roles',
            handle: crumb('roles'),
            lazy: async () => ({
              Component: withPermission(
                'users.manage',
                (await import('@/features/users/routes/roles-page')).RolesPage,
              ),
            }),
          },
          {
            path: 'audit-log',
            handle: crumb('auditLog'),
            lazy: async () => ({
              Component: withPermission(
                'audit.view',
                (await import('@/features/audit/routes/audit-log-page')).AuditLogPage,
              ),
            }),
          },
          {
            path: '*',
            handle: crumb('notFound'),
            lazy: async () => ({ Component: (await import('./not-found-page')).NotFoundPage }),
          },
        ],
      },
    ],
  },
]

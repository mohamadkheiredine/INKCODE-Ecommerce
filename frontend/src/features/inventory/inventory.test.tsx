import { screen, waitFor, within } from '@testing-library/react'
import { HttpResponse, http } from 'msw/http'
import { describe, expect, it } from 'vitest'
import type { CurrentUser } from '@/features/auth/types'
import { apiError } from '@/test/msw/handlers'
import {
  aStockLevel,
  aWarehouse,
  receiptStore,
  stockLevelStore,
  warehouseStore,
} from '@/test/msw/inventory'
import { server } from '@/test/msw/server'
import { signedInAs } from '@/test/msw/session'
import { renderApp } from '@/test/render-app'

const inventoryUser: CurrentUser = {
  id: 3,
  name: 'Maya Haddad',
  email: 'inventory@inkcode.test',
  role: 'inventory',
  permissions: [
    'dashboard.view',
    'catalog.view',
    'inventory.view',
    'inventory.receive',
    'warehouses.manage',
  ],
  preferences: { locale: 'en', theme: 'light' },
}

async function open(path: string, heading: string, user: CurrentUser = inventoryUser) {
  signedInAs(user)
  const view = renderApp(path)
  await screen.findByRole('heading', { name: heading, level: 1 }, { timeout: 10_000 })
  return view
}

describe('Warehouses', () => {
  it('starts empty with a way to create the first one, and refuses a malformed or taken code', async () => {
    const warehouses = warehouseStore([])
    const { user } = await open('/inventory/warehouses', 'Warehouses')

    expect(await screen.findByText('No Warehouses yet')).toBeInTheDocument()
    await user.click(screen.getAllByRole('button', { name: 'New Warehouse' }).at(-1)!)
    const dialog = await screen.findByRole('dialog', { name: 'New Warehouse' })
    await user.type(within(dialog).getByRole('textbox', { name: 'Name' }), 'Beirut Central')
    await user.type(within(dialog).getByRole('textbox', { name: 'Code' }), 'B')
    await user.click(within(dialog).getByRole('button', { name: 'Create Warehouse' }))
    expect(
      await within(dialog).findByText('Use 2 to 16 capital letters, digits or dashes.'),
    ).toBeInTheDocument()
    expect(within(dialog).getByText('Enter the address.')).toBeInTheDocument()

    warehouses.push(aWarehouse({ code: 'BEY-01' }))
    await user.type(within(dialog).getByRole('textbox', { name: 'Code' }), 'ey-01')
    await user.type(within(dialog).getByRole('textbox', { name: 'Address' }), 'Port Road')
    await user.click(within(dialog).getByRole('button', { name: 'Create Warehouse' }))
    expect(await within(dialog).findByText('The code has already been taken.')).toBeInTheDocument()

    await user.clear(within(dialog).getByRole('textbox', { name: 'Code' }))
    await user.type(within(dialog).getByRole('textbox', { name: 'Code' }), 'bey-02')
    await user.click(within(dialog).getByRole('button', { name: 'Create Warehouse' }))
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument())
    expect(warehouses.at(-1)).toMatchObject({ name: 'Beirut Central', code: 'BEY-02' })
  })

  it('lists Warehouses read-only for a Role that may not manage them', async () => {
    warehouseStore([aWarehouse({ name: 'Dubai Hub', code: 'DXB-01', on_hand: 1200 })])
    await open('/inventory/warehouses', 'Warehouses', {
      ...inventoryUser,
      permissions: ['inventory.view'],
    })

    const row = (await screen.findByText('Dubai Hub')).closest('tr')!
    expect(within(row).getByText('1,200')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'New Warehouse' })).not.toBeInTheDocument()
  })
})

describe('Stock levels', () => {
  it('shows a skeleton, then an error with retry, then rows with totals and low-stock badges', async () => {
    warehouseStore([])
    let failures = 1
    server.use(
      http.get('*/api/v1/stock-levels', () =>
        failures-- > 0 ? apiError(500, 'server_error', 'Boom') : HttpResponse.error(),
      ),
    )
    const { user } = await open('/inventory/stock', 'Stock levels')
    expect(screen.getByLabelText('Loading stock levels…')).toBeInTheDocument()
    expect(await screen.findByRole('button', { name: 'Try again' })).toBeInTheDocument()

    stockLevelStore([
      aStockLevel({ variant: { id: 1, sku: 'TEE-BLK-S', options: [] }, stock_value: '50.00' }),
      aStockLevel({
        variant: { id: 2, sku: 'TEE-NAVY-L', options: [] },
        on_hand: 3,
        available: 3,
        stock_value: '15.00',
        is_low_stock: true,
      }),
    ])
    await user.click(screen.getByRole('button', { name: 'Try again' }))

    const low = (await screen.findByText('TEE-NAVY-L')).closest('tr')!
    expect(within(low).getByText('Low stock')).toBeInTheDocument()
    expect(screen.getByTestId('total-on-hand')).toHaveTextContent('13')
    expect(screen.getByTestId('total-value')).toHaveTextContent('$65.00')
  })
})

describe('receiving stock', () => {
  it('validates, adds a Variant found in the chosen Warehouse, and sends one Idempotency-Key', async () => {
    warehouseStore([aWarehouse({ id: 1, name: 'Beirut Central', code: 'BEY-01' })])
    stockLevelStore([
      aStockLevel({ variant: { id: 9, sku: 'TEE-BLK-S', options: [] }, average_cost: '0.0000' }),
    ])
    const posted = receiptStore()
    const { user } = await open('/inventory/receipts/new', 'Receive stock')

    await user.click(screen.getByRole('button', { name: 'Record Receipt' }))
    expect(await screen.findByText('Choose a Warehouse.')).toBeInTheDocument()
    expect(screen.getByText("Enter the supplier's reference.")).toBeInTheDocument()
    expect(screen.getByText('Add at least one Variant.')).toBeInTheDocument()

    await user.selectOptions(
      screen.getByRole('combobox', { name: 'Warehouse' }),
      'BEY-01 · Beirut Central',
    )
    await user.type(screen.getByRole('textbox', { name: 'Supplier reference' }), 'PO-7781')
    await user.type(screen.getByRole('searchbox', { name: 'Search a SKU or Product to add' }), 'tee')
    await user.click(await screen.findByRole('button', { name: 'Add a Variant: TEE-BLK-S' }))
    await user.clear(screen.getByRole('textbox', { name: 'Quantity' }))
    await user.type(screen.getByRole('textbox', { name: 'Quantity' }), '10')
    await user.type(screen.getByRole('textbox', { name: 'Unit cost' }), '5.12345')
    await user.click(screen.getByRole('button', { name: 'Record Receipt' }))
    expect(
      await screen.findByText('Enter a cost with at most four decimals.'),
    ).toBeInTheDocument()

    await user.clear(screen.getByRole('textbox', { name: 'Unit cost' }))
    await user.type(screen.getByRole('textbox', { name: 'Unit cost' }), '5')
    expect(screen.getByTestId('receipt-total')).toHaveTextContent('$50.00')
    await user.click(screen.getByRole('button', { name: 'Record Receipt' }))

    expect(await screen.findByRole('heading', { name: 'RCV-2026-000042', level: 1 })).toBeInTheDocument()
    expect(posted).toHaveLength(1)
    expect(posted[0]?.input).toEqual({
      warehouse_id: 1,
      supplier_reference: 'PO-7781',
      notes: '',
      lines: [{ variant_id: 9, quantity: 10, unit_cost: '5' }],
    })
    expect(posted[0]?.key).toMatch(/^[0-9a-f-]{36}$/)
  })
})

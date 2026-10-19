import { screen, waitFor, within } from '@testing-library/react'
import { HttpResponse, http } from 'msw/http'
import { describe, expect, it } from 'vitest'
import type { CurrentUser } from '@/features/auth/types'
import { viewerUser } from '@/test/msw/handlers'
import { server } from '@/test/msw/server'
import { signedInAs } from '@/test/msw/session'
import {
  aCarrier,
  aShippingMethod,
  aShippingZone,
  carrierStore,
  shippingZoneStore,
} from '@/test/msw/shipping'
import { renderApp } from '@/test/render-app'

const operationsUser: CurrentUser = {
  id: 2,
  name: 'Rania Khoury',
  email: 'operations@inkcode.test',
  role: 'operations',
  permissions: ['dashboard.view', 'orders.view', 'orders.create', 'shipping.manage'],
  preferences: { locale: 'en', theme: 'light' },
}

function demoZones() {
  return [
    aShippingZone({
      id: 1,
      name: 'Lebanon',
      locations: [{ country_code: 'LB', city: null }],
      methods: [
        aShippingMethod({
          name: 'Express',
          price: '10.00',
          min_delivery_days: 1,
          max_delivery_days: 2,
        }),
        aShippingMethod({ name: 'Standard', price: '5.00' }),
        aShippingMethod({ name: 'Overnight', price: '30.00', is_active: false }),
      ],
    }),
    aShippingZone({
      id: 2,
      name: 'Beirut',
      locations: [{ country_code: 'LB', city: 'Beirut' }],
      methods: [
        aShippingMethod({
          name: 'Same-day',
          price: '6.00',
          min_delivery_days: 0,
          max_delivery_days: 0,
        }),
      ],
    }),
  ]
}

async function open(path: string, heading: string, user: CurrentUser = operationsUser) {
  signedInAs(user)
  const view = renderApp(path)
  // The first visit loads the page's code, which can take a moment on a busy machine.
  await screen.findByRole('heading', { name: heading, level: 1 }, { timeout: 10_000 })
  return view
}

describe('the Shipping zones list', () => {
  it('shows each zone with the places it covers and how many methods are active', async () => {
    shippingZoneStore(demoZones())
    await open('/shipping/zones', 'Shipping zones')

    const lebanon = (await screen.findByRole('link', { name: 'Lebanon' })).closest('tr')!
    expect(within(lebanon).getByText('2 of 3 active')).toBeInTheDocument()
    const beirut = screen.getByRole('link', { name: 'Beirut' }).closest('tr')!
    expect(within(beirut).getByText('Beirut, Lebanon')).toBeInTheDocument()
  })

  it('checks an address: the city zone wins, the country zone catches the rest, others get a message', async () => {
    shippingZoneStore(demoZones())
    const { user } = await open('/shipping/zones', 'Shipping zones')
    const checker = screen.getByRole('region', { name: 'Check an address' })
    const country = within(checker).getByRole('combobox', { name: 'Country' })
    const city = within(checker).getByRole('textbox', { name: 'City' })
    const check = within(checker).getByRole('button', { name: 'Check' })

    await user.selectOptions(country, 'LB')
    await user.type(city, 'Beirut')
    await user.click(check)
    expect(await screen.findByText('Shipping zone: Beirut')).toBeInTheDocument()
    expect(screen.getByText('Same day')).toBeInTheDocument()

    await user.clear(city)
    await user.type(city, 'Tripoli')
    await user.click(check)
    expect(await screen.findByText('Shipping zone: Lebanon')).toBeInTheDocument()
    const offered = screen
      .getAllByRole('listitem')
      .filter((item) => item.textContent?.includes('$'))
    expect(offered.map((item) => item.textContent)).toEqual([
      'Standard2–4 days$5.00',
      'Express1–2 days$10.00',
    ])

    await user.selectOptions(country, 'FR')
    await user.clear(city)
    await user.type(city, 'Paris')
    await user.click(check)
    expect(await screen.findByText(/No Shipping zone covers Paris/)).toBeInTheDocument()
  })

  it('creates a zone and shows a place another zone covers under its row', async () => {
    const store = shippingZoneStore(demoZones())
    const { user, router } = await open('/shipping/zones', 'Shipping zones')

    await user.click(screen.getByRole('button', { name: 'New Shipping zone' }))
    const dialog = await screen.findByRole('dialog', { name: 'New Shipping zone' })
    await user.type(within(dialog).getByRole('textbox', { name: 'Name' }), 'Levant')
    await user.selectOptions(within(dialog).getByRole('combobox', { name: 'Country' }), 'LB')
    await user.type(within(dialog).getByRole('textbox', { name: 'City' }), 'Beirut')
    await user.click(within(dialog).getByRole('button', { name: 'Add a country or city' }))
    await user.selectOptions(within(dialog).getAllByRole('combobox', { name: 'Country' })[1]!, 'JO')
    await user.click(within(dialog).getByRole('button', { name: 'Create Shipping zone' }))

    expect(
      await within(dialog).findByText(/Beirut is already in the Shipping zone "Beirut"/),
    ).toBeInTheDocument()

    await user.clear(within(dialog).getAllByRole('textbox', { name: 'City' })[0]!)
    await user.selectOptions(within(dialog).getAllByRole('combobox', { name: 'Country' })[0]!, 'SY')
    await user.click(within(dialog).getByRole('button', { name: 'Create Shipping zone' }))

    await waitFor(() => expect(router.state.location.pathname).toMatch(/^\/shipping\/zones\/\d+$/))
    expect(store.bodies.at(-1)).toEqual({
      name: 'Levant',
      locations: [
        { country_code: 'SY', city: null },
        { country_code: 'JO', city: null },
      ],
    })
  })

  it('asks for a name and a country before sending anything', async () => {
    const store = shippingZoneStore([])
    const { user } = await open('/shipping/zones', 'Shipping zones')

    await user.click(screen.getAllByRole('button', { name: 'New Shipping zone' })[0]!)
    const dialog = await screen.findByRole('dialog', { name: 'New Shipping zone' })
    await user.click(within(dialog).getByRole('button', { name: 'Create Shipping zone' }))

    expect(await within(dialog).findByText('Enter a name.')).toBeInTheDocument()
    expect(within(dialog).getByText('Choose a country.')).toBeInTheDocument()
    expect(store.bodies).toEqual([])
  })

  it('offers a retry when the zones fail to load', async () => {
    server.use(http.get('*/api/v1/shipping-zones', () => HttpResponse.json({}, { status: 500 })))
    const { user } = await open('/shipping/zones', 'Shipping zones')

    shippingZoneStore(demoZones())
    await user.click(await screen.findByRole('button', { name: 'Try again' }))
    expect(await screen.findByRole('link', { name: 'Lebanon' })).toBeInTheDocument()
  })

  it('lets a Viewer look but offers no way to change anything', async () => {
    shippingZoneStore(demoZones())
    await open('/shipping/zones', 'Shipping zones', viewerUser)

    expect(await screen.findByRole('link', { name: 'Lebanon' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'New Shipping zone' })).not.toBeInTheDocument()
    expect(screen.queryByRole('region', { name: 'Check an address' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /Actions for/ })).not.toBeInTheDocument()
  })
})

describe('a Shipping zone page', () => {
  it('lists its methods cheapest first and adds one', async () => {
    const store = shippingZoneStore(demoZones())
    const { user } = await open('/shipping/zones/1', 'Lebanon')

    const table = screen.getByRole('table', { name: 'Shipping methods' })
    expect(
      within(table)
        .getAllByRole('row')
        .slice(1)
        .map((row) => (row as HTMLTableRowElement).cells[0]!.textContent),
    ).toEqual(['Standard', 'Express', 'Overnight'])
    expect(within(table).getByText('Inactive')).toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: 'New Shipping method' }))
    const dialog = await screen.findByRole('dialog', { name: 'New Shipping method' })
    await user.type(within(dialog).getByRole('textbox', { name: 'Name' }), 'Economy')
    await user.type(within(dialog).getByRole('textbox', { name: 'Price' }), '3.5')
    await user.click(within(dialog).getByRole('button', { name: 'Create Shipping method' }))

    expect(await within(table).findByText('Economy')).toBeInTheDocument()
    expect(store.bodies.at(-1)).toEqual({
      name: 'Economy',
      price: '3.5',
      min_delivery_days: 2,
      max_delivery_days: 4,
      is_active: true,
    })
  })

  it('refuses a slowest delivery faster than the fastest', async () => {
    shippingZoneStore(demoZones())
    const { user } = await open('/shipping/zones/1', 'Lebanon')

    await user.click(screen.getByRole('button', { name: 'New Shipping method' }))
    const dialog = await screen.findByRole('dialog', { name: 'New Shipping method' })
    await user.type(within(dialog).getByRole('textbox', { name: 'Name' }), 'Economy')
    await user.type(within(dialog).getByRole('textbox', { name: 'Price' }), '3.555')
    await user.clear(within(dialog).getByRole('textbox', { name: 'Slowest (days)' }))
    await user.type(within(dialog).getByRole('textbox', { name: 'Slowest (days)' }), '1')
    await user.click(within(dialog).getByRole('button', { name: 'Create Shipping method' }))

    expect(await within(dialog).findByText(/two decimals at most/)).toBeInTheDocument()
    expect(
      within(dialog).getByText("The slowest delivery can't be faster than the fastest."),
    ).toBeInTheDocument()
  })
})

describe('Carriers', () => {
  it('creates a Carrier, previewing its tracking link', async () => {
    const store = carrierStore([aCarrier({ name: 'Aramex' })])
    const { user } = await open('/shipping/carriers', 'Carriers')

    await user.click(screen.getByRole('button', { name: 'New Carrier' }))
    const dialog = await screen.findByRole('dialog', { name: 'New Carrier' })
    await user.type(within(dialog).getByRole('textbox', { name: 'Name' }), 'DHL')
    const link = within(dialog).getByRole('textbox', { name: 'Tracking link' })
    await user.type(link, 'https://dhl.example/track')
    await user.click(within(dialog).getByRole('button', { name: 'Create Carrier' }))
    expect(await within(dialog).findByText(/that contains \{tracking_number\}/)).toBeInTheDocument()

    await user.type(link, '?id={{tracking_number}')
    expect(
      within(dialog).getByText('Example: https://dhl.example/track?id=1234567890'),
    ).toBeInTheDocument()
    await user.click(within(dialog).getByRole('button', { name: 'Create Carrier' }))

    expect(await screen.findByText('DHL')).toBeInTheDocument()
    expect(store.bodies).toEqual([
      { name: 'DHL', tracking_url_template: 'https://dhl.example/track?id={tracking_number}' },
    ])
  })
})

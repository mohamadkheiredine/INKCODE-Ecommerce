import { screen, within } from '@testing-library/react'
import { delay } from 'msw'
import { HttpResponse, http } from 'msw/http'
import { describe, expect, it } from 'vitest'
import type { CurrentUser } from '@/features/auth/types'
import { adminUser, apiError, viewerUser } from '@/test/msw/handlers'
import { server } from '@/test/msw/server'
import { signedInAs } from '@/test/msw/session'
import { aStoreSettings, settingsStore } from '@/test/msw/settings'
import { renderApp } from '@/test/render-app'

const admin: CurrentUser = {
  ...adminUser,
  permissions: [...adminUser.permissions, 'settings.manage'],
}

type User = ReturnType<typeof renderApp>['user']

/** The Admin on the settings screen; the store's settings answer like the API's. */
async function openSettings() {
  signedInAs(admin)
  const store = settingsStore()
  const view = renderApp('/settings')
  await screen.findByLabelText('Store name')
  return { ...view, store }
}

const mainNavigation = () => within(screen.getByRole('navigation', { name: 'Main navigation' }))

async function replace(user: User, label: string, value: string) {
  const field = screen.getByLabelText(label)
  await user.clear(field)
  if (value) await user.type(field, value)
}

describe('Store settings', () => {
  it('saves the business settings and the whole dashboard uses the new Store currency', async () => {
    const { user, store } = await openSettings()
    const badge = await screen.findByTestId('store-badge')
    expect(badge).toHaveTextContent('Prices in USD')

    await replace(user, 'Currency code', 'eur')
    await replace(user, 'Currency symbol', '€')
    await replace(user, 'VAT rate', '11.5')
    await user.click(screen.getByRole('radio', { name: /Preparing/ }))
    await replace(user, 'Return window', '30')

    // The preview follows what is typed, before saving.
    const preview = screen.getByRole('status', { name: 'How amounts will look' })
    expect(preview).toHaveTextContent('€1,234.50')
    expect(preview).toHaveTextContent('VAT on €100.00€11.50')

    await user.click(screen.getByRole('button', { name: 'Save changes' }))

    expect(await screen.findByText('Settings saved.')).toBeInTheDocument()
    expect(store.saved).toEqual([
      expect.objectContaining({
        currency_code: 'EUR',
        currency_symbol: '€',
        vat_rate: '11.5',
        deduction_stage: 'preparing',
        return_window_days: 30,
        low_stock_threshold: 5,
        contact_phone: null,
      }),
    ])
    expect(await within(badge).findByText('Prices in EUR')).toBeInTheDocument()
    expect(screen.getByLabelText('VAT rate')).toHaveValue('11.50')
    expect(screen.getByRole('button', { name: 'Save changes' })).toBeDisabled()
  })

  it('explains each business setting', async () => {
    await openSettings()

    expect(screen.getByLabelText('VAT rate')).toHaveAccessibleDescription(
      /An Order line keeps the rate it was priced with/,
    )
    expect(screen.getByRole('radiogroup')).toHaveAccessibleDescription(
      /At this Order status the Reserved units leave On-hand/,
    )
    expect(screen.getByLabelText(/Return window/)).toHaveAccessibleDescription(
      'How many days after delivery a Customer may request a Return.',
    )
  })

  it('checks the values before saving, and shows what the API refuses under its field', async () => {
    const { user, store } = await openSettings()

    await replace(user, 'Store name', '')
    await replace(user, 'Currency code', 'E1')
    await replace(user, 'Return window', '0')
    await user.click(screen.getByRole('button', { name: 'Save changes' }))

    expect(await screen.findByText("Enter the store's name.")).toBeInTheDocument()
    expect(
      screen.getByText('Use the three-letter ISO code in capitals, for example USD.'),
    ).toBeInTheDocument()
    expect(screen.getByText('Enter a whole number of days from 1 to 365.')).toBeInTheDocument()
    expect(store.saved).toEqual([])

    await replace(user, 'Store name', 'Cedar & Co.')
    await replace(user, 'Currency code', 'EUR')
    await replace(user, 'Return window', '14')
    // The API, not the form, is the last word: here it refuses the rate.
    server.use(
      http.put('*/api/v1/settings', () =>
        apiError(422, 'validation_failed', 'The VAT rate field must be between 0 and 100.', {
          vat_rate: ['The VAT rate field must be between 0 and 100.'],
        }),
      ),
    )
    await user.click(screen.getByRole('button', { name: 'Save changes' }))

    expect(
      await screen.findByText('The VAT rate field must be between 0 and 100.'),
    ).toBeInTheDocument()
    expect(screen.getByLabelText('VAT rate')).toHaveAttribute('aria-invalid', 'true')
  })

  it('throws away unsaved changes on Discard', async () => {
    const { user } = await openSettings()

    await replace(user, 'Store name', 'Something else')
    expect(screen.getByText('You have unsaved changes.')).toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Discard' }))

    expect(screen.getByLabelText('Store name')).toHaveValue('Inkcode Demo Store')
  })

  it('shows a skeleton while loading, then a retry when loading fails', async () => {
    let failing = true
    server.use(
      http.get('*/api/v1/settings', async () => {
        await delay(150)
        return failing
          ? apiError(500, 'server_error', 'Something went wrong on our side.')
          : HttpResponse.json({ data: aStoreSettings() })
      }),
    )
    signedInAs(admin)
    const { user } = renderApp('/settings')

    expect(await screen.findByRole('status', { name: 'Loading…' })).toBeInTheDocument()
    const retry = await screen.findByRole('button', { name: 'Try again' })
    failing = false
    await user.click(retry)

    expect(await screen.findByLabelText('Store name')).toHaveValue('Inkcode Demo Store')
  })

  it('appears in the menu for the Admin only', async () => {
    await openSettings()
    expect(mainNavigation().getByRole('link', { name: 'Settings' })).toBeInTheDocument()
  })

  it('is closed to Roles without settings.manage, with the store still shown to them', async () => {
    signedInAs(viewerUser)
    renderApp('/settings')

    expect(await screen.findByText("You can't open this page")).toBeInTheDocument()
    expect(mainNavigation().queryByRole('link', { name: 'Settings' })).not.toBeInTheDocument()
    expect(await screen.findByTestId('store-badge')).toHaveTextContent('Inkcode Demo Store')
  })

  it('works in Arabic, right to left', async () => {
    signedInAs({ ...admin, preferences: { locale: 'ar', theme: 'light' } })
    settingsStore()
    renderApp('/settings')

    expect(await screen.findByLabelText('اسم المتجر')).toHaveValue('Inkcode Demo Store')
    expect(document.documentElement).toHaveAttribute('dir', 'rtl')
    expect(screen.getByRole('radio', { name: /تم الشحن/ })).toBeChecked()
  })
})

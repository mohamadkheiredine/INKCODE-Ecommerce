import {
  EllipsisIcon,
  MapPinIcon,
  MapPinPlusIcon,
  PencilIcon,
  PhoneIcon,
  ReceiptTextIcon,
  Trash2Icon,
  TruckIcon,
} from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { ConfirmDeleteDialog } from '@/components/feedback/confirm-delete-dialog'
import { EmptyState } from '@/components/feedback/empty-state'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import {
  Card,
  CardAction,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import { useDirection } from '@/components/ui/direction'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { countryName } from '@/lib/countries'
import { useFormatters } from '@/lib/format'
import { apiErrorMessage } from '@/lib/forms'
import i18n from '@/lib/i18n'
import { useRemoveAddress, useSaveAddress } from '../hooks/use-customers'
import { makeDefaultInput } from '../schemas/address-schema'
import type { AddressPurpose, CustomerAddress, CustomerProfile } from '../types'
import { AddressFormDialog } from './address-form-dialog'

const PURPOSES: AddressPurpose[] = ['billing', 'shipping']

/** A Customer's addresses, defaults first, with add, edit, make default and remove. */
export function AddressesCard({
  customer,
  canManage,
}: {
  customer: CustomerProfile
  canManage: boolean
}) {
  const { t } = useTranslation('customers')
  const [editing, setEditing] = useState<CustomerAddress | 'new' | null>(null)
  const [removing, setRemoving] = useState<CustomerAddress | null>(null)
  const saveAddress = useSaveAddress(customer.id)
  const removeAddress = useRemoveAddress(customer.id)
  const addresses = customer.addresses

  const makeDefault = (address: CustomerAddress, purpose: AddressPurpose) =>
    saveAddress.mutate(
      { id: address.id, input: makeDefaultInput(address, purpose) },
      {
        onSuccess: () => toast.success(i18n.t(`customers:addresses.madeDefault.${purpose}`)),
        onError: (error) => toast.error(apiErrorMessage(error)),
      },
    )

  const confirmRemove = () => {
    if (!removing) return
    removeAddress.mutate(removing.id, {
      onSuccess: () => toast.success(i18n.t('customers:addresses.removed')),
      onError: (error) => toast.error(apiErrorMessage(error)),
      onSettled: () => setRemoving(null),
    })
  }

  const addButton = (
    <Button
      type="button"
      variant="outline"
      size="sm"
      className="gap-2"
      onClick={() => setEditing('new')}
    >
      <MapPinPlusIcon className="size-4" aria-hidden="true" />
      {t('addresses.add')}
    </Button>
  )

  return (
    <Card className="js-reveal">
      <CardHeader>
        <CardTitle>{t('addresses.title')}</CardTitle>
        <CardDescription>{t('addresses.description')}</CardDescription>
        {canManage && addresses.length > 0 && <CardAction>{addButton}</CardAction>}
      </CardHeader>
      <CardContent>
        {addresses.length === 0 ? (
          <EmptyState
            icon={MapPinIcon}
            title={t('addresses.emptyTitle')}
            description={canManage ? t('addresses.emptyDescription') : t('addresses.emptyReadOnly')}
            action={canManage ? addButton : null}
            className="py-8"
          />
        ) : (
          <ul className="grid gap-3 md:grid-cols-2">
            {addresses.map((address) => (
              <li key={address.id}>
                <AddressTile
                  address={address}
                  actions={
                    canManage && (
                      <AddressActions
                        address={address}
                        onEdit={() => setEditing(address)}
                        onMakeDefault={(purpose) => makeDefault(address, purpose)}
                        onRemove={() => setRemoving(address)}
                      />
                    )
                  }
                />
              </li>
            ))}
          </ul>
        )}
      </CardContent>

      <AddressFormDialog
        customerId={customer.id}
        address={editing}
        isFirst={addresses.length === 0}
        defaultCountry={addresses[0]?.country_code ?? ''}
        onClose={() => setEditing(null)}
      />
      <ConfirmDeleteDialog
        name={removing?.label ?? null}
        description={
          removing && (removing.is_default_billing || removing.is_default_shipping)
            ? t('addresses.removeDefaultDescription')
            : t('addresses.removeDescription')
        }
        pending={removeAddress.isPending}
        onConfirm={confirmRemove}
        onCancel={() => setRemoving(null)}
      />
    </Card>
  )
}

/** One address as a postal label, with its default badges. */
function AddressTile({ address, actions }: { address: CustomerAddress; actions: React.ReactNode }) {
  const { t } = useTranslation('customers')
  const { locale } = useFormatters()
  const isDefault = address.is_default_billing || address.is_default_shipping

  return (
    <article
      aria-label={address.label}
      className={
        isDefault
          ? 'flex h-full flex-col gap-3 rounded-xl border border-primary/30 bg-primary/[0.03] p-4'
          : 'flex h-full flex-col gap-3 rounded-xl border p-4'
      }
    >
      <div className="flex items-start justify-between gap-2">
        <div className="flex min-w-0 flex-col gap-1.5">
          <h3 className="truncate font-medium" dir="auto">
            {address.label}
          </h3>
          {isDefault && (
            <div className="flex flex-wrap gap-1.5">
              {address.is_default_billing && (
                <Badge variant="secondary" className="gap-1 rounded-md bg-primary/10 text-primary">
                  <ReceiptTextIcon className="size-3" aria-hidden="true" />
                  {t('addresses.defaultBadge.billing')}
                </Badge>
              )}
              {address.is_default_shipping && (
                <Badge variant="secondary" className="gap-1 rounded-md bg-primary/10 text-primary">
                  <TruckIcon className="size-3" aria-hidden="true" />
                  {t('addresses.defaultBadge.shipping')}
                </Badge>
              )}
            </div>
          )}
        </div>
        {actions}
      </div>
      <address className="flex flex-col gap-0.5 text-sm text-muted-foreground not-italic">
        <span className="text-foreground" dir="auto">
          {address.recipient_name}
        </span>
        <span dir="auto">{address.line1}</span>
        {address.line2 && <span dir="auto">{address.line2}</span>}
        <span dir="auto">{[address.city, address.postal_code].filter(Boolean).join(' ')}</span>
        <span>{countryName(address.country_code, locale)}</span>
        {address.phone && (
          <span className="mt-1 inline-flex items-center gap-1.5">
            <PhoneIcon className="size-3.5" aria-hidden="true" />
            <span dir="ltr">{address.phone}</span>
          </span>
        )}
      </address>
    </article>
  )
}

function AddressActions({
  address,
  onEdit,
  onMakeDefault,
  onRemove,
}: {
  address: CustomerAddress
  onEdit: () => void
  onMakeDefault: (purpose: AddressPurpose) => void
  onRemove: () => void
}) {
  const { t } = useTranslation('customers')
  const dir = useDirection()
  const purposes = PURPOSES.filter((purpose) => !address[`is_default_${purpose}`])

  return (
    <DropdownMenu dir={dir}>
      <DropdownMenuTrigger asChild>
        <Button
          type="button"
          variant="ghost"
          size="icon-sm"
          className="-me-2 -mt-1 shrink-0"
          aria-label={t('common:dataTable.actionsFor', { name: address.label })}
        >
          <EllipsisIcon className="size-4" aria-hidden="true" />
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end" className="w-60">
        <DropdownMenuItem onSelect={onEdit} className="gap-2">
          <PencilIcon className="size-4" aria-hidden="true" />
          {t('common:actions.edit')}
        </DropdownMenuItem>
        {purposes.map((purpose) => (
          <DropdownMenuItem key={purpose} onSelect={() => onMakeDefault(purpose)} className="gap-2">
            {purpose === 'billing' ? (
              <ReceiptTextIcon className="size-4" aria-hidden="true" />
            ) : (
              <TruckIcon className="size-4" aria-hidden="true" />
            )}
            {t(`addresses.makeDefault.${purpose}`)}
          </DropdownMenuItem>
        ))}
        <DropdownMenuSeparator />
        <DropdownMenuItem variant="destructive" onSelect={onRemove} className="gap-2">
          <Trash2Icon className="size-4" aria-hidden="true" />
          {t('addresses.remove')}
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  )
}

import { SearchIcon, SearchXIcon } from 'lucide-react'
import { useEffect, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useNavigate } from 'react-router'
import { ErrorState } from '@/components/feedback/error-state'
import { Button } from '@/components/ui/button'
import {
  Command,
  CommandDialog,
  CommandGroup,
  CommandInput,
  CommandItem,
  CommandList,
} from '@/components/ui/command'
import { useDirection } from '@/components/ui/direction'
import { Kbd } from '@/components/ui/kbd'
import { Skeleton } from '@/components/ui/skeleton'
import { ProductStatusBadge } from '@/features/catalog/components/product-status-badge'
import { ProductThumbnail } from '@/features/catalog/components/product-thumbnail'
import { CustomerAvatar } from '@/features/customers/components/customer-avatar'
import { OrderStatusBadge } from '@/features/orders/components/order-status-badge'
import { useFormatters } from '@/lib/format'
import { useGlobalSearch } from '../hooks/use-global-search'
import { useQuickActions } from '../hooks/use-quick-actions'
import type { SearchResults } from '../types'

/** Ctrl+K on Windows and Linux, ⌘K on a Mac. */
function isSearchShortcut(event: KeyboardEvent): boolean {
  return (event.ctrlKey || event.metaKey) && !event.altKey && event.key.toLowerCase() === 'k'
}

/**
 * The header's search box and the palette it opens (also with Ctrl+K): Orders, Products
 * and Customers matching what the User types, grouped, plus the quick actions their Role
 * allows. Arrow keys move, Enter opens, Escape closes.
 */
export function GlobalSearch() {
  const { t } = useTranslation('search')
  const [open, setOpen] = useState(false)

  useEffect(() => {
    const onKeyDown = (event: KeyboardEvent) => {
      if (!isSearchShortcut(event)) return
      event.preventDefault()
      setOpen((isOpen) => !isOpen)
    }
    document.addEventListener('keydown', onKeyDown)
    return () => document.removeEventListener('keydown', onKeyDown)
  }, [])

  return (
    <>
      <Button
        type="button"
        variant="outline"
        className="size-9 rounded-full p-0 text-muted-foreground md:w-60 md:justify-start md:gap-2 md:px-3"
        onClick={() => setOpen(true)}
        aria-label={t('open')}
        aria-keyshortcuts="Control+K Meta+K"
      >
        <SearchIcon className="size-4" aria-hidden="true" />
        <span className="hidden font-normal md:inline">{t('trigger')}</span>
        <Kbd className="ms-auto hidden md:inline-flex" dir="ltr" aria-hidden="true">
          Ctrl K
        </Kbd>
      </Button>
      {/* Mounted only while open, so every visit starts from an empty search. */}
      {open && <SearchPalette onClose={() => setOpen(false)} />}
    </>
  )
}

function SearchPalette({ onClose }: { onClose: () => void }) {
  const { t } = useTranslation('search')
  const dir = useDirection()
  const navigate = useNavigate()
  const [input, setInput] = useState('')
  const search = useGlobalSearch(input)

  const go = (to: string) => {
    onClose()
    void navigate(to)
  }

  return (
    <CommandDialog
      open
      onOpenChange={(isOpen) => !isOpen && onClose()}
      title={t('title')}
      description={t('description')}
      className="sm:max-w-xl"
    >
      <Command shouldFilter={false} dir={dir} loop label={t('title')}>
        <CommandInput
          value={input}
          onValueChange={setInput}
          placeholder={t('placeholder')}
          aria-label={t('placeholder')}
        />
        <CommandList className="max-h-[min(28rem,60vh)]" data-lenis-prevent>
          <PaletteBody search={search} onGo={go} />
        </CommandList>
      </Command>
    </CommandDialog>
  )
}

function PaletteBody({
  search,
  onGo,
}: {
  search: ReturnType<typeof useGlobalSearch>
  onGo: (to: string) => void
}) {
  const { t } = useTranslation('search')

  if (!search.enabled) {
    return (
      <>
        <p className="px-3 py-4 text-center text-sm text-muted-foreground">{t('hint')}</p>
        <QuickActionsGroup onGo={onGo} />
      </>
    )
  }

  if (search.isError && !search.isFetching) {
    return (
      <ErrorState
        className="py-6"
        title={t('errorTitle')}
        description={t('errorDescription')}
        onRetry={() => void search.refetch()}
      />
    )
  }

  if (!search.data || (search.isPlaceholderData && search.isFetching)) {
    return <ResultsSkeleton label={t('searching')} />
  }

  if (isEmpty(search.data)) {
    return (
      <>
        <div className="flex flex-col items-center gap-2 px-3 py-6 text-center">
          <SearchXIcon className="size-6 text-muted-foreground" aria-hidden="true" />
          <p className="text-sm font-medium" role="status">
            {t('emptyTitle')}
          </p>
          <p className="text-sm text-muted-foreground">
            <span dir="auto">{t('emptyDescription', { q: search.q })}</span>
          </p>
        </div>
        <QuickActionsGroup onGo={onGo} />
      </>
    )
  }

  return <ResultGroups results={search.data} onGo={onGo} />
}

function isEmpty(results: SearchResults): boolean {
  return [results.orders, results.products, results.customers].every(
    (group) => (group?.length ?? 0) === 0,
  )
}

function ResultGroups({ results, onGo }: { results: SearchResults; onGo: (to: string) => void }) {
  const { t } = useTranslation('search')
  const { money } = useFormatters()

  return (
    <>
      {results.orders && results.orders.length > 0 && (
        <CommandGroup heading={t('groups.orders')}>
          {results.orders.map((order) => (
            <CommandItem
              key={order.id}
              value={`order-${order.id}`}
              onSelect={() => onGo(`/orders/${order.id}`)}
              className="gap-3"
            >
              <div className="flex min-w-0 flex-1 flex-col">
                <span className="font-medium tabular-nums" dir="ltr">
                  {order.number}
                </span>
                <span className="truncate text-xs text-muted-foreground" dir="auto">
                  {order.customer_name}
                </span>
              </div>
              <OrderStatusBadge status={order.status} className="hidden sm:inline-flex" />
              <span className="text-sm tabular-nums">{money(order.total)}</span>
            </CommandItem>
          ))}
        </CommandGroup>
      )}
      {results.products && results.products.length > 0 && (
        <CommandGroup heading={t('groups.products')}>
          {results.products.map((product) => (
            <CommandItem
              key={product.id}
              value={`product-${product.id}`}
              onSelect={() => onGo(`/catalog/products/${product.id}`)}
              className="gap-3"
            >
              <ProductThumbnail src={product.main_image_url} alt="" className="size-8" />
              <div className="flex min-w-0 flex-1 flex-col">
                <span className="truncate font-medium" dir="auto">
                  {product.name}
                </span>
                <span className="truncate text-xs text-muted-foreground" dir="ltr">
                  {product.sku ?? t('noSku')}
                </span>
              </div>
              <span className="hidden sm:inline-flex">
                <ProductStatusBadge status={product.status} />
              </span>
            </CommandItem>
          ))}
        </CommandGroup>
      )}
      {results.customers && results.customers.length > 0 && (
        <CommandGroup heading={t('groups.customers')}>
          {results.customers.map((customer) => (
            <CommandItem
              key={customer.id}
              value={`customer-${customer.id}`}
              onSelect={() => onGo(`/customers/${customer.id}`)}
              className="gap-3"
            >
              <CustomerAvatar name={customer.name} className="size-8" />
              <div className="flex min-w-0 flex-1 flex-col">
                <span className="truncate font-medium" dir="auto">
                  {customer.name}
                </span>
                <span className="truncate text-xs text-muted-foreground" dir="ltr">
                  {[customer.email, customer.phone].filter(Boolean).join(' · ')}
                </span>
              </div>
            </CommandItem>
          ))}
        </CommandGroup>
      )}
    </>
  )
}

function QuickActionsGroup({ onGo }: { onGo: (to: string) => void }) {
  const { t } = useTranslation('search')
  const actions = useQuickActions()

  if (actions.length === 0) return null

  return (
    <CommandGroup heading={t('groups.quickActions')}>
      {actions.map((action) => (
        <CommandItem key={action.id} value={`action-${action.id}`} onSelect={() => onGo(action.to)}>
          <action.icon className="size-4" aria-hidden="true" />
          {t(`actions.${action.id}`)}
        </CommandItem>
      ))}
    </CommandGroup>
  )
}

function ResultsSkeleton({ label }: { label: string }) {
  return (
    <div className="flex flex-col gap-3 p-3" role="status" aria-label={label}>
      {[0, 1, 2].map((row) => (
        <div key={row} className="flex items-center gap-3">
          <Skeleton className="size-8 rounded-lg" />
          <div className="flex flex-1 flex-col gap-1.5">
            <Skeleton className="h-3.5 w-2/5" />
            <Skeleton className="h-3 w-1/4" />
          </div>
        </div>
      ))}
    </div>
  )
}

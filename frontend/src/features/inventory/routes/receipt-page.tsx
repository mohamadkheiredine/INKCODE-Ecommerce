import { ArrowLeftIcon, PackagePlusIcon } from 'lucide-react'
import { useRef } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useParams } from 'react-router'
import { EmptyState } from '@/components/feedback/empty-state'
import { ErrorState } from '@/components/feedback/error-state'
import { PageSkeleton } from '@/components/feedback/page-skeleton'
import { PageHeader } from '@/components/layout/page-header'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import {
  Table,
  TableBody,
  TableCell,
  TableFooter,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { fromCents, toCents } from '@/features/payments/money'
import { isApiError } from '@/lib/api/api-error'
import { useFormatters } from '@/lib/format'
import { useEntranceAnimation } from '@/lib/motion'
import { useReceipt } from '../hooks/use-inventory'
import { optionsLabel, type Receipt } from '../types'

/** One Receipt: where it arrived, from which supplier reference, and its lines. */
export function ReceiptPage() {
  const { t } = useTranslation('inventory')
  const receipt = useReceipt(Number(useParams().receiptId))

  if (receipt.isPending) return <PageSkeleton />
  if (receipt.isError) {
    if (isApiError(receipt.error) && receipt.error.status === 404) {
      return (
        <EmptyState
          icon={PackagePlusIcon}
          title={t('common:pages.receipt')}
          description={t('receipts.notFound')}
          action={<BackLink />}
        />
      )
    }
    return <ErrorState onRetry={() => void receipt.refetch()} retrying={receipt.isFetching} />
  }
  return <ReceiptDetail receipt={receipt.data} />
}

function BackLink() {
  const { t } = useTranslation('inventory')
  return (
    <Button asChild variant="outline" className="gap-2">
      <Link to="/inventory/receipts">
        <ArrowLeftIcon className="size-4 rtl:rotate-180" aria-hidden="true" />
        {t('common:pages.receipts')}
      </Link>
    </Button>
  )
}

function ReceiptDetail({ receipt }: { receipt: Receipt }) {
  const { t } = useTranslation('inventory')
  const { number, money, dateTime } = useFormatters()
  const container = useRef<HTMLDivElement>(null)
  const lines = receipt.lines ?? []
  const units = lines.reduce((sum, line) => sum + line.quantity, 0)
  // The API's own total; added up in cents as a fallback, never through floats.
  const total =
    receipt.total_cost ?? fromCents(lines.reduce((sum, line) => sum + toCents(line.line_cost), 0))

  useEntranceAnimation(container, [{ targets: '.js-reveal', stagger: 0.06 }])

  return (
    <div ref={container} className="flex flex-col gap-6">
      <PageHeader
        title={receipt.number}
        description={t('receipts.detailDescription', {
          warehouse: receipt.warehouse.name,
          date: dateTime(receipt.created_at),
        })}
        actions={<BackLink />}
      />
      <Card className="js-reveal rounded-2xl">
        <CardContent className="grid gap-4 sm:grid-cols-3">
          <div className="flex flex-col gap-1">
            <span className="text-xs text-muted-foreground">{t('fields.supplierReference')}</span>
            <span dir="auto" className="font-medium">
              {receipt.supplier_reference}
            </span>
          </div>
          <div className="flex flex-col gap-1">
            <span className="text-xs text-muted-foreground">{t('fields.warehouse')}</span>
            <span dir="auto" className="font-medium">
              {receipt.warehouse.name}{' '}
              <span dir="ltr" className="font-mono text-xs text-muted-foreground">
                {receipt.warehouse.code}
              </span>
            </span>
          </div>
          <div className="flex flex-col gap-1">
            <span className="text-xs text-muted-foreground">{t('fields.receivedBy')}</span>
            <span dir="auto" className="font-medium">
              {receipt.user?.name ?? t('movements.system')}
            </span>
          </div>
          {receipt.notes && (
            <p dir="auto" className="text-sm text-muted-foreground sm:col-span-3">
              {receipt.notes}
            </p>
          )}
        </CardContent>
      </Card>
      <Card className="js-reveal rounded-2xl py-0">
        <div className="overflow-x-auto" data-lenis-prevent>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>{t('fields.variant')}</TableHead>
                <TableHead className="text-end">{t('fields.quantity')}</TableHead>
                <TableHead className="text-end">{t('fields.unitCost')}</TableHead>
                <TableHead className="text-end">{t('fields.lineCost')}</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {lines.map((line) => (
                <TableRow key={line.id}>
                  <TableCell>
                    <div className="flex flex-col">
                      <Link
                        to={`/catalog/products/${line.variant.product.id}`}
                        dir="auto"
                        className="font-medium hover:underline"
                      >
                        {line.variant.product.name}
                      </Link>
                      <span className="text-xs text-muted-foreground">
                        <span dir="ltr" className="font-mono">
                          {line.variant.sku}
                        </span>
                        {line.variant.options.length > 0 &&
                          ` · ${optionsLabel(line.variant.options)}`}
                      </span>
                    </div>
                  </TableCell>
                  <TableCell className="text-end tabular-nums">{number(line.quantity)}</TableCell>
                  <TableCell className="text-end tabular-nums">{money(line.unit_cost)}</TableCell>
                  <TableCell className="text-end tabular-nums">{money(line.line_cost)}</TableCell>
                </TableRow>
              ))}
            </TableBody>
            <TableFooter>
              <TableRow>
                <TableCell>{t('receipts.total')}</TableCell>
                <TableCell className="text-end tabular-nums">{number(units)}</TableCell>
                <TableCell />
                <TableCell className="text-end font-semibold tabular-nums">
                  {money(total)}
                </TableCell>
              </TableRow>
            </TableFooter>
          </Table>
        </div>
      </Card>
    </div>
  )
}

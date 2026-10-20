import { BoxesIcon } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router'
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
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import type { Product } from '@/features/catalog/types'
import { useFormatters } from '@/lib/format'
import { optionsLabel, type WarehouseRef } from '../types'

/**
 * Each Variant's Available stock (with On-hand) per Warehouse on the Product page. The
 * API sends `stock` only to Roles that see stock; the card is hidden otherwise.
 */
export function ProductStockCard({ product }: { product: Product }) {
  const { t } = useTranslation('inventory')
  const { number } = useFormatters()

  if (product.variants.some((variant) => variant.stock === undefined)) return null

  const warehouses = new Map<number, WarehouseRef>()
  for (const variant of product.variants) {
    for (const balance of variant.stock ?? [])
      warehouses.set(balance.warehouse.id, balance.warehouse)
  }
  const columns = [...warehouses.values()].sort((a, b) => a.code.localeCompare(b.code))

  return (
    <Card className="js-reveal rounded-2xl">
      <CardHeader>
        <CardTitle className="text-base">{t('productStock.title')}</CardTitle>
        <CardDescription>{t('productStock.description')}</CardDescription>
        <CardAction>
          <Button asChild variant="outline" size="sm" className="gap-2">
            <Link to={`/inventory/stock?filter[product]=${product.id}`}>
              <BoxesIcon className="size-4" aria-hidden="true" />
              {t('productStock.manage')}
            </Link>
          </Button>
        </CardAction>
      </CardHeader>
      <CardContent>
        {columns.length === 0 ? (
          <p className="text-sm text-muted-foreground">{t('productStock.empty')}</p>
        ) : (
          <div className="overflow-x-auto" data-lenis-prevent>
            <Table aria-label={t('productStock.title')}>
              <TableHeader>
                <TableRow>
                  <TableHead>{t('fields.variant')}</TableHead>
                  {columns.map((warehouse) => (
                    <TableHead key={warehouse.id} className="text-end" title={warehouse.name}>
                      <span dir="ltr" className="font-mono text-xs">
                        {warehouse.code}
                      </span>
                    </TableHead>
                  ))}
                  <TableHead className="text-end">{t('productStock.total')}</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {product.variants.map((variant) => {
                  const byWarehouse = new Map(
                    (variant.stock ?? []).map((balance) => [balance.warehouse.id, balance]),
                  )
                  const available = (variant.stock ?? []).reduce((sum, b) => sum + b.available, 0)
                  return (
                    <TableRow key={variant.id}>
                      <TableCell>
                        <div className="flex flex-col">
                          <span className="font-medium">
                            {optionsLabel(variant.options) || variant.sku}
                          </span>
                          <span
                            dir="ltr"
                            className="text-start font-mono text-xs text-muted-foreground"
                          >
                            {variant.sku}
                          </span>
                        </div>
                      </TableCell>
                      {columns.map((warehouse) => {
                        const balance = byWarehouse.get(warehouse.id)
                        return (
                          <TableCell key={warehouse.id} className="text-end tabular-nums">
                            <span className="font-medium">{number(balance?.available ?? 0)}</span>
                            {balance && balance.reserved > 0 && (
                              <span className="block text-xs text-muted-foreground">
                                {t('fields.onHand')} {number(balance.on_hand)}
                              </span>
                            )}
                          </TableCell>
                        )
                      })}
                      <TableCell className="text-end">
                        <Badge
                          variant={available > 0 ? 'secondary' : 'outline'}
                          className="tabular-nums"
                        >
                          {number(available)}
                        </Badge>
                      </TableCell>
                    </TableRow>
                  )
                })}
              </TableBody>
            </Table>
          </div>
        )}
      </CardContent>
    </Card>
  )
}

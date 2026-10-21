import type { Fulfillment } from './types'

/** Each Shipment progress's tint, from theme tokens only. */
export const FULFILLMENT_TONES: Record<Fulfillment['status'], string> = {
  unfulfilled: 'border-border bg-muted text-muted-foreground',
  partially_shipped: 'border-chart-2/40 bg-chart-2/15 text-foreground',
  shipped: 'border-chart-3/40 bg-chart-3/15 text-foreground',
  delivered: 'border-success/40 bg-success/15 text-foreground',
}

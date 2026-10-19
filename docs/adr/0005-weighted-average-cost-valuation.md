# Weighted average cost for stock valuation

Stock value is On-hand × Average cost. Each Receipt re-weights the Variant's Average cost: ((old On-hand × old cost) + (received × unit cost)) ÷ new On-hand. We chose this over FIFO cost layers because it needs one number per Variant instead of a queue of purchase lots. That keeps Receipts, Transfers, Returns and the inventory report simple and consistent.

## Consequences

- Historic reports show the Average cost as it is now, not the cost at the time of each sale; cost of goods sold per Order is not tracked.
- Switching to FIFO later would need every Receipt to be replayed as cost layers.

# Reports count confirmed orders by the date they were placed

The dashboard and every report include only Counted orders: Confirmed or later, and not Cancelled. They are dated by the day the Order was placed. Revenue, Net sales, Average order value and Lifetime value all use this one rule, so the order list, the Payment ledger and the sales report reconcile, as the "financial consistency" demo scenario requires.

## Considered options

- **Recognise revenue on delivery:** closer to accounting practice, but Orders would move between periods as they ship, so a report run twice for the same dates would change.
- **Include Pending orders:** staff create Orders that may never be confirmed, so this would inflate Revenue with Orders that never happen.
- **Date by payment:** Cash on delivery would make most Orders appear days after they were placed, which would hide daily sales activity.

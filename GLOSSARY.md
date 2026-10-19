# INKCODE Commerce

The back office of a single online store: staff manage the catalog, stock, orders, money and returns, and see reports built from that data.

## People and access

**User**:
A staff member who signs in to the admin dashboard.
_Avoid_: Admin, account, employee, operator

**Role**:
One of seven fixed permission sets (Admin, Operations, Inventory, Sales, Support, Finance, Viewer); every User has exactly one.
_Avoid_: Group, profile, user type

**Demo account**:
A local-only User, one per Role, created only when demo data is seeded, so reviewers can sign in with one click.
_Avoid_: Test user, sample login

**Customer**:
A person the store sells to; a record kept by Users, never someone who signs in.
_Avoid_: Client, buyer, user, account

**Segment**:
An automatic grouping of Customers by buying behaviour: New (first Order in the last 30 days), VIP (Lifetime value of $1,000 or more), At risk (no Order for 90 days), or Regular (everyone else).
_Avoid_: Tier, cohort, group

**Tag**:
A free label a User puts on a Customer (for example "wholesale").
_Avoid_: Label, category

**Lifetime value**:
The Revenue of a Customer's Counted orders minus that Customer's Refunds.
_Avoid_: LTV (in the interface), total spent, customer value

**Audit entry**:
One recorded change to a record: who made it, what they did, which record, the values before and after, and when; together they form the audit log.
_Avoid_: History row, activity, change log

## Catalog

**Product**:
Something the store sells, described once (name, category, brand, images); it is sold through one or more Variants.
_Avoid_: Item, article

**Variant**:
One concrete sellable version of a Product (for example "Red / M"), with its own SKU, price and stock.
_Avoid_: Option, SKU (the SKU is the Variant's code, not the Variant)

**Attribute**:
A shared dimension Variants differ by (Size, Color, Material), with a list of values; a Product's Variants are the combinations of the values chosen for it.
_Avoid_: Option, property, characteristic

**Product status**:
Draft, Active or Archived; a Product that appears on any Order can be Archived but never deleted.
_Avoid_: Visibility, published

**Category**:
A group of Products in two levels: a top-level Category, or a child Category under one; a child never has children.
_Avoid_: Collection, department, section

**Brand**:
Who makes a Product, with an optional logo; Products can be filtered and reported by Brand.
_Avoid_: Manufacturer, label, vendor

**Slug**:
A record's short, unique, lowercase name for links ("summer-shirts"), generated from its name unless a User chooses one.
_Avoid_: Handle, permalink, URL key

## Stock

**Warehouse**:
A place that holds stock and ships Orders.
_Avoid_: Location, store, depot

**On-hand**:
Units of a Variant physically in a Warehouse.
_Avoid_: Stock level, quantity

**Reserved**:
On-hand units promised to Confirmed Orders that have not shipped yet.
_Avoid_: Allocated, held, committed

**Available**:
On-hand minus Reserved: what can still be promised to a new Order.
_Avoid_: Free stock, sellable quantity

**Fulfilling warehouse**:
The one Warehouse an Order ships from, chosen when the Order is created.
_Avoid_: Source warehouse, origin

**Deduction stage**:
The Order status (Preparing, Ready for shipping, or Shipped) at which Reserved units leave On-hand; chosen in Settings, Shipped by default.
_Avoid_: Fulfillment stage, commit point

**Stock movement**:
One recorded change to a Variant's On-hand or Reserved quantity in a Warehouse, with its cause; a Warehouse's stock is always the sum of its Stock movements.
_Avoid_: Stock log, inventory transaction, ledger entry

**Adjustment**:
A manual Stock movement made by a User, always with a reason: count correction, damaged, lost, or found.
_Avoid_: Correction, manual edit

**Transfer**:
Moving stock from one Warehouse to another in two steps: Sent (it leaves the first Warehouse and is in transit) then Received (it arrives in the second).
_Avoid_: Relocation, move

**Receipt**:
Stock arriving from a supplier into a Warehouse, with quantities, unit cost and the supplier's reference.
_Avoid_: Goods-in, purchase, delivery

**Average cost**:
A Variant's cost per unit, re-weighted every time a Receipt arrives at a different unit cost.
_Avoid_: Cost price, unit cost, landed cost

**Stock value**:
On-hand multiplied by Average cost.
_Avoid_: Inventory valuation, stock worth

**Low-stock threshold**:
The Available quantity, set per Variant, at or below which a Warehouse raises a low-stock alert.
_Avoid_: Reorder point, minimum stock

## Selling

**Order**:
A Customer's purchase, entered and managed by a User, identified by an Order number such as ORD-2026-000123.
_Avoid_: Purchase, sale, transaction, cart

**Order status**:
Where an Order is in its life: Pending, Confirmed, Preparing, Ready for shipping, Shipped, Delivered, or Cancelled; it only moves forward one step at a time.
_Avoid_: State, stage, fulfillment status

**Locked order**:
An Order that has reached Preparing; its items, quantities, addresses and discount can no longer change.
_Avoid_: Frozen, closed

**Assignee**:
The User responsible for handling an Order; an active Admin, Operations or Sales User.
_Avoid_: Owner, assigned employee, handler

**Partial cancellation**:
Cancelling some lines or quantities of an Order before they ship.
_Avoid_: Line void, removal

**Coupon**:
A code that discounts an Order by a percentage or a fixed amount, within its dates, limits and eligible items; an Order carries at most one Coupon.
_Avoid_: Voucher, promo code, discount code

**Invoice**:
The printable statement of an Order's lines, discount, VAT, shipping and total.
_Avoid_: Bill, receipt

## Shipping

**Shipment**:
A parcel that leaves the Fulfilling warehouse carrying some or all of an Order's lines, with a Carrier and a tracking number.
_Avoid_: Delivery, consignment, package

**Partial fulfillment**:
Shipping an Order in more than one Shipment.
_Avoid_: Split order, backorder

**Shipping zone**:
A group of countries or cities that share the same Shipping methods and prices.
_Avoid_: Region, area

**Shipping method**:
A way of delivering within a Shipping zone (for example Standard or Express) with a flat price.
_Avoid_: Shipping rate, delivery option

**Carrier**:
The company that physically delivers a Shipment.
_Avoid_: Courier, provider

## Money

**Store currency**:
The single currency every price, payment and report is expressed in.
_Avoid_: Default currency, base currency

**VAT rate**:
The one store-wide tax percentage applied to taxable products; the rate in force when an Order line is priced stays with that line.
_Avoid_: Tax class, tax rule

**Payment method**:
How a Customer pays: Cash on delivery, Manual (bank transfer or cash), or Card.
_Avoid_: Payment type, gateway

**Payment ledger**:
The list of every payment and refund recorded against an Order.
_Avoid_: Transactions, payment history

**Captured amount**:
The money actually received for an Order, according to its Payment ledger.
_Avoid_: Paid amount, collected amount

**Payment status**:
A summary read from the Payment ledger: Unpaid, Partially paid, Paid, Partially refunded, or Refunded.
_Avoid_: Billing status, financial status

**Refund**:
Money given back to a Customer; all Refunds on an Order together can never exceed its Captured amount.
_Avoid_: Reimbursement, chargeback

## Reporting

**Counted order**:
An Order that is Confirmed or later and not Cancelled; only Counted orders feed the dashboard and reports, dated by the day the Order was placed.
_Avoid_: Valid order, completed order, sale

**Revenue**:
What Customers were charged on Counted orders: items minus discount plus VAT plus shipping.
_Avoid_: Gross sales, turnover, total sales

**Net sales**:
Items minus discount minus Refunds on Counted orders, without VAT or shipping.
_Avoid_: Net revenue, profit

**Average order value**:
Revenue divided by the number of Counted orders.
_Avoid_: AOV (in the interface), basket size

## Returns

**Return**:
A Customer sending back some items of a Delivered Order; it is Requested, then Approved or Rejected, then Received, then each item gets a Restock decision, then it may be Refunded.
_Avoid_: RMA, exchange

**Return window**:
How many days after delivery a Return may be Requested; 14 by default, set in Settings.
_Avoid_: Return period, return policy

**Restock decision**:
The choice, per returned item after it is Received, to put it back into On-hand or to write it off.
_Avoid_: Disposition

**Write-off**:
A returned item that is not put back into stock, for example because it is damaged.
_Avoid_: Scrap, loss

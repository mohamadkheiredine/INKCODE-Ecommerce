# One fulfilling warehouse per order

Every Order ships from exactly one Fulfilling warehouse, chosen when the Order is created. Confirming reserves stock only in that Warehouse, and is refused if it lacks Available stock, rather than automatically splitting lines across Warehouses. With one Warehouse per Order, one set of balance rows to lock, one reservation and one deduction per line, it is straightforward to prove the "no overselling, no double deduction" guarantee under concurrency. Partial fulfillment (several Shipments) still works within that Warehouse.

## Considered options

- **Automatic split across Warehouses:** fewer refused confirmations, but reservations, Shipments and Returns would have to track a Warehouse per line, and concurrency proofs would cover many rows. Rejected for the assessment's scope.
- **Warehouse chosen per line by staff:** same complexity, plus a heavier order form.

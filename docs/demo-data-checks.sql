-- Consistency checks for the seeded demo store (or any copy of the database).
-- Every query in the "must be zero" section returns 0 when the data is consistent.
--   docker exec -i inkcode-mysql mysql -uinkcode -psecret inkcode < docs/demo-data-checks.sql

-- Volumes the brief asks for: 200 products, 1,000 orders, 300 customers, 3 warehouses.
SELECT
    (SELECT COUNT(*) FROM products) AS products,
    (SELECT COUNT(*) FROM variants) AS variants,
    (SELECT COUNT(*) FROM orders) AS orders,
    (SELECT COUNT(*) FROM customers) AS customers,
    (SELECT COUNT(*) FROM warehouses WHERE is_active) AS active_warehouses,
    (SELECT COUNT(*) FROM payments WHERE kind = 'payment') AS payments,
    (SELECT COUNT(*) FROM payments WHERE kind = 'refund') AS refunds,
    (SELECT COUNT(*) FROM returns) AS returns_count,
    (SELECT COUNT(*) FROM stock_movements) AS stock_movements,
    (SELECT COUNT(DISTINCT r.name) FROM roles r JOIN model_has_roles m ON m.role_id = r.id) AS staff_roles_in_use;

SELECT status, COUNT(*) AS orders FROM orders GROUP BY status ORDER BY orders DESC;
SELECT payment_status, COUNT(*) AS orders FROM orders GROUP BY payment_status ORDER BY orders DESC;

-- Must be zero ---------------------------------------------------------------------------

-- 1. Every stock balance equals the sum of its movements (On-hand and Reserved).
SELECT COUNT(*) AS balances_not_matching_movements
FROM stock_balances b
LEFT JOIN (
    SELECT variant_id, warehouse_id, SUM(on_hand_delta) AS on_hand, SUM(reserved_delta) AS reserved
    FROM stock_movements GROUP BY variant_id, warehouse_id
) m ON m.variant_id = b.variant_id AND m.warehouse_id = b.warehouse_id
WHERE b.on_hand <> COALESCE(m.on_hand, 0) OR b.reserved <> COALESCE(m.reserved, 0);

-- 2. No negative stock, no reservation above On-hand, Available = On-hand - Reserved.
SELECT COUNT(*) AS invalid_balances
FROM stock_balances
WHERE on_hand < 0 OR reserved < 0 OR reserved > on_hand OR available <> on_hand - reserved;

-- 3. Each movement's "after" values continue from the one before it (an unbroken ledger).
SELECT COUNT(*) AS broken_ledger_rows
FROM (
    SELECT on_hand_after, reserved_after, on_hand_delta, reserved_delta,
           LAG(on_hand_after, 1, 0) OVER w AS previous_on_hand,
           LAG(reserved_after, 1, 0) OVER w AS previous_reserved
    FROM stock_movements
    WINDOW w AS (PARTITION BY variant_id, warehouse_id ORDER BY id)
) ledger
WHERE on_hand_after <> previous_on_hand + on_hand_delta OR reserved_after <> previous_reserved + reserved_delta;

-- 4. Order totals add up: items - discount + VAT + shipping, and the lines add up to the items.
SELECT COUNT(*) AS orders_with_wrong_totals
FROM orders o
JOIN (
    SELECT order_id, SUM(quantity * unit_price) AS items, SUM(discount_amount) AS discount, SUM(vat_amount) AS vat
    FROM order_lines GROUP BY order_id
) l ON l.order_id = o.id
WHERE o.status <> 'cancelled'
  AND (o.total <> o.items_total - o.discount_total + o.vat_total + o.shipping_total
       OR o.items_total <> l.items OR o.discount_total <> l.discount OR o.vat_total <> l.vat);

-- 5. Each Order's captured and refunded amounts equal its succeeded ledger rows.
SELECT COUNT(*) AS orders_not_matching_ledger
FROM orders o
LEFT JOIN (
    SELECT order_id,
           SUM(CASE WHEN kind = 'payment' THEN amount ELSE 0 END) AS captured,
           SUM(CASE WHEN kind = 'refund' THEN amount ELSE 0 END) AS refunded
    FROM payments WHERE status = 'succeeded' GROUP BY order_id
) p ON p.order_id = o.id
WHERE o.captured_amount <> COALESCE(p.captured, 0) OR o.refunded_amount <> COALESCE(p.refunded, 0);

-- 6. No Order has been refunded more than was captured.
SELECT COUNT(*) AS over_refunded_orders FROM orders WHERE refunded_amount > captured_amount;

-- 7. Shipped units never exceed ordered units, and a Delivered Order has every Shipment delivered.
SELECT COUNT(*) AS lines_over_shipped FROM order_lines WHERE shipped_quantity > quantity;
SELECT COUNT(*) AS delivered_orders_with_open_shipments
FROM orders o JOIN shipments s ON s.order_id = o.id
WHERE o.status = 'delivered' AND s.status <> 'delivered';

-- 8. Every Order line keeps its historical snapshot (name, SKU, price).
SELECT COUNT(*) AS lines_without_snapshot
FROM order_lines WHERE product_name = '' OR sku = '' OR unit_price IS NULL OR options IS NULL;

-- 9. Coupon usage never passes its limit (redemptions still held).
SELECT COUNT(*) AS coupons_over_limit
FROM coupons c
JOIN (SELECT coupon_id, COUNT(*) AS used FROM coupon_redemptions WHERE released_at IS NULL GROUP BY coupon_id) r ON r.coupon_id = c.id
WHERE c.usage_limit IS NOT NULL AND r.used > c.usage_limit;

-- 10. No Order is dated in the future, before its Customer joined, or paid before it was placed.
SELECT COUNT(*) AS orders_in_future FROM orders WHERE placed_at > NOW();
SELECT COUNT(*) AS payments_before_order FROM payments p JOIN orders o ON o.id = p.order_id WHERE p.created_at < o.placed_at;
SELECT COUNT(*) AS orders_before_customer_joined FROM orders o JOIN customers c ON c.id = o.customer_id WHERE o.placed_at < c.created_at;

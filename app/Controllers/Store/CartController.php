<?php
namespace App\Controllers\Store;
use Core\{Request, Response, Session, Database};

class CartController
{
    public function show(Request $request, array $params = []): void
    {
        $cart = $this->getCart();
        $items = $this->getItems($cart['id']);
        $subtotal = array_reduce($items, fn($s,$i) => $s + ($i['unit_price'] * $i['quantity']), 0);
        Response::json([
            'success'=>true, 'items'=>$items,
            'count'=>array_sum(array_column($items,'quantity')),
            'subtotal'=>$subtotal,
        ]);
    }

    public function add(Request $request, array $params = []): void
    {
        $productId = (int)$request->input('product_id');
        $quantity = max(1, (int)($request->input('quantity') ?? 1));
        $comboId = (int)($request->input('variant_combo_id') ?? 0);

        $product = Database::fetch("SELECT id,price,sale_price,stock_quantity FROM wk_products WHERE id=? AND is_active=1", [$productId]);
        if (!$product) { Response::json(['success'=>false,'message'=>'Product not found'], 404); return; }

        $unitPrice = $product['sale_price'] ?: $product['price'];
        $stockAvailable = $product['stock_quantity'];
        $variantLabel = '';

        // If variant selected, use combo's price and stock
        if ($comboId) {
            $combo = Database::fetch("SELECT * FROM wk_variant_combos WHERE id=? AND product_id=? AND is_active=1", [$comboId, $productId]);
            if (!$combo) { Response::json(['success'=>false,'message'=>'Variant not available'], 400); return; }
            if ($combo['price_override']) $unitPrice = (float)$combo['price_override'];
            $stockAvailable = $combo['stock_quantity'];
            $variantLabel = $combo['label'];
        }

        if ($stockAvailable <= 0) {
            Response::json(['success'=>false,'message'=>'Out of stock'], 400);
            return;
        }

        $cart = $this->getCart();

        // Check existing — match by product_id AND variant_combo_id
        $existing = Database::fetch(
            "SELECT id,quantity FROM wk_cart_items WHERE cart_id=? AND product_id=? AND COALESCE(variant_combo_id,0)=?",
            [$cart['id'], $productId, $comboId]
        );

        if ($existing) {
            $newQty = $existing['quantity'] + $quantity;
            if ($newQty > $stockAvailable) {
                Response::json(['success'=>false,'message'=>'Only '.$stockAvailable.' available'], 400);
                return;
            }
            Database::update('wk_cart_items', ['quantity'=>$newQty,'unit_price'=>$unitPrice], 'id=?', [$existing['id']]);
        } else {
            if ($quantity > $stockAvailable) {
                Response::json(['success'=>false,'message'=>'Only '.$stockAvailable.' available'], 400);
                return;
            }
            $insertData = [
                'cart_id'=>$cart['id'], 'product_id'=>$productId,
                'quantity'=>$quantity, 'unit_price'=>$unitPrice,
            ];
            // Add variant fields if they exist in the table
            try {
                $insertData['variant_combo_id'] = $comboId ?: null;
            } catch (\Exception $e) {}
            Database::insert('wk_cart_items', $insertData);
        }
        Response::json(['success'=>true,'message'=>'Added to cart']);
    }

    public function update(Request $request, array $params = []): void
    {
        $itemId = (int)$request->input('item_id');
        $quantity = max(0, (int)$request->input('quantity'));
        $cart = $this->getCart();

        if ($quantity === 0) {
            Database::delete('wk_cart_items', 'id=? AND cart_id=?', [$itemId, $cart['id']]);
        } else {
            // Check stock availability before updating
            $item = Database::fetch("SELECT product_id, variant_combo_id FROM wk_cart_items WHERE id=? AND cart_id=?", [$itemId, $cart['id']]);
            if ($item) {
                $comboId = $item['variant_combo_id'] ?? 0;
                if ($comboId) {
                    $stock = (int)Database::fetchValue("SELECT stock_quantity FROM wk_variant_combos WHERE id=?", [$comboId]);
                } else {
                    $stock = (int)Database::fetchValue("SELECT stock_quantity FROM wk_products WHERE id=?", [$item['product_id']]);
                }
                if ($quantity > $stock) {
                    Response::json(['success' => false, 'message' => "Only {$stock} available"], 400);
                    return;
                }
            }
            Database::update('wk_cart_items', ['quantity' => $quantity], 'id=? AND cart_id=?', [$itemId, $cart['id']]);
        }
        Response::json(['success' => true]);
    }

    public function remove(Request $request, array $params = []): void
    {
        $itemId = (int)$request->input('item_id');
        $cart = $this->getCart();
        Database::delete('wk_cart_items', 'id=? AND cart_id=?', [$itemId, $cart['id']]);
        Response::json(['success'=>true]);
    }

    public function applyCoupon(Request $request, array $params = []): void
    {
        // Rate limit: 10 coupon attempts per session per hour
        if (!\Core\RateLimiter::attempt('coupon', \Core\Session::cartId(), 10, 3600)) {
            Response::json(['success' => false, 'message' => 'Too many attempts. Try again later.'], 429);
            return;
        }

        $code = strtoupper(trim($request->input('coupon_code') ?? ''));
        $coupon = Database::fetch(
            "SELECT * FROM wk_coupons WHERE code=? AND is_active=1 AND (expires_at IS NULL OR expires_at>NOW()) AND (usage_limit IS NULL OR used_count<usage_limit)",
            [$code]
        );
        if (!$coupon) { Response::json(['success'=>false,'message'=>'Invalid or expired coupon'], 400); return; }
        Session::set('wk_coupon', $coupon);
        Response::json(['success'=>true,'coupon'=>['code'=>$coupon['code'],'type'=>$coupon['type'],'value'=>$coupon['value']]]);
    }

    public function clear(Request $request, array $params = []): void
    {
        $cart = $this->getCart();
        Database::delete('wk_cart_items', 'cart_id=?', [$cart['id']]);
        Response::json(['success'=>true]);
    }

    private function getCart(): array
    {
        $sid = Session::cartId();
        $cart = Database::fetch("SELECT id FROM wk_carts WHERE session_id=? AND status='active'", [$sid]);
        if (!$cart) {
            $id = Database::insert('wk_carts', [
                'session_id'=>$sid, 'customer_id'=>Session::customerId(),
                'status'=>'active', 'expires_at'=>date('Y-m-d H:i:s', strtotime('+7 days')),
            ]);
            $cart = ['id'=>$id];
        } else {
            // Link to customer if logged in but cart wasn't linked
            $custId = Session::customerId();
            if ($custId) {
                try {
                    Database::query("UPDATE wk_carts SET customer_id=? WHERE id=? AND customer_id IS NULL", [$custId, $cart['id']]);
                    $email = Database::fetchValue("SELECT email FROM wk_customers WHERE id=?", [$custId]);
                    if ($email) Database::query("UPDATE wk_carts SET email=? WHERE id=? AND email IS NULL", [$email, $cart['id']]);
                } catch (\Exception $e) {}
            }
        }
        return $cart;
    }

    private function getItems(int $cartId): array
    {
        try {
            return Database::fetchAll(
                "SELECT ci.id, ci.product_id, ci.quantity, ci.unit_price, p.name, p.slug,
                        ci.variant_combo_id,
                        vc.label AS variant_label,
                        COALESCE(
                            (SELECT image_path FROM wk_product_images WHERE product_id=p.id AND alt_text=CONCAT('variant_opt_', SUBSTRING_INDEX(vc.option_ids,',',1)) LIMIT 1),
                            (SELECT image_path FROM wk_product_images WHERE product_id=p.id AND is_primary=1 LIMIT 1)
                        ) AS image
                 FROM wk_cart_items ci
                 JOIN wk_products p ON p.id=ci.product_id
                 LEFT JOIN wk_variant_combos vc ON vc.id=ci.variant_combo_id
                 WHERE ci.cart_id=?", [$cartId]
            );
        } catch (\Exception $e) {
            // Fallback if variant columns don't exist
            return Database::fetchAll(
                "SELECT ci.id, ci.product_id, ci.quantity, ci.unit_price, p.name, p.slug,
                        (SELECT image_path FROM wk_product_images WHERE product_id=p.id AND is_primary=1 LIMIT 1) AS image
                 FROM wk_cart_items ci JOIN wk_products p ON p.id=ci.product_id WHERE ci.cart_id=?", [$cartId]
            );
        }
    }
}
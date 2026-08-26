<?php
namespace App\Controllers\Store;

use App\Services\CartRecoveryService;
use Core\{Request, View, Database, Response, Session, RateLimiter};

class CartRecoveryController
{
    /**
     * Follow a recovery link back to an abandoned basket.
     *
     * The token identifies the cart, so the visitor's session simply adopts
     * it — no copying of items, and it works on a device that has never seen
     * this shop before, which is the whole point of the link.
     */
    public function recover(Request $request, array $params = []): void
    {
        $token = (string) ($params['token'] ?? '');

        // A token is a bearer credential; guessing at them should be slow.
        if (!RateLimiter::attempt('cart_recover', $request->ip(), 20, 900)) {
            Session::flash('error', 'Too many attempts. Please try again shortly.');
            Response::redirect(View::url('') . '?cart=open');
            return;
        }

        $cart = CartRecoveryService::cartForToken($token);
        if (!$cart) {
            Session::flash('error', 'That link has expired. Your basket may have changed since — here it is.');
            Response::redirect(View::url('') . '?cart=open');
            return;
        }

        $currentSession = Session::cartId();
        if ($currentSession !== $cart['session_id']) {
            $this->mergeInto((int) $cart['id'], $currentSession);
            Session::adoptCart($cart['session_id']);
        }

        // Bring it back to life so the rest of the shop treats it normally.
        try {
            Database::query(
                "UPDATE wk_carts SET status='active', abandoned_at=NULL WHERE id=?",
                [$cart['id']]
            );
        } catch (\Exception $e) {
            Database::query("UPDATE wk_carts SET status='active' WHERE id=?", [$cart['id']]);
        }

        Session::flash('success', 'Welcome back — your basket is just as you left it.');
        Response::redirect(View::url('') . '?cart=open');
    }

    /**
     * Stop emailing this address.
     *
     * The link is signed, so following one cannot be used to find out whether
     * an address is on file, and cannot unsubscribe someone else.
     */
    public function unsubscribe(Request $request, array $params = []): void
    {
        $email = trim((string) ($params['email'] ?? ''));
        $token = (string) ($params['token'] ?? '');

        $ok = $email !== ''
            && filter_var($email, FILTER_VALIDATE_EMAIL)
            && CartRecoveryService::verifyUnsubscribe($email, $token);

        if ($ok) {
            CartRecoveryService::suppress($email, 'unsubscribed');
        }

        View::render('store/unsubscribed', [
            'pageTitle' => $ok ? 'You will not hear from us again' : 'Link not recognised',
            'ok'        => $ok,
            'email'     => $ok ? $email : '',
        ], 'store/layouts/main');
    }

    /**
     * Move a leftover basket into the one being recovered, so anything added
     * in the meantime is not quietly dropped.
     */
    private function mergeInto(int $targetCartId, string $fromSessionId): void
    {
        try {
            $from = Database::fetch(
                "SELECT id FROM wk_carts WHERE session_id=? AND status='active' AND id<>?",
                [$fromSessionId, $targetCartId]
            );
            if (!$from) return;

            $items = Database::fetchAll("SELECT * FROM wk_cart_items WHERE cart_id=?", [$from['id']]);
            foreach ($items as $item) {
                $exists = Database::fetchValue(
                    "SELECT id FROM wk_cart_items
                      WHERE cart_id=? AND product_id=?
                        AND COALESCE(variant_combo_id,0)=COALESCE(?,0)",
                    [$targetCartId, $item['product_id'], $item['variant_combo_id'] ?? null]
                );
                if ($exists) {
                    Database::query(
                        "UPDATE wk_cart_items SET quantity = quantity + ? WHERE id=?",
                        [$item['quantity'], $exists]
                    );
                } else {
                    Database::query(
                        "UPDATE wk_cart_items SET cart_id=? WHERE id=?",
                        [$targetCartId, $item['id']]
                    );
                }
            }

            Database::query("UPDATE wk_carts SET status='merged' WHERE id=?", [$from['id']]);
        } catch (\Exception $e) {
            // A merge that fails must not stop the recovery itself.
            error_log('Whisker: merging a cart into a recovered one failed — ' . $e->getMessage());
        }
    }
}

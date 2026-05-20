<?php
namespace App\Controllers\Admin;
use Core\{Request, View, Database, Response, Session};

class CustomerController
{
    public function index(Request $request, array $params = []): void
    {
        $customers = Database::fetchAll("SELECT * FROM wk_customers ORDER BY created_at DESC");
        View::render('admin/customers/index', ['pageTitle'=>'Customers','customers'=>$customers], 'admin/layouts/main');
    }

    public function show(Request $request, array $params = []): void
    {
        $customer = Database::fetch("SELECT * FROM wk_customers WHERE id=?", [$params['id']]);
        if (!$customer) { Response::notFound(); return; }
        $orders = Database::fetchAll("SELECT * FROM wk_orders WHERE customer_id=? ORDER BY created_at DESC", [$params['id']]);
        View::render('admin/customers/show', [
            'pageTitle'=>$customer['first_name'].' '.$customer['last_name'], 'customer'=>$customer, 'orders'=>$orders
        ], 'admin/layouts/main');
    }

    /**
     * M32: GDPR "right to erasure" handler.
     *
     * We pseudonymize rather than hard-delete because:
     *   - Tax authorities require retention of order records (typically 7+ years)
     *   - Order history must remain intact for financial reconciliation
     *   - Cascading the delete to orders would break invoice references
     *
     * What happens:
     *   - Personal fields (name, email, phone) are replaced with anonymized
     *     stub values that still satisfy NOT NULL / UNIQUE constraints
     *   - password_hash is set to '*' (the same shadow-file sentinel used
     *     for guest customers — password_verify always rejects)
     *   - is_active=0 so they can't log in
     *   - wk_customer_addresses rows are deleted (FK CASCADE handles this)
     *   - Active carts are deleted (no commercial value, full of PII)
     *   - Orders are kept with customer_id pointing at the pseudonymized row.
     *     wk_orders.customer_email/customer_phone are scrubbed to the
     *     pseudonymized values too so PII doesn't survive there either.
     */
    public function pseudonymize(Request $request, array $params = []): void
    {
        if (!Session::verifyCsrf($request->input('wk_csrf'))) {
            Session::flash('error', 'Session expired.');
            Response::redirect(View::url('admin/customers/' . $params['id']));
            return;
        }

        $id = (int)$params['id'];
        $customer = Database::fetch("SELECT id, email FROM wk_customers WHERE id=?", [$id]);
        if (!$customer) {
            Response::redirect(View::url('admin/customers'));
            return;
        }

        // Build a globally-unique pseudonym so the UNIQUE constraint on
        // email holds even if multiple customers are pseudonymized.
        $stubEmail = 'deleted+' . $id . '@whisker.invalid';
        $stubName  = 'Deleted';
        $stubPhone = '';

        try {
            Database::transaction(function () use ($id, $stubEmail, $stubName, $stubPhone) {
                // Wipe address rows (PII, no business value once customer is gone).
                Database::query("DELETE FROM wk_customer_addresses WHERE customer_id=?", [$id]);

                // Wipe active carts for this customer (full of PII via cart
                // items, and they'll never be converted). Carts have
                // customer_id ON DELETE SET NULL, so we DELETE the rows
                // outright rather than orphaning them.
                Database::query("DELETE FROM wk_carts WHERE customer_id=?", [$id]);

                // Scrub the wk_orders.customer_email / customer_phone fields
                // on this customer's orders. The order rows themselves are
                // retained (legal/financial obligation) but the contact
                // fields no longer carry PII.
                Database::query(
                    "UPDATE wk_orders SET customer_email=?, customer_phone=? WHERE customer_id=?",
                    [$stubEmail, $stubPhone, $id]
                );

                // Finally rewrite the customer row itself. password_hash='*'
                // is the shadow-file sentinel — password_verify always
                // rejects it, so the account is unreachable. is_active=0
                // doubles as a soft-delete flag for any admin queries that
                // honor it.
                Database::update('wk_customers', [
                    'first_name'    => $stubName,
                    'last_name'     => '',
                    'email'         => $stubEmail,
                    'phone'         => $stubPhone,
                    'password_hash' => '*',
                    'is_active'     => 0,
                    'email_verified'=> 0,
                ], 'id=?', [$id]);
            });
        } catch (\Throwable $e) {
            Session::flash('error', 'Could not pseudonymize customer. Please try again.');
            Response::redirect(View::url('admin/customers/' . $id));
            return;
        }

        Session::flash('success', "Customer #{$id} pseudonymized. Order records retained for legal compliance.");
        Response::redirect(View::url('admin/customers'));
    }
}

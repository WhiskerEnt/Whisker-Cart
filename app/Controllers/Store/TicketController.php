<?php
namespace App\Controllers\Store;

use Core\{Request, View, Database, Response, Session};

class TicketController
{
    /** Customer: List tickets */
    public function index(Request $request, array $params = []): void
    {
        $custId = Session::customerId();
        if (!$custId) { Response::redirect(View::url('account/login')); return; }
        $tickets = Database::fetchAll(
            "SELECT t.*, (SELECT COUNT(*) FROM wk_ticket_replies WHERE ticket_id=t.id) AS reply_count
             FROM wk_tickets t WHERE t.customer_id=? ORDER BY t.updated_at DESC", [$custId]
        );
        View::render('store/account/tickets', ['pageTitle'=>'My Tickets','tickets'=>$tickets], 'store/layouts/main');
    }

    /** Customer: View ticket + replies */
    public function show(Request $request, array $params = []): void
    {
        // Login is required, and the SELECT is scoped to the customer's own
        // tickets so ownership is enforced at the SQL level as defense in depth.
        $custId = Session::customerId();
        if (!$custId) { Response::redirect(View::url('account/login')); return; }

        $ticket = Database::fetch(
            "SELECT * FROM wk_tickets WHERE id=? AND customer_id=?",
            [(int)$params['id'], $custId]
        );
        if (!$ticket) { Response::notFound(); return; }

        $replies = Database::fetchAll("SELECT * FROM wk_ticket_replies WHERE ticket_id=? ORDER BY created_at", [$ticket['id']]);
        View::render('store/account/ticket-detail', ['pageTitle'=>'Ticket #'.$ticket['ticket_number'],'ticket'=>$ticket,'replies'=>$replies], 'store/layouts/main');
    }

    /** Customer: Create ticket form */
    public function create(Request $request, array $params = []): void
    {
        $customer = null;
        if (Session::customerId()) $customer = Database::fetch("SELECT * FROM wk_customers WHERE id=?", [Session::customerId()]);
        View::render('store/account/ticket-create', ['pageTitle'=>'New Support Ticket','customer'=>$customer], 'store/layouts/main');
    }

    /** Customer: Store ticket */
    public function store(Request $request, array $params = []): void
    {
        if (!Session::verifyCsrf($request->input('wk_csrf'))) {
            Session::flash('error', 'Session expired.');
            Response::redirect(View::url('account/tickets/create'));
            return;
        }

        $name = $request->clean('name');
        $email = $request->clean('email');
        $phone = $request->clean('phone');
        $subject = $request->clean('subject');
        $message = trim($request->input('message') ?? '');
        $orderId = $request->input('order_id') ?: null;

        if (!$name || !$email || !$subject || !$message) {
            Session::flash('error','Please fill in all required fields.');
            Response::redirect(View::url('account/tickets/create'));
            return;
        }

        $ticketNumber = 'TK-' . strtoupper(date('ymd')) . '-' . strtoupper(bin2hex(random_bytes(3)));
        $custId = Session::customerId();

        $ticketId = Database::insert('wk_tickets', [
            'ticket_number'=>$ticketNumber, 'customer_id'=>$custId,
            'name'=>$name, 'email'=>$email, 'phone'=>$phone,
            'subject'=>$subject, 'status'=>'open', 'priority'=>'medium',
            'order_id'=>$orderId,
        ]);

        // First message as reply
        Database::insert('wk_ticket_replies', [
            'ticket_id'=>$ticketId, 'sender_type'=>'customer', 'sender_name'=>$name, 'message'=>$message,
        ]);

        // Email notification to admin
        $adminEmail = Database::fetchValue("SELECT setting_value FROM wk_settings WHERE setting_group='general' AND setting_key='contact_email'")
            ?: Database::fetchValue("SELECT email FROM wk_admins WHERE role='superadmin' LIMIT 1");
        if ($adminEmail) {
            $storeName = Database::fetchValue("SELECT setting_value FROM wk_settings WHERE setting_group='general' AND setting_key='site_name'") ?: 'Store';
            \App\Services\EmailService::send($adminEmail, "New Ticket: {$subject} [{$ticketNumber}]",
                '<h2>New Support Ticket</h2>
                <div style="background:#faf8f6;border-radius:10px;padding:20px;margin:16px 0">
                    <table style="font-size:14px"><tr><td style="padding:5px 14px 5px 0;font-weight:700;color:#6b7280">Ticket</td><td style="font-weight:800;color:#8b5cf6;font-family:monospace">'.$ticketNumber.'</td></tr>
                    <tr><td style="padding:5px 14px 5px 0;font-weight:700;color:#6b7280">From</td><td>'.htmlspecialchars($name).' ('.htmlspecialchars($email).')</td></tr>
                    <tr><td style="padding:5px 14px 5px 0;font-weight:700;color:#6b7280">Subject</td><td>'.htmlspecialchars($subject).'</td></tr></table>
                </div>
                <div style="padding:16px;border-left:3px solid #8b5cf6;margin:16px 0;font-size:14px;white-space:pre-line">'.htmlspecialchars($message).'</div>
                <a href="'.View::url('admin/tickets/'.$ticketId).'" style="display:inline-block;background:#8b5cf6;color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:700">View Ticket →</a>'
            );
        }

        // Confirm to customer
        $vars = [
            '{{customer_name}}'=>$name, '{{ticket_number}}'=>$ticketNumber, '{{ticket_subject}}'=>$subject,
            '{{store_name}}'=>Database::fetchValue("SELECT setting_value FROM wk_settings WHERE setting_group='general' AND setting_key='site_name'") ?: 'Store',
            '{{store_url}}'=>View::url(''),
        ];
        \App\Services\EmailService::sendFromTemplate('ticket-created', $email, $vars);

        Session::flash('success',"Ticket {$ticketNumber} created! We'll get back to you soon.");
        if ($custId) Response::redirect(View::url('account/tickets/'.$ticketId));
        else Response::redirect(View::url('contact'));
    }

    /** Customer: Reply to ticket */
    public function reply(Request $request, array $params = []): void
    {
        // Login is required and ownership is enforced in the SQL, same as show():
        // no matching row, no reply.
        $custId = Session::customerId();
        if (!$custId) { Response::redirect(View::url('account/login')); return; }

        $ticketId = (int)$params['id'];
        $ticket = Database::fetch(
            "SELECT * FROM wk_tickets WHERE id=? AND customer_id=?",
            [$ticketId, $custId]
        );
        if (!$ticket || $ticket['status'] === 'closed') {
            Session::flash('error','Cannot reply to this ticket.');
            Response::redirect(View::url('account/tickets'));
            return;
        }

        if (!Session::verifyCsrf($request->input('wk_csrf'))) {
            Session::flash('error', 'Session expired.');
            Response::redirect(View::url('account/tickets/' . $ticketId));
            return;
        }

        $message = trim($request->input('message') ?? '');
        if (!$message) { Response::redirect(View::url('account/tickets/'.$ticketId)); return; }

        Database::insert('wk_ticket_replies', [
            'ticket_id'=>$ticketId, 'sender_type'=>'customer', 'sender_name'=>$ticket['name'], 'message'=>$message,
        ]);

        // Reopen if resolved/waiting
        if (in_array($ticket['status'], ['resolved','waiting'])) {
            Database::update('wk_tickets', ['status'=>'open','closed_at'=>null], 'id=?', [$ticketId]);
        }

        // Notify admin
        $adminEmail = Database::fetchValue("SELECT setting_value FROM wk_settings WHERE setting_group='general' AND setting_key='contact_email'")
            ?: Database::fetchValue("SELECT email FROM wk_admins WHERE role='superadmin' LIMIT 1");
        if ($adminEmail) {
            \App\Services\EmailService::send($adminEmail, "Reply on Ticket {$ticket['ticket_number']}",
                '<p><strong>'.htmlspecialchars($ticket['name']).'</strong> replied to ticket <strong style="color:#8b5cf6">'.$ticket['ticket_number'].'</strong>:</p>
                <div style="padding:16px;border-left:3px solid #8b5cf6;margin:16px 0;font-size:14px;white-space:pre-line">'.htmlspecialchars($message).'</div>
                <a href="'.View::url('admin/tickets/'.$ticketId).'" style="display:inline-block;background:#8b5cf6;color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:700">View Ticket →</a>'
            );
        }

        Session::flash('success','Reply sent!');
        Response::redirect(View::url('account/tickets/'.$ticketId));
    }
}

<?php
namespace App\Controllers\Admin;

use Core\{Request, View, Database, Response, Session};

class TicketController
{
    public function index(Request $request, array $params = []): void
    {
        $status = $request->query('status') ?? '';
        $where = $status ? "WHERE t.status='" . addslashes($status) . "'" : '';
        $tickets = Database::fetchAll(
            "SELECT t.*, (SELECT COUNT(*) FROM wk_ticket_replies WHERE ticket_id=t.id) AS reply_count,
                    (SELECT created_at FROM wk_ticket_replies WHERE ticket_id=t.id ORDER BY created_at DESC LIMIT 1) AS last_reply_at
             FROM wk_tickets t {$where} ORDER BY
                CASE t.status WHEN 'open' THEN 1 WHEN 'in_progress' THEN 2 WHEN 'waiting' THEN 3 WHEN 'resolved' THEN 4 WHEN 'closed' THEN 5 END,
                t.updated_at DESC LIMIT 50"
        );
        $counts = [];
        foreach (['open','in_progress','waiting','resolved','closed'] as $s) {
            $counts[$s] = (int)Database::fetchValue("SELECT COUNT(*) FROM wk_tickets WHERE status=?", [$s]);
        }
        View::render('admin/tickets/index', [
            'pageTitle'=>'Support Tickets','tickets'=>$tickets,'counts'=>$counts,'currentStatus'=>$status,
        ], 'admin/layouts/main');
    }

    public function show(Request $request, array $params = []): void
    {
        $ticket = Database::fetch("SELECT * FROM wk_tickets WHERE id=?", [$params['id']]);
        if (!$ticket) { Response::notFound(); return; }
        $replies = Database::fetchAll("SELECT * FROM wk_ticket_replies WHERE ticket_id=? ORDER BY created_at", [$ticket['id']]);
        $order = $ticket['order_id'] ? Database::fetch("SELECT * FROM wk_orders WHERE id=?", [$ticket['order_id']]) : null;
        View::render('admin/tickets/show', [
            'pageTitle'=>'Ticket #'.$ticket['ticket_number'],'ticket'=>$ticket,'replies'=>$replies,'order'=>$order,
        ], 'admin/layouts/main');
    }

    public function reply(Request $request, array $params = []): void
    {
        $ticketId = (int)$params['id'];

        if (!Session::verifyCsrf($request->input('wk_csrf'))) {
            Session::flash('error', 'Session expired.');
            Response::redirect(View::url('admin/tickets/' . $ticketId));
            return;
        }

        $message = trim($request->input('message') ?? '');
        if (!$message) { Session::flash('error','Reply cannot be empty.'); Response::redirect(View::url('admin/tickets/'.$ticketId)); return; }

        $admin = Database::fetch("SELECT username FROM wk_admins WHERE id=?", [Session::adminId()]);
        Database::insert('wk_ticket_replies', [
            'ticket_id'=>$ticketId, 'sender_type'=>'admin',
            'sender_name'=>$admin['username'] ?? 'Admin', 'message'=>$message,
        ]);

        // Update ticket status to in_progress if it was open
        $ticket = Database::fetch("SELECT * FROM wk_tickets WHERE id=?", [$ticketId]);
        if ($ticket && $ticket['status'] === 'open') {
            Database::update('wk_tickets', ['status'=>'in_progress'], 'id=?', [$ticketId]);
        }

        // Email the customer
        $this->notifyCustomer($ticket, $message, $admin['username'] ?? 'Support');

        Session::flash('success','Reply sent!');
        Response::redirect(View::url('admin/tickets/'.$ticketId));
    }

    public function updateStatus(Request $request, array $params = []): void
    {
        if (!Session::verifyCsrf($request->input('wk_csrf'))) {
            Session::flash('error', 'Session expired.');
            Response::redirect(View::url('admin/tickets'));
            return;
        }
        $ticketId = (int)$params['id'];
        $newStatus = $request->input('status');
        $allowed = ['open','in_progress','waiting','resolved','closed'];
        if (!in_array($newStatus, $allowed)) { Response::redirect(View::url('admin/tickets/'.$ticketId)); return; }

        $update = ['status'=>$newStatus];
        if ($newStatus === 'closed' || $newStatus === 'resolved') $update['closed_at'] = date('Y-m-d H:i:s');
        if ($newStatus === 'open' || $newStatus === 'in_progress') $update['closed_at'] = null;
        Database::update('wk_tickets', $update, 'id=?', [$ticketId]);

        // Notify customer of status change
        $ticket = Database::fetch("SELECT * FROM wk_tickets WHERE id=?", [$ticketId]);
        if ($ticket) {
            $statusLabels = ['open'=>'Open','in_progress'=>'In Progress','waiting'=>'Waiting for Response','resolved'=>'Resolved','closed'=>'Closed'];
            $vars = [
                '{{customer_name}}'=>$ticket['name'], '{{ticket_number}}'=>$ticket['ticket_number'],
                '{{ticket_subject}}'=>$ticket['subject'], '{{ticket_status}}'=>$statusLabels[$newStatus] ?? ucfirst($newStatus),
                '{{store_name}}'=>Database::fetchValue("SELECT setting_value FROM wk_settings WHERE setting_group='general' AND setting_key='site_name'") ?: 'Our Store',
                '{{store_url}}'=>View::url(''),
            ];
            \App\Services\EmailService::sendFromTemplate('ticket-status-update', $ticket['email'], $vars);
        }

        Session::flash('success','Status updated to '.ucfirst($newStatus));
        Response::redirect(View::url('admin/tickets/'.$ticketId));
    }

    private function notifyCustomer(array $ticket, string $replyMessage, string $adminName): void
    {
        $storeName = Database::fetchValue("SELECT setting_value FROM wk_settings WHERE setting_group='general' AND setting_key='site_name'") ?: 'Our Store';
        $vars = [
            '{{customer_name}}'=>$ticket['name'], '{{ticket_number}}'=>$ticket['ticket_number'],
            '{{ticket_subject}}'=>$ticket['subject'], '{{reply_message}}'=>nl2br(htmlspecialchars($replyMessage)),
            '{{agent_name}}'=>$adminName,
            '{{store_name}}'=>$storeName, '{{store_url}}'=>View::url(''),
            '{{ticket_url}}'=>View::url('account/tickets/'.$ticket['id']),
        ];
        \App\Services\EmailService::sendFromTemplate('ticket-reply', $ticket['email'], $vars);
    }
}

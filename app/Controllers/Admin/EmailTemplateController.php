<?php
namespace App\Controllers\Admin;

use Core\{Request, View, Database, Response, Session};

class EmailTemplateController
{
    public function __construct()
    {
        try { Database::query("SELECT 1 FROM wk_email_templates LIMIT 1"); }
        catch (\Exception $e) {
            Database::connect()->exec("CREATE TABLE IF NOT EXISTS wk_email_templates (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, slug VARCHAR(50) NOT NULL UNIQUE,
                name VARCHAR(100) NOT NULL, subject VARCHAR(255) NOT NULL, body TEXT NOT NULL,
                is_active TINYINT(1) DEFAULT 1,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB");
            self::seedDefaults();
        }
    }

    public function index(Request $request, array $params = []): void
    {
        $templates = Database::fetchAll("SELECT * FROM wk_email_templates ORDER BY name");
        View::render('admin/email-templates/index', ['pageTitle' => 'Email Templates', 'templates' => $templates], 'admin/layouts/main');
    }

    public function edit(Request $request, array $params = []): void
    {
        $tpl = Database::fetch("SELECT * FROM wk_email_templates WHERE id=?", [$params['id']]);
        if (!$tpl) { Response::notFound(); return; }
        View::render('admin/email-templates/edit', [
            'pageTitle' => 'Edit: ' . $tpl['name'], 'template' => $tpl,
            'variables' => self::getVariables($tpl['slug']),
        ], 'admin/layouts/main');
    }

    public function update(Request $request, array $params = []): void
    {
        if (!Session::verifyCsrf($request->input('wk_csrf'))) {
            Session::flash('error', 'Session expired.'); Response::redirect(View::url('admin/email-templates/edit/'.$params['id'])); return;
        }
        Database::update('wk_email_templates', [
            'name' => $request->clean('name'), 'subject' => $request->clean('subject'),
            'body' => $request->input('body'), 'is_active' => $request->input('is_active') ? 1 : 0,
        ], 'id=?', [$params['id']]);
        Session::flash('success', 'Template saved!');
        Response::redirect(View::url('admin/email-templates'));
    }

    public function create(Request $request, array $params = []): void
    {
        View::render('admin/email-templates/create', ['pageTitle' => 'Create Email Template'], 'admin/layouts/main');
    }

    public function store(Request $request, array $params = []): void
    {
        if (!Session::verifyCsrf($request->input('wk_csrf'))) {
            Session::flash('error', 'Session expired.'); Response::redirect(View::url('admin/email-templates/create')); return;
        }
        $slug = trim(strtolower(preg_replace('/[^a-z0-9]+/', '-', $request->clean('name'))), '-');
        Database::insert('wk_email_templates', [
            'slug' => $slug, 'name' => $request->clean('name'),
            'subject' => $request->clean('subject'), 'body' => $request->input('body'), 'is_active' => 1,
        ]);
        Session::flash('success', 'Template created!');
        Response::redirect(View::url('admin/email-templates'));
    }

    public function delete(Request $request, array $params = []): void
    {
        if (!Session::verifyCsrf($request->input('wk_csrf'))) {
            Session::flash('error', 'Session expired.');
            Response::redirect(View::url('admin/email-templates'));
            return;
        }
        $tpl = Database::fetch("SELECT slug FROM wk_email_templates WHERE id=?", [$params['id']]);
        $system = ['order-confirmation','shipping-notification','welcome','password-reset','abandoned-cart'];
        if ($tpl && in_array($tpl['slug'], $system)) {
            Session::flash('error', 'System templates cannot be deleted.'); Response::redirect(View::url('admin/email-templates')); return;
        }
        Database::delete('wk_email_templates', 'id=?', [$params['id']]);
        Session::flash('success', 'Template deleted.');
        Response::redirect(View::url('admin/email-templates'));
    }

    /**
     * Send test email
     */
    public function testSend(Request $request, array $params = []): void
    {
        $tpl = Database::fetch("SELECT * FROM wk_email_templates WHERE id=?", [$params['id']]);
        if (!$tpl) { Response::json(['success' => false, 'message' => 'Template not found']); return; }

        $email = $request->input('test_email');
        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::json(['success' => false, 'message' => 'Enter a valid email address']); return;
        }

        $sample = self::getSampleData();
        $subject = str_replace(array_keys($sample), array_values($sample), $tpl['subject']);
        $body = str_replace(array_keys($sample), array_values($sample), $tpl['body']);

        $sent = \App\Services\EmailService::send($email, '[TEST] ' . $subject, $body);
        Response::json(['success' => $sent, 'message' => $sent ? 'Test email sent to ' . $email : 'Failed to send. Check SMTP settings.']);
    }

    /**
     * Preview in browser
     */
    public function preview(Request $request, array $params = []): void
    {
        $tpl = Database::fetch("SELECT * FROM wk_email_templates WHERE id=?", [$params['id']]);
        if (!$tpl) { Response::notFound(); return; }

        $storeName = Database::fetchValue("SELECT setting_value FROM wk_settings WHERE setting_group='general' AND setting_key='site_name'") ?: 'Whisker Store';
        $logoUrl = Database::fetchValue("SELECT setting_value FROM wk_settings WHERE setting_group='general' AND setting_key='logo_url'");
        $logoHtml = $logoUrl
            ? '<img src="'.htmlspecialchars($logoUrl).'" style="max-height:48px;max-width:200px" alt="">'
            : '<span style="font-size:22px;font-weight:900;color:#8b5cf6">🐱 '.htmlspecialchars($storeName).'</span>';

        $sample = self::getSampleData();
        $body = str_replace(array_keys($sample), array_values($sample), $tpl['body']);
        $subject = str_replace(array_keys($sample), array_values($sample), $tpl['subject']);

        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
        <body style="margin:0;padding:0;background:#f3f0eb;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif">
        <div style="background:#8b5cf6;color:#fff;padding:12px;text-align:center;font-size:13px;font-weight:700">
            PREVIEW — <strong>'.htmlspecialchars($subject).'</strong>
            <button onclick="window.close()" style="margin-left:16px;background:#fff;color:#8b5cf6;border:none;padding:4px 16px;border-radius:4px;font-weight:700;cursor:pointer">Close</button>
        </div>
        <div style="max-width:600px;margin:0 auto;padding:24px">
            <div style="text-align:center;padding:24px 0">'.$logoHtml.'</div>
            <div style="background:#fff;border-radius:12px;padding:36px;border:1px solid #e8e5df">'.$body.'</div>
            <div style="text-align:center;padding:24px 0;font-size:12px;color:#9ca3af">
                <p>'.htmlspecialchars($storeName).'</p>
                <p style="margin-top:6px;font-size:10px;color:#c4b5fd">Powered by Whisker</p>
            </div>
        </div></body></html>';
        exit;
    }

    private static function getSampleData(): array
    {
        $storeName = Database::fetchValue("SELECT setting_value FROM wk_settings WHERE setting_group='general' AND setting_key='site_name'") ?: 'Whisker Store';
        $logoUrl = Database::fetchValue("SELECT setting_value FROM wk_settings WHERE setting_group='general' AND setting_key='logo_url'");
        $cur = Database::fetchValue("SELECT setting_value FROM wk_settings WHERE setting_group='general' AND setting_key='currency_symbol'") ?: '₹';
        $logoHtml = $logoUrl ? '<img src="'.htmlspecialchars($logoUrl).'" style="max-height:40px" alt="">' : '🐱 '.htmlspecialchars($storeName);

        $sampleItems = '
        <table style="width:100%;font-size:14px;border-collapse:collapse">
            <tr><td style="padding:14px 0;border-bottom:1px solid #e8e5df"><div style="display:flex;align-items:center;gap:12px"><div style="width:56px;height:56px;background:#faf8f6;border-radius:8px;overflow:hidden;border:1px solid #e8e5df;display:flex;align-items:center;justify-content:center"><span style="font-size:24px">👕</span></div><div><div style="font-weight:700">High T Shirt</div><div style="font-size:12px;color:#8b5cf6;font-weight:700">Green / XL</div><div style="font-size:12px;color:#6b7280">Qty: 2 × '.$cur.'1,500.00</div></div></div></td><td style="text-align:right;font-family:monospace;font-weight:700;vertical-align:top;padding-top:18px">'.$cur.'3,000.00</td></tr>
            <tr><td style="padding:14px 0;border-bottom:1px solid #e8e5df"><div style="display:flex;align-items:center;gap:12px"><div style="width:56px;height:56px;background:#faf8f6;border-radius:8px;overflow:hidden;border:1px solid #e8e5df;display:flex;align-items:center;justify-content:center"><span style="font-size:24px">👟</span></div><div><div style="font-weight:700">Whisker Shoes</div><div style="font-size:12px;color:#8b5cf6;font-weight:700">Red / M</div><div style="font-size:12px;color:#6b7280">Qty: 1 × '.$cur.'1,500.00</div></div></div></td><td style="text-align:right;font-family:monospace;font-weight:700;vertical-align:top;padding-top:18px">'.$cur.'1,500.00</td></tr>
        </table>';

        return [
            '{{store_name}}' => $storeName, '{{store_url}}' => View::url(''), '{{logo}}' => $logoHtml,
            '{{customer_name}}' => 'John Doe', '{{customer_email}}' => 'john@example.com', '{{customer_phone}}' => '+91 98765 43210',
            '{{order_number}}' => 'WK-260306-ABC123', '{{order_total}}' => $cur.'4,590.00', '{{order_subtotal}}' => $cur.'4,500.00',
            '{{order_tax}}' => $cur.'810.00', '{{order_shipping}}' => $cur.'50.00', '{{order_discount}}' => $cur.'0.00', '{{order_date}}' => date('M j, Y g:i A'),
            '{{order_items_html}}' => $sampleItems,
            '{{shipping_address}}' => '<strong>John Doe</strong><br>123 Main St, Apt 4B<br>Mumbai, Maharashtra 400001<br>India',
            '{{billing_address}}' => '<strong>John Doe</strong><br>456 Business Rd<br>Delhi, DL 110001<br>India',
            '{{payment_method}}' => 'Razorpay', '{{payment_status}}' => 'Captured',
            '{{tracking_number}}' => 'DELHIVERY123456', '{{carrier_name}}' => 'Delhivery',
            '{{tracking_url}}' => 'https://www.delhivery.com/track/DELHIVERY123456', '{{reset_link}}' => '#',
            '{{invoice_number}}' => 'INV-20260306-ABC', '{{currency_symbol}}' => $cur,
            '{{cart_items_html}}' => $sampleItems,
            '{{cart_total}}' => $cur.'4,500.00', '{{cart_url}}' => View::url('cart'),
            '{{ticket_number}}' => 'TK-260306-ABC123', '{{ticket_subject}}' => 'Order not received',
            '{{ticket_status}}' => 'In Progress', '{{reply_message}}' => 'We are looking into this and will update you within 24 hours.',
            '{{agent_name}}' => 'Support Team', '{{ticket_url}}' => View::url('account/tickets/1'),
        ];
    }

    public static function getVariables(string $slug): array
    {
        $common = [
            '{{store_name}}'=>'Store name', '{{store_url}}'=>'Store URL', '{{logo}}'=>'Store logo (rendered)',
            '{{customer_name}}'=>'Full name', '{{customer_email}}'=>'Email', '{{customer_phone}}'=>'Phone',
            '{{currency_symbol}}'=>'Currency symbol',
        ];
        return match($slug) {
            'order-confirmation' => array_merge($common, [
                '{{order_number}}'=>'Order number', '{{order_date}}'=>'Date & time',
                '{{order_items_html}}'=>'Items table with images, variants, prices',
                '{{order_subtotal}}'=>'Subtotal', '{{order_tax}}'=>'Tax', '{{order_shipping}}'=>'Shipping',
                '{{order_discount}}'=>'Discount', '{{order_total}}'=>'Grand total',
                '{{shipping_address}}'=>'Shipping address block', '{{billing_address}}'=>'Billing address block',
                '{{payment_method}}'=>'Payment gateway', '{{payment_status}}'=>'Payment status', '{{invoice_number}}'=>'Invoice number',
            ]),
            'shipping-notification' => array_merge($common, [
                '{{order_number}}'=>'Order number', '{{carrier_name}}'=>'Carrier', '{{tracking_number}}'=>'Tracking #', '{{tracking_url}}'=>'Track URL',
            ]),
            'abandoned-cart' => array_merge($common, [
                '{{cart_items_html}}'=>'Cart items with images & variants', '{{cart_total}}'=>'Cart total', '{{cart_url}}'=>'Link to cart page',
            ]),
            'ticket-created' => array_merge($common, ['{{ticket_number}}'=>'Ticket #', '{{ticket_subject}}'=>'Subject']),
            'ticket-reply' => array_merge($common, ['{{ticket_number}}'=>'Ticket #', '{{ticket_subject}}'=>'Subject', '{{reply_message}}'=>'Reply text', '{{agent_name}}'=>'Agent name', '{{ticket_url}}'=>'Ticket link']),
            'ticket-status-update' => array_merge($common, ['{{ticket_number}}'=>'Ticket #', '{{ticket_subject}}'=>'Subject', '{{ticket_status}}'=>'New status']),
            'order-status-update' => array_merge($common, ['{{order_number}}'=>'Order number', '{{order_status}}'=>'New status', '{{status_emoji}}'=>'Status emoji', '{{order_total}}'=>'Order total', '{{order_date}}'=>'Order date']),
            'password-reset' => array_merge($common, ['{{reset_link}}'=>'Reset URL']),
            default => $common,
        };
    }

    private static function seedDefaults(): void
    {
        $d = [];
        // Order Confirmation — rich with items, addresses, price breakdown
        $d[] = ['slug'=>'order-confirmation','name'=>'Order Confirmation','subject'=>'🎉 Order Confirmed — {{order_number}}',
            'body'=>'<div style="text-align:center;margin-bottom:28px"><div style="font-size:52px;margin-bottom:8px">🎉</div><h1 style="font-size:28px;font-weight:900;margin:0 0 6px;color:#1e1b2e">Order Confirmed!</h1><p style="color:#6b7280;margin:0;font-size:15px">Thank you for your purchase, {{customer_name}}</p></div><div style="background:#faf8f6;border-radius:10px;padding:20px;margin-bottom:24px"><table style="width:100%;font-size:14px"><tr><td style="color:#6b7280;padding:6px 0">Order Number</td><td style="text-align:right;font-weight:800;font-family:monospace;color:#8b5cf6;font-size:16px">{{order_number}}</td></tr><tr><td style="color:#6b7280;padding:6px 0">Date</td><td style="text-align:right;font-weight:700">{{order_date}}</td></tr><tr><td style="color:#6b7280;padding:6px 0">Payment</td><td style="text-align:right;font-weight:700">{{payment_method}}</td></tr></table></div><h2 style="font-size:16px;font-weight:800;margin:0 0 8px">🛍️ Items Ordered</h2>{{order_items_html}}<div style="background:#faf8f6;border-radius:10px;padding:20px;margin-top:20px"><table style="width:100%;font-size:14px"><tr><td style="color:#6b7280;padding:5px 0">Subtotal</td><td style="text-align:right;font-weight:700;font-family:monospace">{{order_subtotal}}</td></tr><tr><td style="color:#6b7280;padding:5px 0">Tax</td><td style="text-align:right;font-weight:700;font-family:monospace">{{order_tax}}</td></tr><tr><td style="color:#6b7280;padding:5px 0">Shipping</td><td style="text-align:right;font-weight:700;font-family:monospace">{{order_shipping}}</td></tr><tr><td style="padding:14px 0 0;border-top:2px solid #1e1b2e;font-size:20px;font-weight:900">Total</td><td style="text-align:right;padding:14px 0 0;border-top:2px solid #1e1b2e;font-size:20px;font-weight:900;font-family:monospace">{{order_total}}</td></tr></table></div><div style="display:flex;gap:24px;margin-top:28px;padding-top:24px;border-top:1px solid #e8e5df"><div style="flex:1"><div style="font-size:11px;font-weight:800;text-transform:uppercase;color:#6b7280;margin-bottom:6px">🚚 Shipping Address</div><div style="font-size:14px;line-height:1.7">{{shipping_address}}</div></div><div style="flex:1"><div style="font-size:11px;font-weight:800;text-transform:uppercase;color:#6b7280;margin-bottom:6px">🧾 Billing Address</div><div style="font-size:14px;line-height:1.7">{{billing_address}}</div></div></div><div style="text-align:center;margin-top:32px"><a href="{{store_url}}" style="display:inline-block;background:linear-gradient(135deg,#8b5cf6,#ec4899);color:#fff;padding:16px 36px;border-radius:10px;text-decoration:none;font-weight:800;font-size:16px">View Your Order →</a></div>'];

        // Shipping
        $d[] = ['slug'=>'shipping-notification','name'=>'Shipping Notification','subject'=>'📦 Your Order Has Shipped — {{order_number}}',
            'body'=>'<div style="text-align:center;margin-bottom:28px"><div style="font-size:52px;margin-bottom:8px">📦</div><h1 style="font-size:28px;font-weight:900;margin:0 0 6px">Your Order Has Shipped!</h1><p style="color:#6b7280;margin:0;font-size:15px">Hi {{customer_name}}, great news!</p></div><div style="background:#faf8f6;border-radius:10px;padding:24px;margin-bottom:24px"><table style="width:100%;font-size:15px"><tr><td style="color:#6b7280;padding:8px 0">Order</td><td style="text-align:right;font-weight:800;color:#8b5cf6;font-family:monospace">{{order_number}}</td></tr><tr><td style="color:#6b7280;padding:8px 0">Carrier</td><td style="text-align:right;font-weight:800">{{carrier_name}}</td></tr><tr><td style="color:#6b7280;padding:8px 0">Tracking Number</td><td style="text-align:right;font-weight:800;font-family:monospace">{{tracking_number}}</td></tr></table></div><div style="text-align:center;margin-top:28px"><a href="{{tracking_url}}" style="display:inline-block;background:linear-gradient(135deg,#8b5cf6,#ec4899);color:#fff;padding:16px 36px;border-radius:10px;text-decoration:none;font-weight:800;font-size:16px">Track Your Package →</a></div><p style="text-align:center;margin-top:16px;font-size:13px;color:#9ca3af">Your package is on its way! You\'ll receive delivery updates via your carrier.</p>'];

        // Welcome
        $d[] = ['slug'=>'welcome','name'=>'Welcome Email','subject'=>'Welcome to {{store_name}}! 🐱',
            'body'=>'<div style="text-align:center;margin-bottom:28px"><div style="font-size:52px;margin-bottom:8px">🐱</div><h1 style="font-size:28px;font-weight:900;margin:0 0 6px">Welcome, {{customer_name}}!</h1><p style="color:#6b7280;margin:0;font-size:15px">Your account at {{store_name}} is ready. Start exploring our amazing collection!</p></div><div style="background:#faf8f6;border-radius:10px;padding:24px;margin:24px 0;text-align:center"><div style="font-size:14px;color:#6b7280;margin-bottom:8px">Your account email</div><div style="font-size:16px;font-weight:800;color:#8b5cf6">{{customer_email}}</div></div><div style="text-align:center;margin-top:28px"><a href="{{store_url}}" style="display:inline-block;background:linear-gradient(135deg,#8b5cf6,#ec4899);color:#fff;padding:16px 36px;border-radius:10px;text-decoration:none;font-weight:800;font-size:16px">Start Shopping →</a></div>'];

        // Password Reset
        $d[] = ['slug'=>'password-reset','name'=>'Password Reset','subject'=>'🔑 Reset Your Password — {{store_name}}',
            'body'=>'<div style="text-align:center;margin-bottom:28px"><div style="font-size:52px;margin-bottom:8px">🔑</div><h1 style="font-size:28px;font-weight:900;margin:0 0 6px">Reset Your Password</h1><p style="color:#6b7280;margin:0;font-size:15px">Hi {{customer_name}}, we received a request to reset your password.</p></div><div style="text-align:center;margin:32px 0"><a href="{{reset_link}}" style="display:inline-block;background:linear-gradient(135deg,#8b5cf6,#ec4899);color:#fff;padding:16px 36px;border-radius:10px;text-decoration:none;font-weight:800;font-size:16px">Reset Password →</a></div><div style="background:#faf8f6;border-radius:10px;padding:16px;text-align:center;font-size:13px;color:#6b7280"><p style="margin:0">This link expires in <strong>1 hour</strong>. If you didn\'t request this, you can safely ignore this email.</p></div>'];

        // Abandoned Cart
        $d[] = ['slug'=>'abandoned-cart','name'=>'Abandoned Cart Reminder','subject'=>'🛒 You left something behind, {{customer_name}}!',
            'body'=>'<div style="text-align:center;margin-bottom:28px"><div style="font-size:52px;margin-bottom:8px">🛒</div><h1 style="font-size:28px;font-weight:900;margin:0 0 6px">Forgot Something?</h1><p style="color:#6b7280;margin:0;font-size:15px">Hi {{customer_name}}, you left some items in your cart at {{store_name}}.</p></div><h2 style="font-size:16px;font-weight:800;margin:0 0 8px">Your Cart Items</h2>{{cart_items_html}}<div style="background:#faf8f6;border-radius:10px;padding:16px;margin-top:16px;display:flex;justify-content:space-between;align-items:center"><span style="font-size:15px;color:#6b7280">Cart Total</span><span style="font-size:22px;font-weight:900;font-family:monospace">{{cart_total}}</span></div><div style="text-align:center;margin-top:32px"><a href="{{cart_url}}" style="display:inline-block;background:linear-gradient(135deg,#8b5cf6,#ec4899);color:#fff;padding:16px 36px;border-radius:10px;text-decoration:none;font-weight:800;font-size:16px">Complete Your Order →</a></div><p style="text-align:center;margin-top:16px;font-size:13px;color:#9ca3af">Your items are waiting! Don\'t miss out before they\'re gone.</p>'];

        // Ticket Created
        $d[] = ['slug'=>'ticket-created','name'=>'Ticket Created Confirmation','subject'=>'🎫 Ticket {{ticket_number}} — We received your request',
            'body'=>'<div style="text-align:center;margin-bottom:28px"><div style="font-size:52px;margin-bottom:8px">🎫</div><h1 style="font-size:28px;font-weight:900;margin:0 0 6px">Ticket Created!</h1><p style="color:#6b7280;margin:0;font-size:15px">Hi {{customer_name}}, we\'ve received your support request.</p></div><div style="background:#faf8f6;border-radius:10px;padding:20px;margin-bottom:24px"><table style="width:100%;font-size:14px"><tr><td style="padding:6px 0;color:#6b7280">Ticket</td><td style="text-align:right;font-weight:800;font-family:monospace;color:#8b5cf6">{{ticket_number}}</td></tr><tr><td style="padding:6px 0;color:#6b7280">Subject</td><td style="text-align:right;font-weight:700">{{ticket_subject}}</td></tr><tr><td style="padding:6px 0;color:#6b7280">Status</td><td style="text-align:right;font-weight:700">Open</td></tr></table></div><p style="font-size:14px;color:#6b7280;text-align:center">Our team will review your request and get back to you as soon as possible. You\'ll receive email updates on any replies.</p><div style="text-align:center;margin-top:24px"><a href="{{store_url}}" style="display:inline-block;background:linear-gradient(135deg,#8b5cf6,#ec4899);color:#fff;padding:14px 32px;border-radius:10px;text-decoration:none;font-weight:800">Visit Store →</a></div>'];

        // Ticket Reply
        $d[] = ['slug'=>'ticket-reply','name'=>'Ticket Reply Notification','subject'=>'💬 Reply on Ticket {{ticket_number}} — {{ticket_subject}}',
            'body'=>'<div style="text-align:center;margin-bottom:28px"><div style="font-size:52px;margin-bottom:8px">💬</div><h1 style="font-size:28px;font-weight:900;margin:0 0 6px">New Reply on Your Ticket</h1><p style="color:#6b7280;margin:0">Ticket <strong style="color:#8b5cf6">{{ticket_number}}</strong> — {{ticket_subject}}</p></div><div style="background:#faf8f6;border-radius:10px;padding:20px;margin-bottom:20px"><div style="font-size:12px;color:#6b7280;font-weight:700;margin-bottom:8px">{{agent_name}} replied:</div><div style="font-size:14px;line-height:1.8">{{reply_message}}</div></div><div style="text-align:center"><a href="{{ticket_url}}" style="display:inline-block;background:linear-gradient(135deg,#8b5cf6,#ec4899);color:#fff;padding:14px 32px;border-radius:10px;text-decoration:none;font-weight:800">View & Reply →</a></div>'];

        // Ticket Status Update
        $d[] = ['slug'=>'ticket-status-update','name'=>'Ticket Status Update','subject'=>'🎫 Ticket {{ticket_number}} — Status: {{ticket_status}}',
            'body'=>'<div style="text-align:center;margin-bottom:28px"><div style="font-size:52px;margin-bottom:8px">📋</div><h1 style="font-size:28px;font-weight:900;margin:0 0 6px">Ticket Status Updated</h1><p style="color:#6b7280;margin:0">Your ticket <strong style="color:#8b5cf6">{{ticket_number}}</strong> has been updated.</p></div><div style="background:#faf8f6;border-radius:10px;padding:24px;text-align:center;margin-bottom:24px"><div style="font-size:14px;color:#6b7280;margin-bottom:6px">{{ticket_subject}}</div><div style="font-size:22px;font-weight:900;color:#8b5cf6">{{ticket_status}}</div></div><div style="text-align:center"><a href="{{store_url}}" style="display:inline-block;background:linear-gradient(135deg,#8b5cf6,#ec4899);color:#fff;padding:14px 32px;border-radius:10px;text-decoration:none;font-weight:800">Visit Store →</a></div>'];

        // Order Status Update (for all status changes: processing, paid, delivered, cancelled, refunded)
        $d[] = ['slug'=>'order-status-update','name'=>'Order Status Update','subject'=>'{{status_emoji}} Order {{order_number}} — {{order_status}}',
            'body'=>'<div style="text-align:center;margin-bottom:28px"><div style="font-size:52px;margin-bottom:8px">{{status_emoji}}</div><h1 style="font-size:28px;font-weight:900;margin:0 0 6px">Order Update</h1><p style="color:#6b7280;margin:0">Hi {{customer_name}}, your order status has changed.</p></div><div style="background:#faf8f6;border-radius:10px;padding:24px;margin-bottom:24px"><table style="width:100%;font-size:14px"><tr><td style="padding:6px 0;color:#6b7280">Order</td><td style="text-align:right;font-weight:800;font-family:monospace;color:#8b5cf6">{{order_number}}</td></tr><tr><td style="padding:6px 0;color:#6b7280">Date</td><td style="text-align:right;font-weight:700">{{order_date}}</td></tr><tr><td style="padding:6px 0;color:#6b7280">Total</td><td style="text-align:right;font-weight:800;font-family:monospace">{{order_total}}</td></tr><tr><td style="padding:12px 0 0;border-top:2px solid #1e1b2e;font-size:16px;font-weight:800">Status</td><td style="padding:12px 0 0;border-top:2px solid #1e1b2e;text-align:right;font-size:20px;font-weight:900;color:#8b5cf6">{{order_status}}</td></tr></table></div><div style="text-align:center"><a href="{{store_url}}" style="display:inline-block;background:linear-gradient(135deg,#8b5cf6,#ec4899);color:#fff;padding:14px 32px;border-radius:10px;text-decoration:none;font-weight:800">View Your Order →</a></div>'];

        foreach ($d as $t) {
            try { Database::insert('wk_email_templates', array_merge($t, ['is_active' => 1])); } catch (\Exception $e) {}
        }
    }
}

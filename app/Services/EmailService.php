<?php
namespace App\Services;

use Core\Database;
use Core\View;

class EmailService
{
    /**
     * Send email using a stored template with variable replacement
     */
    public static function sendFromTemplate(string $slug, string $to, array $vars = []): bool
    {
        try {
            $tpl = Database::fetch("SELECT * FROM wk_email_templates WHERE slug=? AND is_active=1", [$slug]);
        } catch (\Exception $e) {
            $tpl = null;
        }

        // No template row: fall back to the built-in wording for this slug.
        // Sending $vars['body'] ?? '' here delivered an empty email with the
        // subject "Notification" whenever a template had not been created.
        if (!$tpl) {
            $fallback = self::builtInTemplate($slug, $vars);
            if ($fallback) {
                return self::send($to, $fallback['subject'], $fallback['body']);
            }
            return self::send($to, $vars['subject'] ?? 'Notification', $vars['body'] ?? '');
        }

        $subject = self::replaceVars($tpl['subject'], $vars);
        $body = self::replaceVars($tpl['body'], $vars);

        return self::send($to, $subject, $body);
    }

    /**
     * Send an email using explicit SMTP credentials, without reading from or
     * writing to the wk_settings table. Used by the admin SMTP test flow so
     * that a failed test (e.g. wrong password typed) does NOT poison the live
     * email configuration.
     *
     * @param array $smtpConfig Required keys: host, port, user, pass.
     *                          Optional: from_email, from_name (defaults to
     *                          the values in wk_settings).
     */
    public static function sendWithSmtp(string $to, string $subject, string $htmlBody, array $smtpConfig): bool
    {
        $host = (string)($smtpConfig['host'] ?? '');
        if ($host === '') return false;
        $port = (int)($smtpConfig['port'] ?? 587);
        $user = (string)($smtpConfig['user'] ?? '');
        $pass = (string)($smtpConfig['pass'] ?? '');

        $fromEmail = isset($smtpConfig['from_email']) && $smtpConfig['from_email'] !== ''
            ? (string)$smtpConfig['from_email']
            : (Database::fetchValue("SELECT setting_value FROM wk_settings WHERE setting_group='email' AND setting_key='from_email'") ?: 'noreply@example.com');
        $fromName  = isset($smtpConfig['from_name']) && $smtpConfig['from_name'] !== ''
            ? (string)$smtpConfig['from_name']
            : (Database::fetchValue("SELECT setting_value FROM wk_settings WHERE setting_group='email' AND setting_key='from_name'") ?: 'Whisker Store');

        // Header-injection guard — same rules as send()
        $strip = static fn($s) => str_replace(["\r", "\n", "%0a", "%0d"], '', (string)$s);
        $fromEmail = $strip($fromEmail);
        $fromName  = $strip($fromName);
        $to        = $strip($to);
        $subject   = self::headerSubject($subject);

        $html = self::wrapTemplate($htmlBody);
        return self::sendSmtpRaw($to, $subject, $html, $fromEmail, $fromName, $host, $port, $user, $pass);
    }

    /**
     * Prepare a subject line for a mail header.
     *
     * Template bodies are HTML, so an author naturally writes entities like
     * &mdash; in the subject too — but a subject is plain text and would show
     * those characters literally. Entities are decoded, then anything
     * non-ASCII is MIME encoded so it survives every mail client.
     */
    private static function headerSubject(string $subject): string
    {
        $subject = html_entity_decode($subject, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $subject = str_replace(["\r", "\n", '%0a', '%0d'], '', $subject);
        $subject = trim($subject);

        if (preg_match('/[\x80-\xFF]/', $subject)) {
            return '=?UTF-8?B?' . base64_encode($subject) . '?=';
        }
        return $subject;
    }

    /**
     * Send raw email
     */
    public static function send(string $to, string $subject, string $htmlBody, ?string $replyTo = null): bool
    {
        $fromEmail = Database::fetchValue("SELECT setting_value FROM wk_settings WHERE setting_group='email' AND setting_key='from_email'") ?: 'noreply@example.com';
        $fromName  = Database::fetchValue("SELECT setting_value FROM wk_settings WHERE setting_group='email' AND setting_key='from_name'") ?: 'Whisker Store';
        $smtpHost  = Database::fetchValue("SELECT setting_value FROM wk_settings WHERE setting_group='email' AND setting_key='smtp_host'");

        // Prevent email header injection — strip newlines from all header values
        $fromEmail = str_replace(["\r", "\n", "%0a", "%0d"], '', $fromEmail);
        $fromName  = str_replace(["\r", "\n", "%0a", "%0d"], '', $fromName);
        $to        = str_replace(["\r", "\n", "%0a", "%0d"], '', $to);
        $subject   = self::headerSubject($subject);
        if ($replyTo) $replyTo = str_replace(["\r", "\n", "%0a", "%0d"], '', $replyTo);

        $headers  = "MIME-Version: 1.0\r\nContent-type: text/html; charset=UTF-8\r\nFrom: {$fromName} <{$fromEmail}>\r\n";
        if ($replyTo) $headers .= "Reply-To: {$replyTo}\r\n";

        $html = self::wrapTemplate($htmlBody);

        if ($smtpHost) return self::sendSmtp($to, $subject, $html, $fromEmail, $fromName);
        return @mail($to, $subject, $html, $headers);
    }

    /**
     * Send order confirmation with full details
     */
    public static function sendOrderConfirmation(array $order, array $items): bool
    {
        $currency = self::currencySymbol();
        $storeName = self::storeName();
        $fmt = fn($v) => $currency . number_format((float)$v, 2);
        $billing = json_decode($order['billing_address'] ?? '{}', true) ?: [];
        $shipping = json_decode($order['shipping_address'] ?? '{}', true) ?: [];

        // Build items HTML with images and variants
        $itemsHtml = '';
        foreach ($items as $item) {
            $img = '';
            try {
                $imgPath = Database::fetchValue("SELECT image_path FROM wk_product_images WHERE product_id=? AND is_primary=1 LIMIT 1", [$item['product_id']]);
                if ($imgPath) $img = '<img src="' . View::url('storage/uploads/products/' . $imgPath) . '" style="width:56px;height:56px;object-fit:cover;border-radius:6px" alt="">';
            } catch (\Exception $e) {}

            $variantLine = '';
            if (!empty($item['variant_label'])) {
                $variantLine = '<div style="font-size:12px;color:#8b5cf6;font-weight:700">' . htmlspecialchars($item['variant_label']) . '</div>';
            }

            $itemsHtml .= '<tr>
                <td style="padding:12px 0;border-bottom:1px solid #e8e5df">
                    <div style="display:flex;align-items:center;gap:12px">
                        ' . ($img ?: '<div style="width:56px;height:56px;background:#faf8f6;border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:20px">📦</div>') . '
                        <div>
                            <div style="font-weight:700">' . htmlspecialchars($item['product_name']) . '</div>
                            ' . $variantLine . '
                            <div style="font-size:12px;color:#6b7280">Qty: ' . $item['quantity'] . ' × ' . $fmt($item['unit_price']) . '</div>
                        </div>
                    </div>
                </td>
                <td style="padding:12px 0;border-bottom:1px solid #e8e5df;text-align:right;font-family:monospace;font-weight:700;vertical-align:top;padding-top:16px">' . $fmt($item['total_price']) . '</td>
            </tr>';
        }

        // Address blocks. Pickup orders snapshot the locker into the shipping
        // address (line1 = locker name, line2 = street) with a 'pickup' flag —
        // relabel the block and include line2/opening hours so the customer
        // knows where to collect.
        $addrBlock = function ($a, $label) {
            if (!empty($a['pickup'])) $label = '📦 Pickup Point';
            return '<div style="flex:1">
            <div style="font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:1px;color:#6b7280;margin-bottom:6px">' . $label . '</div>
            <div style="font-size:14px;line-height:1.7">
                <strong>' . htmlspecialchars($a['name'] ?? '') . '</strong><br>
                ' . htmlspecialchars($a['line1'] ?? '') . '<br>
                ' . (!empty($a['line2']) ? htmlspecialchars($a['line2']) . '<br>' : '') . '
                ' . htmlspecialchars(($a['city'] ?? '') . ', ' . ($a['state'] ?? '') . ' ' . ($a['zip'] ?? '')) . '
                ' . (!empty($a['pickup']) && !empty($a['opening_hours']) ? '<br><span style="color:#6b7280;font-size:13px">🕐 ' . htmlspecialchars($a['opening_hours']) . '</span>' : '') . '
            </div>
        </div>';
        };

        $body = '
        <div style="text-align:center;margin-bottom:28px">
            <div style="font-size:48px;margin-bottom:8px">🎉</div>
            <h1 style="font-size:26px;font-weight:900;margin:0 0 6px">Order Confirmed!</h1>
            <p style="color:#6b7280;margin:0;font-size:15px">Thank you for shopping with ' . htmlspecialchars($storeName) . '</p>
        </div>

        <div style="background:#faf8f6;border-radius:10px;padding:20px;margin-bottom:24px">
            <table style="width:100%;font-size:14px">
                <tr><td style="color:#6b7280;padding:5px 0">Order Number</td><td style="text-align:right;font-weight:800;font-family:monospace;color:#8b5cf6;font-size:16px">' . htmlspecialchars($order['order_number']) . '</td></tr>
                <tr><td style="color:#6b7280;padding:5px 0">Date</td><td style="text-align:right;font-weight:700">' . date('M j, Y g:i A', strtotime($order['created_at'])) . '</td></tr>
                <tr><td style="color:#6b7280;padding:5px 0">Payment</td><td style="text-align:right;font-weight:700">' . ucfirst($order['payment_gateway'] ?? 'Pending') . '</td></tr>
            </table>
        </div>

        <h2 style="font-size:16px;font-weight:800;margin:0 0 12px">Items Ordered</h2>
        <table style="width:100%;font-size:14px;border-collapse:collapse">' . $itemsHtml . '</table>

        <div style="margin-top:20px;font-size:14px">
            <div style="display:flex;justify-content:space-between;padding:5px 0"><span style="color:#6b7280">Subtotal</span><span style="font-weight:700;font-family:monospace">' . $fmt($order['subtotal']) . '</span></div>
            <div style="display:flex;justify-content:space-between;padding:5px 0"><span style="color:#6b7280">Tax</span><span style="font-weight:700;font-family:monospace">' . $fmt($order['tax_amount']) . '</span></div>
            <div style="display:flex;justify-content:space-between;padding:5px 0"><span style="color:#6b7280">Shipping</span><span style="font-weight:700;font-family:monospace">' . $fmt($order['shipping_amount']) . '</span></div>
            ' . ($order['discount_amount'] > 0 ? '<div style="display:flex;justify-content:space-between;padding:5px 0"><span style="color:#10b981">Discount</span><span style="font-weight:700;color:#10b981;font-family:monospace">-' . $fmt($order['discount_amount']) . '</span></div>' : '') . '
            <div style="display:flex;justify-content:space-between;padding:14px 0 0;margin-top:10px;border-top:2px solid #1e1b2e;font-size:22px"><span style="font-weight:900">Total</span><span style="font-weight:900;font-family:monospace">' . $fmt($order['total']) . '</span></div>
        </div>

        <div style="display:flex;gap:24px;margin-top:28px;padding-top:24px;border-top:1px solid #e8e5df">
            ' . $addrBlock($shipping, '🚚 Shipping Address') . '
            ' . $addrBlock($billing, '🧾 Billing Address') . '
        </div>';

        return self::send($order['customer_email'], "Order Confirmed — {$order['order_number']}", $body);
    }

    /**
     * Wording used when a store has not customised the template for a slug.
     *
     * @return array{subject:string, body:string}|null
     */
    private static function builtInTemplate(string $slug, array $vars): ?array
    {
        $v = fn(string $key, string $default = '') => $vars['{{' . $key . '}}'] ?? $default;
        $store = $v('store_name', self::storeName());

        if ($slug === 'shipping-notification') {
            $tracking = $v('tracking_number');
            $url      = $v('tracking_url');
            $body = '
            <div style="text-align:center;margin-bottom:28px">
                <div style="font-size:48px;margin-bottom:8px">📦</div>
                <h1 style="font-size:26px;font-weight:900;margin:0 0 6px">Your order is on its way</h1>
                <p style="color:#6b7280;margin:0;font-size:15px">Order ' . htmlspecialchars($v('order_number')) . '</p>
            </div>
            <div style="background:#faf8f6;border-radius:10px;padding:20px;margin-bottom:24px">
                <table style="width:100%;font-size:14px">
                    <tr><td style="color:#6b7280;padding:5px 0">Carrier</td><td style="text-align:right;font-weight:700">' . htmlspecialchars($v('carrier_name')) . '</td></tr>
                    ' . ($tracking !== '' ? '<tr><td style="color:#6b7280;padding:5px 0">Tracking number</td><td style="text-align:right;font-weight:800;font-family:monospace">' . htmlspecialchars($tracking) . '</td></tr>' : '') . '
                </table>
            </div>
            ' . ($url !== '' ? '<div style="text-align:center;margin-bottom:24px"><a href="' . htmlspecialchars($url) . '" style="display:inline-block;background:#8b5cf6;color:#fff;text-decoration:none;font-weight:800;padding:13px 28px;border-radius:8px">Track your parcel</a></div>' : '') . '
            <p style="color:#6b7280;font-size:14px;line-height:1.7;margin:0">
                Tracking can take a few hours to start updating after the carrier collects the parcel.
            </p>';
            return ['subject' => 'Your order has shipped — ' . $v('order_number'), 'body' => $body];
        }

        if ($slug === 'refund-notification') {
            $body = '
            <div style="text-align:center;margin-bottom:28px">
                <div style="font-size:48px;margin-bottom:8px">&#128184;</div>
                <h1 style="font-size:26px;font-weight:900;margin:0 0 6px">Your refund has been issued</h1>
                <p style="color:#6b7280;margin:0;font-size:15px">Order ' . htmlspecialchars($v('order_number')) . '</p>
            </div>
            <div style="background:#faf8f6;border-radius:10px;padding:20px;margin-bottom:24px">
                <table style="width:100%;font-size:14px">
                    <tr><td style="color:#6b7280;padding:6px 0">Refund amount</td><td style="text-align:right;font-weight:900;font-family:monospace;font-size:20px">' . htmlspecialchars($v('refund_amount')) . '</td></tr>
                    <tr><td style="color:#6b7280;padding:6px 0">Refund ID</td><td style="text-align:right;font-weight:800;font-family:monospace;color:#8b5cf6">' . htmlspecialchars($v('refund_id')) . '</td></tr>
                    <tr><td style="color:#6b7280;padding:6px 0">Date</td><td style="text-align:right;font-weight:700">' . htmlspecialchars($v('refund_date')) . '</td></tr>
                    <tr><td style="color:#6b7280;padding:6px 0">Time</td><td style="text-align:right;font-weight:700">' . htmlspecialchars($v('refund_time')) . '</td></tr>
                    <tr><td style="color:#6b7280;padding:6px 0">Refunded to</td><td style="text-align:right;font-weight:700">' . htmlspecialchars($v('refund_method')) . '</td></tr>
                </table>
            </div>
            <p style="font-size:14px;line-height:1.75;margin:0 0 18px">
                It usually appears on your statement within 5 to 10 working days, depending on your bank.
                Quote refund ID <strong style="font-family:monospace">' . htmlspecialchars($v('refund_id')) . '</strong> if you need to ask us about it.
            </p>
            <p style="font-size:14px;line-height:1.75;color:#6b7280;margin:0">Thank you for shopping with ' . htmlspecialchars($store) . '.</p>';
            return ['subject' => 'Refund issued — ' . $v('order_number'), 'body' => $body];
        }

        if ($slug === 'welcome') {
            $body = '
            <div style="text-align:center;margin-bottom:28px">
                <div style="font-size:48px;margin-bottom:8px">👋</div>
                <h1 style="font-size:26px;font-weight:900;margin:0 0 6px">Welcome to ' . htmlspecialchars($store) . '</h1>
            </div>
            <p style="font-size:15px;line-height:1.7;margin:0 0 20px">
                Hello ' . htmlspecialchars($v('customer_name', 'there')) . ', your account is ready. You can now check out faster
                and follow your orders from one place.
            </p>
            <div style="text-align:center">
                <a href="' . htmlspecialchars($v('store_url', View::url(''))) . '" style="display:inline-block;background:#8b5cf6;color:#fff;text-decoration:none;font-weight:800;padding:13px 28px;border-radius:8px">Start shopping</a>
            </div>';
            return ['subject' => 'Welcome to ' . $store, 'body' => $body];
        }

        return null;
    }

    /**
     * Refund confirmation.
     *
     * Sent whenever money goes back to the customer, so they have the
     * reference, the amount and the date in writing. Banks can take several
     * days to show the credit, and this is what they quote when they ask.
     */
    public static function sendRefundConfirmation(array $order, array $refund): bool
    {
        $email = trim((string) ($order['customer_email'] ?? ''));
        if ($email === '') return false;

        $currencyCode = $order['currency'] ?: (Database::setting('general', 'currency') ?: 'INR');
        $issuedAt = !empty($refund['created_at']) ? strtotime($refund['created_at']) : time();

        $method = !empty($refund['is_manual'])
            ? 'Sent by ' . self::storeName() . ' directly'
            : 'Your original payment method'
              . (!empty($refund['gateway_code']) ? ' via ' . ucfirst((string) $refund['gateway_code']) : '');

        $vars = [
            '{{store_name}}'    => self::storeName(),
            '{{store_url}}'     => View::url(''),
            '{{customer_name}}' => self::orderCustomerName($order),
            '{{customer_email}}'=> $email,
            '{{order_number}}'  => $order['order_number'],
            '{{refund_amount}}' => CurrencyService::format((float) $refund['amount'], $currencyCode),
            '{{refund_id}}'     => $refund['refund_ref'],
            '{{refund_date}}'   => date('M j, Y', $issuedAt),
            '{{refund_time}}'   => date('g:i A', $issuedAt),
            '{{refund_method}}' => $method,
        ];

        return self::sendFromTemplate('refund-notification', $email, $vars);
    }

    /**
     * Shipping notification
     */
    public static function sendShippingNotification(array $order, string $carrier, string $trackingNumber, string $trackingUrl = ''): bool
    {
        $vars = [
            '{{customer_name}}' => self::orderCustomerName($order),
            '{{order_number}}' => $order['order_number'],
            '{{carrier_name}}' => $carrier,
            '{{tracking_number}}' => $trackingNumber,
            '{{tracking_url}}' => $trackingUrl,
            '{{store_name}}' => self::storeName(),
            '{{store_url}}' => View::url(''),
        ];
        return self::sendFromTemplate('shipping-notification', $order['customer_email'], $vars);
    }

    /**
     * Welcome email
     */
    public static function sendWelcome(string $email, string $name): bool
    {
        $vars = [
            '{{customer_name}}' => $name,
            '{{customer_email}}' => $email,
            '{{store_name}}' => self::storeName(),
            '{{store_url}}' => View::url(''),
        ];
        return self::sendFromTemplate('welcome', $email, $vars);
    }

    /**
     * Wrap content in branded email template with logo
     */
    private static function wrapTemplate(string $content): string
    {
        $storeName = self::storeName();
        $logoUrl = Database::fetchValue("SELECT setting_value FROM wk_settings WHERE setting_group='general' AND setting_key='logo_url'");

        // Validate the logo URL scheme. javascript: in <img src> won't
        // execute in modern browsers but webmail clients vary; safer to
        // reject anything that isn't http/https or a relative path.
        $safeLogoUrl = $logoUrl && \App\Services\HtmlSanitizer::isSafeUrl((string)$logoUrl, true)
            ? (string)$logoUrl
            : '';
        $logoHtml = $safeLogoUrl
            ? '<img src="' . htmlspecialchars($safeLogoUrl) . '" style="max-height:48px;max-width:200px" alt="' . htmlspecialchars($storeName) . '">'
            : '<span style="font-size:22px;font-weight:900;background:linear-gradient(135deg,#8b5cf6,#ec4899);-webkit-background-clip:text;-webkit-text-fill-color:transparent">🐱 ' . htmlspecialchars($storeName) . '</span>';

        return '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
        <body style="margin:0;padding:0;background:#f3f0eb;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif">
        <div style="max-width:600px;margin:0 auto;padding:24px">
            <div style="text-align:center;padding:24px 0">' . $logoHtml . '</div>
            <div style="background:#ffffff;border-radius:12px;padding:36px;border:1px solid #e8e5df">' . $content . '</div>
            <div style="text-align:center;padding:24px 0;font-size:12px;color:#9ca3af">
                <p>' . htmlspecialchars($storeName) . '</p>
                <p style="margin-top:6px;font-size:10px;color:#c4b5fd">Powered by Whisker</p>
            </div>
        </div></body></html>';
    }

    private static function replaceVars(string $text, array $vars): string
    {
        // Templates are HTML; variable values are plain text and are
        // HTML-escaped before interpolation into the body. A small set of
        // variables legitimately carry pre-built (already-safe) HTML and stay
        // unescaped: the names listed below, plus any variable ending in
        // "_html" by convention.
        $rawHtmlNames = [
            '{{logo}}',
            '{{order_items_html}}',
            '{{shipping_address}}',
            '{{billing_address}}',
            '{{reply_message}}',
        ];
        $escaped = [];
        foreach ($vars as $key => $value) {
            $isRawHtml = in_array($key, $rawHtmlNames, true)
                || (is_string($key) && str_ends_with($key, '_html}}'));
            $escaped[$key] = $isRawHtml
                ? (string)$value
                : htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
        }
        return str_replace(array_keys($escaped), array_values($escaped), $text);
    }

    private static function storeName(): string
    {
        return Database::fetchValue("SELECT setting_value FROM wk_settings WHERE setting_group='general' AND setting_key='site_name'") ?: 'Whisker Store';
    }

    private static function currencySymbol(): string
    {
        return Database::fetchValue("SELECT setting_value FROM wk_settings WHERE setting_group='general' AND setting_key='currency_symbol'") ?: '₹';
    }

    private static function orderCustomerName(array $order): string
    {
        $b = json_decode($order['billing_address'] ?? '{}', true);
        return trim($b['name'] ?? '') ?: ($order['customer_email'] ?? 'Customer');
    }

    private static function sendSmtp(string $to, string $subject, string $html, string $fromEmail, string $fromName): bool
    {
        $host = Database::fetchValue("SELECT setting_value FROM wk_settings WHERE setting_group='email' AND setting_key='smtp_host'");
        $port = (int)(Database::fetchValue("SELECT setting_value FROM wk_settings WHERE setting_group='email' AND setting_key='smtp_port'") ?: 587);
        $user = Database::fetchValue("SELECT setting_value FROM wk_settings WHERE setting_group='email' AND setting_key='smtp_user'");
        $pass = Database::fetchValue("SELECT setting_value FROM wk_settings WHERE setting_group='email' AND setting_key='smtp_pass'");
        if (!$host || !$user) return false;
        return self::sendSmtpRaw($to, $subject, $html, $fromEmail, $fromName, (string)$host, $port, (string)$user, (string)$pass);
    }

    /**
     * Low-level SMTP send with explicit credentials. Used by both the live
     * sender (sendSmtp() reads from DB) and the test flow (sendWithSmtp()
     * receives credentials directly without touching DB).
     */
    private static function sendSmtpRaw(
        string $to,
        string $subject,
        string $html,
        string $fromEmail,
        string $fromName,
        string $host,
        int    $port,
        string $user,
        string $pass
    ): bool {
        try {
            $ctx = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
            $conn = stream_socket_client("tcp://{$host}:{$port}", $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $ctx);
            if (!$conn) return false;
            fgets($conn); fwrite($conn, "EHLO whisker\r\n"); fgets($conn);
            fwrite($conn, "STARTTLS\r\n"); fgets($conn);
            stream_socket_enable_crypto($conn, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            fwrite($conn, "EHLO whisker\r\n"); fgets($conn);
            fwrite($conn, "AUTH LOGIN\r\n"); fgets($conn);
            fwrite($conn, base64_encode($user) . "\r\n"); fgets($conn);
            fwrite($conn, base64_encode($pass) . "\r\n"); fgets($conn);
            fwrite($conn, "MAIL FROM:<{$fromEmail}>\r\n"); fgets($conn);
            fwrite($conn, "RCPT TO:<{$to}>\r\n"); fgets($conn);
            fwrite($conn, "DATA\r\n"); fgets($conn);
            $msg = "From: {$fromName} <{$fromEmail}>\r\nTo: {$to}\r\nSubject: {$subject}\r\nMIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n\r\n{$html}\r\n.\r\n";
            fwrite($conn, $msg); $r = fgets($conn); fwrite($conn, "QUIT\r\n"); fclose($conn);
            return str_starts_with(trim($r), '250');
        } catch (\Exception $e) { return false; }
    }
}

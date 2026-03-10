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

        if (!$tpl) return self::send($to, $vars['subject'] ?? 'Notification', $vars['body'] ?? '');

        $subject = self::replaceVars($tpl['subject'], $vars);
        $body = self::replaceVars($tpl['body'], $vars);

        return self::send($to, $subject, $body);
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
        $subject   = str_replace(["\r", "\n", "%0a", "%0d"], '', $subject);
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

        // Address blocks
        $addrBlock = fn($a, $label) => '<div style="flex:1">
            <div style="font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:1px;color:#6b7280;margin-bottom:6px">' . $label . '</div>
            <div style="font-size:14px;line-height:1.7">
                <strong>' . htmlspecialchars($a['name'] ?? '') . '</strong><br>
                ' . htmlspecialchars($a['line1'] ?? '') . '<br>
                ' . htmlspecialchars(($a['city'] ?? '') . ', ' . ($a['state'] ?? '') . ' ' . ($a['zip'] ?? '')) . '
            </div>
        </div>';

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

        $logoHtml = $logoUrl
            ? '<img src="' . htmlspecialchars($logoUrl) . '" style="max-height:48px;max-width:200px" alt="' . htmlspecialchars($storeName) . '">'
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
        return str_replace(array_keys($vars), array_values($vars), $text);
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

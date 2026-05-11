<?php
namespace App\Services;

use Core\Database;
use Core\View;

/**
 * WHISKER — Invoice & Receipt Generator
 * Generates HTML invoices that can be printed/saved as PDF via browser
 * Also generates a server-side HTML file for email attachment
 */
class InvoiceService
{
    /**
     * Get or create invoice for an order
     */
    public static function getOrCreate(int $orderId): array
    {
        $existing = Database::fetch("SELECT * FROM wk_invoices WHERE order_id=?", [$orderId]);
        if ($existing) return $existing;

        $order = Database::fetch("SELECT * FROM wk_orders WHERE id=?", [$orderId]);
        if (!$order) return [];

        $invoiceNumber = 'INV-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));

        $id = Database::insert('wk_invoices', [
            'order_id'       => $orderId,
            'invoice_number' => $invoiceNumber,
            'issued_at'      => date('Y-m-d H:i:s'),
        ]);

        return Database::fetch("SELECT * FROM wk_invoices WHERE id=?", [$id]);
    }

    /**
     * Generate printable HTML invoice
     */
    public static function generateHTML(int $orderId): string
    {
        $order = Database::fetch("SELECT * FROM wk_orders WHERE id=?", [$orderId]);
        if (!$order) return '';

        $items = Database::fetchAll("SELECT * FROM wk_order_items WHERE order_id=?", [$orderId]);
        $invoice = self::getOrCreate($orderId);
        $billing = json_decode($order['billing_address'] ?? '{}', true) ?: [];
        $shipping = json_decode($order['shipping_address'] ?? '{}', true) ?: [];

        $storeName = Database::fetchValue("SELECT setting_value FROM wk_settings WHERE setting_group='general' AND setting_key='site_name'") ?: 'Whisker Store';
        $currency = Database::fetchValue("SELECT setting_value FROM wk_settings WHERE setting_group='general' AND setting_key='currency_symbol'") ?: '₹';

        $fmt = fn($v) => $currency . number_format((float)$v, 2);

        $itemRows = '';
        foreach ($items as $i => $item) {
            $itemRows .= '<tr>
                <td style="padding:10px 12px;border-bottom:1px solid #e8e5df">' . ($i + 1) . '</td>
                <td style="padding:10px 12px;border-bottom:1px solid #e8e5df;font-weight:700">' . htmlspecialchars($item['product_name']) . '
                    ' . ($item['variant_label'] ? '<br><span style="font-weight:400;font-size:12px;color:#6b7280">' . htmlspecialchars($item['variant_label']) . '</span>' : '') . '
                </td>
                <td style="padding:10px 12px;border-bottom:1px solid #e8e5df;text-align:center">' . $item['quantity'] . '</td>
                <td style="padding:10px 12px;border-bottom:1px solid #e8e5df;text-align:right;font-family:monospace">' . $fmt($item['unit_price']) . '</td>
                <td style="padding:10px 12px;border-bottom:1px solid #e8e5df;text-align:right;font-family:monospace;font-weight:700">' . $fmt($item['total_price']) . '</td>
            </tr>';
        }

        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Invoice ' . htmlspecialchars($invoice['invoice_number'] ?? '') . '</title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; color: #1e1b2e; font-size:14px; }
        @media print {
            body { padding:0; }
            .no-print { display:none !important; }
            @page { margin:20mm; }
        }
        .invoice { max-width:800px; margin:0 auto; padding:40px; }
        .header { display:flex; justify-content:space-between; margin-bottom:40px; }
        .invoice-title { font-size:32px; font-weight:900; color:#8b5cf6; }
        table { width:100%; border-collapse:collapse; }
    </style>
</head>
<body>
<div class="no-print" style="background:#8b5cf6;color:#fff;padding:12px;text-align:center;font-weight:700;font-size:14px">
    <button onclick="window.print()" style="background:#fff;color:#8b5cf6;border:none;padding:8px 24px;border-radius:6px;font-weight:800;cursor:pointer;margin-right:12px">🖨 Print / Save as PDF</button>
    <button onclick="window.close()" style="background:transparent;color:#fff;border:1px solid #fff;padding:8px 24px;border-radius:6px;font-weight:700;cursor:pointer">Close</button>
</div>
<div class="invoice">
    <div class="header">
        <div>
            <div class="invoice-title">INVOICE</div>
            <div style="color:#6b7280;margin-top:4px">
                <strong>' . htmlspecialchars($invoice['invoice_number'] ?? '') . '</strong><br>
                Date: ' . date('M j, Y', strtotime($invoice['issued_at'] ?? 'now')) . '
            </div>
        </div>
        <div style="text-align:right">
            <div style="font-size:20px;font-weight:900">' . htmlspecialchars($storeName) . '</div>
            <div style="color:#6b7280;margin-top:4px;font-size:13px">
                Order: ' . htmlspecialchars($order['order_number']) . '<br>
                ' . date('M j, Y g:i A', strtotime($order['created_at'])) . '
            </div>
        </div>
    </div>

    <div style="display:flex;gap:40px;margin-bottom:32px">
        <div style="flex:1">
            <div style="font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:1px;color:#6b7280;margin-bottom:8px">Bill To</div>
            <strong>' . htmlspecialchars($billing['name'] ?? '') . '</strong><br>
            ' . htmlspecialchars($billing['line1'] ?? '') . '<br>
            ' . htmlspecialchars(($billing['city'] ?? '') . ', ' . ($billing['state'] ?? '') . ' ' . ($billing['zip'] ?? '')) . '<br>
            ' . htmlspecialchars($order['customer_email'] ?? '') . '
            ' . ($order['customer_phone'] ? '<br>' . htmlspecialchars($order['customer_phone']) : '') . '
        </div>
        <div style="flex:1">
            <div style="font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:1px;color:#6b7280;margin-bottom:8px">Ship To</div>
            <strong>' . htmlspecialchars($shipping['name'] ?? $billing['name'] ?? '') . '</strong><br>
            ' . htmlspecialchars($shipping['line1'] ?? $billing['line1'] ?? '') . '<br>
            ' . htmlspecialchars(($shipping['city'] ?? $billing['city'] ?? '') . ', ' . ($shipping['state'] ?? $billing['state'] ?? '') . ' ' . ($shipping['zip'] ?? $billing['zip'] ?? '')) . '
        </div>
        <div style="flex:1">
            <div style="font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:1px;color:#6b7280;margin-bottom:8px">Payment</div>
            <strong>' . ucfirst($order['payment_gateway'] ?? 'Pending') . '</strong><br>
            Status: <span style="color:' . ($order['payment_status'] === 'captured' ? '#10b981' : '#f59e0b') . ';font-weight:700">' . ucfirst($order['payment_status'] ?? 'pending') . '</span>
            ' . ($order['payment_id'] ? '<br><span style="font-family:monospace;font-size:12px">' . htmlspecialchars($order['payment_id']) . '</span>' : '') . '
        </div>
    </div>

    <table>
        <thead>
            <tr style="background:#faf8f6">
                <th style="padding:10px 12px;text-align:left;font-size:11px;text-transform:uppercase;letter-spacing:1px;color:#6b7280;border-bottom:2px solid #1e1b2e">#</th>
                <th style="padding:10px 12px;text-align:left;font-size:11px;text-transform:uppercase;letter-spacing:1px;color:#6b7280;border-bottom:2px solid #1e1b2e">Item</th>
                <th style="padding:10px 12px;text-align:center;font-size:11px;text-transform:uppercase;letter-spacing:1px;color:#6b7280;border-bottom:2px solid #1e1b2e">Qty</th>
                <th style="padding:10px 12px;text-align:right;font-size:11px;text-transform:uppercase;letter-spacing:1px;color:#6b7280;border-bottom:2px solid #1e1b2e">Price</th>
                <th style="padding:10px 12px;text-align:right;font-size:11px;text-transform:uppercase;letter-spacing:1px;color:#6b7280;border-bottom:2px solid #1e1b2e">Total</th>
            </tr>
        </thead>
        <tbody>' . $itemRows . '</tbody>
    </table>

    <div style="display:flex;justify-content:flex-end;margin-top:20px">
        <div style="width:280px;font-size:14px">
            <div style="display:flex;justify-content:space-between;padding:6px 0"><span style="color:#6b7280">Subtotal</span><span style="font-weight:700;font-family:monospace">' . $fmt($order['subtotal']) . '</span></div>
            ' . self::taxBreakdownHtml($order, $fmt) . '
            <div style="display:flex;justify-content:space-between;padding:6px 0"><span style="color:#6b7280">Shipping</span><span style="font-weight:700;font-family:monospace">' . $fmt($order['shipping_amount']) . '</span></div>
            ' . ($order['discount_amount'] > 0 ? '<div style="display:flex;justify-content:space-between;padding:6px 0"><span style="color:#10b981">Discount</span><span style="font-weight:700;color:#10b981;font-family:monospace">-' . $fmt($order['discount_amount']) . '</span></div>' : '') . '
            <div style="display:flex;justify-content:space-between;padding:12px 0 0;border-top:2px solid #1e1b2e;margin-top:8px;font-size:20px"><span style="font-weight:900">Total</span><span style="font-weight:900;font-family:monospace">' . $fmt($order['total']) . '</span></div>
        </div>
    </div>

    <div style="margin-top:48px;padding-top:24px;border-top:1px solid #e8e5df;text-align:center;color:#9ca3af;font-size:12px">
        <p>' . htmlspecialchars($storeName) . ' · Powered by Whisker</p>
        <p style="margin-top:4px">Thank you for your business!</p>
    </div>
</div>
</body>
</html>';
    }

    /**
     * Generate HTML for tax breakdown lines in invoice.
     * Shows CGST+SGST, IGST, VAT, Sales Tax, etc. depending on order data.
     */
    private static function taxBreakdownHtml(array $order, callable $fmt): string
    {
        $details = json_decode($order['tax_details'] ?? '[]', true);
        if (!empty($details) && is_array($details)) {
            $html = '';
            foreach ($details as $b) {
                $label = htmlspecialchars($b['label'] ?? 'Tax') . ' (' . ($b['rate'] ?? 0) . '%)';
                $html .= '<div style="display:flex;justify-content:space-between;padding:6px 0"><span style="color:#6b7280">' . $label . '</span><span style="font-weight:700;font-family:monospace">' . $fmt($b['amount'] ?? 0) . '</span></div>';
            }
            return $html;
        }
        // Fallback: single tax line
        return '<div style="display:flex;justify-content:space-between;padding:6px 0"><span style="color:#6b7280">Tax</span><span style="font-weight:700;font-family:monospace">' . $fmt($order['tax_amount']) . '</span></div>';
    }
}
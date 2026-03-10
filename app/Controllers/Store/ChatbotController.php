<?php
namespace App\Controllers\Store;

use Core\{Request, Response, Database, Session};

class ChatbotController
{
    public function handle(Request $request, array $params = []): void
    {
        $msg = strtolower(trim($request->input('message') ?? ''));
        $sessionKey = 'wk_chatbot_' . Session::cartId();

        // Get chatbot state
        $state = $_SESSION[$sessionKey] ?? ['step' => 'idle'];

        // ── Ticket creation flow ──
        if ($state['step'] === 'ticket_name') {
            $_SESSION[$sessionKey] = array_merge($state, ['step'=>'ticket_email','name'=>trim($request->input('message'))]);
            Response::json(['reply' => "Thanks, **" . htmlspecialchars(trim($request->input('message'))) . "**! Now please enter your **email address**."]);
            return;
        }
        if ($state['step'] === 'ticket_email') {
            $email = trim($request->input('message'));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                Response::json(['reply' => "That doesn't look like a valid email. Please try again."]); return;
            }
            $_SESSION[$sessionKey] = array_merge($state, ['step'=>'ticket_phone','email'=>$email]);
            Response::json(['reply' => "Got it! Your **phone number**? (or type **skip** to skip)"]);
            return;
        }
        if ($state['step'] === 'ticket_phone') {
            $phone = strtolower(trim($request->input('message'))) === 'skip' ? '' : trim($request->input('message'));
            $_SESSION[$sessionKey] = array_merge($state, ['step'=>'ticket_subject','phone'=>$phone]);
            Response::json(['reply' => "What's the **subject** of your issue? (brief summary)"]);
            return;
        }
        if ($state['step'] === 'ticket_subject') {
            $_SESSION[$sessionKey] = array_merge($state, ['step'=>'ticket_message','subject'=>trim($request->input('message'))]);
            Response::json(['reply' => "Now describe your issue in **detail**. I'll create a ticket for you."]);
            return;
        }
        if ($state['step'] === 'ticket_message') {
            $message = trim($request->input('message'));
            $d = $state;

            // Rate limit ticket creation: 3 per session per hour
            if (!\Core\RateLimiter::attempt('chatbot_ticket', Session::cartId(), 3, 3600)) {
                $_SESSION[$sessionKey] = ['step' => 'idle'];
                Response::json(['reply' => "You've created too many tickets recently. Please try again later or use our [Contact Form →](contact).",
                    'actions' => [['label'=>'Main Menu','value'=>'menu']]]);
                return;
            }
            $_SESSION[$sessionKey] = ['step'=>'idle'];

            $ticketCtrl = new \App\Controllers\Store\TicketController();
            $fakeReq = new Request();
            // Direct insert instead
            $ticketNumber = 'TK-' . strtoupper(date('ymd')) . '-' . strtoupper(bin2hex(random_bytes(3)));
            $custId = Session::customerId();
            try {
                $ticketId = Database::insert('wk_tickets', [
                    'ticket_number'=>$ticketNumber, 'customer_id'=>$custId,
                    'name'=>$d['name'], 'email'=>$d['email'], 'phone'=>$d['phone'] ?? '',
                    'subject'=>$d['subject'], 'status'=>'open', 'priority'=>'medium',
                ]);
                Database::insert('wk_ticket_replies', [
                    'ticket_id'=>$ticketId, 'sender_type'=>'customer', 'sender_name'=>$d['name'], 'message'=>$message,
                ]);
                // Notify admin
                $adminEmail = Database::fetchValue("SELECT setting_value FROM wk_settings WHERE setting_group='general' AND setting_key='contact_email'")
                    ?: Database::fetchValue("SELECT email FROM wk_admins WHERE role='superadmin' LIMIT 1");
                if ($adminEmail) {
                    \App\Services\EmailService::send($adminEmail, "New Chat Ticket: {$d['subject']} [{$ticketNumber}]",
                        '<h2>New Support Ticket (via Chatbot)</h2><p><strong>'.htmlspecialchars($d['name']).'</strong> ('.htmlspecialchars($d['email']).')</p><div style="padding:16px;border-left:3px solid #8b5cf6;margin:16px 0;white-space:pre-line">'.htmlspecialchars($message).'</div>');
                }
                Response::json(['reply' => "✅ **Ticket Created!**\n\n🎫 **{$ticketNumber}**\n\nOur team will review your issue and get back to you at **{$d['email']}**. You can also check status anytime by asking me to track your ticket.",
                    'actions' => [['label'=>'Main Menu','value'=>'menu']]]);
            } catch (\Exception $e) {
                Response::json(['reply' => "Sorry, there was an error creating your ticket. Please try using our [Contact Form →](contact) instead.",
                    'actions' => [['label'=>'Main Menu','value'=>'menu']]]);
            }
            return;
        }

        // ── Order lookup flow ──
        if ($state['step'] === 'awaiting_order_number') {
            $orderNum = strtoupper(trim($request->input('message')));
            $order = Database::fetch("SELECT * FROM wk_orders WHERE order_number=?", [$orderNum]);
            if (!$order) {
                Response::json(['reply' => "I couldn't find order **{$orderNum}**. Please check the order number and try again. You can find it in your confirmation email.", 'actions' => [['label'=>'Try Again','value'=>'track order'],['label'=>'Main Menu','value'=>'menu']]]);
                return;
            }
            $_SESSION[$sessionKey] = ['step' => 'awaiting_otp_email', 'order' => $order];
            Response::json(['reply' => "Found order **{$orderNum}**! For security, please enter the email address associated with this order.", 'input_type' => 'email']);
            return;
        }

        if ($state['step'] === 'awaiting_otp_email') {
            $email = strtolower(trim($request->input('message')));
            $order = $state['order'];
            if ($email === strtolower($order['customer_email'])) {
                $_SESSION[$sessionKey] = ['step' => 'idle'];
                $statusEmoji = ['pending'=>'⏳','processing'=>'🔄','paid'=>'✅','shipped'=>'📦','delivered'=>'🎉','cancelled'=>'❌','refunded'=>'↩️'];
                $emoji = $statusEmoji[$order['status']] ?? '📋';

                $notes = json_decode($order['notes'] ?? '{}', true) ?: [];
                $tracking = '';
                if (!empty($notes['tracking_number'])) {
                    $tracking = "\n📦 **Carrier:** {$notes['shipping_carrier']}\n🔢 **Tracking:** {$notes['tracking_number']}";
                    if (!empty($notes['tracking_url'])) $tracking .= "\n[Track Package →]({$notes['tracking_url']})";
                }

                $currency = Database::fetchValue("SELECT setting_value FROM wk_settings WHERE setting_group='general' AND setting_key='currency_symbol'") ?: '₹';

                $reply = "{$emoji} **Order {$order['order_number']}**\n\n**Status:** " . ucfirst($order['status']) . "\n**Total:** {$currency}" . number_format($order['total'], 2) . "\n**Date:** " . date('M j, Y', strtotime($order['created_at'])) . $tracking;

                Response::json(['reply' => $reply, 'actions' => [['label'=>'Track Another','value'=>'track order'],['label'=>'Refund Policy','value'=>'refund'],['label'=>'Main Menu','value'=>'menu']]]);
            } else {
                Response::json(['reply' => "The email doesn't match our records for this order. Please try again with the correct email.", 'actions' => [['label'=>'Try Again','value'=>'track order'],['label'=>'Main Menu','value'=>'menu']]]);
                $_SESSION[$sessionKey] = ['step' => 'idle'];
            }
            return;
        }

        // Reset state
        $_SESSION[$sessionKey] = ['step' => 'idle'];

        // Intent matching
        if ($this->matches($msg, ['ticket','support ticket','create ticket','open ticket','new ticket','file complaint','complaint','issue'])) {
            $custId = Session::customerId();
            if ($custId) {
                $cust = Database::fetch("SELECT * FROM wk_customers WHERE id=?", [$custId]);
                if ($cust) {
                    $_SESSION[$sessionKey] = ['step'=>'ticket_subject','name'=>trim($cust['first_name'].' '.$cust['last_name']),'email'=>$cust['email'],'phone'=>$cust['phone']??''];
                    Response::json(['reply' => "I'll create a support ticket for you, **".htmlspecialchars(trim($cust['first_name']))."**!\n\nWhat's the **subject** of your issue?"]);
                    return;
                }
            }
            $_SESSION[$sessionKey] = ['step'=>'ticket_name'];
            Response::json(['reply' => "I'll help you create a support ticket! 🎫\n\nFirst, what's your **full name**?"]);
            return;
        }

        if ($this->matches($msg, ['track','order status','where is my order','order number','my order','order info','order tracking'])) {
            $_SESSION[$sessionKey] = ['step' => 'awaiting_order_number'];
            Response::json(['reply' => "I can help you track your order! 📦\n\nPlease enter your order number (e.g. **WK-260306-ABC123**). You can find it in your order confirmation email."]);
            return;
        }

        if ($this->matches($msg, ['refund','money back','return money','get refund','refund policy'])) {
            $content = $this->getPageSummary('refund-policy');
            Response::json(['reply' => "💰 **Refund Policy**\n\n{$content}\n\n[Read full policy →](page/refund-policy)", 'actions' => [['label'=>'Exchange Policy','value'=>'exchange'],['label'=>'Contact Us','value'=>'contact'],['label'=>'Main Menu','value'=>'menu']]]);
            return;
        }

        if ($this->matches($msg, ['exchange','swap','replace','exchange policy','size change','wrong size'])) {
            $content = $this->getPageSummary('exchange-policy');
            Response::json(['reply' => "🔄 **Exchange Policy**\n\n{$content}\n\n[Read full policy →](page/exchange-policy)", 'actions' => [['label'=>'Refund Policy','value'=>'refund'],['label'=>'Contact Us','value'=>'contact'],['label'=>'Main Menu','value'=>'menu']]]);
            return;
        }

        if ($this->matches($msg, ['terms','terms and conditions','tos','conditions'])) {
            Response::json(['reply' => "📋 You can read our full Terms and Conditions here:\n\n[Terms & Conditions →](page/terms-and-conditions)", 'actions' => [['label'=>'Privacy Policy','value'=>'privacy'],['label'=>'Main Menu','value'=>'menu']]]);
            return;
        }

        if ($this->matches($msg, ['privacy','privacy policy','data','personal information','cookies'])) {
            Response::json(['reply' => "🔒 We take your privacy seriously. Read our full Privacy Policy:\n\n[Privacy Policy →](page/privacy-policy)", 'actions' => [['label'=>'Terms','value'=>'terms'],['label'=>'Main Menu','value'=>'menu']]]);
            return;
        }

        if ($this->matches($msg, ['contact','phone','email','reach','call','support','help','talk to'])) {
            $email = Database::fetchValue("SELECT setting_value FROM wk_settings WHERE setting_group='email' AND setting_key='from_email'") ?: '';
            $reply = "📞 **Contact Us**\n\nWe're here to help!";
            if ($email) $reply .= "\n📧 **Email:** {$email}";
            $reply .= "\n\nYou can also use our [Contact Form →](contact) to send us a message.";
            Response::json(['reply' => $reply, 'actions' => [['label'=>'Track Order','value'=>'track order'],['label'=>'Main Menu','value'=>'menu']]]);
            return;
        }

        if ($this->matches($msg, ['shipping','delivery','how long','when will','deliver time','shipping cost','shipping charges'])) {
            Response::json(['reply' => "🚚 **Shipping Information**\n\nShipping times and costs vary based on your location and order total. Tracking details are provided once your order ships.\n\nNeed to check a specific order?", 'actions' => [['label'=>'Track Order','value'=>'track order'],['label'=>'Contact Us','value'=>'contact'],['label'=>'Main Menu','value'=>'menu']]]);
            return;
        }

        if ($this->matches($msg, ['payment','pay','payment methods','how to pay','upi','card'])) {
            $gateways = Database::fetchAll("SELECT display_name FROM wk_payment_gateways WHERE is_active=1");
            $names = array_column($gateways, 'display_name');
            $list = !empty($names) ? implode(', ', $names) : 'Multiple payment options';
            Response::json(['reply' => "💳 **Payment Methods**\n\nWe accept: **{$list}**\n\nAll payments are secure and encrypted.", 'actions' => [['label'=>'Track Order','value'=>'track order'],['label'=>'Main Menu','value'=>'menu']]]);
            return;
        }

        if ($this->matches($msg, ['hi','hello','hey','menu','help','start','main menu'])) {
            $botName = Database::fetchValue("SELECT setting_value FROM wk_settings WHERE setting_group='general' AND setting_key='chatbot_name'") ?: 'Whisker Bot';
            $storeName = Database::fetchValue("SELECT setting_value FROM wk_settings WHERE setting_group='general' AND setting_key='site_name'") ?: 'Our Store';
            Response::json([
                'reply' => "👋 Hi! I'm **{$botName}** from {$storeName}. How can I help you today?",
                'actions' => [
                    ['label'=>'📦 Track Order','value'=>'track order'],
                    ['label'=>'🎫 Support Ticket','value'=>'create ticket'],
                    ['label'=>'💰 Refund Policy','value'=>'refund'],
                    ['label'=>'🔄 Exchange Policy','value'=>'exchange'],
                    ['label'=>'🚚 Shipping Info','value'=>'shipping'],
                    ['label'=>'📞 Contact Us','value'=>'contact'],
                    ['label'=>'📋 Terms','value'=>'terms'],
                    ['label'=>'💳 Payment Methods','value'=>'payment'],
                ]
            ]);
            return;
        }

        // Fallback
        Response::json([
            'reply' => "I'm not sure I understand. Here are some things I can help with:",
            'actions' => [
                ['label'=>'📦 Track Order','value'=>'track order'],
                ['label'=>'💰 Refund Policy','value'=>'refund'],
                ['label'=>'📞 Contact Us','value'=>'contact'],
                ['label'=>'📋 Menu','value'=>'menu'],
            ]
        ]);
    }

    private function matches(string $msg, array $keywords): bool
    {
        foreach ($keywords as $kw) {
            // Use word boundary matching for single words, str_contains for phrases
            if (str_contains($kw, ' ')) {
                if (str_contains($msg, $kw)) return true;
            } else {
                if (preg_match('/\b' . preg_quote($kw, '/') . '\b/', $msg)) return true;
            }
        }
        return false;
    }

    private function getPageSummary(string $slug): string
    {
        try {
            $content = Database::fetchValue("SELECT content FROM wk_pages WHERE slug=? AND is_active=1", [$slug]);
            if ($content) {
                $text = strip_tags($content);
                $text = preg_replace('/\s+/', ' ', $text);
                return mb_substr($text, 0, 300) . '...';
            }
        } catch (\Exception $e) {}
        return 'Please visit our website for full details.';
    }
}

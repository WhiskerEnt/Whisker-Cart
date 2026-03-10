<?php
namespace App\Controllers\Store;

use Core\{Request, View, Database, Response, Session};

class PageController
{
    public function show(Request $request, array $params = []): void
    {
        try {
            $page = Database::fetch("SELECT * FROM wk_pages WHERE slug=? AND is_active=1", [$params['slug']]);
        } catch (\Exception $e) { $page = null; }
        if (!$page) { Response::notFound(); return; }

        View::render('store/page', ['pageTitle'=>$page['title'],'page'=>$page], 'store/layouts/main');
    }

    public function contact(Request $request, array $params = []): void
    {
        View::render('store/contact', ['pageTitle'=>'Contact Us'], 'store/layouts/main');
    }

    public function submitContact(Request $request, array $params = []): void
    {
        if (!Session::verifyCsrf($request->input('wk_csrf'))) {
            Session::flash('error', 'Session expired.');
            Response::redirect(View::url('contact'));
            return;
        }

        // Rate limit: 5 per IP per hour
        if (!\Core\RateLimiter::attempt('contact', $request->ip(), 5, 3600)) {
            Session::flash('error', 'Too many submissions. Please try again later.');
            Response::redirect(View::url('contact'));
            return;
        }

        $name = $request->clean('name');
        $email = $request->clean('email');
        $subject = $request->clean('subject');
        $message = $request->input('message');

        if (!$name || !$email || !$message) {
            Session::flash('error','Please fill in all required fields.');
            Response::redirect(View::url('contact'));
            return;
        }

        // Save to DB
        try {
            Database::insert('wk_contact_messages', ['name'=>$name,'email'=>$email,'subject'=>$subject,'message'=>$message]);
        } catch (\Exception $e) {
            // Table might not exist if upgrading from older version — create it
            try {
                Database::exec("CREATE TABLE IF NOT EXISTS wk_contact_messages (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100), email VARCHAR(255),
                    subject VARCHAR(255), message TEXT, is_read TINYINT(1) DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB");
                Database::insert('wk_contact_messages', ['name'=>$name,'email'=>$email,'subject'=>$subject,'message'=>$message]);
            } catch (\Exception $e2) {}
        }

        // Notify admin — use contact_email setting, fallback to superadmin
        $adminEmail = Database::fetchValue("SELECT setting_value FROM wk_settings WHERE setting_group='general' AND setting_key='contact_email'");
        if (!$adminEmail) $adminEmail = Database::fetchValue("SELECT email FROM wk_admins WHERE role='superadmin' LIMIT 1");
        if ($adminEmail) {
            \App\Services\EmailService::send($adminEmail, 'New Contact: '.$subject,
                '<h2>New Contact Form Submission</h2><table style="font-size:14px"><tr><td style="padding:6px 12px 6px 0;font-weight:700;color:#6b7280">Name</td><td>'.htmlspecialchars($name).'</td></tr><tr><td style="padding:6px 12px 6px 0;font-weight:700;color:#6b7280">Email</td><td>'.htmlspecialchars($email).'</td></tr><tr><td style="padding:6px 12px 6px 0;font-weight:700;color:#6b7280">Subject</td><td>'.htmlspecialchars($subject).'</td></tr></table><div style="margin-top:16px;padding:16px;background:#faf8f6;border-radius:8px;white-space:pre-line;font-size:14px">'.htmlspecialchars($message).'</div>',
                $email
            );
        }

        Session::flash('success','Thank you! Your message has been sent. We\'ll get back to you soon.');
        Response::redirect(View::url('contact'));
    }
}
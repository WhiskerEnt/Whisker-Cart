<?php
namespace App\Controllers\Admin;

use Core\{Request, View, Database, Response, Session, Validator};

class PageController
{
    public function __construct()
    {
        try { Database::query("SELECT 1 FROM wk_pages LIMIT 1"); }
        catch (\Exception $e) {
            Database::connect()->exec("CREATE TABLE IF NOT EXISTS wk_pages (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                slug VARCHAR(60) NOT NULL UNIQUE,
                title VARCHAR(200) NOT NULL,
                content LONGTEXT NOT NULL,
                is_active TINYINT(1) DEFAULT 1,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB");
            self::seedDefaults();
        }
    }

    public function index(Request $request, array $params = []): void
    {
        $pages = Database::fetchAll("SELECT * FROM wk_pages ORDER BY title");
        View::render('admin/pages/index', ['pageTitle'=>'Pages','pages'=>$pages], 'admin/layouts/main');
    }

    public function edit(Request $request, array $params = []): void
    {
        $page = Database::fetch("SELECT * FROM wk_pages WHERE id=?", [$params['id']]);
        if (!$page) { Response::notFound(); return; }
        View::render('admin/pages/edit', ['pageTitle'=>'Edit: '.$page['title'],'page'=>$page], 'admin/layouts/main');
    }

    public function update(Request $request, array $params = []): void
    {
        if (!Session::verifyCsrf($request->input('wk_csrf'))) {
            Session::flash('error','Session expired.'); Response::redirect(View::url('admin/pages/edit/'.$params['id'])); return;
        }
        // L16: input validation. Empty title would write garbage to nav.
        $v = new Validator($request->all(), [
            'title' => 'required|min:2|max:200',
        ]);
        if ($v->fails()) {
            Session::flash('error', $v->firstError());
            Response::redirect(View::url('admin/pages/edit/'.$params['id'])); return;
        }
        // Sanitize on save so the stored HTML is already safe — display path
        // can render it verbatim without per-request purification, and a
        // compromised admin can only insert markup that the allowlist
        // permits. Strips <script>, <iframe>, on* handlers, javascript: URIs.
        $content = \App\Services\HtmlSanitizer::purify((string)$request->input('content'));
        Database::update('wk_pages', [
            'title'=>$request->clean('title'), 'content'=>$content,
            'is_active'=>$request->input('is_active')?1:0,
        ], 'id=?', [$params['id']]);
        Session::flash('success','Page saved!');
        Response::redirect(View::url('admin/pages'));
    }

    public function create(Request $request, array $params = []): void
    {
        View::render('admin/pages/create', ['pageTitle'=>'Create Page'], 'admin/layouts/main');
    }

    public function store(Request $request, array $params = []): void
    {
        if (!Session::verifyCsrf($request->input('wk_csrf'))) {
            Session::flash('error','Session expired.'); Response::redirect(View::url('admin/pages/create')); return;
        }
        // L16: validate title before slugify. If title is all non-alphanumeric,
        // slug ends up '' and the row would fail the UNIQUE constraint on the
        // first save (since seed defaults already include an empty-slug clash
        // risk on edge cases).
        $v = new Validator($request->all(), [
            'title' => 'required|min:2|max:200',
        ]);
        if ($v->fails()) {
            Session::flash('error', $v->firstError());
            Response::redirect(View::url('admin/pages/create')); return;
        }
        $slug = trim(strtolower(preg_replace('/[^a-z0-9]+/','-',$request->clean('title'))),'-');
        if ($slug === '') {
            Session::flash('error', 'Page title must contain at least 2 alphanumeric characters.');
            Response::redirect(View::url('admin/pages/create')); return;
        }
        // Sanitize on save — see comment in update().
        $content = \App\Services\HtmlSanitizer::purify((string)$request->input('content'));
        Database::insert('wk_pages', ['slug'=>$slug,'title'=>$request->clean('title'),'content'=>$content,'is_active'=>1]);
        Session::flash('success','Page created!');
        Response::redirect(View::url('admin/pages'));
    }

    public function delete(Request $request, array $params = []): void
    {
        Database::delete('wk_pages', 'id=?', [$params['id']]);
        Session::flash('success','Page deleted.');
        Response::redirect(View::url('admin/pages'));
    }

    private static function seedDefaults(): void
    {
        $storeName = Database::fetchValue("SELECT setting_value FROM wk_settings WHERE setting_group='general' AND setting_key='site_name'") ?: 'Our Store';

        $pages = [
            ['terms-and-conditions', 'Terms and Conditions', self::termsContent($storeName)],
            ['privacy-policy', 'Privacy Policy', self::privacyContent($storeName)],
            ['refund-policy', 'Refund Policy', self::refundContent($storeName)],
            ['exchange-policy', 'Exchange Policy', self::exchangeContent($storeName)],
        ];
        foreach ($pages as [$slug,$title,$content]) {
            try { Database::insert('wk_pages', ['slug'=>$slug,'title'=>$title,'content'=>$content,'is_active'=>1]); } catch(\Exception $e) {}
        }
    }

    private static function termsContent($s): string
    {
        return '<h2>1. Introduction</h2><p>Welcome to '.$s.'. By accessing or using our website, you agree to be bound by these Terms and Conditions. If you do not agree, please do not use our services.</p><h2>2. Use of Website</h2><p>You may use our website for lawful purposes only. You must not use our site in any way that causes, or may cause, damage to the website or impairment of the availability or accessibility of the website.</p><h2>3. Account Registration</h2><p>When you create an account, you must provide accurate and complete information. You are responsible for maintaining the confidentiality of your account credentials and for all activities under your account.</p><h2>4. Products & Pricing</h2><p>All products are subject to availability. We reserve the right to modify pricing at any time without prior notice. Prices displayed include applicable taxes unless otherwise stated.</p><h2>5. Orders & Payment</h2><p>By placing an order, you make an offer to purchase the selected products. We reserve the right to accept or reject any order. Payment must be made in full at the time of purchase through our accepted payment methods.</p><h2>6. Shipping & Delivery</h2><p>Delivery times are estimates only and are not guaranteed. We are not responsible for delays caused by shipping carriers, customs, or other circumstances beyond our control.</p><h2>7. Intellectual Property</h2><p>All content on this website, including text, images, logos, and graphics, is the property of '.$s.' and is protected by copyright laws. You may not reproduce, distribute, or modify any content without our written consent.</p><h2>8. Limitation of Liability</h2><p>'.$s.' shall not be liable for any indirect, incidental, special, or consequential damages arising from the use of our website or products.</p><h2>9. Changes to Terms</h2><p>We reserve the right to update these terms at any time. Changes will be posted on this page with an updated date. Continued use of the website constitutes acceptance of modified terms.</p><h2>10. Contact</h2><p>If you have questions about these terms, please contact us through our contact page.</p><p><em>Last updated: '.date('F j, Y').'</em></p>';
    }

    private static function privacyContent($s): string
    {
        return '<h2>1. Information We Collect</h2><p>We collect information you provide directly to us, such as your name, email address, shipping address, phone number, and payment details when you create an account, place an order, or contact us.</p><h2>2. How We Use Your Information</h2><p>We use the information we collect to: process and fulfill your orders, communicate with you about orders and promotions, improve our website and services, prevent fraud, and comply with legal obligations.</p><h2>3. Information Sharing</h2><p>We do not sell, trade, or rent your personal information to third parties. We may share your information with: payment processors to complete transactions, shipping carriers to deliver your orders, and service providers who assist in operating our website.</p><h2>4. Data Security</h2><p>We implement appropriate security measures to protect your personal information, including encryption of sensitive data, secure servers, and regular security assessments. However, no method of transmission over the internet is 100% secure.</p><h2>5. Cookies</h2><p>We use cookies and similar technologies to enhance your browsing experience, analyze site traffic, and personalize content. You can control cookie settings through your browser preferences.</p><h2>6. Your Rights</h2><p>You have the right to: access your personal data, request correction of inaccurate data, request deletion of your data, opt out of marketing communications, and request a copy of your data in a portable format.</p><h2>7. Data Retention</h2><p>We retain your personal information for as long as your account is active or as needed to provide you services, comply with legal obligations, and resolve disputes.</p><h2>8. Third-Party Links</h2><p>Our website may contain links to third-party websites. We are not responsible for the privacy practices of these external sites.</p><h2>9. Children\'s Privacy</h2><p>Our services are not directed to individuals under 18. We do not knowingly collect personal information from children.</p><h2>10. Updates to This Policy</h2><p>We may update this privacy policy from time to time. We will notify you of any significant changes by posting the new policy on this page.</p><p><em>Last updated: '.date('F j, Y').'</em></p>';
    }

    private static function refundContent($s): string
    {
        return '<h2>Refund Policy</h2><p>At '.$s.', we want you to be completely satisfied with your purchase. If you are not satisfied, we offer refunds under the following conditions:</p><h2>Eligibility for Refund</h2><ul><li>Refund requests must be made within <strong>30 days</strong> of the delivery date</li><li>Items must be unused, unwashed, and in their original packaging with all tags attached</li><li>Items purchased on sale or marked as final sale are not eligible for refunds</li><li>Gift cards and downloadable products are non-refundable</li></ul><h2>How to Request a Refund</h2><p>To initiate a refund, please contact our customer support team through our contact page with your order number and reason for the refund. We will provide you with return shipping instructions.</p><h2>Refund Process</h2><ul><li>Once we receive and inspect the returned item, we will notify you of the approval or rejection of your refund</li><li>Approved refunds will be processed within <strong>5-10 business days</strong></li><li>The refund will be credited to your original method of payment</li><li>Shipping charges are non-refundable unless the return is due to our error</li></ul><h2>Damaged or Defective Items</h2><p>If you received a damaged or defective item, please contact us within <strong>48 hours</strong> of delivery with photos of the damaged product. We will arrange a replacement or full refund including shipping charges.</p><h2>Late or Missing Refunds</h2><p>If you haven\'t received a refund after the processing period, please check your bank account, then contact your payment provider. If you still have not received your refund, please contact us.</p><p><em>Last updated: '.date('F j, Y').'</em></p>';
    }

    private static function exchangeContent($s): string
    {
        return '<h2>Exchange Policy</h2><p>We understand that sometimes a product may not be the right fit. '.$s.' offers exchanges under the following guidelines:</p><h2>Eligibility for Exchange</h2><ul><li>Exchange requests must be made within <strong>15 days</strong> of delivery</li><li>Items must be unused, unworn, and in their original packaging with all tags</li><li>Exchanges are subject to product availability</li><li>Sale items may only be exchanged for items of equal or greater value</li></ul><h2>What Can Be Exchanged</h2><ul><li>Wrong size — exchange for the correct size of the same product</li><li>Wrong color — exchange for a different color of the same product</li><li>Different product — exchange for a different product of equal or greater value (you pay the difference)</li></ul><h2>How to Request an Exchange</h2><p>Contact our support team through the contact page with your order number, the item you wish to exchange, and the item you want instead. We will confirm availability and provide shipping instructions.</p><h2>Exchange Shipping</h2><ul><li>For exchanges due to our error (wrong item shipped), we cover all shipping costs</li><li>For other exchanges, the customer is responsible for return shipping costs</li><li>We will ship the replacement item free of charge within India</li></ul><h2>Processing Time</h2><p>Exchanges are processed within <strong>3-5 business days</strong> after we receive the returned item. You will receive a tracking number for the replacement shipment.</p><p><em>Last updated: '.date('F j, Y').'</em></p>';
    }
}
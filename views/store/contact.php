<?php
$e=fn($v)=>\Core\View::e($v); $url=fn($p)=>\Core\View::url($p);
$storeName = \Core\Database::fetchValue("SELECT setting_value FROM wk_settings WHERE setting_group='general' AND setting_key='site_name'") ?: 'Our Store';
?>
<section class="wk-section">
    <div class="wk-container" style="max-width:700px">
        <div class="wk-page-intro">
            <div style="font-size:48px;margin-bottom:8px"><?= $icon('message-circle', 48) ?></div>
            <h1 style="font-size:32px;font-weight:900;margin-bottom:8px">Contact Us</h1>
            <p style="color:var(--wk-muted);font-size:15px">Have a question? We'd love to hear from you.</p>
        </div>

        <div style="background:var(--wk-surface);border:2px solid var(--wk-border);border-radius:var(--radius);padding:36px">
            <form method="POST" action="<?= $url('contact/submit') ?>">
                <?= \Core\Session::csrfField() ?>
                <div class="wk-cols-2" style="gap:16px;margin-bottom:16px">
                    <div><label class="wk-eyebrow">Your Name *</label><input type="text" name="name" required placeholder="John Doe" class="wk-field-input-lg"></div>
                    <div><label class="wk-eyebrow">Email Address *</label><input type="email" name="email" required placeholder="john@example.com" class="wk-field-input-lg"></div>
                </div>
                <div class="wk-mb-16">
                    <label class="wk-eyebrow">Subject</label>
                    <select name="subject" class="wk-field-input-lg" style="cursor:pointer">
                        <option value="General Inquiry">General Inquiry</option>
                        <option value="Order Issue">Order Issue</option>
                        <option value="Return/Exchange">Return / Exchange</option>
                        <option value="Product Question">Product Question</option>
                        <option value="Shipping">Shipping</option>
                        <option value="Feedback">Feedback</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="wk-mb-24">
                    <label class="wk-eyebrow">Message *</label>
                    <textarea name="message" required placeholder="How can we help you?" rows="6" class="wk-field-input-lg" style="resize:vertical"></textarea>
                </div>
                <button type="submit" style="width:100%;background:linear-gradient(135deg,var(--wk-purple),var(--wk-pink));color:#fff;border:none;padding:16px;border-radius:10px;font-family:var(--font);font-size:16px;font-weight:800;cursor:pointer">Send Message →</button>
            </form>
        </div>
    </div>
</section>
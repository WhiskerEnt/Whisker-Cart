<?php
$e=fn($v)=>\Core\View::e($v); $url=fn($p)=>\Core\View::url($p);
$storeName = \Core\Database::fetchValue("SELECT setting_value FROM wk_settings WHERE setting_group='general' AND setting_key='site_name'") ?: 'Our Store';
$is='width:100%;padding:12px 16px;border:2px solid var(--wk-border);border-radius:10px;font-family:var(--font);font-size:14px;font-weight:600;outline:none;transition:border-color .2s;background:var(--wk-surface)';
?>
<section class="wk-section">
    <div class="wk-container" style="max-width:700px">
        <div style="text-align:center;margin-bottom:32px">
            <div style="font-size:48px;margin-bottom:8px">💬</div>
            <h1 style="font-size:32px;font-weight:900;margin-bottom:8px">Contact Us</h1>
            <p style="color:var(--wk-muted);font-size:15px">Have a question? We'd love to hear from you.</p>
        </div>

        <div style="background:var(--wk-surface);border:2px solid var(--wk-border);border-radius:var(--radius);padding:36px">
            <form method="POST" action="<?= $url('contact/submit') ?>">
                <?= \Core\Session::csrfField() ?>
                <div class="wk-cols-2" style="gap:16px;margin-bottom:16px">
                    <div><label style="display:block;font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.5px;color:var(--wk-muted);margin-bottom:6px">Your Name *</label><input type="text" name="name" required placeholder="John Doe" style="<?= $is ?>"></div>
                    <div><label style="display:block;font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.5px;color:var(--wk-muted);margin-bottom:6px">Email Address *</label><input type="email" name="email" required placeholder="john@example.com" style="<?= $is ?>"></div>
                </div>
                <div style="margin-bottom:16px">
                    <label style="display:block;font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.5px;color:var(--wk-muted);margin-bottom:6px">Subject</label>
                    <select name="subject" style="<?= $is ?>;cursor:pointer">
                        <option value="General Inquiry">General Inquiry</option>
                        <option value="Order Issue">Order Issue</option>
                        <option value="Return/Exchange">Return / Exchange</option>
                        <option value="Product Question">Product Question</option>
                        <option value="Shipping">Shipping</option>
                        <option value="Feedback">Feedback</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div style="margin-bottom:24px">
                    <label style="display:block;font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.5px;color:var(--wk-muted);margin-bottom:6px">Message *</label>
                    <textarea name="message" required placeholder="How can we help you?" rows="6" style="<?= $is ?>;resize:vertical"></textarea>
                </div>
                <button type="submit" style="width:100%;background:linear-gradient(135deg,var(--wk-purple),var(--wk-pink));color:#fff;border:none;padding:16px;border-radius:10px;font-family:var(--font);font-size:16px;font-weight:800;cursor:pointer">Send Message →</button>
            </form>
        </div>
    </div>
</section>
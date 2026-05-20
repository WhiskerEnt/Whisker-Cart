<?php $e=fn($v)=>\Core\View::e($v); ?>
<section class="wk-section">
    <div class="wk-container" style="max-width:800px">
        <h1 style="font-size:32px;font-weight:900;margin-bottom:24px"><?= $e($page['title']) ?></h1>
        <div style="font-size:15px;line-height:1.9;color:var(--wk-text)" class="wk-page-content">
            <?php
            // Defense in depth: rows written before sanitize-on-save was added
            // may still contain unsafe HTML, and we'd rather purify twice than
            // never. The sanitizer is idempotent — purifying already-clean
            // HTML returns the same HTML.
            echo \App\Services\HtmlSanitizer::purify((string)($page['content'] ?? ''));
            ?>
        </div>
    </div>
</section>
<style>
.wk-page-content h2 { font-size:20px; font-weight:800; margin:32px 0 12px; }
.wk-page-content h3 { font-size:17px; font-weight:800; margin:24px 0 8px; }
.wk-page-content p { margin:0 0 16px; }
.wk-page-content ul, .wk-page-content ol { margin:0 0 16px 24px; }
.wk-page-content li { margin-bottom:8px; }
.wk-page-content a { color:var(--wk-purple); font-weight:700; }
</style>
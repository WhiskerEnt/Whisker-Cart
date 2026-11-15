<?php
/**
 * A note above the homepage slider that only a signed-in shopkeeper sees,
 * while the slider is filling itself. Shoppers never get it.
 *
 * Set before requiring this:
 *   $showcase  array  from ShowcaseService::pick()
 */
if (!\Core\Session::isAdmin() || empty($showcase) || \App\Services\ShowcaseService::autoCount($showcase) === 0) return;
?>
<div class="wk-admin-note" data-wk-admin-note="showcase" role="note">
    <div class="wk-container wk-admin-note-inner">
        <span class="wk-admin-note-tag">Only you can see this</span>
        <span class="wk-admin-note-text">
            <?= \Core\View::e(\App\Services\ShowcaseService::describe($showcase)) ?>
            <a href="<?= \Core\View::e(\Core\View::url('admin/products')) ?>">Choose products</a>
            by ticking "Show in homepage slider" on each one.
        </span>
        <button type="button" class="wk-admin-note-close" data-wk-admin-note-close aria-label="Hide this note"><?= $icon('x', 16) ?></button>
    </div>
</div>

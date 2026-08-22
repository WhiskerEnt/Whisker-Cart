<?php $e=fn($v)=>\Core\View::e($v); $url=fn($p)=>\Core\View::url($p);
$allVars = [
    '{{store_name}}'=>'Store name','{{store_url}}'=>'Store URL','{{logo}}'=>'Store logo','{{customer_name}}'=>'Customer name',
    '{{customer_email}}'=>'Customer email','{{customer_phone}}'=>'Phone','{{currency_symbol}}'=>'Currency symbol',
    '{{order_number}}'=>'Order number','{{order_total}}'=>'Grand total','{{order_date}}'=>'Order date',
];
?>

<a href="<?= $url('admin/email-templates') ?>" class="wk-btn wk-btn-secondary" style="margin-bottom:20px">← Back</a>

<form method="POST" action="<?= $url('admin/email-templates/store') ?>">
    <?= \Core\Session::csrfField() ?>
    <div style="display:grid;grid-template-columns:2fr 1fr;gap:20px">
        <div>
            <div class="wk-card" style="margin-bottom:20px">
                <div class="wk-card-header"><h2>New Email Template</h2></div>
                <div class="wk-card-body">
                    <div class="wk-form-group"><label>Template Name</label><input type="text" name="name" class="wk-input" required placeholder="e.g. Abandoned Cart Reminder"></div>
                    <div class="wk-form-group"><label>Email Subject</label><input type="text" name="subject" class="wk-input" required placeholder="e.g. You left something in your cart!"></div>
                </div>
            </div>
            <div class="wk-card">
                <div class="wk-card-header"><h2>Email Body</h2><span style="font-size:11px;color:var(--wk-text-muted)">Click &lt;/&gt; for HTML source</span></div>
                <div class="wk-card-body" style="padding:0">
                    <div style="display:flex;flex-wrap:wrap;gap:3px;padding:10px 14px;border-bottom:1px solid var(--wk-border);background:var(--wk-bg)">
                        <button type="button" onclick="execCmd('bold')" style="background:none;border:1px solid var(--wk-border);border-radius:4px;padding:5px 9px;cursor:pointer;font-weight:900;font-size:13px">B</button>
                        <button type="button" onclick="execCmd('italic')" style="background:none;border:1px solid var(--wk-border);border-radius:4px;padding:5px 9px;cursor:pointer;font-style:italic;font-size:13px">I</button>
                        <button type="button" onclick="execCmd('underline')" style="background:none;border:1px solid var(--wk-border);border-radius:4px;padding:5px 9px;cursor:pointer;text-decoration:underline;font-size:13px">U</button>
                        <span style="width:1px;background:var(--wk-border);margin:0 3px"></span>
                        <button type="button" onclick="execCmd('formatBlock','<h1>')" style="background:none;border:1px solid var(--wk-border);border-radius:4px;padding:5px 8px;cursor:pointer;font-size:11px;font-weight:800">H1</button>
                        <button type="button" onclick="execCmd('formatBlock','<h2>')" style="background:none;border:1px solid var(--wk-border);border-radius:4px;padding:5px 8px;cursor:pointer;font-size:11px;font-weight:800">H2</button>
                        <button type="button" onclick="execCmd('formatBlock','<p>')" style="background:none;border:1px solid var(--wk-border);border-radius:4px;padding:5px 8px;cursor:pointer;font-size:11px">¶</button>
                        <span style="width:1px;background:var(--wk-border);margin:0 3px"></span>
                        <button type="button" onclick="execCmd('justifyCenter')" style="background:none;border:1px solid var(--wk-border);border-radius:4px;padding:5px 8px;cursor:pointer;font-size:11px">Center</button>
                        <button type="button" onclick="insertLink()" style="background:none;border:1px solid var(--wk-border);border-radius:4px;padding:5px 8px;cursor:pointer;font-size:11px">🔗</button>
                        <button type="button" onclick="insertButton()" style="background:var(--wk-purple);color:#fff;border:none;border-radius:4px;padding:5px 10px;cursor:pointer;font-size:11px;font-weight:700">+ Button</button>
                        <select onchange="if(this.value){insertVariable(this.value);this.selectedIndex=0}" style="padding:5px 8px;border:1px solid var(--wk-border);border-radius:4px;font-size:11px;font-weight:700;cursor:pointer">
                            <option value="">+ Variable</option>
                            <?php foreach ($allVars as $var => $desc): ?><option value="<?= $e($var) ?>"><?= $e($var) ?></option><?php endforeach; ?>
                        </select>
                        <span style="flex:1"></span>
                        <button type="button" onclick="toggleSource()" style="background:#1e1b2e;color:#e2e8f0;border:none;border-radius:4px;padding:5px 10px;cursor:pointer;font-size:11px;font-family:var(--font-mono);font-weight:700">&lt;/&gt;</button>
                    </div>
                    <div id="editor" contenteditable="true" style="min-height:400px;padding:28px;font-size:14px;line-height:1.8;outline:none;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,sans-serif"><div style="text-align:center;margin-bottom:24px"><div style="font-size:48px;margin-bottom:8px">📧</div><h1 style="font-size:24px;font-weight:900;margin:0 0 6px">Hello {{customer_name}}!</h1><p style="color:#6b7280;margin:0">Your content here...</p></div><div style="text-align:center;margin-top:28px"><a href="{{store_url}}" style="display:inline-block;background:linear-gradient(135deg,#8b5cf6,#ec4899);color:#fff;padding:14px 32px;border-radius:8px;text-decoration:none;font-weight:800">Visit Store →</a></div></div>
                    <textarea name="body" id="bodyHidden" style="display:none"></textarea>
                    <textarea id="sourceView" style="display:none;width:100%;min-height:400px;padding:20px;font-family:var(--font-mono);font-size:12px;border:none;outline:none;resize:vertical;background:#1e1b2e;color:#e2e8f0;line-height:1.6"></textarea>
                </div>
            </div>
        </div>
        <div>
            <div class="wk-card" style="margin-bottom:20px;background:var(--wk-bg)">
                <div class="wk-card-body" style="padding:16px;font-size:12px;color:var(--wk-text-muted)">
                    <strong style="color:var(--wk-text)">💡 Tips:</strong>
                    <ul style="margin:8px 0 0 16px;line-height:1.8">
                        <li>Use toolbar to format text visually</li>
                        <li>Click <strong>+ Variable</strong> to insert dynamic placeholders</li>
                        <li>Click <strong>&lt;/&gt;</strong> to edit raw HTML</li>
                        <li>You can edit with the full rich editor after creating</li>
                    </ul>
                </div>
            </div>
            <button type="submit" class="wk-btn wk-btn-primary" style="width:100%;justify-content:center">Create Template</button>
        </div>
    </div>
</form>

<script>
const editor = document.getElementById('editor');
const hidden = document.getElementById('bodyHidden');
const sourceView = document.getElementById('sourceView');
let sourceMode = false;
// Scope to this editor's own form — the layout renders other forms first, so a
// document-wide selector would bind to the wrong one.
hidden.closest('form').addEventListener('submit', function() {
    if (sourceMode) editor.innerHTML = sourceView.value;
    hidden.value = editor.innerHTML;
});
function execCmd(cmd, val) { document.execCommand(cmd, false, val || null); editor.focus(); }
function insertVariable(v) {
    if (sourceMode) { sourceView.value += v; return; }
    const span = document.createElement('span');
    span.style.cssText = 'background:#ede9fe;color:#8b5cf6;padding:1px 4px;border-radius:3px;font-family:monospace;font-size:12px;font-weight:700';
    span.textContent = v;
    const sel = window.getSelection();
    if (sel.rangeCount) { const r = sel.getRangeAt(0); r.deleteContents(); r.insertNode(span); r.setStartAfter(span); sel.removeAllRanges(); sel.addRange(r); }
    else editor.appendChild(span);
    editor.focus();
}
function insertLink() { const u = prompt('URL:','https://'); if(u) execCmd('createLink',u); }
function insertButton() {
    const text = prompt('Button text:','Shop Now →'), url = prompt('Button URL:','{{store_url}}');
    if(text&&url) document.execCommand('insertHTML',false,'<div style="text-align:center;margin:24px 0"><a href="'+url+'" style="display:inline-block;background:linear-gradient(135deg,#8b5cf6,#ec4899);color:#fff;padding:14px 32px;border-radius:8px;text-decoration:none;font-weight:800">'+text+'</a></div>');
    editor.focus();
}
function toggleSource() {
    sourceMode = !sourceMode;
    if (sourceMode) { sourceView.value = editor.innerHTML; editor.style.display='none'; sourceView.style.display='block'; }
    else { editor.innerHTML = sourceView.value; sourceView.style.display='none'; editor.style.display='block'; }
}
</script>
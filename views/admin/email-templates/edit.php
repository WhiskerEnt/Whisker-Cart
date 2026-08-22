<?php $e=fn($v)=>\Core\View::e($v); $url=fn($p)=>\Core\View::url($p); $t=$template; ?>

<div style="display:flex;gap:12px;margin-bottom:24px">
    <a href="<?= $url('admin/email-templates') ?>" class="wk-btn wk-btn-secondary">← Back</a>
    <a href="<?= $url('admin/email-templates/preview/'.$t['id']) ?>" target="_blank" class="wk-btn wk-btn-secondary">👁 Preview</a>
</div>

<form method="POST" action="<?= $url('admin/email-templates/update/'.$t['id']) ?>">
    <?= \Core\Session::csrfField() ?>
    <div style="display:grid;grid-template-columns:2fr 1fr;gap:20px">
        <div>
            <div class="wk-card" style="margin-bottom:20px">
                <div class="wk-card-header"><h2>Template Details</h2></div>
                <div class="wk-card-body">
                    <div class="wk-form-group"><label>Template Name</label><input type="text" name="name" class="wk-input" value="<?= $e($t['name']) ?>" required></div>
                    <div class="wk-form-group"><label>Email Subject</label><input type="text" name="subject" class="wk-input" value="<?= $e($t['subject']) ?>" required></div>
                </div>
            </div>
            <div class="wk-card">
                <div class="wk-card-header"><h2>Email Body</h2><span style="font-size:11px;color:var(--wk-text-muted)">Click &lt;/&gt; for HTML</span></div>
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
                        <button type="button" onclick="insertDivider()" style="background:none;border:1px solid var(--wk-border);border-radius:4px;padding:5px 8px;cursor:pointer;font-size:11px">― Line</button>
                        <select onchange="if(this.value){insertVariable(this.value);this.selectedIndex=0}" style="padding:5px 8px;border:1px solid var(--wk-border);border-radius:4px;font-size:11px;font-weight:700;cursor:pointer;max-width:180px">
                            <option value="">+ Variable</option>
                            <?php foreach ($variables as $var => $desc): ?><option value="<?= $e($var) ?>"><?= $e($var) ?></option><?php endforeach; ?>
                        </select>
                        <span style="flex:1"></span>
                        <button type="button" onclick="toggleSource()" style="background:#1e1b2e;color:#e2e8f0;border:none;border-radius:4px;padding:5px 10px;cursor:pointer;font-size:11px;font-family:var(--font-mono);font-weight:700">&lt;/&gt;</button>
                    </div>
                    <?php
                    // Sanitize before injecting into contenteditable — rows
                    // stored before sanitize-on-save was introduced may still
                    // hold <script> or event handlers. Idempotent.
                    $editorBody = \App\Services\HtmlSanitizer::purify((string)$t['body']);
                    ?>
                    <div id="editor" contenteditable="true" style="min-height:450px;padding:28px;font-size:14px;line-height:1.8;outline:none;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,sans-serif"><?= $editorBody ?></div>
                    <textarea name="body" id="bodyHidden" style="display:none"><?= $e($editorBody) ?></textarea>
                    <textarea id="sourceView" style="display:none;width:100%;min-height:450px;padding:20px;font-family:var(--font-mono);font-size:12px;border:none;outline:none;resize:vertical;background:#1e1b2e;color:#e2e8f0;line-height:1.6"></textarea>
                </div>
            </div>
        </div>
        <div>
            <div class="wk-card" style="margin-bottom:20px">
                <div class="wk-card-header"><h2>Settings</h2></div>
                <div class="wk-card-body">
                    <div class="wk-form-group"><label>Slug</label><input type="text" class="wk-input" value="<?= $e($t['slug']) ?>" disabled style="opacity:.6;font-family:var(--font-mono);font-size:12px"></div>
                    <div class="wk-form-group"><label style="display:flex;align-items:center;gap:8px;cursor:pointer"><input type="checkbox" name="is_active" value="1" <?= $t['is_active']?'checked':'' ?>> Active</label></div>
                    <div style="font-size:11px;color:var(--wk-text-muted)">Updated: <?= date('M j, Y g:i A', strtotime($t['updated_at'])) ?></div>
                </div>
            </div>

            <!-- Send Test Email -->
            <div class="wk-card" style="margin-bottom:20px;border:2px solid var(--wk-purple)">
                <div class="wk-card-header" style="background:var(--wk-purple-soft)"><h2>📨 Send Test Email</h2></div>
                <div class="wk-card-body">
                    <div class="wk-form-group"><label>Send to</label><input type="email" id="testEmail" class="wk-input" placeholder="your@email.com"></div>
                    <button type="button" onclick="sendTestEmail()" id="testSendBtn" class="wk-btn wk-btn-primary wk-btn-sm" style="width:100%;justify-content:center">Send Test Email →</button>
                    <div id="testResult" style="margin-top:8px;font-size:12px;font-weight:700;min-height:18px"></div>
                </div>
            </div>

            <div class="wk-card" style="margin-bottom:20px">
                <div class="wk-card-header"><h2>Variables</h2></div>
                <div class="wk-card-body" style="max-height:350px;overflow-y:auto">
                    <?php foreach ($variables as $var => $desc): ?>
                    <div style="margin-bottom:8px;cursor:pointer;padding:5px 7px;border-radius:5px;transition:background .15s" onclick="insertVariable('<?= $e($var) ?>')" onmouseover="this.style.background='var(--wk-bg)'" onmouseout="this.style.background='transparent'">
                        <code style="background:var(--wk-purple-soft);color:var(--wk-purple);padding:2px 5px;border-radius:3px;font-size:11px;font-weight:700"><?= $e($var) ?></code>
                        <span style="font-size:11px;color:var(--wk-text-muted);margin-left:4px"><?= $e($desc) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <button type="submit" class="wk-btn wk-btn-primary" style="width:100%;justify-content:center">Save Template</button>
            <a href="<?= $url('admin/email-templates/preview/'.$t['id']) ?>" target="_blank" class="wk-btn wk-btn-secondary" style="width:100%;justify-content:center;margin-top:10px">👁 Preview</a>
        </div>
    </div>
</form>

<script>
const editor = document.getElementById('editor'), hidden = document.getElementById('bodyHidden'), sourceView = document.getElementById('sourceView');
let sourceMode = false;
// Scope to this editor's own form — the layout renders other forms first, so a
// document-wide selector would bind to the wrong one.
hidden.closest('form').addEventListener('submit', function() { if(sourceMode) editor.innerHTML=sourceView.value; hidden.value=editor.innerHTML; });
function execCmd(c,v){document.execCommand(c,false,v||null);editor.focus();}
function insertVariable(v){if(sourceMode){sourceView.value+=v;return;}const s=document.createElement('span');s.style.cssText='background:#ede9fe;color:#8b5cf6;padding:1px 4px;border-radius:3px;font-family:monospace;font-size:12px;font-weight:700';s.textContent=v;const sel=window.getSelection();if(sel.rangeCount){const r=sel.getRangeAt(0);r.deleteContents();r.insertNode(s);r.setStartAfter(s);sel.removeAllRanges();sel.addRange(r);}else editor.appendChild(s);editor.focus();}
function insertLink(){const u=prompt('URL:','https://');if(u)execCmd('createLink',u);}
function insertButton(){const t=prompt('Button text:','Shop Now →'),u=prompt('URL:','{{store_url}}');if(t&&u)document.execCommand('insertHTML',false,'<div style="text-align:center;margin:24px 0"><a href="'+u+'" style="display:inline-block;background:linear-gradient(135deg,#8b5cf6,#ec4899);color:#fff;padding:14px 32px;border-radius:8px;text-decoration:none;font-weight:800">'+t+'</a></div>');editor.focus();}
function insertDivider(){document.execCommand('insertHTML',false,'<div style="margin:24px 0;border-top:1px solid #e8e5df"></div>');editor.focus();}
function toggleSource(){sourceMode=!sourceMode;if(sourceMode){sourceView.value=editor.innerHTML;editor.style.display='none';sourceView.style.display='block';}else{editor.innerHTML=sourceView.value;sourceView.style.display='none';editor.style.display='block';}}

async function sendTestEmail() {
    const email = document.getElementById('testEmail').value.trim();
    const btn = document.getElementById('testSendBtn');
    const result = document.getElementById('testResult');
    if (!email) { result.innerHTML = '<span style="color:var(--wk-red)">Enter an email address</span>'; return; }
    btn.disabled = true; btn.textContent = 'Sending...'; result.innerHTML = '';
    const form = new FormData(); form.append('test_email', email);
    try {
        const res = await fetch('<?= $url('admin/email-templates/test-send/'.$t['id']) ?>', {method:'POST', body:form});
        const data = await res.json();
        result.innerHTML = data.success
            ? '<span style="color:var(--wk-green)">✓ ' + data.message + '</span>'
            : '<span style="color:var(--wk-red)">✗ ' + data.message + '</span>';
    } catch(e) { result.innerHTML = '<span style="color:var(--wk-red)">Network error</span>'; }
    btn.disabled = false; btn.textContent = 'Send Test Email →';
}
</script>
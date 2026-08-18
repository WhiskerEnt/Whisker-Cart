<?php $e=fn($v)=>\Core\View::e($v); $url=fn($p)=>\Core\View::url($p); $p=$page; ?>
<div style="display:flex;gap:12px;margin-bottom:24px">
    <a href="<?= $url('admin/pages') ?>" class="wk-btn wk-btn-secondary">← Back</a>
    <a href="<?= $url('page/'.$p['slug']) ?>" target="_blank" class="wk-btn wk-btn-secondary">👁 View Page</a>
</div>
<form method="POST" action="<?= $url('admin/pages/update/'.$p['id']) ?>">
    <?= \Core\Session::csrfField() ?>
    <div style="display:grid;grid-template-columns:2.5fr 1fr;gap:20px">
        <div>
            <div class="wk-card" style="margin-bottom:20px">
                <div class="wk-card-body"><div class="wk-form-group"><label>Page Title</label><input type="text" name="title" class="wk-input" value="<?= $e($p['title']) ?>" required></div></div>
            </div>
            <div class="wk-card">
                <div class="wk-card-header"><h2>Content</h2></div>
                <div class="wk-card-body" style="padding:0">
                    <div style="display:flex;flex-wrap:wrap;gap:3px;padding:10px 14px;border-bottom:1px solid var(--wk-border);background:var(--wk-bg)">
                        <button type="button" onclick="execCmd('bold')" style="background:none;border:1px solid var(--wk-border);border-radius:4px;padding:5px 9px;cursor:pointer;font-weight:900;font-size:13px">B</button>
                        <button type="button" onclick="execCmd('italic')" style="background:none;border:1px solid var(--wk-border);border-radius:4px;padding:5px 9px;cursor:pointer;font-style:italic;font-size:13px">I</button>
                        <button type="button" onclick="execCmd('underline')" style="background:none;border:1px solid var(--wk-border);border-radius:4px;padding:5px 9px;cursor:pointer;text-decoration:underline;font-size:13px">U</button>
                        <span style="width:1px;background:var(--wk-border);margin:0 3px"></span>
                        <button type="button" onclick="execCmd('formatBlock','<h2>')" style="background:none;border:1px solid var(--wk-border);border-radius:4px;padding:5px 8px;cursor:pointer;font-size:11px;font-weight:800">H2</button>
                        <button type="button" onclick="execCmd('formatBlock','<h3>')" style="background:none;border:1px solid var(--wk-border);border-radius:4px;padding:5px 8px;cursor:pointer;font-size:11px;font-weight:800">H3</button>
                        <button type="button" onclick="execCmd('formatBlock','<p>')" style="background:none;border:1px solid var(--wk-border);border-radius:4px;padding:5px 8px;cursor:pointer;font-size:11px">¶</button>
                        <button type="button" onclick="execCmd('insertUnorderedList')" style="background:none;border:1px solid var(--wk-border);border-radius:4px;padding:5px 8px;cursor:pointer;font-size:11px">• List</button>
                        <button type="button" onclick="execCmd('insertOrderedList')" style="background:none;border:1px solid var(--wk-border);border-radius:4px;padding:5px 8px;cursor:pointer;font-size:11px">1. List</button>
                        <button type="button" onclick="var u=prompt('URL:','https://');if(u)execCmd('createLink',u)" style="background:none;border:1px solid var(--wk-border);border-radius:4px;padding:5px 8px;cursor:pointer;font-size:11px">🔗</button>
                        <button type="button" onclick="execCmd('insertHorizontalRule')" style="background:none;border:1px solid var(--wk-border);border-radius:4px;padding:5px 8px;cursor:pointer;font-size:11px">―</button>
                        <span style="flex:1"></span>
                        <button type="button" onclick="toggleSource()" style="background:#1e1b2e;color:#e2e8f0;border:none;border-radius:4px;padding:5px 10px;cursor:pointer;font-size:11px;font-family:var(--font-mono);font-weight:700">&lt;/&gt;</button>
                    </div>
                    <div id="editor" contenteditable="true" style="min-height:500px;padding:28px;font-size:15px;line-height:1.8;outline:none"><?= $p['content'] ?></div>
                    <textarea name="content" id="bodyHidden" style="display:none"><?= $e($p['content']) ?></textarea>
                    <textarea id="sourceView" style="display:none;width:100%;min-height:500px;padding:20px;font-family:var(--font-mono);font-size:12px;border:none;outline:none;resize:vertical;background:#1e1b2e;color:#e2e8f0;line-height:1.6"></textarea>
                </div>
            </div>
        </div>
        <div>
            <div class="wk-card" style="margin-bottom:20px">
                <div class="wk-card-header"><h2>Settings</h2></div>
                <div class="wk-card-body">
                    <div class="wk-form-group"><label>URL</label><div style="font-family:var(--font-mono);font-size:12px;color:var(--wk-purple);padding:8px 12px;background:var(--wk-bg);border-radius:6px">/page/<?= $e($p['slug']) ?></div></div>
                    <div class="wk-form-group"><label style="display:flex;align-items:center;gap:8px;cursor:pointer"><input type="checkbox" name="is_active" value="1" <?= $p['is_active']?'checked':'' ?>> Published</label></div>
                    <div style="font-size:11px;color:var(--wk-text-muted)">Updated: <?= date('M j, Y g:i A', strtotime($p['updated_at'])) ?></div>
                </div>
            </div>
            <button type="submit" class="wk-btn wk-btn-primary" style="width:100%;justify-content:center">Save Page</button>
        </div>
    </div>
</form>
<script>
const editor=document.getElementById('editor'),hidden=document.getElementById('bodyHidden'),sourceView=document.getElementById('sourceView');let sourceMode=false;
// Scope to this editor's own form — the layout renders other forms first, so a
// document-wide selector would bind to the wrong one.
hidden.closest('form').addEventListener('submit',function(){if(sourceMode)editor.innerHTML=sourceView.value;hidden.value=editor.innerHTML;});
function execCmd(c,v){document.execCommand(c,false,v||null);editor.focus();}
function toggleSource(){sourceMode=!sourceMode;if(sourceMode){sourceView.value=editor.innerHTML;editor.style.display='none';sourceView.style.display='block';}else{editor.innerHTML=sourceView.value;sourceView.style.display='none';editor.style.display='block';}}
</script>
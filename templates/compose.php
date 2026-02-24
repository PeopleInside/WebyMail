<?php
/**
 * Compose / Reply / Forward template
 * @var array  $prefill  pre-filled fields (to, subject, reply_to, in_reply_to, body_html, body_text)
 * @var int    $replyMsg  message number being replied to (0 = new)
 * @var string $folder
 * @var array  $accounts  all accounts (for From selector)
 * @var int    $currentAccountId
 * @var string $signature  HTML signature for the active account's user
 */
$isReply   = !empty($replyMsg);
$folder    = $folder    ?? 'INBOX';
$folderEnc = urlencode($folder);
$signature = $signature ?? '';
?>

<div class="wm-compose-wrap" style="height:calc(100dvh - 52px)">

    <!-- Toolbar -->
    <div class="wm-toolbar">
        <a href="javascript:history.back()" class="btn btn-ghost btn-sm">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
            Cancel
        </a>
        <div class="wm-topbar-spacer"></div>
        <button type="submit" form="compose-form" name="save_draft" value="1" class="btn btn-outline btn-sm" style="margin-right:.5rem">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16v16H4z"/><path d="M4 9h16"/><path d="M9 4v5"/></svg>
            Save draft
        </button>
        <button type="submit" form="compose-form" class="btn btn-primary btn-sm">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
            Send
        </button>
    </div>

    <form id="compose-form" method="post" action="?action=send" enctype="multipart/form-data" style="display:contents">
        <!-- Hidden fields -->
        <input type="hidden" name="folder"       value="<?= htmlspecialchars($folder) ?>">
        <input type="hidden" name="in_reply_to"  value="<?= htmlspecialchars($prefill['in_reply_to'] ?? '') ?>">
        <input type="hidden" name="reply_msg"    value="<?= (int)($replyMsg ?? 0) ?>">
        <input type="hidden" name="body_html"    id="body-html-hidden">

        <!-- Address fields -->
        <div class="wm-compose-fields">

            <div class="wm-compose-field">
                <label for="to">To</label>
                <input type="text" id="to" name="to" autocomplete="off"
                       value="<?= htmlspecialchars($prefill['to'] ?? '') ?>"
                       placeholder="recipient@example.com"
                       required>
                <button type="button" id="show-cc"  class="btn btn-ghost btn-sm" style="font-size:.75rem">Cc</button>
                <button type="button" id="show-bcc" class="btn btn-ghost btn-sm" style="font-size:.75rem">Bcc</button>
            </div>

            <div class="wm-compose-field" id="cc-row" style="display:none">
                <label for="cc">Cc</label>
                <input type="text" id="cc" name="cc" autocomplete="off"
                       value="<?= htmlspecialchars($prefill['cc'] ?? '') ?>"
                       placeholder="cc@example.com">
            </div>

            <div class="wm-compose-field" id="bcc-row" style="display:none">
                <label for="bcc">Bcc</label>
                <input type="text" id="bcc" name="bcc" autocomplete="off"
                       placeholder="bcc@example.com">
            </div>

            <!-- Reply-To (shown when composing new mail) -->
            <?php if (!$isReply): ?>
            <div class="wm-compose-field" id="replyto-row" style="display:none">
                <label for="reply_to">Reply-To</label>
                <input type="text" id="reply_to" name="reply_to" autocomplete="off"
                       value="<?= htmlspecialchars($prefill['reply_to'] ?? '') ?>"
                       placeholder="replies-to@example.com">
            </div>
            <?php endif; ?>

            <div class="wm-compose-field">
                <label for="subject">Subject</label>
                <input type="text" id="subject" name="subject" autocomplete="off"
                       value="<?= htmlspecialchars($prefill['subject'] ?? '') ?>"
                       placeholder="Subject…">
                 <?php if (!$isReply): ?>
                <button type="button" id="show-replyto" class="btn btn-ghost btn-sm" style="font-size:.75rem">Reply-To</button>
                <?php endif; ?>
            </div>

            <div class="wm-compose-field">
                <label for="attachments">Attachments</label>
                <input type="file" id="attachments" name="attachments[]" multiple
                       style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0);border:0;padding:0;">
                <button type="button" class="btn btn-outline btn-sm" id="attachment-trigger" aria-label="Add attachments">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.44 11.05l-9.19 9.19a5 5 0 01-7.07-7.07l9.19-9.19a3.5 3.5 0 114.95 4.95l-8.48 8.48a2 2 0 01-2.83-2.83l7.78-7.78"/></svg>
                    Add attachments
                </button>
                <span id="attachment-label" style="font-size:.85rem;color:var(--wm-text-muted);margin-left:.35rem"></span>
            </div>
        </div>

        <!-- Quill HTML editor -->
        <div id="editor-container">
            <input type="file" id="inline-image-input" accept="image/*" style="display:none">
            <div id="quill-toolbar">
                <div class="wm-editor-group">
                    <label class="wm-editor-label">Size
                        <select data-cmd="fontSize">
                            <option value="3">Normal</option>
                            <option value="2">Small</option>
                            <option value="4">Large</option>
                            <option value="5">X-Large</option>
                        </select>
                    </label>
                </div>
                <div class="wm-editor-group">
                    <button type="button" class="ql-bold" data-cmd="bold" title="Bold"><b>B</b></button>
                    <button type="button" class="ql-italic" data-cmd="italic" title="Italic"><i>I</i></button>
                    <button type="button" class="ql-underline" data-cmd="underline" title="Underline"><u>U</u></button>
                    <button type="button" class="ql-strike" data-cmd="strikeThrough" title="Strike"><s>S</s></button>
                </div>
                 <div class="wm-editor-group">
                     <label class="wm-editor-label">Color
                         <input type="color" data-cmd="foreColor" value="#2563eb" id="editor-color-picker">
                     </label>
                     <button type="button" class="btn btn-outline btn-sm" id="apply-color" aria-label="Apply text color">Apply color</button>
                     <label class="wm-editor-label">Highlight
                         <input type="color" data-cmd="hiliteColor" value="#ffff00" id="editor-highlight-picker">
                     </label>
                     <button type="button" class="btn btn-outline btn-sm" id="apply-highlight" aria-label="Apply highlight color">Apply highlight</button>
                     <button type="button" class="ql-color-reset" data-cmd="clearColor" title="Reset color">Reset color</button>
                 </div>
                <div class="wm-editor-group">
                    <button type="button" class="ql-list" data-cmd="insertOrderedList" title="Numbered list">1.</button>
                    <button type="button" class="ql-list" data-cmd="insertUnorderedList" title="Bullet list">•</button>
                    <button type="button" class="ql-indent" data-cmd="outdent" title="Outdent">◀</button>
                    <button type="button" class="ql-indent" data-cmd="indent" title="Indent">▶</button>
                </div>
                    <div class="wm-editor-group">
                        <button type="button" class="ql-link" data-cmd="createLink" title="Insert link">🔗</button>
                        <button type="button" class="ql-image-url" data-cmd="insertImage" title="Insert image from URL" aria-label="Insert image from URL">🖼️ URL</button>
                        <button type="button" class="ql-image-upload" data-cmd="insertImageUpload" title="Insert image from device" aria-label="Insert image from device">🖼️ File</button>
                        <button type="button" class="ql-blockquote" data-cmd="formatBlock" data-value="blockquote" title="Quote">❝</button>
                        <button type="button" class="ql-clean" data-cmd="removeFormat" title="Clear formatting">✕</button>
                    </div>
            </div>
            <div id="quill-editor" style="flex:1;min-height:300px"></div>
        </div>
    </form>
</div>

<script>
(function() {
    var form       = document.getElementById('compose-form');
    var toolbar    = document.getElementById('quill-toolbar');
    var editorEl   = document.getElementById('quill-editor');
    var hidden     = document.getElementById('body-html-hidden');
    var imageInput = document.getElementById('inline-image-input');
    var inlineLimit = 2 * 1024 * 1024; // 2 MB inline embed cap
    var initialHtml = <?= json_encode($prefill['body_html'] ?? '') ?>;
    var signature   = <?= json_encode($signature) ?>;
    var defaultColor = '';
    var imgOverlay = null;
    var dragState = null;

    function buildInitialContent() {
        var content = '';
        if (initialHtml) content += initialHtml;
        if (signature) {
            content += '<br><hr style="border:none;border-top:1px solid #ccc;margin:8px 0">' + signature;
        }
        return content;
    }

    editorEl.contentEditable = 'true';
    editorEl.innerHTML = buildInitialContent() || '<p><br></p>';
    defaultColor = getComputedStyle(editorEl).color;
    try { document.execCommand('enableObjectResizing', false, true); } catch (e) {}

    // Lightweight image resize handles (4 corners)
    (function setupImageOverlay() {
        var style = document.createElement('style');
        style.textContent = '#img-resize-overlay{position:absolute;border:1px dashed var(--wm-primary);pointer-events:none;z-index:10}#img-resize-overlay .handle{width:10px;height:10px;background:var(--wm-primary);border:2px solid #fff;box-shadow:0 0 0 1px var(--wm-primary);position:absolute;pointer-events:auto;cursor:nwse-resize;border-radius:3px}#img-resize-overlay .handle.br{cursor:nwse-resize;right:-6px;bottom:-6px}#img-resize-overlay .handle.bl{cursor:nesw-resize;left:-6px;bottom:-6px}#img-resize-overlay .handle.tr{cursor:nesw-resize;right:-6px;top:-6px}#img-resize-overlay .handle.tl{cursor:nwse-resize;left:-6px;top:-6px}';
        document.head.appendChild(style);

        imgOverlay = document.createElement('div');
        imgOverlay.id = 'img-resize-overlay';
        imgOverlay.style.display = 'none';
        ['tl','tr','bl','br'].forEach(function(pos) {
            var h = document.createElement('div');
            h.className = 'handle ' + pos;
            h.dataset.dir = pos;
            imgOverlay.appendChild(h);
        });
        document.body.appendChild(imgOverlay);
    })();

    if (toolbar) {
        toolbar.querySelectorAll('button[data-cmd]').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                var cmd = btn.dataset.cmd;
                var val = btn.dataset.value || null;
                if (cmd === 'createLink') {
                    val = prompt('Enter URL');
                    if (!val) return;
                }
                if (cmd === 'insertImage') {
                    val = prompt('Image URL');
                    if (!val) return;
                }
                if (cmd === 'insertImageUpload' && imageInput) {
                    imageInput.click();
                    return;
                }
                if (cmd === 'clearColor') {
                    var currentDefault = getComputedStyle(editorEl).color || defaultColor;
                    document.execCommand('foreColor', false, currentDefault);
                    document.execCommand('hiliteColor', false, 'transparent');
                    editorEl.focus();
                    return;
                }
                document.execCommand(cmd, false, val);
                editorEl.focus();
            });
        });
        toolbar.querySelectorAll('input[data-cmd], select[data-cmd]').forEach(function(el) {
            el.addEventListener('change', function() {
                var cmd = el.dataset.cmd;
                var val = el.value;
                document.execCommand(cmd, false, val);
                editorEl.focus();
            });
        });
    }

    // Explicit apply buttons for color/highlight
    document.getElementById('apply-color')?.addEventListener('click', function() {
        var picker = document.getElementById('editor-color-picker');
        if (picker) {
            document.execCommand('foreColor', false, picker.value);
            editorEl.focus();
        }
    });
    document.getElementById('apply-highlight')?.addEventListener('click', function() {
        var picker = document.getElementById('editor-highlight-picker');
        if (picker) {
            document.execCommand('hiliteColor', false, picker.value);
            editorEl.focus();
        }
    });

    if (imageInput) {
        imageInput.addEventListener('change', function() {
            var file = imageInput.files && imageInput.files[0];
            if (!file) return;
            if (file.size > inlineLimit) {
                alert('Inline editor images are limited to 2 MB. Please attach the file instead for larger images (subject to server attachment limits, typically 50 MB).');
                imageInput.value = '';
                return;
            }
            var reader = new FileReader();
            reader.onload = function(e) {
                var dataUrl = e.target && e.target.result;
                if (dataUrl) {
                    document.execCommand('insertImage', false, dataUrl);
                    editorEl.focus();
                }
            };
            reader.readAsDataURL(file);
            imageInput.value = '';
        });
    }

    // Attachment picker helper
    var attachInput = document.getElementById('attachments');
    var attachBtn   = document.getElementById('attachment-trigger');
    var attachLabel = document.getElementById('attachment-label');
    if (attachBtn && attachInput) {
        if (attachLabel) attachLabel.textContent = 'No files selected';
        attachBtn.addEventListener('click', function() {
            attachInput.click();
        });
        attachInput.addEventListener('change', function() {
            if (!attachLabel) return;
            var files = Array.from(attachInput.files || []).map(function(f) { return f.name; });
            attachLabel.textContent = files.length ? files.join(', ') : '';
        });
    }

    // Image resize overlay logic
    function hideOverlay() {
        if (imgOverlay) {
            imgOverlay.style.display = 'none';
            imgOverlay._target = null;
        }
        dragState = null;
    }
    function showOverlay(target) {
        if (!imgOverlay || !target) return;
        var rect = target.getBoundingClientRect();
        imgOverlay._target = target;
        imgOverlay.style.display = 'block';
        imgOverlay.style.width  = rect.width + 'px';
        imgOverlay.style.height = rect.height + 'px';
        imgOverlay.style.left   = window.scrollX + rect.left + 'px';
        imgOverlay.style.top    = window.scrollY + rect.top + 'px';
    }
    function startDrag(e, dir) {
        var target = imgOverlay?._target;
        if (!target) return;
        e.preventDefault();
        dragState = {
            target: target,
            startX: e.clientX,
            startY: e.clientY,
            startW: target.getBoundingClientRect().width,
            startH: target.getBoundingClientRect().height,
            dir: dir
        };
    }
    function onMove(e) {
        if (!dragState) return;
        e.preventDefault();
        var dx = e.clientX - dragState.startX;
        var dy = e.clientY - dragState.startY;
        var dir = dragState.dir;
        var newW = dragState.startW + dx * (dir.x || 0);
        var newH = dragState.startH + dy * (dir.y || 0);
        newW = Math.max(30, newW);
        newH = Math.max(30, newH);
        dragState.target.style.width = newW + 'px';
        dragState.target.style.height = newH + 'px';
        showOverlay(dragState.target);
    }
    function onUp() { dragState = null; }
    if (imgOverlay) {
        imgOverlay.addEventListener('mousedown', function(e) {
            var dirKey = e.target.dataset.dir;
            if (!dirKey) return;
            var dir = {
                tl: {x:-1, y:-1},
                tr: {x: 1, y:-1},
                bl: {x:-1, y: 1},
                br: {x: 1, y: 1},
            }[dirKey];
            startDrag(e, dir);
        });
        document.addEventListener('mousemove', onMove);
        document.addEventListener('mouseup', onUp);
    }

    // Show handles on image click
    editorEl.addEventListener('click', function(e) {
        var target = e.target;
        if (target && target.tagName === 'IMG') {
            showOverlay(target);
        } else {
            hideOverlay();
        }
    });

    window.addEventListener('scroll', function() {
        if (imgOverlay && imgOverlay._target) {
            showOverlay(imgOverlay._target);
        }
    });

    form.addEventListener('submit', function() {
        hidden.value = editorEl.innerHTML;
    });

})();
</script>

<?php if (!$isReply): ?>
<script>
// Show reply-to field toggle
document.getElementById('show-replyto')?.addEventListener('click', function() {
    var row = document.getElementById('replyto-row');
    row.style.display = 'flex';
    this.style.display = 'none';
});
</script>
<?php endif; ?>

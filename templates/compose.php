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

            <!-- From (account selector) -->
            <?php if (!empty($accounts) && count($accounts) > 1): ?>
            <div class="wm-compose-field">
                <label for="from_account">From</label>
                <select name="from_account" id="from_account" class="form-control" style="flex:1;height:auto;padding:.15rem .5rem;font-size:.9rem">
                    <?php foreach ($accounts as $acc): ?>
                    <option value="<?= (int)$acc['id'] ?>" <?= $acc['id'] == ($currentAccountId ?? 0) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($acc['label']) ?> &lt;<?= htmlspecialchars($acc['email']) ?>&gt;
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

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
                <input type="file" id="attachments" name="attachments[]" multiple>
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
                        <input type="color" data-cmd="foreColor" value="#2563eb">
                    </label>
                    <label class="wm-editor-label">Highlight
                        <input type="color" data-cmd="hiliteColor" value="#ffff00">
                    </label>
                    <button type="button" class="ql-clean" data-cmd="clearColor" title="Reset color">Reset color</button>
                </div>
                <div class="wm-editor-group">
                    <button type="button" class="ql-list" data-cmd="insertOrderedList" title="Numbered list">1.</button>
                    <button type="button" class="ql-list" data-cmd="insertUnorderedList" title="Bullet list">•</button>
                    <button type="button" class="ql-indent" data-cmd="outdent" title="Outdent">◀</button>
                    <button type="button" class="ql-indent" data-cmd="indent" title="Indent">▶</button>
                </div>
                    <div class="wm-editor-group">
                        <button type="button" class="ql-link" data-cmd="createLink" title="Insert link">🔗</button>
                        <button type="button" class="ql-image" data-cmd="insertImage" title="Insert image from URL">🖼️ URL</button>
                        <button type="button" class="ql-image" data-cmd="insertImageUpload" title="Insert image from device">🖼️ File</button>
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
    var initialHtml = <?= json_encode($prefill['body_html'] ?? '') ?>;
    var signature   = <?= json_encode($signature) ?>;
    var defaultColor = '';

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
                    document.execCommand('removeFormat', false, null);
                    document.execCommand('foreColor', false, defaultColor);
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

    if (imageInput) {
        imageInput.addEventListener('change', function() {
            var file = imageInput.files && imageInput.files[0];
            if (!file) return;
            var reader = new FileReader();
            reader.onload = function(e) {
                var dataUrl = e.target?.result;
                if (dataUrl) {
                    document.execCommand('insertImage', false, dataUrl);
                    editorEl.focus();
                }
            };
            reader.readAsDataURL(file);
            imageInput.value = '';
        });
    }

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

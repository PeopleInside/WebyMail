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
        <button type="submit" form="compose-form" class="btn btn-primary btn-sm">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
            Send
        </button>
    </div>

    <form id="compose-form" method="post" action="?action=send" style="display:contents">
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
        </div>

        <!-- Quill HTML editor -->
        <div id="editor-container">
            <div id="quill-toolbar">
                <span class="ql-formats">
                    <select class="ql-font"></select>
                    <select class="ql-size"></select>
                </span>
                <span class="ql-formats">
                    <button class="ql-bold"></button>
                    <button class="ql-italic"></button>
                    <button class="ql-underline"></button>
                    <button class="ql-strike"></button>
                </span>
                <span class="ql-formats">
                    <select class="ql-color"></select>
                    <select class="ql-background"></select>
                </span>
                <span class="ql-formats">
                    <button class="ql-list" value="ordered"></button>
                    <button class="ql-list" value="bullet"></button>
                    <button class="ql-indent" value="-1"></button>
                    <button class="ql-indent" value="+1"></button>
                </span>
                <span class="ql-formats">
                    <button class="ql-link"></button>
                    <button class="ql-image"></button>
                    <button class="ql-blockquote"></button>
                </span>
                <span class="ql-formats">
                    <button class="ql-clean"></button>
                </span>
            </div>
            <div id="quill-editor" style="flex:1;min-height:300px"></div>
        </div>
    </form>
</div>

<!-- Quill from CDN -->
<link  rel="stylesheet" href="https://cdn.jsdelivr.net/npm/quill@2/dist/quill.snow.css">
<script src="https://cdn.jsdelivr.net/npm/quill@2/dist/quill.js"></script>
<script>
(function() {
    var quill = new Quill('#quill-editor', {
        theme:   'snow',
        modules: { toolbar: '#quill-toolbar' },
    });

    // Pre-populate with reply quote + signature
    var initialHtml = <?= json_encode($prefill['body_html'] ?? '') ?>;
    var signature   = <?= json_encode($signature) ?>;

    var content = '';
    if (initialHtml) {
        content += initialHtml;
    }
    if (signature) {
        content += '<br><hr style="border:none;border-top:1px solid #ccc;margin:8px 0">' + signature;
    }
    if (content) {
        quill.clipboard.dangerouslyPasteHTML(0, content);
    }

    // Move cursor to top for replies/forwards
    <?php if ($isReply): ?>
    quill.setSelection(0, 0);
    <?php endif; ?>

    // Sync on submit
    document.getElementById('compose-form').addEventListener('submit', function() {
        document.getElementById('body-html-hidden').value = quill.root.innerHTML;
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

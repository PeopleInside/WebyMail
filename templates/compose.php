<?php
/**
 * Compose / Reply / Forward template
 * @var array  $prefill  pre-filled fields (to, subject, reply_to, in_reply_to, body_html, body_text)
 * @var int    $replyMsg  message number being replied to (0 = new)
 * @var string $folder
 * @var array  $accounts  all accounts (for From selector)
 * @var int    $currentAccountId
 * @var string $signature  HTML signature for the active account's user
 * @var bool   $resumeSend whether we are returning from a failed send
 */
$isReply   = !empty($replyMsg);
$folder    = $folder    ?? 'INBOX';
$folderEnc = urlencode($folder);
$signature = $signature ?? '';
$resumeSend = !empty($resumeSend);
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
        <span id="draft-status" style="font-size:.75rem;color:var(--wm-text-muted);margin-right:1rem;display:none"></span>
        <button type="submit" form="compose-form" class="btn btn-primary btn-sm">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
            Send
        </button>
        <div style="position:relative;margin-left:.5rem">
            <button type="button" id="msg-options-btn" class="btn btn-ghost btn-sm" title="Message options">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
                Options
            </button>
            <div id="msg-options-menu" class="wm-options-menu" style="display:none">
                <div style="margin-bottom:.75rem">
                    <label style="display:block;font-size:.75rem;font-weight:600;margin-bottom:.35rem;color:var(--wm-text-muted)">Priority</label>
                    <select name="priority" form="compose-form" class="form-control" style="font-size:.82rem;height:32px">
                        <option value="normal" <?= ($prefill['priority'] ?? '') === 'normal' ? 'selected' : '' ?>>Normal</option>
                        <option value="high"   <?= ($prefill['priority'] ?? '') === 'high'   ? 'selected' : '' ?>>High</option>
                        <option value="low"    <?= ($prefill['priority'] ?? '') === 'low'    ? 'selected' : '' ?>>Low</option>
                    </select>
                </div>
                <div style="display:flex;align-items:center;gap:.5rem;cursor:pointer">
                    <input type="checkbox" name="request_read_receipt" form="compose-form" id="req-receipt" value="1" style="width:14px;height:14px" <?= !empty($prefill['request_read_receipt']) ? 'checked' : '' ?>>
                    <label for="req-receipt" style="font-size:.82rem;cursor:pointer">Request read receipt</label>
                </div>
            </div>
        </div>
    </div>

    <form id="compose-form" method="post" action="?action=send" enctype="multipart/form-data" style="display:contents">
        <?= csrfInput() ?>
        <!-- Hidden fields -->
        <input type="hidden" name="folder"       value="<?= htmlspecialchars($folder) ?>">
        <input type="hidden" name="in_reply_to"  value="<?= htmlspecialchars($prefill['in_reply_to'] ?? '') ?>">
        <input type="hidden" name="reply_msg"    value="<?= (int)($replyMsg ?? 0) ?>">
        <input type="hidden" name="draft_uid"    id="draft-uid" value="<?= (int)($prefill['draft_uid'] ?? 0) ?>">
        <input type="hidden" name="draft_folder" value="<?= htmlspecialchars($prefill['draft_folder'] ?? $folder) ?>">
        <input type="hidden" name="body_html"    id="body-html-hidden">

        <!-- Address fields -->
        <div class="wm-compose-fields">

            <div class="wm-compose-field">
                <label for="to">To</label>
                <input type="text" id="to" name="to" autocomplete="off"
                       value="<?= htmlspecialchars($prefill['to'] ?? '') ?>"
                       placeholder="recipient@example.com">
                <button type="button" class="btn btn-ghost btn-icon open-contacts" data-target="to" title="Pick from contacts">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
                </button>
                <button type="button" id="show-cc"  class="btn btn-ghost btn-sm" style="font-size:.75rem">Cc</button>
                <button type="button" id="show-bcc" class="btn btn-ghost btn-sm" style="font-size:.75rem">Bcc</button>
            </div>

            <div class="wm-compose-field" id="cc-row" style="display:none">
                <label for="cc">Cc</label>
                <input type="text" id="cc" name="cc" autocomplete="off"
                       value="<?= htmlspecialchars($prefill['cc'] ?? '') ?>"
                       placeholder="cc@example.com">
                <button type="button" class="btn btn-ghost btn-icon open-contacts" data-target="cc" title="Pick from contacts">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
                </button>
            </div>

            <div class="wm-compose-field" id="bcc-row" style="display:none">
                <label for="bcc">Bcc</label>
                <input type="text" id="bcc" name="bcc" autocomplete="off"
                       placeholder="bcc@example.com">
                <button type="button" class="btn btn-ghost btn-icon open-contacts" data-target="bcc" title="Pick from contacts">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
                </button>
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

            <div class="wm-compose-field" style="border-top:1px solid var(--wm-border);padding-top:.5rem;margin-top:.5rem;flex-wrap:wrap;gap:1.5rem">
                <label>Attachments</label>
                <div style="display:flex;flex-wrap:wrap;gap:.35rem;align-items:center">
                    <input type="file" id="attachments" name="attachments[]" multiple
                           style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0);border:0;padding:0;">
                    <button type="button" class="btn btn-outline btn-sm" id="attachment-trigger" aria-label="Add attachments">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.44 11.05l-9.19 9.19a5 5 0 01-7.07-7.07l9.19-9.19a3.5 3.5 0 114.95 4.95l-8.48 8.48a2 2 0 01-2.83-2.83l7.78-7.78"/></svg>
                        Add attachment
                    </button>
                    <div id="attachment-list" style="display:flex;flex-wrap:wrap;gap:.35rem;align-items:center">
                        <?php if (!empty($prefill['attachments'])): ?>
                            <?php foreach ($prefill['attachments'] as $att): ?>
                                <span class="existing-attachment" style="display:inline-flex;align-items:center;gap:.2rem;background:var(--wm-surface-2);border:1px solid var(--wm-border);border-radius:5px;padding:.15rem .45rem;font-size:.8rem" data-section="<?= htmlspecialchars($att['section']) ?>">
                                    <input type="hidden" name="keep_attachments[]" value="<?= htmlspecialchars($att['section']) ?>">
                                    <?= htmlspecialchars($att['filename']) ?>
                                    <button type="button" class="remove-existing" style="background:none;border:none;cursor:pointer;color:var(--wm-danger);font-size:1rem;line-height:1;padding:0 .1rem" title="Remove">×</button>
                                </span>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
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
                    <button type="button" data-cmd="justifyLeft" title="Align left">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="17" y1="10" x2="3" y2="10"/><line x1="21" y1="6" x2="3" y2="6"/><line x1="21" y1="14" x2="3" y2="14"/><line x1="17" y1="18" x2="3" y2="18"/></svg>
                    </button>
                    <button type="button" data-cmd="justifyCenter" title="Align center">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="10" x2="6" y2="10"/><line x1="21" y1="6" x2="3" y2="6"/><line x1="21" y1="14" x2="3" y2="14"/><line x1="18" y1="18" x2="6" y2="18"/></svg>
                    </button>
                    <button type="button" data-cmd="justifyRight" title="Align right">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="21" y1="10" x2="7" y2="10"/><line x1="21" y1="6" x2="3" y2="6"/><line x1="21" y1="14" x2="3" y2="14"/><line x1="21" y1="18" x2="7" y2="18"/></svg>
                    </button>
                    <button type="button" data-cmd="justifyFull" title="Justify">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="21" y1="10" x2="3" y2="10"/><line x1="21" y1="6" x2="3" y2="6"/><line x1="21" y1="14" x2="3" y2="14"/><line x1="21" y1="18" x2="3" y2="18"/></svg>
                    </button>
                </div>
                    <div class="wm-editor-group">
                        <button type="button" class="ql-link" data-cmd="createLink" title="Insert link">🔗</button>
                        <button type="button" class="ql-image-url" data-cmd="insertImage" title="Insert image from URL" aria-label="Insert image from URL">🖼️ URL</button>
                        <button type="button" class="ql-image-upload" data-cmd="insertImageUpload" title="Insert image from device" aria-label="Insert image from device">🖼️ File</button>
                        <button type="button" class="ql-blockquote" data-cmd="formatBlock" data-value="blockquote" title="Quote">❝</button>
                        <button type="button" class="ql-clean" data-cmd="removeFormat" title="Clear formatting">✕</button>
                    </div>
                    <div class="wm-editor-group">
                        <button type="button" id="source-toggle" title="Toggle HTML source">&lt;/&gt;</button>
                    </div>
            </div>
            <div id="quill-editor" style="flex:1;min-height:300px"></div>
            <textarea id="source-editor" style="display:none;flex:1;min-height:300px;width:100%;box-sizing:border-box;font-family:monospace;font-size:.85rem;padding:.75rem;border:none;border-top:1px solid var(--wm-border);resize:none;background:var(--wm-surface);color:var(--wm-text);outline:none" spellcheck="false"></textarea>
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
    var initialHtml = <?= json_encode((string)($prefill['body_html'] ?? '')) ?>;
    var signature   = <?= json_encode((string)$signature) ?>;
    var isEditDraft = <?= !empty($prefill['draft_uid']) ? 'true' : 'false' ?>;
    var resumeSend  = <?= $resumeSend ? 'true' : 'false' ?>;
    var defaultColor = '';
    var imgOverlay = null;
    var dragState = null;
    var MIN_IMG_SIZE = 30;

    // Accumulated attachment file list
    var pendingFiles = [];
    // Hidden inputs container for accumulated attachments
    var attachContainer = document.createElement('div');
    attachContainer.style.display = 'none';
    form.appendChild(attachContainer);

    function buildInitialContent() {
        var content = '';
        // Only add signature automatically for new messages/replies, not when resuming a draft
        // as the draft likely already contains the signature from the previous save.
        if (signature && !isEditDraft && !resumeSend) {
            content += '<p><br></p>' + signature + '<br><br>';
        }
        if (initialHtml) content += initialHtml;
        return content;
    }

    editorEl.contentEditable = 'true';
    editorEl.innerHTML = buildInitialContent() || '<p><br></p>';
    defaultColor = getComputedStyle(editorEl).color;
    try { document.execCommand('enableObjectResizing', false, true); } catch (e) {}

    // Lightweight image resize handles (4 corners)
    var resizeDirs = {
        tl: {x:-1, y:-1},
        tr: {x: 1, y:-1},
        bl: {x:-1, y: 1},
        br: {x: 1, y: 1},
    };

    (function setupImageOverlay() {
        var style = document.createElement('style');
        style.textContent = '#img-resize-overlay{position:absolute;border:1px dashed var(--wm-primary);pointer-events:none;z-index:10}#img-resize-overlay .handle{width:10px;height:10px;background:var(--wm-primary);border:2px solid #fff;box-shadow:0 0 0 1px var(--wm-primary);position:absolute;pointer-events:auto;cursor:nwse-resize;border-radius:3px;touch-action:none}#img-resize-overlay .handle.br{cursor:nwse-resize;right:-6px;bottom:-6px}#img-resize-overlay .handle.bl{cursor:nesw-resize;left:-6px;bottom:-6px}#img-resize-overlay .handle.tr{cursor:nesw-resize;right:-6px;top:-6px}#img-resize-overlay .handle.tl{cursor:nwse-resize;left:-6px;top:-6px}@media(pointer:coarse){#img-resize-overlay .handle{width:22px;height:22px;border-radius:4px}#img-resize-overlay .handle.br{right:-12px;bottom:-12px}#img-resize-overlay .handle.bl{left:-12px;bottom:-12px}#img-resize-overlay .handle.tr{right:-12px;top:-12px}#img-resize-overlay .handle.tl{left:-12px;top:-12px}}';
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

    // ── Link popover ──────────────────────────────────────────────────────
    var linkPopover = document.createElement('div');
    linkPopover.style.cssText = 'position:fixed;z-index:9999;display:none;align-items:center;gap:.4rem;' +
        'background:var(--wm-surface-2,#fff);border:1px solid var(--wm-border,#ccc);' +
        'border-radius:6px;padding:.35rem .5rem;box-shadow:0 2px 8px rgba(0,0,0,.15);' +
        'font-size:.82rem;max-width:320px;';
    document.body.appendChild(linkPopover);

    var currentAnchor = null;

    function removeLinkNode(a) {
        if (!a.parentNode) return;
        var frag = document.createDocumentFragment();
        while (a.firstChild) frag.appendChild(a.firstChild);
        a.parentNode.replaceChild(frag, a);
    }

    function hideLinkPopover() {
        linkPopover.style.display = 'none';
        currentAnchor = null;
    }

    function showLinkPopover(anchor) {
        currentAnchor = anchor;
        var href = anchor.getAttribute('href') || '';
        var rect = anchor.getBoundingClientRect();
        linkPopover.innerHTML = '';

        var urlSpan = document.createElement('span');
        urlSpan.title = href;
        urlSpan.textContent = href;
        urlSpan.style.cssText = 'overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:140px;color:var(--wm-primary,#2563eb);';
        linkPopover.appendChild(urlSpan);

        var sep = document.createElement('span');
        sep.style.cssText = 'width:1px;height:14px;background:var(--wm-border,#ccc);flex-shrink:0;';
        linkPopover.appendChild(sep);

        var openA = document.createElement('a');
        openA.href = href;
        openA.target = '_blank';
        openA.rel = 'noopener noreferrer';
        openA.title = 'Open in new tab';
        openA.style.cssText = 'display:flex;align-items:center;color:inherit;';
        openA.innerHTML = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>';
        linkPopover.appendChild(openA);

        var editBtn = document.createElement('button');
        editBtn.type = 'button';
        editBtn.title = 'Edit link';
        editBtn.style.cssText = 'border:none;background:none;cursor:pointer;padding:0;display:flex;align-items:center;color:inherit;';
        editBtn.innerHTML = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>';
        editBtn.addEventListener('mousedown', function(e) { e.preventDefault(); });
        editBtn.addEventListener('click', function() {
            var a = currentAnchor;
            hideLinkPopover();
            var newUrl = prompt('Edit URL', a.getAttribute('href') || '');
            if (newUrl === null) return;
            newUrl = newUrl.trim();
            if (newUrl === '') { removeLinkNode(a); } else { a.href = newUrl; }
            editorEl.focus();
            if (typeof checkDirty === 'function') checkDirty();
        });
        linkPopover.appendChild(editBtn);

        var removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.title = 'Remove link';
        removeBtn.style.cssText = 'border:none;background:none;cursor:pointer;padding:0;display:flex;align-items:center;color:inherit;';
        removeBtn.innerHTML = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';
        removeBtn.addEventListener('mousedown', function(e) { e.preventDefault(); });
        removeBtn.addEventListener('click', function() {
            var a = currentAnchor;
            hideLinkPopover();
            removeLinkNode(a);
            editorEl.focus();
            if (typeof checkDirty === 'function') checkDirty();
        });
        linkPopover.appendChild(removeBtn);

        linkPopover.style.display = 'flex';
        var left = Math.max(4, Math.min(rect.left, window.innerWidth - 324));
        var top  = rect.bottom + 5;
        if (top + 44 > window.innerHeight) top = rect.top - 49;
        linkPopover.style.left = left + 'px';
        linkPopover.style.top  = top  + 'px';
    }

    if (toolbar) {
        toolbar.querySelectorAll('button[data-cmd]').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                hideLinkPopover();
                var cmd = btn.dataset.cmd;
                var val = btn.dataset.value || null;
                if (cmd === 'createLink') {
                    var overlayTarget = imgOverlay && imgOverlay._target;
                    var existingAnchor = null;
                    var existingUrl = '';
                    if (overlayTarget) {
                        // Check if image is already wrapped in a link
                        var parentNode = overlayTarget.parentNode;
                        if (parentNode && parentNode.tagName === 'A') {
                            existingUrl = parentNode.href || '';
                        }
                    } else {
                        // Check if current text selection is already a link
                        var sel = window.getSelection();
                        if (sel && sel.rangeCount > 0) {
                            var container = sel.getRangeAt(0).commonAncestorContainer;
                            var node = container.nodeType === 3 ? container.parentNode : container;
                            while (node && node !== editorEl) {
                                if (node.tagName === 'A') { existingAnchor = node; existingUrl = node.getAttribute('href') || ''; break; }
                                node = node.parentNode;
                            }
                        }
                    }
                    val = prompt('Enter URL', existingUrl);
                    if (val === null) return; // cancelled
                    val = val.trim();
                    if (overlayTarget) {
                        var existing = overlayTarget.parentNode;
                        if (val === '') {
                            // Remove link if URL is empty
                            if (existing && existing.tagName === 'A') {
                                existing.parentNode.insertBefore(overlayTarget, existing);
                                existing.parentNode.removeChild(existing);
                            }
                        } else if (existing && existing.tagName === 'A') {
                            existing.href = val;
                        } else {
                            var a = document.createElement('a');
                            a.href = val;
                            overlayTarget.parentNode.insertBefore(a, overlayTarget);
                            a.appendChild(overlayTarget);
                        }
                        editorEl.focus();
                        return;
                    }
                    if (existingAnchor) {
                        if (val === '') { removeLinkNode(existingAnchor); } else { existingAnchor.href = val; }
                        editorEl.focus();
                        return;
                    }
                    if (val === '') {
                        document.execCommand('unlink', false, null);
                        editorEl.focus();
                        return;
                    }
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
                if (typeof checkDirty === 'function') checkDirty();
            });
        });
        toolbar.querySelectorAll('input[data-cmd], select[data-cmd]').forEach(function(el) {
            el.addEventListener('change', function() {
                var cmd = el.dataset.cmd;
                var val = el.value;
                document.execCommand(cmd, false, val);
                editorEl.focus();
                if (typeof checkDirty === 'function') checkDirty();
            });
        });
    }

    // Explicit apply buttons for color/highlight
    document.getElementById('apply-color')?.addEventListener('click', function() {
        var picker = document.getElementById('editor-color-picker');
        if (picker) {
            document.execCommand('foreColor', false, picker.value);
            editorEl.focus();
            if (typeof checkDirty === 'function') checkDirty();
        }
    });
    document.getElementById('apply-highlight')?.addEventListener('click', function() {
        var picker = document.getElementById('editor-highlight-picker');
        if (picker) {
            document.execCommand('hiliteColor', false, picker.value);
            editorEl.focus();
            if (typeof checkDirty === 'function') checkDirty();
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

    // Delete selected image via keyboard (Delete or Backspace) when overlay is active
    document.addEventListener('keydown', function(e) {
        if ((e.key === 'Delete' || e.key === 'Backspace') && imgOverlay && imgOverlay._target) {
            var target = imgOverlay._target;
            hideOverlay();
            target.parentNode && target.parentNode.removeChild(target);
            editorEl.focus();
            e.preventDefault();
        }
    });

    // Attachment picker – accumulate files one by one
    var attachInput = document.getElementById('attachments');
    var attachBtn   = document.getElementById('attachment-trigger');
    var attachList  = document.getElementById('attachment-list');

    function renderAttachList() {
        if (!attachList) return;
        attachList.innerHTML = '';
        pendingFiles.forEach(function(file, idx) {
            var tag = document.createElement('span');
            tag.style.cssText = 'display:inline-flex;align-items:center;gap:.2rem;background:var(--wm-surface-2);border:1px solid var(--wm-border);border-radius:5px;padding:.15rem .45rem;font-size:.8rem';
            var name = document.createTextNode(file.name);
            var rm = document.createElement('button');
            rm.type = 'button';
            rm.textContent = '×';
            rm.style.cssText = 'background:none;border:none;cursor:pointer;color:var(--wm-danger);font-size:1rem;line-height:1;padding:0 .1rem';
            rm.title = 'Remove';
            rm.addEventListener('click', (function(i) {
                return function() {
                    pendingFiles.splice(i, 1);
                    renderAttachList();
                    syncAttachInputs();
                };
            })(idx));
            tag.appendChild(name);
            tag.appendChild(rm);
            attachList.appendChild(tag);
        });
    }

    function syncAttachInputs() {
        // We cannot reconstruct a FileList; instead we'll use a DataTransfer to
        // set the single file input's files, or just hidden markers.
        // Build a DataTransfer to create an aggregated FileList on the real input.
        try {
            var dt = new DataTransfer();
            pendingFiles.forEach(function(f) { dt.items.add(f); });
            attachInput.files = dt.files;
        } catch (err) {
            // DataTransfer not supported – fallback: files already tracked in pendingFiles
        }
    }

    if (attachBtn && attachInput) {
        // Handle removal of existing attachments
        attachList.addEventListener('click', function(e) {
            if (e.target.classList.contains('remove-existing')) {
                var span = e.target.closest('.existing-attachment');
                if (span) span.parentNode.removeChild(span);
            }
        });

        attachBtn.addEventListener('click', function() {
            attachInput.value = '';
            attachInput.click();
        });
        attachInput.addEventListener('change', function() {
            if (!attachInput.files) return;
            Array.from(attachInput.files).forEach(function(f) {
                // Avoid duplicates by name
                var exists = pendingFiles.some(function(p) { return p.name === f.name && p.size === f.size; });
                if (!exists) pendingFiles.push(f);
            });
            renderAttachList();
            syncAttachInputs();
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
    function getEventCoords(e) {
        if (e.touches && e.touches.length) return {x: e.touches[0].clientX, y: e.touches[0].clientY};
        if (e.changedTouches && e.changedTouches.length) return {x: e.changedTouches[0].clientX, y: e.changedTouches[0].clientY};
        return {x: e.clientX, y: e.clientY};
    }
    function startDrag(e, dir) {
        var target = imgOverlay?._target;
        if (!target) return;
        e.preventDefault();
        var coords = getEventCoords(e);
        dragState = {
            target: target,
            startX: coords.x,
            startY: coords.y,
            startW: target.getBoundingClientRect().width,
            startH: target.getBoundingClientRect().height,
            dir: dir
        };
        document.addEventListener('mousemove', onMove);
        document.addEventListener('mouseup', onUp);
        document.addEventListener('touchmove', onMove, {passive: false});
        document.addEventListener('touchend', onUp);
    }
    function onMove(e) {
        if (!dragState) return;
        e.preventDefault();
        var coords = getEventCoords(e);
        var dx = coords.x - dragState.startX;
        var dy = coords.y - dragState.startY;
        var dir = dragState.dir;
        var newW = dragState.startW + dx * (dir.x || 0);
        var newH = dragState.startH + dy * (dir.y || 0);
        newW = Math.max(MIN_IMG_SIZE, newW);
        newH = Math.max(MIN_IMG_SIZE, newH);
        dragState.target.style.width = newW + 'px';
        dragState.target.style.height = newH + 'px';
        showOverlay(dragState.target);
    }
    function onUp() {
        dragState = null;
        document.removeEventListener('mousemove', onMove);
        document.removeEventListener('mouseup', onUp);
        document.removeEventListener('touchmove', onMove);
        document.removeEventListener('touchend', onUp);
    }
    if (imgOverlay) {
        imgOverlay.addEventListener('mousedown', function(e) {
            var dirKey = e.target.dataset.dir;
            if (!dirKey) return;
            var dir = resizeDirs[dirKey];
            if (dir) startDrag(e, dir);
        });
        imgOverlay.addEventListener('touchstart', function(e) {
            var dirKey = e.target.dataset.dir;
            if (!dirKey) return;
            var dir = resizeDirs[dirKey];
            if (dir) startDrag(e, dir);
        }, {passive: false});
    }

    // Show handles on image click or tap; show link popover on link click
    editorEl.addEventListener('click', function(e) {
        var target = e.target;
        if (target && target.tagName === 'IMG') {
            hideLinkPopover();
            showOverlay(target);
        } else {
            hideOverlay();
            var node = e.target;
            while (node && node !== editorEl) {
                if (node.tagName === 'A') {
                    e.preventDefault();
                    if (linkPopover.style.display !== 'none' && currentAnchor === node) {
                        hideLinkPopover();
                    } else {
                        showLinkPopover(node);
                    }
                    return;
                }
                node = node.parentNode;
            }
            hideLinkPopover();
        }
    });
    editorEl.addEventListener('touchend', function(e) {
        if (dragState) return; // ongoing resize, ignore
        var target = e.target;
        if (target && target.tagName === 'IMG') {
            e.preventDefault(); // suppress emulated click to avoid double-handling
            showOverlay(target);
        } else {
            hideOverlay();
        }
    });

    var scrollTick = false;
    window.addEventListener('scroll', function() {
        if (!imgOverlay || !imgOverlay._target || scrollTick) return;
        scrollTick = true;
        requestAnimationFrame(function() {
            if (imgOverlay && imgOverlay._target) {
                showOverlay(imgOverlay._target);
            }
            scrollTick = false;
        });
    });

    document.addEventListener('mousedown', function(e) {
        if (linkPopover.style.display !== 'none' &&
            !linkPopover.contains(e.target) &&
            !editorEl.contains(e.target)) {
            hideLinkPopover();
        }
    }, true);

    // Require "to" field only when actually sending (not saving draft)
    var toField = document.getElementById('to');
    var subjectField = document.getElementById('subject');
    
    form.addEventListener('submit', function(e) {
        // Sync source editor back to visual editor if in source mode
        var sourceEditor = document.getElementById('source-editor');
        if (sourceEditor && sourceEditor.style.display !== 'none') {
            editorEl.innerHTML = sourceEditor.value;
        }
        hidden.value = editorEl.innerHTML;
        var isSaveDraft = (e.submitter && e.submitter.name === 'save_draft');
        
        if (!isSaveDraft) {
            if (toField && !toField.value.trim()) {
                toField.required = true;
                return;
            }
            if (subjectField && !subjectField.value.trim()) {
                if (!confirm('Send this message without a subject?')) {
                    e.preventDefault();
                    subjectField.focus();
                    return;
                }
            }
        }
        
        if (toField) {
            toField.required = !isSaveDraft;
        }
    });

    // Message options toggle
    (function() {
        var optBtn = document.getElementById('msg-options-btn');
        var optMenu = document.getElementById('msg-options-menu');
        if (!optBtn || !optMenu) return;
        optBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            var isHidden = optMenu.style.display === 'none' || !optMenu.style.display;
            if (isHidden) {
                var rect = optBtn.getBoundingClientRect();
                var gap = parseInt(getComputedStyle(document.documentElement).getPropertyValue('--wm-menu-edge-gap')) || 12;
                var availableWidth = Math.max(0, window.innerWidth - (2 * gap));
                var desiredWidth = optMenu.scrollWidth || 0;
                var targetWidth = Math.max(220, desiredWidth);
                var menuWidth = availableWidth > 0 ? Math.min(targetWidth, availableWidth) : targetWidth;

                var left = Math.min(
                    Math.max(rect.right - menuWidth, gap),
                    window.innerWidth - gap - menuWidth
                );

                optMenu.style.position = 'fixed';
                optMenu.style.width = menuWidth + 'px';
                optMenu.style.left = left + 'px';
                optMenu.style.right = 'auto';
                optMenu.style.top = (rect.bottom + 4) + 'px';
            }
            optMenu.style.display = isHidden ? 'block' : 'none';
        });
        document.addEventListener('click', function() {
            optMenu.style.display = 'none';
        });
        optMenu.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    })();

    // HTML source editor toggle
    (function() {
        var sourceBtn    = document.getElementById('source-toggle');
        var sourceEditor = document.getElementById('source-editor');
        var sourceMode   = false;
        if (!sourceBtn || !sourceEditor) return;
        sourceBtn.addEventListener('click', function() {
            sourceMode = !sourceMode;
            if (sourceMode) {
                sourceEditor.value = editorEl.innerHTML;
                editorEl.style.display = 'none';
                sourceEditor.style.display = 'block';
                sourceBtn.style.fontWeight = '700';
                sourceBtn.title = 'Switch to visual editor';
            } else {
                editorEl.innerHTML = sourceEditor.value;
                sourceEditor.style.display = 'none';
                editorEl.style.display = '';
                sourceBtn.style.fontWeight = '';
                sourceBtn.title = 'Toggle HTML source';
                editorEl.focus();
            }
        });
    })();

    // Contact picker integration
    document.querySelectorAll('.open-contacts').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var target = btn.dataset.target;
            window.dispatchEvent(new CustomEvent('open-contacts-picker', { detail: { target: target } }));
        });
    });

    // ── Unsaved changes & Auto-save ───────────────────────────────────────────
    var isDirty = false;
    var lastSavedContent = editorEl.innerHTML;
    var lastSavedMeta = {
        to: toField.value,
        cc: document.getElementById('cc')?.value || '',
        bcc: document.getElementById('bcc')?.value || '',
        subject: subjectField.value
    };

    function checkDirty() {
        var currentContent = editorEl.innerHTML;
        var currentMeta = {
            to: toField.value,
            cc: document.getElementById('cc')?.value || '',
            bcc: document.getElementById('bcc')?.value || '',
            subject: subjectField.value
        };
        
        var contentChanged = currentContent !== lastSavedContent;
        var metaChanged = JSON.stringify(currentMeta) !== JSON.stringify(lastSavedMeta);
        
        isDirty = contentChanged || metaChanged;
    }

    // Listen for changes
    editorEl.addEventListener('input', checkDirty);
    [toField, subjectField, document.getElementById('cc'), document.getElementById('bcc')].forEach(function(el) {
        if (el) el.addEventListener('input', checkDirty);
    });

    // Warning when leaving
    window.addEventListener('beforeunload', function(e) {
        if (isDirty) {
            e.preventDefault();
            e.returnValue = '';
        }
    });

    // Reset dirty flag on form submission
    form.addEventListener('submit', function() {
        isDirty = false;
    });

    // Auto-save logic
    var draftStatus = document.getElementById('draft-status');
    var autoSaveInterval = 60000; // 1 minute

    async function saveDraft(isAuto = false) {
        if (isAuto && !isDirty) return;
        
        // Sync source editor if needed
        var sourceEditor = document.getElementById('source-editor');
        if (sourceEditor && sourceEditor.style.display !== 'none') {
            editorEl.innerHTML = sourceEditor.value;
        }
        
        // Ensure hidden field is updated
        if (hidden) {
            hidden.value = editorEl.innerHTML;
        }
        
        var formData = new FormData(form);
        formData.append('save_draft', '1');
        formData.append('ajax', '1');
        // Explicitly set body_html to ensure it's not empty if hidden field sync was delayed
        formData.set('body_html', editorEl.innerHTML);

        if (isAuto) {
            draftStatus.textContent = 'Auto-saving...';
            draftStatus.style.display = 'inline';
        }

        try {
            var resp = await fetch(form.action, {
                method: 'POST',
                body: formData
            });
            var data = await resp.json();
            if (data.status === 'success') {
                isDirty = false;
                lastSavedContent = editorEl.innerHTML;
                lastSavedMeta = {
                    to: toField.value,
                    cc: document.getElementById('cc')?.value || '',
                    bcc: document.getElementById('bcc')?.value || '',
                    subject: subjectField.value
                };
                
                if (draftStatus) {
                    draftStatus.textContent = (isAuto ? 'Auto-saved' : 'Saved') + ' at ' + data.time;
                    draftStatus.style.display = 'inline';
                }

                if (data.draft_uid) {
                    document.getElementById('draft-uid').value = data.draft_uid;
                }
                
                // Update sidebar unread count in real-time
                const activeAccountId = <?= (int)$currentAccountId ?>;
                const fromAccountEl = document.querySelector('select[name="from_account"]');
                const fromAccountId = fromAccountEl ? parseInt(fromAccountEl.value) : activeAccountId;
                
                if (fromAccountId === activeAccountId && typeof window.updateFolderUnread === 'function' && data.folder && data.unread_count !== undefined) {
                    window.updateFolderUnread(data.folder, data.unread_count);
                }
            } else {
                if (isAuto && draftStatus) {
                    draftStatus.textContent = 'Auto-save failed';
                }
            }
        } catch (err) {
            console.error('Draft save error:', err);
            if (isAuto && draftStatus) {
                draftStatus.textContent = 'Auto-save error';
            }
        }
    }

    // Manual save button override for AJAX
    var saveBtn = document.querySelector('button[name="save_draft"]');
    if (saveBtn) {
        saveBtn.addEventListener('click', function(e) {
            e.preventDefault();
            saveDraft(false);
        });
    }

    setInterval(function() {
        saveDraft(true);
    }, autoSaveInterval);

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

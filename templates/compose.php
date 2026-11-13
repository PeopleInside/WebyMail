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
$isReply         = !empty($replyMsg);
$folder          = $folder    ?? 'INBOX';
$folderEnc       = urlencode($folder);
$signature       = $signature ?? '';
$resumeSend      = !empty($resumeSend);
$fromUnified     = (string) ($fromUnified ?? '');
$activeSessAccId = (int) ($session['account_id'] ?? 0);
$currentAccountId = (int) ($currentAccountId ?? $activeSessAccId);
$accountParam    = ($currentAccountId > 0 && ($currentAccountId !== $activeSessAccId || $fromUnified !== ''))
    ? '&account_id=' . $currentAccountId
    : '';
$unifiedParam    = $fromUnified !== '' ? '&from_unified=' . urlencode($fromUnified) : '';
$cancelUrl       = $isReply
    ? '?action=view&folder=' . urlencode($folder) . '&msg=' . (int)$replyMsg . $accountParam . $unifiedParam
    : ($fromUnified !== '' ? '?action=unified&ufolder=' . urlencode($fromUnified) : '?action=inbox&folder=' . urlencode($folder));
$hasPrefillCc    = !empty($prefill['cc']);
?>

<div class="wm-compose-wrap" style="height:calc(100dvh - 52px)">

    <!-- Toolbar -->
    <div class="wm-toolbar">
        <a href="<?= htmlspecialchars($cancelUrl) ?>" class="btn btn-ghost btn-sm" target="_self">
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
        <button type="button" id="templates-btn" class="btn btn-outline btn-sm" style="margin-left:.5rem" title="Manage and insert email templates">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
            Templates
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

    <form id="compose-form" method="post" action="?action=send" target="_self" enctype="multipart/form-data" style="display:contents">
        <?= csrfInput() ?>
        <!-- Hidden fields -->
        <input type="hidden" name="folder"       value="<?= htmlspecialchars($folder) ?>">
        <input type="hidden" name="from_unified" value="<?= htmlspecialchars($fromUnified) ?>">
        <input type="hidden" name="in_reply_to"  value="<?= htmlspecialchars($prefill['in_reply_to'] ?? '') ?>">
        <input type="hidden" name="reply_msg"    value="<?= (int)($replyMsg ?? 0) ?>">
        <input type="hidden" name="draft_uid"    id="draft-uid" value="<?= (int)($prefill['draft_uid'] ?? 0) ?>">
        <input type="hidden" name="draft_folder" value="<?= htmlspecialchars($prefill['draft_folder'] ?? $folder) ?>">
        <input type="hidden" name="body_html"    id="body-html-hidden">

        <!-- Address fields -->
        <div class="wm-compose-fields">

            <?php if (!empty($accounts) && (int)($user['allow_from_change'] ?? 0) === 1): ?>
            <div class="wm-compose-field">
                <label for="from_account">From</label>
                <select name="from_account" id="from_account" class="form-control">
                    <?php foreach ($accounts as $acc): ?>
                        <option value="<?= (int)$acc['id'] ?>" 
                                data-signature="<?= htmlspecialchars($acc['signature'] ?: ($user['signature'] ?? '')) ?>"
                                <?= (int)$acc['id'] === (int)$currentAccountId ? 'selected' : '' ?>>
                            <?= htmlspecialchars(($acc['sender_name'] ?: ($acc['label'] ?: (explode('@', $acc['email'])[0]))) . " <" . $acc['email'] . ">") ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php else: ?>
                <input type="hidden" name="from_account" value="<?= (int)$currentAccountId ?>">
                <?php if (!empty($composeAccount) && ($currentAccountId !== $activeSessAccId || $fromUnified !== '')): ?>
                <div class="wm-compose-field">
                    <label>From</label>
                    <span style="font-size:.875rem;color:var(--wm-text-muted)"><?= htmlspecialchars(($composeAccount['sender_name'] ?: ($composeAccount['label'] ?: (explode('@', $composeAccount['email'])[0]))) . ' <' . $composeAccount['email'] . '>') ?></span>
                </div>
                <?php endif; ?>
            <?php endif; ?>

            <div class="wm-compose-field">
                <label for="to">To</label>
                <input type="text" id="to" name="to" autocomplete="off"
                       value="<?= htmlspecialchars($prefill['to'] ?? '') ?>"
                       placeholder="recipient@example.com">
                <button type="button" class="btn btn-ghost btn-icon open-contacts" data-target="to" title="Pick from contacts">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
                </button>
                <button type="button" id="show-cc"  class="btn btn-ghost btn-sm" style="font-size:.75rem<?= $hasPrefillCc ? ';display:none' : '' ?>">Cc</button>
                <button type="button" id="show-bcc" class="btn btn-ghost btn-sm" style="font-size:.75rem">Bcc</button>
            </div>

            <div class="wm-compose-field" id="cc-row" style="display:<?= $hasPrefillCc ? 'flex' : 'none' ?>">
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

<!-- Email Templates Modal -->
<div id="templates-modal" class="wm-modal" style="display:none">
    <div class="wm-modal-content" style="max-width:650px;max-height:90dvh">
        <div class="wm-modal-header">
            <h3 style="margin:0;font-size:1.1rem">Email Templates</h3>
            <button type="button" id="close-templates-modal" class="btn-ghost btn-icon close-modal" style="font-size:1.5rem;line-height:1;padding:0 .5rem">&times;</button>
        </div>
        <div class="wm-modal-body">
            <div style="display:flex;gap:.5rem;margin-bottom:.75rem;flex-wrap:wrap;align-items:center">
                <div style="position:relative;flex:1;min-width:160px">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="position:absolute;left:.65rem;top:50%;transform:translateY(-50%);color:var(--wm-text-muted);pointer-events:none"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="search" id="template-search" class="form-control" placeholder="Search saved templates..." aria-label="Search saved templates" style="width:100%;padding-left:2rem">
                </div>
                <button type="button" id="save-current-template-btn" class="btn btn-primary btn-sm" title="Save current subject and message body (without signature) as a template">
                    + Save current
                </button>
                <button type="button" id="new-template-btn" class="btn btn-outline btn-sm">
                    + New blank
                </button>
            </div>

            <div style="display:flex;align-items:center;justify-content:space-between;gap:.5rem;margin-bottom:1rem;font-size:.82rem;color:var(--wm-text-muted);flex-wrap:wrap">
                <label style="display:inline-flex;align-items:center;gap:.4rem;cursor:pointer">
                    <input type="checkbox" id="template-overwrite-subject" <?= $isReply ? '' : 'checked' ?>>
                    <span>Set/overwrite Subject when applying<?= $isReply ? ' (uncheck to keep Re:/Fwd:)' : '' ?></span>
                </label>
                <span>Content is inserted above any existing signature.</span>
            </div>

            <!-- Add/Edit Template Form -->
            <form id="template-form" style="display:none;background:var(--wm-surface-2);padding:1rem;border-radius:8px;margin-bottom:1rem;border:1px solid var(--wm-border)">
                <input type="hidden" id="template-id" value="">
                <div style="margin-bottom:.75rem">
                    <label for="template-name-input" style="font-size:.75rem;display:block;margin-bottom:.25rem">Template Name *</label>
                    <input type="text" id="template-name-input" class="form-control" placeholder="e.g. Support Follow-up" required>
                </div>
                <div style="margin-bottom:.75rem">
                    <label for="template-subject-input" style="font-size:.75rem;display:block;margin-bottom:.25rem">Subject</label>
                    <input type="text" id="template-subject-input" class="form-control" placeholder="Email subject...">
                </div>
                <div style="margin-bottom:.75rem">
                    <label style="font-size:.75rem;display:block;margin-bottom:.25rem">Message Content (without signature)</label>
                    <div id="template-body-editor" contenteditable="true"
                         style="min-height:130px;max-height:240px;overflow-y:auto;padding:.6rem .75rem;border:1px solid var(--wm-border);border-radius:6px;background:var(--wm-surface);color:var(--wm-text);font-size:.9rem;line-height:1.5;outline:none"></div>
                </div>
                <div style="display:flex;justify-content:flex-end;gap:.5rem">
                    <button type="button" id="cancel-template-form" class="btn btn-ghost btn-sm">Cancel</button>
                    <button type="submit" id="save-template-submit" class="btn btn-primary btn-sm">Save Template</button>
                </div>
            </form>

            <!-- Templates List -->
            <div id="templates-list-container" style="max-height:360px;overflow-y:auto">
                <div class="wm-email-loading">
                    <div class="spinner"></div>
                    <span>Loading templates...</span>
                </div>
            </div>
        </div>
    </div>
</div>

<script nonce="<?= $cspNonce ?>">
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
            content += '<p><br></p><div class="wm-signature">' + signature + '</div><br><br>';
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

    // From account signature switcher
    var fromSelect = document.getElementById('from_account');
    if (fromSelect) {
        fromSelect.addEventListener('change', function() {
            var selectedOpt = fromSelect.options[fromSelect.selectedIndex];
            var newSig = selectedOpt.dataset.signature;
            var sigDiv = editorEl.querySelector('.wm-signature');
            if (sigDiv && newSig !== undefined) {
                sigDiv.innerHTML = newSig;
                checkDirty();
            }
        });
    }

    // ── Email Templates (DB-persisted) ────────────────────────────────────────
    (function() {
        var tplBtn          = document.getElementById('templates-btn');
        var tplModal        = document.getElementById('templates-modal');
        var tplCloseBtn     = document.getElementById('close-templates-modal');
        var tplSearch       = document.getElementById('template-search');
        var tplSaveCurrent  = document.getElementById('save-current-template-btn');
        var tplNewBtn       = document.getElementById('new-template-btn');
        var tplForm         = document.getElementById('template-form');
        var tplIdInput      = document.getElementById('template-id');
        var tplNameInput    = document.getElementById('template-name-input');
        var tplSubjectInput = document.getElementById('template-subject-input');
        var tplBodyEditor   = document.getElementById('template-body-editor');
        var tplCancelBtn    = document.getElementById('cancel-template-form');
        var tplSubmitBtn    = document.getElementById('save-template-submit');
        var tplListEl       = document.getElementById('templates-list-container');
        var overwriteSubjCb = document.getElementById('template-overwrite-subject');
        var sourceEditor    = document.getElementById('source-editor');
        var allTemplates    = [];

        if (!tplBtn || !tplModal) return;

        function escapeHtml(text) {
            if (!text) return '';
            var d = document.createElement('div');
            d.textContent = text;
            return d.innerHTML;
        }

        function htmlToPlainSnippet(html) {
            if (!html) return '';
            var d = document.createElement('div');
            d.innerHTML = html;
            return (d.textContent || d.innerText || '').replace(/\s+/g, ' ').trim();
        }

        function syncVisualFromSourceIfActive() {
            if (sourceEditor && sourceEditor.style.display !== 'none') {
                editorEl.innerHTML = sourceEditor.value;
            }
        }

        function syncSourceFromVisualIfActive() {
            if (sourceEditor && sourceEditor.style.display !== 'none') {
                sourceEditor.value = editorEl.innerHTML;
            }
        }

        // Extract current compose message body WITHOUT signature (and without quoted reply/forward)
        function extractComposeBodyWithoutSignature() {
            syncVisualFromSourceIfActive();
            var clone = document.createElement('div');
            clone.innerHTML = editorEl.innerHTML;

            // Remove signature blocks
            clone.querySelectorAll('.wm-signature').forEach(function(el) {
                el.parentNode && el.parentNode.removeChild(el);
            });

            // Remove quoted reply/forward section if present
            var quoteNode = clone.querySelector('blockquote, hr');
            if (quoteNode) {
                // Remove preceding "On ... wrote:" line or <br> right before blockquote/hr
                var toRemove = [];
                var cur = quoteNode;
                while (cur) {
                    toRemove.push(cur);
                    cur = cur.nextSibling;
                }
                var prev = quoteNode.previousSibling;
                while (prev) {
                    var prevText = (prev.textContent || '').trim();
                    if (prev.nodeName === 'BR' || /^On .+ wrote:$/i.test(prevText)) {
                        var p = prev.previousSibling;
                        toRemove.push(prev);
                        prev = p;
                    } else {
                        break;
                    }
                }
                toRemove.forEach(function(n) {
                    n.parentNode && n.parentNode.removeChild(n);
                });
            }

            // Trim trailing <br> and empty <p><br></p> left by signature spacing
            while (clone.lastChild) {
                var last = clone.lastChild;
                if (last.nodeName === 'BR') {
                    clone.removeChild(last);
                } else if (last.nodeType === 3 && !last.textContent.trim()) {
                    clone.removeChild(last);
                } else if (last.nodeType === 1 && !last.querySelector('img') && (last.textContent || '').trim() === '') {
                    clone.removeChild(last);
                } else {
                    break;
                }
            }
            return clone.innerHTML.trim();
        }

        function hasContentBeforeNode(parent, stopNode) {
            var cur = parent.firstChild;
            while (cur && cur !== stopNode) {
                if (cur.nodeType === 3 && cur.textContent.trim() !== '') return true;
                if (cur.nodeType === 1) {
                    if (cur.querySelector('img') || cur.tagName === 'IMG') return true;
                    if ((cur.textContent || '').trim() !== '') return true;
                }
                cur = cur.nextSibling;
            }
            return false;
        }

        function applyTemplate(tpl) {
            syncVisualFromSourceIfActive();

            var sigEl = editorEl.querySelector('.wm-signature');
            var quoteEl = !sigEl ? editorEl.querySelector('blockquote, hr') : null;
            var anchorNode = sigEl || quoteEl;

            if (anchorNode) {
                if (hasContentBeforeNode(editorEl, anchorNode)) {
                    if (!confirm('Replace the current text above your signature/quote with this template?')) {
                        return;
                    }
                }
                // Remove nodes before anchorNode
                while (editorEl.firstChild && editorEl.firstChild !== anchorNode) {
                    editorEl.removeChild(editorEl.firstChild);
                }
                // Remove immediate empty <br> directly after signature? Keep signature & quote intact!
                var wrapper = document.createElement('div');
                wrapper.innerHTML = (tpl.body_html || '<p><br></p>') + '<p><br></p>';
                var frag = document.createDocumentFragment();
                while (wrapper.firstChild) {
                    frag.appendChild(wrapper.firstChild);
                }
                editorEl.insertBefore(frag, anchorNode);
            } else {
                var hasAnyContent = (editorEl.textContent || '').trim() !== '' || !!editorEl.querySelector('img');
                if (hasAnyContent) {
                    if (!confirm('Replace the current message content with this template?')) {
                        return;
                    }
                }
                editorEl.innerHTML = tpl.body_html || '<p><br></p>';
            }

            // Apply Subject if empty or if overwrite checkbox is checked
            if (subjectField && tpl.subject !== undefined && tpl.subject !== '') {
                var curSubj = subjectField.value.trim();
                if (curSubj === '' || (overwriteSubjCb && overwriteSubjCb.checked)) {
                    subjectField.value = tpl.subject;
                }
            }

            syncSourceFromVisualIfActive();
            if (typeof checkDirty === 'function') checkDirty();
            tplModal.style.display = 'none';
            editorEl.focus();
        }

        function loadTemplates() {
            tplListEl.innerHTML = '<div class="wm-email-loading"><div class="spinner"></div><span>Loading templates...</span></div>';
            fetch('?action=templates_list&_=' + Date.now(), {
                cache: 'no-store',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.ok) {
                    allTemplates = data.templates || [];
                    filterAndRender();
                } else {
                    tplListEl.innerHTML = '<div style="text-align:center;padding:1.5rem;color:var(--wm-danger)">Could not load templates.</div>';
                }
            })
            .catch(function() {
                tplListEl.innerHTML = '<div style="text-align:center;padding:1.5rem;color:var(--wm-danger)">Could not load templates.</div>';
            });
        }

        function sortTemplates() {
            allTemplates.sort(function(a, b) {
                return (a.name || '').localeCompare(b.name || '', undefined, { numeric: true, sensitivity: 'base' });
            });
        }

        function filterAndRender() {
            var q = (tplSearch.value || '').toLowerCase().trim();
            var list = q === '' ? allTemplates : allTemplates.filter(function(t) {
                return (t.name || '').toLowerCase().includes(q)
                    || (t.subject || '').toLowerCase().includes(q)
                    || htmlToPlainSnippet(t.body_html).toLowerCase().includes(q);
            });
            renderTemplates(list);
        }

        function renderTemplates(list) {
            if (!list.length) {
                if (allTemplates.length > 0 && (tplSearch.value || '').trim() !== '') {
                    tplListEl.innerHTML = '<div style="text-align:center;padding:1.5rem;color:var(--wm-text-muted)">No templates match your search. <button type="button" id="clear-tpl-search-btn" class="btn btn-ghost btn-sm" style="margin-left:.35rem">Clear search</button></div>';
                    var clearBtn = document.getElementById('clear-tpl-search-btn');
                    if (clearBtn) {
                        clearBtn.addEventListener('click', function() {
                            tplSearch.value = '';
                            filterAndRender();
                        });
                    }
                } else {
                    tplListEl.innerHTML = '<div style="text-align:center;padding:2rem;color:var(--wm-text-muted)">No email templates saved yet.</div>';
                }
                return;
            }
            tplListEl.innerHTML = '';
            list.forEach(function(tpl) {
                var snippet = htmlToPlainSnippet(tpl.body_html);
                var row = document.createElement('div');
                row.className = 'contact-item';
                row.style.alignItems = 'flex-start';
                row.innerHTML = `
                    <div class="contact-info" style="flex:1;min-width:0">
                        <span class="contact-name">${escapeHtml(tpl.name)}</span>
                        ${tpl.subject ? `<span class="contact-email" style="font-weight:500">Subject: ${escapeHtml(tpl.subject)}</span>` : ''}
                        ${snippet ? `<span style="font-size:.76rem;display:block;color:var(--wm-text-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-top:.15rem">${escapeHtml(snippet)}</span>` : ''}
                    </div>
                    <div style="display:flex;gap:.25rem;flex-shrink:0;align-items:center">
                        <button type="button" class="btn btn-primary btn-sm apply-template" data-id="${tpl.id}">Apply</button>
                        <button type="button" class="btn btn-ghost btn-sm edit-template" data-id="${tpl.id}" title="Edit template">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        </button>
                        <button type="button" class="btn btn-ghost btn-sm delete-template" data-id="${tpl.id}" title="Delete template">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/></svg>
                        </button>
                    </div>
                `;
                tplListEl.appendChild(row);
            });
        }

        function openTemplateForm(opts) {
            tplIdInput.value      = opts.id ? String(opts.id) : '';
            tplNameInput.value    = opts.name || '';
            tplSubjectInput.value = opts.subject || '';
            tplBodyEditor.innerHTML = opts.body_html || '';
            tplSubmitBtn.textContent = opts.id ? 'Update Template' : 'Save Template';
            tplForm.style.display = 'block';
            tplNameInput.focus();
        }

        tplBtn.addEventListener('click', function() {
            tplSearch.value = '';
            tplForm.style.display = 'none';
            tplModal.style.display = 'flex';
            loadTemplates();
        });

        tplCloseBtn.addEventListener('click', function() {
            tplModal.style.display = 'none';
        });

        tplModal.addEventListener('click', function(e) {
            if (e.target === tplModal) tplModal.style.display = 'none';
        });

        tplSearch.addEventListener('input', filterAndRender);

        tplNewBtn.addEventListener('click', function() {
            openTemplateForm({ id: 0, name: '', subject: '', body_html: '' });
        });

        tplSaveCurrent.addEventListener('click', function() {
            var curSubj = subjectField ? subjectField.value.trim() : '';
            var curBody = extractComposeBodyWithoutSignature();
            openTemplateForm({
                id: 0,
                name: curSubj,
                subject: curSubj,
                body_html: curBody
            });
        });

        tplCancelBtn.addEventListener('click', function() {
            tplForm.style.display = 'none';
        });

        tplForm.addEventListener('submit', function(e) {
            e.preventDefault();
            var id = parseInt(tplIdInput.value || '0', 10);
            var fd = new FormData();
            if (id > 0) fd.append('id', String(id));
            fd.append('name', tplNameInput.value.trim());
            fd.append('subject', tplSubjectInput.value.trim());
            fd.append('body_html', tplBodyEditor.innerHTML);
            fd.append('csrf_token', document.querySelector('meta[name="csrf-token"]').content);

            tplSubmitBtn.disabled = true;
            fetch('?action=' + (id > 0 ? 'templates_edit' : 'templates_add'), {
                method: 'POST',
                cache: 'no-store',
                body: fd,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.ok) {
                    tplForm.style.display = 'none';
                    tplSearch.value = '';
                    if (data.template) {
                        var savedId = parseInt(data.template.id, 10);
                        var idx = allTemplates.findIndex(function(t) { return parseInt(t.id, 10) === savedId; });
                        if (idx >= 0) {
                            allTemplates[idx] = data.template;
                        } else {
                            allTemplates.push(data.template);
                        }
                        sortTemplates();
                        filterAndRender();
                    } else {
                        loadTemplates();
                    }
                } else {
                    alert(data.error || 'Could not save template.');
                }
            })
            .catch(function() {
                alert('Could not save template.');
            })
            .finally(function() {
                tplSubmitBtn.disabled = false;
            });
        });

        tplListEl.addEventListener('click', function(e) {
            var applyBtn = e.target.closest('.apply-template');
            if (applyBtn) {
                var id = parseInt(applyBtn.dataset.id, 10);
                var tpl = allTemplates.find(function(t) { return parseInt(t.id, 10) === id; });
                if (tpl) applyTemplate(tpl);
                return;
            }

            var editBtn = e.target.closest('.edit-template');
            if (editBtn) {
                var id = parseInt(editBtn.dataset.id, 10);
                var tpl = allTemplates.find(function(t) { return parseInt(t.id, 10) === id; });
                if (tpl) openTemplateForm(tpl);
                return;
            }

            var delBtn = e.target.closest('.delete-template');
            if (delBtn) {
                if (!confirm('Delete this email template?')) return;
                var delId = parseInt(delBtn.dataset.id, 10);
                var fd = new FormData();
                fd.append('id', String(delId));
                fd.append('csrf_token', document.querySelector('meta[name="csrf-token"]').content);
                fetch('?action=templates_delete', {
                    method: 'POST',
                    cache: 'no-store',
                    body: fd,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.ok) {
                        allTemplates = allTemplates.filter(function(t) { return parseInt(t.id, 10) !== delId; });
                        filterAndRender();
                    }
                });
            }
        });
    })();

})();
</script>

<?php if (!$isReply): ?>
<script nonce="<?= $cspNonce ?>">
// Show reply-to field toggle
document.getElementById('show-replyto')?.addEventListener('click', function() {
    var row = document.getElementById('replyto-row');
    row.style.display = 'flex';
    this.style.display = 'none';
});
</script>
<?php endif; ?>

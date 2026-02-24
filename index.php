<?php
declare(strict_types=1);

/**
 * WebyMail – main entry-point / router
 *
 * URL routing is handled via the `action` query parameter.
 * All pages are rendered through templates/layout.php.
 */

// ── Bootstrap ────────────────────────────────────────────────────────────────

define('WEBYMAIL_ROOT', __DIR__);

// PHP native session is used only for the 2FA pending state
session_set_cookie_params([
    'lifetime' => 300,
    'path'     => '/',
    'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'Strict',
]);
session_start();

spl_autoload_register(function (string $class): void {
    $file = __DIR__ . '/src/' . $class . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

require_once __DIR__ . '/src/Config.php';

function appName(): string
{
    return (string) Config::get('app_name', 'WebyMail');
}

function pageTitle(string $section = ''): string
{
    $name = appName();
    return $section !== '' ? ($name . ' – ' . $section) : $name;
}

// ── First-run: redirect to setup wizard ──────────────────────────────────────
if (!Config::isSetup()) {
    header('Location: setup.php');
    exit;
}

// ── Helpers ───────────────────────────────────────────────────────────────────

/**
 * Render a template inside the layout.
 *
 * @param string $template   Template file name (without .php) inside templates/
 * @param array  $vars       Variables to extract into the template scope
 * @param bool   $shell      Whether to use the full app shell (sidebar + topbar)
 */
function render(string $template, array $vars = [], bool $shell = true): void
{
    extract($vars, EXTR_SKIP);

    // Capture inner template
    ob_start();
    include __DIR__ . '/templates/' . $template . '.php';
    $content = ob_get_clean();

    // Render in layout
    $shellLayout = $shell;
    include __DIR__ . '/templates/layout.php';
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function jsonResponse(array $data): never
{
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function findFolderName(array $folders, array $candidates, string $fallback): string
{
    /**
     * Find the first folder whose display or name matches any candidate (case-insensitive).
     * Falls back to the provided default if no match is found.
     */
    foreach ($folders as $f) {
        foreach ($candidates as $cand) {
            if (strcasecmp($f['display'], $cand) === 0 || strcasecmp($f['name'], $cand) === 0) {
                return $f['name'];
            }
        }
    }
    return $fallback;
}

function parseIniSize(string $value): int
{
    $value = trim($value);
    $unit  = strtolower(substr($value, -1));
    $num   = (float) $value;
    return match ($unit) {
        'g' => (int) ($num * 1024 * 1024 * 1024),
        'm' => (int) ($num * 1024 * 1024),
        'k' => (int) ($num * 1024),
        default => (int) $num,
    };
}

function isAjax(): bool
{
    return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
}

function flashSet(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function flashGet(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

function requireAuth(): array
{
    $session = (new Session())->current();
    if ($session === null) {
        redirect('?action=login');
    }
    return $session;
}

// ── Router ────────────────────────────────────────────────────────────────────

$action = $_GET['action'] ?? 'inbox';

// ── Proof-of-Work captcha challenge endpoint (GET, public) ─────────────────────
if ($action === 'pow_challenge') {
    if (!Config::get('altcha_enabled', true)) {
        http_response_code(404);
        exit;
    }
    $captcha   = new Captcha();
    $challenge = $captcha->issue();
    header('Content-Type: application/json');
    echo json_encode($challenge);
    exit;
}

// ── Login ─────────────────────────────────────────────────────────────────────
if ($action === 'login') {
    // Already logged in?
    $session = (new Session())->current();
    if ($session !== null) {
        redirect('?action=inbox');
    }

    $error   = null;
    $needs2fa = isset($_SESSION['pending_2fa']) && time() < ($_SESSION['pending_2fa']['expires'] ?? 0);
    $captchaEnabled = (bool) Config::get('altcha_enabled', true);
    $captcha = new Captcha();

    // Pick up any flash error (e.g. from a failed 2FA attempt)
    $flashMsg = flashGet();
    if ($flashMsg !== null) {
        $error = $flashMsg['message'];
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Verify proof-of-work captcha
        $captchaCheckPassed = !$captchaEnabled;
        if ($captchaEnabled) {
            $powSolution = $_POST['pow_solution'] ?? '';
            $powToken    = $_POST['pow_token'] ?? '';
            if ($captcha->verify($powSolution, $powToken)) {
                $captchaCheckPassed = true;
            } else {
                $error = 'Security check failed. Please try again.';
            }
        }
        if ($captchaCheckPassed && $error === null) {
            $auth     = new Auth();
            $host     = trim($_POST['imap_host'] ?? Config::get('imap_host', ''));
            $port     = (int) ($_POST['imap_port'] ?? Config::get('imap_port', 993));
            $ssl      = !empty($_POST['imap_ssl']);
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $smtpHost = trim($_POST['smtp_host'] ?? Config::get('smtp_host', $host));
            $smtpPort = (int) ($_POST['smtp_port'] ?? Config::get('smtp_port', 587));
            $smtpSsl  = !empty($_POST['smtp_ssl']);
            $smtpTls  = !empty($_POST['smtp_starttls']);
            if ($smtpSsl) {
                $smtpTls = false;
            }

            // Auto-fill host from email domain if left blank
            if ($host === '' && str_contains($username, '@')) {
                $domain   = substr($username, strpos($username, '@') + 1);
                $host     = 'mail.' . $domain;
                $smtpHost = $smtpHost ?: $host;
            }

            $result = $auth->loginWithImap(
                $host, $port, $ssl, $username, $password,
                $smtpHost, $smtpPort, $smtpSsl, $smtpTls
            );

            if (!$result['ok']) {
                $error = $result['error'];
            } elseif ($result['needs_2fa']) {
                $needs2fa = true;
                redirect('?action=login');
            } else {
                redirect('?action=inbox');
            }
        }
    }

    $challenge = $captchaEnabled ? $captcha->issue() : null;
    render('login', [
        'error' => $error,
        'needs2fa' => $needs2fa,
        'challenge' => $challenge,
        'pageTitle' => pageTitle('Login'),
    ], false);
    exit;
}

// ── 2FA verification ──────────────────────────────────────────────────────────
if ($action === 'login2fa') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $auth   = new Auth();
        $result = $auth->completeTwoFactor(trim($_POST['code'] ?? ''));
        if ($result['ok']) {
            if (!empty($result['recovery_used'])) {
                flashSet('warning', "Recovery code used. {$result['remaining']} code(s) remaining.");
            }
            redirect('?action=inbox');
        } else {
            // Redirect back to login with 2FA step still pending
            flashSet('danger', $result['error']);
            redirect('?action=login');
        }
    }
    redirect('?action=login');
}

// ── Logout ────────────────────────────────────────────────────────────────────
if ($action === 'logout') {
    (new Auth())->logout();
    redirect('?action=login');
}

// ═════════════════════════════════════════════════════════════════════════════
// All routes below require authentication
// ═════════════════════════════════════════════════════════════════════════════

$session   = requireAuth();
$userId    = (int) $session['user_id'];
$accountId = (int) $session['account_id'];

$accountMgr = new Account();
$accounts   = $accountMgr->getForUser($userId);
$flash      = flashGet();

// Build folder list (from IMAP)
$folders       = [];
$currentFolder = $_GET['folder'] ?? 'INBOX';
try {
    $imap    = $accountMgr->imapConnect($accountId);
    $folders = $imap->getFolders();
    // Add unread count for INBOX
    foreach ($folders as &$f) {
        if (strtoupper($f['name']) === 'INBOX') {
            $f['unread'] = $imap->getUnreadCount('INBOX');
        }
    }
    unset($f);
} catch (RuntimeException) {
    // IMAP might be temporarily unavailable; non-fatal
}
if (empty($folders)) {
    $folders = [
        ['name' => 'INBOX',  'display' => 'Inbox',  'unread' => 0],
        ['name' => 'Sent',   'display' => 'Sent',   'unread' => 0],
        ['name' => 'Drafts', 'display' => 'Drafts', 'unread' => 0],
    ];
}

$layoutCommon = [
    'session'       => $session,
    'accounts'      => $accounts,
    'folders'       => $folders,
    'currentFolder' => $currentFolder,
    'flash'         => $flash,
];

// ── Account switch ────────────────────────────────────────────────────────────
if ($action === 'switch_account') {
    $newId = (int) ($_POST['account_id'] ?? 0);
    if ($newId === 0 && isAjax()) {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $newId = (int) ($body['account_id'] ?? 0);
    }
    if ($accountMgr->belongsToUser($newId, $userId)) {
        (new Session())->switchAccount($newId);
    }
    if (isAjax()) jsonResponse(['ok' => true]);
    redirect('?action=inbox');
}

// ── Inbox ─────────────────────────────────────────────────────────────────────
if ($action === 'inbox' || $action === 'search') {
    $page    = max(1, (int) ($_GET['page'] ?? 1));
    $mailData = [];

    try {
        $imap = $accountMgr->imapConnect($accountId);
        $mailData = $imap->getMessages($currentFolder, $page);
        $imap->disconnect();
    } catch (RuntimeException $e) {
        $flash = ['type' => 'danger', 'message' => 'IMAP error: ' . $e->getMessage()];
    }

    render('inbox', $layoutCommon + [
        'mailData'     => $mailData,
        'folder'       => $currentFolder,
        'pageTitle'    => pageTitle('Inbox'),
    ]);
    exit;
}

// ── View message ──────────────────────────────────────────────────────────────
if ($action === 'view') {
    $msgNo  = (int) ($_GET['msg'] ?? 0);
    $message = null;

    try {
        $imap    = $accountMgr->imapConnect($accountId);
        $message = $imap->getMessage($currentFolder, $msgNo);
        $imap->disconnect();
    } catch (RuntimeException $e) {
        flashSet('danger', 'Could not load message: ' . $e->getMessage());
        redirect('?action=inbox&folder=' . urlencode($currentFolder));
    }

    // Detect external images / URLs in HTML body
    $hasExternal = false;
    if (!empty($message['body_html'])) {
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $hasExternal = (bool) preg_match(
            '/\b(src|href)\s*=\s*["\']https?:\/\/(?!' . preg_quote($host, '/') . ')/i',
            $message['body_html']
        );
    }

    render('view', $layoutCommon + [
        'message'     => $message,
        'folder'      => $currentFolder,
        'hasExternal' => $hasExternal,
        'pageTitle'   => pageTitle($message['subject'] ?? 'View message'),
    ]);
    exit;
}

// ── Email body (rendered in sandboxed iframe) ─────────────────────────────────
if ($action === 'email_body') {
    $msgNo  = (int) ($_GET['msg']    ?? 0);
    $folder = $_GET['folder'] ?? 'INBOX';
    $showImages = !empty($_GET['images']) && $_GET['images'] === '1';

    try {
        $imap    = $accountMgr->imapConnect($accountId);
        $message = $imap->getMessage($folder, $msgNo);
        $imap->disconnect();
    } catch (RuntimeException) {
        http_response_code(404);
        exit;
    }

    $html = $message['body_html'] ?? '';

    // Strip external images if not allowed
    if (!$showImages) {
        $html = preg_replace_callback(
            '/<img([^>]*)\bsrc\s*=\s*(["\'])(https?:\/\/[^"\']+)\2([^>]*)>/i',
            fn($m) => '<img' . $m[1] . ' src="" data-blocked-src=' . $m[2] . $m[3] . $m[2] . $m[4] . ' alt="[image blocked]">',
            $html
        );
    }

    // Determine dark/light from cookie preference for iframe body color
    $theme = $_COOKIE['wm_theme'] ?? 'system';
    $bodyBg = $theme === 'dark' ? '#161b22' : ($theme === 'light' ? '#ffffff' : '#ffffff');
    $bodyColor = $theme === 'dark' ? '#e6edf3' : '#1a2332';

    header('Content-Type: text/html; charset=UTF-8');
    header('Content-Security-Policy: default-src \'none\'; style-src \'unsafe-inline\'; img-src ' . ($showImages ? 'https: data:' : 'data:') . '; font-src \'none\'');
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8">';
    echo '<style>body{margin:0;padding:1rem;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;font-size:.9rem;line-height:1.6;background:' . $bodyBg . ';color:' . $bodyColor . '}img{max-width:100%;height:auto}a{color:#2563eb}</style>';
    echo '</head><body>' . $html . '</body></html>';
    exit;
}

// ── Delete message ────────────────────────────────────────────────────────────
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $msgNo  = (int) ($_GET['msg']    ?? 0);
    $folder = $_GET['folder'] ?? 'INBOX';
    $trashCandidates = ['Trash', 'Deleted', 'Deleted Items'];
    $isTrashFolder = in_array(strtoupper($folder), array_map('strtoupper', $trashCandidates), true);

    try {
        $imap = $accountMgr->imapConnect($accountId);
        if ($isTrashFolder) {
            $imap->deleteMessage($folder, $msgNo);
        } else {
            $trash = findFolderName($folders, $trashCandidates, 'Trash');
            $imap->moveMessage($folder, $msgNo, $trash);
        }
        $imap->disconnect();
        flashSet('success', $isTrashFolder ? 'Message deleted.' : 'Moved to Trash.');
    } catch (RuntimeException $e) {
        flashSet('danger', 'Delete failed: ' . $e->getMessage());
    }
    redirect('?action=inbox&folder=' . urlencode($folder));
}

// ── Bulk actions ──────────────────────────────────────────────────────────────
if ($action === 'bulk' && isAjax()) {
    $body   = json_decode(file_get_contents('php://input'), true) ?? [];
    $folder = $body['folder'] ?? 'INBOX';
    $uids   = array_map('intval', $body['uids'] ?? []);
    $act    = $body['action'] ?? '';
    $trashCandidates = ['Trash', 'Deleted', 'Deleted Items'];

    try {
        $imap = $accountMgr->imapConnect($accountId);
        foreach ($uids as $uid) {
            match ($act) {
                'delete' => (function() use ($imap, $folder, $trashCandidates, $uid) {
                    $isTrash = in_array(strtoupper($folder), array_map('strtoupper', $trashCandidates), true);
                    if ($isTrash) {
                        $imap->deleteMessage($folder, $uid);
                    } else {
                        $trash = findFolderName($imap->getFolders(), $trashCandidates, 'Trash');
                        $imap->moveMessage($folder, $uid, $trash);
                    }
                })(),
                'read'   => $imap->markRead($folder, $uid, true),
                'unread' => $imap->markRead($folder, $uid, false),
                default  => null,
            };
        }
        $imap->disconnect();
        jsonResponse(['ok' => true]);
    } catch (RuntimeException $e) {
        jsonResponse(['ok' => false, 'error' => $e->getMessage()]);
    }
}

// ── Attachment download ───────────────────────────────────────────────────────
if ($action === 'attachment') {
    $msgNo   = (int) ($_GET['msg']     ?? 0);
    $section = $_GET['section'] ?? '';
    $folder  = $_GET['folder']  ?? 'INBOX';
    $name    = basename($_GET['name'] ?? 'attachment');

    try {
        $imap = $accountMgr->imapConnect($accountId);
        $data = $imap->fetchAttachment($msgNo, $section);
        $imap->disconnect();
    } catch (RuntimeException $e) {
        http_response_code(500);
        exit('Error: ' . htmlspecialchars($e->getMessage()));
    }

    header('Content-Disposition: attachment; filename="' . $name . '"');
    header('Content-Type: application/octet-stream');
    header('Content-Length: ' . strlen($data));
    echo $data;
    exit;
}

// ── Compose / Reply / Forward ─────────────────────────────────────────────────
if ($action === 'compose') {
    $prefill  = [];
    $replyMsg = 0;

    // Reply
    if (isset($_GET['reply']) || isset($_GET['reply_all']) || isset($_GET['forward'])) {
        $origNo = (int) ($_GET['reply'] ?? $_GET['reply_all'] ?? $_GET['forward'] ?? 0);
        try {
            $imap    = $accountMgr->imapConnect($accountId);
            $orig    = $imap->getMessage($currentFolder, $origNo);
            $imap->disconnect();

            $isForward  = isset($_GET['forward']);
            $isReplyAll = isset($_GET['reply_all']);

            $replyAddress = $orig['reply_to'] ?: $orig['from'];

            $prefill = [
                'to'          => $isForward ? '' : $replyAddress,
                'cc'          => $isReplyAll ? $orig['to'] : '',
                'subject'     => ($isForward ? 'Fwd: ' : 'Re: ') . preg_replace('/^(Re:|Fwd:)\s*/i', '', $orig['subject']),
                'in_reply_to' => "<{$orig['uid']}@{$_SERVER['HTTP_HOST']}>",
                'reply_to'    => '',
                'body_html'   => $isForward
                    ? '<br><br><hr><b>--- Forwarded message ---</b><br>' . $orig['body_html']
                    : '<br><br><blockquote style="border-left:3px solid #ccc;margin:0;padding-left:1em;color:#666">' . $orig['body_html'] . '</blockquote>',
            ];
            $replyMsg = $origNo;
        } catch (RuntimeException $e) {
            flashSet('danger', 'Could not load original: ' . $e->getMessage());
        }
    }

    $account = $accountMgr->get($accountId);
    $user    = Database::getInstance()->fetch('SELECT signature FROM users WHERE id = ?', [$userId]);
    $signature = $account['signature'] ?? ($user['signature'] ?? '');

    render('compose', $layoutCommon + [
        'prefill'           => $prefill,
        'replyMsg'          => $replyMsg,
        'folder'            => $currentFolder,
        'currentAccountId'  => $accountId,
        'signature'         => $signature,
        'pageTitle'         => pageTitle('Compose'),
    ]);
    exit;
}

// ── Send message ──────────────────────────────────────────────────────────────
if ($action === 'send' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $fromAccountId = (int) ($_POST['from_account'] ?? $accountId);
    if (!$accountMgr->belongsToUser($fromAccountId, $userId)) {
        $fromAccountId = $accountId;
    }

    $smtp   = new SmtpClient();
    $params = $accountMgr->smtpParams($fromAccountId);
    $user   = Database::getInstance()->fetch('SELECT display_name FROM users WHERE id = ?', [$userId]);
    $accountFrom = $accountMgr->get($fromAccountId);

    $fromName = ($accountFrom['sender_name'] ?? '');
    if ($fromName === '' && ($user['display_name'] ?? '') !== '') {
        $fromName = $user['display_name'];
    }
    if ($fromName === '' && ($accountFrom['label'] ?? '') !== '') {
        $fromName = $accountFrom['label'];
    }

    $message = [
        'to'          => $_POST['to']       ?? '',
        'cc'          => $_POST['cc']       ?? '',
        'bcc'         => $_POST['bcc']      ?? '',
        'subject'     => $_POST['subject']  ?? '',
        'reply_to'    => $_POST['reply_to'] ?? '',
        'in_reply_to' => $_POST['in_reply_to'] ?? '',
        'body_html'   => $_POST['body_html'] ?? '',
        'from_name'   => $fromName,
    ];

    $attachments = [];
    if (!empty($_FILES['attachments']) && is_array($_FILES['attachments']['name'])) {
        $maxSize = 50 * 1024 * 1024; // 50 MB (requires matching PHP upload_max_filesize/post_max_size)
        $phpUpload = parseIniSize(ini_get('upload_max_filesize'));
        $phpPost   = parseIniSize(ini_get('post_max_size'));
        $limitAdjusted = false;
        if ($phpUpload > 0 && $phpUpload < $maxSize) {
            error_log('Attachment limit reduced to PHP upload_max_filesize: ' . $phpUpload . ' bytes');
            $maxSize = $phpUpload;
            $limitAdjusted = true;
        }
        if ($phpPost > 0 && $phpPost < $maxSize) {
            error_log('Attachment limit reduced to PHP post_max_size: ' . $phpPost . ' bytes');
            $maxSize = $phpPost;
            $limitAdjusted = true;
        }
        if ($limitAdjusted) {
            $mb = round($maxSize / (1024 * 1024), 1);
            flashSet('warning', 'Server limits attachments to ' . $mb . ' MB due to PHP configuration.');
        }
        foreach ($_FILES['attachments']['name'] as $i => $name) {
            if (($_FILES['attachments']['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                continue;
            }
            $tmp = $_FILES['attachments']['tmp_name'][$i] ?? '';
            if ($tmp === '' || !is_uploaded_file($tmp)) {
                continue;
            }
            if (filesize($tmp) > $maxSize) {
                continue;
            }
            $data = file_get_contents($tmp);
            if ($data === false) {
                continue;
            }
            $attachments[] = [
                'name' => $name,
                'type' => $_FILES['attachments']['type'][$i] ?: 'application/octet-stream',
                'data' => $data,
            ];
        }
    }
    if (!empty($attachments)) {
        $message['attachments'] = $attachments;
    }

    $isDraft = !empty($_POST['save_draft']);

    if ($isDraft) {
        $raw = $smtp->buildRaw($accountFrom['email'], $message);
        try {
            $imap = $accountMgr->imapConnect($fromAccountId);
            $drafts = findFolderName($imap->getFolders(), ['Drafts'], 'Drafts');
            $imap->appendToFolder($drafts, $raw, '\\Draft');
            $imap->disconnect();
            flashSet('success', 'Draft saved.');
        } catch (RuntimeException $e) {
            flashSet('danger', 'Could not save draft: ' . $e->getMessage());
        }
        redirect('?action=inbox');
    }

    if ($smtp->send($params, $message)) {
        $raw = $smtp->getLastRaw() ?: $smtp->buildRaw($accountFrom['email'], $message);
        try {
            $imap = $accountMgr->imapConnect($fromAccountId);
            $sent = findFolderName($imap->getFolders(), ['Sent', 'Sent Items'], 'Sent');
            $imap->appendToFolder($sent, $raw);
            $imap->disconnect();
        } catch (RuntimeException $e) {
            error_log('Sent folder sync failed for account ' . $fromAccountId . ': ' . $e->getMessage());
            // Non-fatal: still consider email sent
        }
        flashSet('success', 'Message sent successfully.');
        redirect('?action=inbox');
    } else {
        flashSet('danger', 'Failed to send: ' . $smtp->getLog());
        redirect('?action=compose');
    }
}

// ── Settings ──────────────────────────────────────────────────────────────────
if ($action === 'settings') {
    $tab  = $_GET['tab'] ?? 'profile';
    $user = Database::getInstance()->fetch('SELECT * FROM users WHERE id = ?', [$userId]);
    $activeAccount = $accountMgr->get($accountId);
    $tf   = new TwoFactor();

    $totpSecret    = null;
    $recoveryCodes = null;
    $qrUrl         = null;

    // Start 2FA enrollment
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['start_2fa'])) {
        $totpSecret    = $tf->generateSecret();
        $recoveryCodes = $tf->generateRecoveryCodes();
        $qrUrl         = $tf->getQRCodeUrl($totpSecret, $user['email']);
        $tab           = 'security';
        // Stash in session so we can retrieve after verification
        $_SESSION['2fa_enroll'] = [
            'secret' => $totpSecret,
            'codes'  => $recoveryCodes,
        ];
    }

    render('settings', $layoutCommon + [
        'tab'           => $tab,
        'user'          => $user,
        'account'       => $activeAccount,
        'totpSecret'    => $totpSecret,
        'recoveryCodes' => $recoveryCodes,
        'qrUrl'         => $qrUrl,
        'pageTitle'     => pageTitle('Settings'),
    ]);
    exit;
}

if ($action === 'settings_save') {
    $tab = $_GET['tab'] ?? 'profile';
    $db  = Database::getInstance();
    $auth = new Auth();
    $account = $accountMgr->get($accountId);

    switch ($tab) {
        case 'profile':
            $name = trim($_POST['display_name'] ?? '');
            if ($account !== null) {
                $accountMgr->update($accountId, $userId, [
                    'label'         => $account['label'],
                    'sender_name'   => $name,
                    'signature'     => $account['signature'] ?? '',
                    'email'         => $account['email'],
                    'imap_host'     => $account['imap_host'],
                    'imap_port'     => (int)$account['imap_port'],
                    'imap_ssl'      => (int)$account['imap_ssl'],
                    'smtp_host'     => $account['smtp_host'],
                    'smtp_port'     => (int)$account['smtp_port'],
                    'smtp_ssl'      => (int)$account['smtp_ssl'],
                    'smtp_starttls' => (int)$account['smtp_starttls'],
                    'username'      => $account['username'],
                ]);
            }
            flashSet('success', 'Profile saved for this account.');
            redirect('?action=settings&tab=profile');

        case 'signature':
            $sig = $_POST['signature'] ?? '';
            if ($account !== null) {
                $accountMgr->update($accountId, $userId, [
                    'label'         => $account['label'],
                    'sender_name'   => $account['sender_name'] ?? '',
                    'signature'     => $sig,
                    'email'         => $account['email'],
                    'imap_host'     => $account['imap_host'],
                    'imap_port'     => (int)$account['imap_port'],
                    'imap_ssl'      => (int)$account['imap_ssl'],
                    'smtp_host'     => $account['smtp_host'],
                    'smtp_port'     => (int)$account['smtp_port'],
                    'smtp_ssl'      => (int)$account['smtp_ssl'],
                    'smtp_starttls' => (int)$account['smtp_starttls'],
                    'username'      => $account['username'],
                ]);
            }
            flashSet('success', 'Signature saved for this account.');
            redirect('?action=settings&tab=profile');

        case 'enable_2fa':
            $tf      = new TwoFactor();
            $secret  = $_POST['totp_secret']    ?? ($_SESSION['2fa_enroll']['secret'] ?? '');
            $codes   = json_decode($_POST['recovery_codes'] ?? '[]', true) ?: ($_SESSION['2fa_enroll']['codes'] ?? []);
            $code    = trim($_POST['verify_code'] ?? '');

            if (!$tf->verify($secret, $code)) {
                flashSet('danger', 'Verification code is incorrect. Please try again.');
                redirect('?action=settings&tab=security');
            }

            $auth->enable2FA($userId, $secret, $codes);
            unset($_SESSION['2fa_enroll']);
            flashSet('success', '2FA has been enabled. Please log in again.');
            redirect('?action=login');

        case 'disable_2fa':
            $auth->disable2FA($userId);
            flashSet('success', 'Two-factor authentication has been disabled.');
            redirect('?action=settings&tab=security');

        case 'revoke_sessions':
            (new Session())->destroyAll($userId);
            redirect('?action=login');

        case 'add_account':
            $mgr = new Account();
            $smtpSsl = !empty($_POST['smtp_ssl']);
            $smtpStarttls = !empty($_POST['smtp_starttls']);
            if ($smtpSsl) {
                $smtpStarttls = false;
            }
            $mgr->add($userId, [
                'label'         => trim($_POST['label']      ?? ''),
                'sender_name'   => trim($_POST['sender_name'] ?? ''),
                'email'         => trim($_POST['email']      ?? ''),
                'imap_host'     => trim($_POST['imap_host']  ?? ''),
                'imap_port'     => (int) ($_POST['imap_port'] ?? 993),
                'imap_ssl'      => !empty($_POST['imap_ssl']),
                'smtp_host'     => trim($_POST['smtp_host']  ?? ''),
                'smtp_port'     => (int) ($_POST['smtp_port'] ?? 587),
                'smtp_ssl'      => $smtpSsl,
                'smtp_starttls' => $smtpStarttls,
                'username'      => trim($_POST['username']   ?? ''),
                'password'      => $_POST['password']        ?? '',
            ]);
            flashSet('success', 'Account added.');
            redirect('?action=settings&tab=accounts');

        case 'delete_account':
            $mgr = new Account();
            $mgr->delete((int) ($_POST['account_id'] ?? 0), $userId);
            flashSet('success', 'Account removed.');
            redirect('?action=settings&tab=accounts');

        case 'appearance':
            $theme = $_POST['theme'] ?? 'system';
            if (!in_array($theme, Config::THEMES, true)) {
                $theme = 'system';
            }
            $db->query('UPDATE users SET theme = ? WHERE id = ?', [$theme, $userId]);
            flashSet('success', 'Theme preference saved.');
            redirect('?action=settings&tab=appearance');

        default:
            redirect('?action=settings');
    }
}

// ── Fallback ──────────────────────────────────────────────────────────────────
redirect('?action=inbox');

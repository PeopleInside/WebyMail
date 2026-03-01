<?php
declare(strict_types=1);

ob_start();

// Prevent caching for the whole app
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');

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

// Set timezone from config
date_default_timezone_set(Config::get('timezone', 'Europe/Rome'));

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

const TRASH_FOLDER_VARIANTS = ['Trash', 'Deleted', 'Deleted Items'];
const SPAM_FOLDER_VARIANTS  = ['Spam', 'Junk', 'Junk E-mail', 'Junk Mail'];

function resolveTrashFolder(ImapClient $imap): string
{
    /**
     * Resolve the Trash folder name across common variants, falling back to "Trash".
     *
     * @param ImapClient $imap
     * @return string
     */
    return findFolderName($imap->getFolders(), TRASH_FOLDER_VARIANTS, 'Trash');
}

function resolveSpamFolder(ImapClient $imap): string
{
    return findFolderName($imap->getFolders(), SPAM_FOLDER_VARIANTS, 'Spam');
}

function isTrashFolderEquivalent(string $folder, string $trash): bool
{
    $normalizeFolderName = static function (string $name): string {
        $parts = preg_split('/[\.\\/]/', $name) ?: [$name];
        return strtolower(end($parts));
    };
    return $normalizeFolderName($folder) === $normalizeFolderName($trash);
}

function moveToTrashOrDelete(ImapClient $imap, string $folder, int $msgNo, string $trash, bool $isTrashFolder): void
{
    // Case-insensitive folder name comparison using the last path segment.
    // Messages already in Trash are permanently deleted; others are moved into Trash.
    if ($isTrashFolder) {
        $imap->deleteMessage($folder, $msgNo);
    } else {
        $imap->moveMessage($folder, $msgNo, $trash);
    }
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
    $sessionObj = new Session();
    $session = $sessionObj->current();
    if ($session === null) {
        redirect('?action=login');
    }

    // Validate CSRF for all POST requests when authenticated
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if ($token === '' && isAjax()) {
            $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        }
        if ($token === '' || $token !== $sessionObj->getCsrfToken()) {
            if (isAjax()) {
                jsonResponse(['ok' => false, 'error' => 'Security token mismatch. Please refresh the page.']);
            }
            flashSet('danger', 'Security token mismatch. Please try again.');
            redirect($_SERVER['HTTP_REFERER'] ?? '?action=inbox');
        }
    }

    return $session;
}

function csrfToken(): string
{
    return (new Session())->getCsrfToken();
}

function csrfInput(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken()) . '">';
}

// ── Router ────────────────────────────────────────────────────────────────────

$action = $_GET['action'] ?? 'inbox';

// ── Proof-of-Work captcha challenge endpoint (GET, public) ─────────────────────
if ($action === 'pow_challenge') {
    if (!Config::get('captcha_enabled', true)) {
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
if ($action === 'cancel_2fa') {
    unset($_SESSION['pending_2fa']);
    redirect('?action=login');
}

if ($action === 'login') {
    // Already logged in?
    $session = (new Session())->current();
    if ($session !== null) {
        redirect('?action=inbox');
    }

    $error   = null;
    $needs2fa = isset($_SESSION['pending_2fa']) && time() < ($_SESSION['pending_2fa']['expires'] ?? 0);
    $captchaEnabled = (bool) Config::get('captcha_enabled', true);
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
            $serverSettingsShown = !empty($_POST['server_settings_shown']);

            $host     = trim($_POST['imap_host'] ?? Config::get('imap_host', ''));
            $port     = (int) ($_POST['imap_port'] ?? Config::get('imap_port', 993));
            $ssl      = $serverSettingsShown ? !empty($_POST['imap_ssl']) : (bool) Config::get('imap_ssl', true);
            
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            
            $smtpHost = trim($_POST['smtp_host'] ?? Config::get('smtp_host', $host));
            $smtpPort = (int) ($_POST['smtp_port'] ?? Config::get('smtp_port', 465));
            $smtpSsl  = $serverSettingsShown ? !empty($_POST['smtp_ssl']) : (bool) Config::get('smtp_ssl', true);
            $smtpTls  = $serverSettingsShown ? !empty($_POST['smtp_starttls']) : (bool) Config::get('smtp_starttls', false);

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
                sleep(1); // Deter brute force
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

// Check if setup.php still exists to show a warning banner
$setupBanner = null;
if (file_exists(WEBYMAIL_ROOT . '/setup.php')) {
    $setupBanner = 'Setup is incomplete. Please open <a href="setup.php?force=1">setup.php?force=1</a> to finish configuration.';
}

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
    // Sort: INBOX first, then others alphabetically
    usort($folders, function (array $a, array $b): int {
        $aIsInbox = strtoupper($a['name']) === 'INBOX';
        $bIsInbox = strtoupper($b['name']) === 'INBOX';
        if ($aIsInbox !== $bIsInbox) {
            return $aIsInbox ? -1 : 1;
        }
        return strcmp($a['display'], $b['display']);
    });
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
    'setupBanner'   => $setupBanner,
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

// ── Folder management ─────────────────────────────────────────────────────────
if ($action === 'create_folder' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    if ($name === '') {
        flashSet('danger', 'Folder name cannot be empty.');
        redirect('?action=inbox');
    }
    try {
        $imap = $accountMgr->imapConnect($accountId);
        $imap->createFolder($name);
        $imap->disconnect();
        flashSet('success', 'Folder "' . htmlspecialchars($name) . '" created.');
    } catch (RuntimeException $e) {
        flashSet('danger', 'Could not create folder: ' . $e->getMessage());
    }
    redirect('?action=inbox');
}

if ($action === 'rename_folder' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $oldName = trim($_POST['old_name'] ?? '');
    $newName = trim($_POST['new_name'] ?? '');
    if ($oldName === '' || $newName === '') {
        flashSet('danger', 'Folder names cannot be empty.');
        redirect('?action=inbox');
    }
    try {
        $imap = $accountMgr->imapConnect($accountId);
        $imap->renameFolder($oldName, $newName);
        $imap->disconnect();
        flashSet('success', 'Folder renamed to "' . htmlspecialchars($newName) . '".');
    } catch (RuntimeException $e) {
        flashSet('danger', 'Could not rename folder: ' . $e->getMessage());
    }
    redirect('?action=inbox');
}

if ($action === 'delete_folder' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    if (strtoupper($name) === 'INBOX') {
        flashSet('danger', 'Cannot delete INBOX.');
        redirect('?action=inbox');
    }
    if ($name === '') {
        flashSet('danger', 'Folder name cannot be empty.');
        redirect('?action=inbox');
    }
    try {
        $imap = $accountMgr->imapConnect($accountId);
        $imap->deleteFolder($name);
        $imap->disconnect();
        flashSet('success', 'Folder "' . htmlspecialchars($name) . '" deleted.');
    } catch (RuntimeException $e) {
        flashSet('danger', 'Could not delete folder: ' . $e->getMessage());
    }
    redirect('?action=inbox');
}

if ($action === 'empty_folder' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $folder = $_POST['folder'] ?? $currentFolder;
    try {
        $imap = $accountMgr->imapConnect($accountId);
        $imap->emptyFolder($folder);
        $imap->disconnect();
        flashSet('success', 'Folder "' . htmlspecialchars($folder) . '" emptied.');
    } catch (RuntimeException $e) {
        flashSet('danger', 'Could not empty folder: ' . $e->getMessage());
    }
    redirect('?action=inbox&folder=' . urlencode($folder));
}

// ── Contacts ──────────────────────────────────────────────────────────────────
if ($action === 'contacts_list' && isAjax()) {
    $contactMgr = new Contact($userId);
    jsonResponse(['ok' => true, 'contacts' => $contactMgr->list()]);
}

if ($action === 'contacts_add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $phone   = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $notes   = trim($_POST['notes'] ?? '');
    
    if ($name === '' || $email === '') {
        if (isAjax()) jsonResponse(['ok' => false, 'error' => 'Name and email are required.']);
        flashSet('danger', 'Name and email are required.');
        redirect($_SERVER['HTTP_REFERER'] ?? '?action=inbox');
    }
    
    $contactMgr = new Contact($userId);
    $contactMgr->add($name, $email, $phone, $address, $notes);
    
    if (isAjax()) jsonResponse(['ok' => true]);
    flashSet('success', 'Contact added.');
    redirect($_SERVER['HTTP_REFERER'] ?? '?action=inbox');
}

if ($action === 'contacts_edit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id      = (int) ($_POST['id'] ?? 0);
    $name    = trim($_POST['name'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $phone   = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $notes   = trim($_POST['notes'] ?? '');
    
    if ($id === 0 || $name === '' || $email === '') {
        if (isAjax()) jsonResponse(['ok' => false, 'error' => 'ID, name and email are required.']);
        flashSet('danger', 'ID, name and email are required.');
        redirect($_SERVER['HTTP_REFERER'] ?? '?action=inbox');
    }
    
    $contactMgr = new Contact($userId);
    $contactMgr->edit($id, $name, $email, $phone, $address, $notes);
    
    if (isAjax()) jsonResponse(['ok' => true]);
    flashSet('success', 'Contact updated.');
    redirect($_SERVER['HTTP_REFERER'] ?? '?action=inbox');
}

if ($action === 'contacts_delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
    $contactMgr = new Contact($userId);
    $contactMgr->delete($id);
    
    if (isAjax()) jsonResponse(['ok' => true]);
    flashSet('success', 'Contact deleted.');
    redirect($_SERVER['HTTP_REFERER'] ?? '?action=inbox');
}

// ── Inbox ─────────────────────────────────────────────────────────────────────
if ($action === 'inbox' || $action === 'search') {
    $page    = max(1, (int) ($_GET['page'] ?? 1));
    $mailData = [];

    $imap = null;
    $isTrash = false;
    $isSpam = false;
    try {
        $imap = $accountMgr->imapConnect($accountId);
        $mailData = $imap->getMessages($currentFolder, $page);
        $trash = resolveTrashFolder($imap);
        $isTrash = isTrashFolderEquivalent($currentFolder, $trash);
        $isSpam = in_array(strtoupper($currentFolder), ['SPAM', 'JUNK', 'JUNK E-MAIL'], true);
        $imap->disconnect();
    } catch (RuntimeException $e) {
        $flash = ['type' => 'danger', 'message' => 'IMAP error: ' . $e->getMessage()];
    }

    render('inbox', $layoutCommon + [
        'mailData'     => $mailData,
        'folder'       => $currentFolder,
        'isTrash'      => $isTrash,
        'isSpam'       => $isSpam,
        'pageTitle'    => pageTitle($imap ? $imap->getFriendlyName($currentFolder) : $currentFolder),
    ]);
    exit;
}

// ── View message ──────────────────────────────────────────────────────────────
if ($action === 'view') {
    $msgNo  = (int) ($_GET['msg'] ?? 0);
    $message = null;
    $isTrash = false;

    try {
        $imap    = $accountMgr->imapConnect($accountId);
        $message = $imap->getMessage($currentFolder, $msgNo);
        $trash   = resolveTrashFolder($imap);
        $isTrash = isTrashFolderEquivalent($currentFolder, $trash);
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
        'isTrash'     => $isTrash,
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
    $isAjax = !empty($_GET['ajax']) && $_GET['ajax'] === '1';

    // Determine initial theme from cookie
    $theme = $_COOKIE['wm_theme'] ?? 'system';
    
    // We use CSS variables so we can toggle them via JS without reload
    $lightBg = '#ffffff';
    $lightColor = '#1a2332';
    $darkBg = '#161b22';
    $darkColor = '#e6edf3';

    // Sanitize and process HTML
    if (class_exists('DOMDocument') && trim($html) !== '') {
        $doc = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        
        // Check if it's a full document or a fragment
        $isFullDoc = str_contains(strtolower($html), '<html');
        
        if ($isFullDoc) {
            $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NODEFDTD);
        } else {
            $doc->loadHTML('<?xml encoding="UTF-8"><html><body>' . $html . '</body></html>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        }
        libxml_clear_errors();

        // Remove dangerous tags
        $dangerousTags = ['script', 'iframe', 'object', 'embed', 'link', 'meta', 'base', 'style', 'applet', 'frameset', 'frame', 'video', 'audio', 'canvas'];
        foreach ($dangerousTags as $tag) {
            $nodes = $doc->getElementsByTagName($tag);
            while ($nodes->length > 0) {
                $node = $nodes->item(0);
                $node->parentNode->removeChild($node);
            }
        }

        // Remove event handlers and other dangerous attributes
        $xpath = new DOMXPath($doc);
        $nodes = $xpath->query('//@*');
        foreach ($nodes as $attr) {
            $name = strtolower($attr->nodeName);
            if (str_starts_with($name, 'on') || in_array($name, ['formaction', 'form'], true)) {
                $attr->parentNode->removeAttribute($attr->nodeName);
            }
        }

        // Sanitize links
        foreach ($doc->getElementsByTagName('a') as $a) {
            $href = $a->getAttribute('href');
            $normalised = strtolower(preg_replace('/[\x00-\x20]+/', '', html_entity_decode($href, ENT_QUOTES | ENT_HTML5)));
            if (str_starts_with($normalised, 'javascript:') || str_starts_with($normalised, 'vbscript:') || str_starts_with($normalised, 'data:text/html')) {
                $a->setAttribute('href', '#');
            }
            $a->setAttribute('target', '_blank');
            $a->setAttribute('rel', 'noopener noreferrer');
        }

        // Handle images (blocking)
        if (!$showImages) {
            foreach ($doc->getElementsByTagName('img') as $img) {
                $src = $img->getAttribute('src');
                if (str_starts_with(strtolower($src), 'http')) {
                    $img->setAttribute('data-blocked-src', $src);
                    $img->setAttribute('src', '');
                    $img->setAttribute('alt', '[image blocked]');
                }
            }
        }

        // Set initial theme
        $doc->documentElement->setAttribute('data-theme', $theme);

        // Inject base styles and meta
        $head = $doc->getElementsByTagName('head')->item(0);
        if (!$head) {
            $head = $doc->createElement('head');
            $doc->documentElement->insertBefore($head, $doc->documentElement->firstChild);
        }
        
        // Add base target
        $base = $doc->createElement('base');
        $base->setAttribute('target', '_blank');
        $base->setAttribute('rel', 'noopener noreferrer');
        $head->appendChild($base);

        // Add our styles with CSS variables for dynamic theming
        $styleStr = '
            :root { --bg: ' . $lightBg . '; --color: ' . $lightColor . '; }
            [data-theme="dark"] { --bg: ' . $darkBg . '; --color: ' . $darkColor . '; }
            @media (prefers-color-scheme: dark) {
                :root:not([data-theme="light"]) { --bg: ' . $darkBg . '; --color: ' . $darkColor . '; }
            }
            body {
                margin: 0;
                padding: 1.5rem;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
                font-size: .95rem;
                line-height: 1.6;
                background: var(--bg);
                color: var(--color);
                word-break: break-word;
                height: auto;
                min-height: auto;
                transition: background-color .2s, color .2s;
            }
            img { max-width: 100%; height: auto; }
            a { color: #2563eb; }
        ';
        $style = $doc->createElement('style', $styleStr);
        $head->appendChild($style);

        // Add script for live theme updates
        $scriptStr = "
            window.addEventListener('message', function(e) {
                if (e.data && e.data.type === 'wm-theme-change') {
                    document.documentElement.setAttribute('data-theme', e.data.theme);
                }
            });
        ";
        $script = $doc->createElement('script', $scriptStr);
        $head->appendChild($script);

        $html = $doc->saveHTML();
    } else {
        // Fallback simple wrap if DOMDocument fails or is missing
        $styleStr = '
            :root { --bg: ' . $lightBg . '; --color: ' . $lightColor . '; }
            [data-theme="dark"] { --bg: ' . $darkBg . '; --color: ' . $darkColor . '; }
            body {
                margin: 0;
                padding: 1.5rem;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
                font-size: .95rem;
                line-height: 1.6;
                background: var(--bg);
                color: var(--color);
                word-break: break-word;
            }
            img { max-width: 100%; height: auto; }
            a { color: #2563eb; }
        ';
        $scriptStr = "<script>window.addEventListener('message', function(e) { if (e.data && e.data.type === 'wm-theme-change') { document.documentElement.setAttribute('data-theme', e.data.theme); } });</script>";
        $html = '<!DOCTYPE html><html data-theme="' . htmlspecialchars($theme) . '"><head><meta charset="UTF-8"><style>' . $styleStr . '</style>' . $scriptStr . '</head><body>' . $html . '</body></html>';
    }

    header('Content-Type: text/html; charset=UTF-8');
    header('Content-Security-Policy: default-src \'none\'; script-src \'unsafe-inline\'; style-src \'unsafe-inline\'; img-src ' . ($showImages ? 'https: data:' : 'data:') . '; font-src \'none\'');
    echo $html;
    exit;
}

// ── Import EML ──────────────────────────────────────────────────────────────
if ($action === 'import_eml' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $folder = $_POST['folder'] ?? 'INBOX';
    $file = $_FILES['eml_file'] ?? null;

    if ($file && $file['error'] === UPLOAD_ERR_OK) {
        $raw = file_get_contents($file['tmp_name']);
        try {
            $imap = $accountMgr->imapConnect($accountId);
            if ($imap->appendMessage($folder, $raw)) {
                $flash = ['type' => 'success', 'message' => 'Email imported successfully.'];
            } else {
                $flash = ['type' => 'danger', 'message' => 'Failed to import email.'];
            }
            $imap->disconnect();
        } catch (Exception $e) {
            $flash = ['type' => 'danger', 'message' => 'Error: ' . $e->getMessage()];
        }
    } else {
        $flash = ['type' => 'danger', 'message' => 'No file uploaded or upload error.'];
    }
    
    $_SESSION['flash'] = $flash;
    redirect('?action=inbox&folder=' . urlencode($folder));
}

// ── Delete message ────────────────────────────────────────────────────────────
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $msgNo  = (int) ($_GET['msg']    ?? 0);
    $folder = $_GET['folder'] ?? 'INBOX';

    try {
        $imap = $accountMgr->imapConnect($accountId);
        $trash = resolveTrashFolder($imap);
        $inTrash = isTrashFolderEquivalent($folder, $trash);
        moveToTrashOrDelete($imap, $folder, $msgNo, $trash, $inTrash);
        $imap->disconnect();
        flashSet('success', $inTrash ? 'Message deleted.' : 'Message moved to Trash.');
    } catch (RuntimeException $e) {
        flashSet('danger', 'Delete failed: ' . $e->getMessage());
    }
    redirect('?action=inbox&folder=' . urlencode($folder));
}

// ── Mark as spam ──────────────────────────────────────────────────────────────
if ($action === 'spam' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $msgNo  = (int) ($_GET['msg']    ?? 0);
    $folder = $_GET['folder'] ?? 'INBOX';

    try {
        $imap = $accountMgr->imapConnect($accountId);
        $spam = resolveSpamFolder($imap);
        $imap->moveMessage($folder, $msgNo, $spam);
        $imap->disconnect();
        flashSet('success', 'Message moved to Spam.');
    } catch (RuntimeException $e) {
        flashSet('danger', 'Could not move to Spam: ' . $e->getMessage());
    }
    redirect('?action=inbox&folder=' . urlencode($folder));
}

// ── Email headers (raw) ───────────────────────────────────────────────────────
if ($action === 'email_headers' && isAjax()) {
    $msgNo  = (int) ($_GET['msg']    ?? 0);
    $folder = $_GET['folder'] ?? 'INBOX';

    try {
        $imap    = $accountMgr->imapConnect($accountId);
        $headers = $imap->getRawHeaders($folder, $msgNo);
        $imap->disconnect();
        jsonResponse(['ok' => true, 'headers' => $headers]);
    } catch (RuntimeException $e) {
        jsonResponse(['ok' => false, 'error' => $e->getMessage()]);
    }
}

// ── Bulk actions ──────────────────────────────────────────────────────────────
if ($action === 'bulk' && isAjax()) {
    $body   = json_decode(file_get_contents('php://input'), true) ?? [];
    $folder = $body['folder'] ?? 'INBOX';
    $uids   = array_map('intval', $body['uids'] ?? []);
    $act    = $body['action'] ?? '';

    try {
        $imap = $accountMgr->imapConnect($accountId);
        if ($act === 'delete') {
            $trash = resolveTrashFolder($imap);
            $inTrash = isTrashFolderEquivalent($folder, $trash);
            foreach ($uids as $uid) {
                moveToTrashOrDelete($imap, $folder, $uid, $trash, $inTrash);
            }
        } else {
            foreach ($uids as $uid) {
                match ($act) {
                    'read'   => $imap->markRead($folder, $uid, true),
                    'unread' => $imap->markRead($folder, $uid, false),
                    default  => null,
                };
            }
        }
        $imap->disconnect();
        jsonResponse(['ok' => true]);
    } catch (RuntimeException $e) {
        jsonResponse(['ok' => false, 'error' => $e->getMessage()]);
    }
}

// ── Attachment download ───────────────────────────────────────────────────────
if ($action === 'attachment' || $action === 'download_all') {
    $msgNo   = (int) ($_GET['msg']     ?? 0);
    $folder  = $_GET['folder']  ?? 'INBOX';

    if ($action === 'download_all') {
        if (!class_exists('ZipArchive')) {
            exit('Error: ZipArchive extension is not enabled on this server.');
        }

        try {
            $imap = $accountMgr->imapConnect($accountId);
            $imap->selectFolder($folder);
            $structure = $imap->fetchStructure($msgNo);
            $attachments = $imap->getAttachments($msgNo, $structure);

            if (empty($attachments)) {
                exit('No attachments found.');
            }

            $zip = new ZipArchive();
            $zipFile = tempnam(sys_get_temp_dir(), 'wm_zip');
            if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                exit('Failed to create ZIP file.');
            }

            foreach ($attachments as $att) {
                $data = $imap->fetchAttachment($msgNo, $att['section']);
                $zip->addFromString($att['filename'], $data);
            }
            $zip->close();
            $imap->disconnect();

            if (ob_get_level()) ob_clean();
            
            // Anti-cache for downloads
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
            
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="attachments_' . $msgNo . '.zip"');
            header('Content-Length: ' . filesize($zipFile));
            readfile($zipFile);
            unlink($zipFile);
            exit;
        } catch (Throwable $e) {
            if (ob_get_level()) ob_clean();
            http_response_code(500);
            header('Content-Type: text/plain; charset=utf-8');
            exit('Error: ' . $e->getMessage());
        }
    }

    $section = $_GET['section'] ?? '';
    $name    = basename($_GET['name'] ?? 'attachment');

    // Disable errors for binary output to prevent corruption
    ini_set('display_errors', '0');
    error_reporting(0);
    set_time_limit(0);
    ini_set('memory_limit', '256M');

    try {
        $imap = $accountMgr->imapConnect($accountId);
        $data = $imap->fetchAttachment($msgNo, $section);
        $imap->disconnect();
        
        if ($data === '') {
            throw new Exception("Attachment data is empty or could not be retrieved.");
        }
    } catch (Throwable $e) {
        if (ob_get_level()) ob_clean();
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: inline');
        exit('Error downloading attachment: ' . $e->getMessage());
    }

    if (ob_get_level()) ob_clean();
    
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

    $ctype = 'application/octet-stream';
    if ($ext === 'eml') $ctype = 'message/rfc822';

    // Anti-cache for downloads
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');

    // Robust filename handling
    $safeName = str_replace(['"', "\r", "\n"], '', $name);
    header('Content-Disposition: attachment; filename="' . $safeName . '"; filename*=UTF-8\'\'' . rawurlencode($name));
    header('Content-Type: ' . $ctype . '; name="' . $safeName . '"');
    header('Content-Length: ' . strlen($data));
    echo $data;
    exit;
}

// ── Email Export (EML / ZIP) ──────────────────────────────────────────────────
if ($action === 'export_eml') {
    $msgNo   = (int) ($_GET['msg'] ?? 0);
    $folder  = $_GET['folder'] ?? 'INBOX';

    try {
        $imap = $accountMgr->imapConnect($accountId);
        $raw  = $imap->getRawMessage($folder, $msgNo);
        $imap->disconnect();

        if (ob_get_level()) ob_clean();
        header('Content-Type: message/rfc822');
        header('Content-Disposition: attachment; filename="email_' . $msgNo . '.eml"');
        header('Content-Length: ' . strlen($raw));
        echo $raw;
        exit;
    } catch (RuntimeException $e) {
        http_response_code(500);
        exit('Error: ' . htmlspecialchars($e->getMessage()));
    }
}

if ($action === 'export_zip') {
    $uids   = array_map('intval', explode(',', $_GET['uids'] ?? ''));
    $folder = $_GET['folder'] ?? 'INBOX';

    if (empty($uids)) exit('No emails selected.');
    if (!class_exists('ZipArchive')) exit('Error: ZipArchive extension is not enabled.');

    try {
        $imap = $accountMgr->imapConnect($accountId);
        $zip = new ZipArchive();
        $zipFile = tempnam(sys_get_temp_dir(), 'wm_export');
        if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            exit('Failed to create ZIP.');
        }

        foreach ($uids as $uid) {
            $raw = $imap->getRawMessage($folder, $uid);
            $zip->addFromString("email_{$uid}.eml", $raw);
        }
        $zip->close();
        $imap->disconnect();

        if (ob_get_level()) ob_clean();
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="emails_export.zip"');
        header('Content-Length: ' . filesize($zipFile));
        readfile($zipFile);
        unlink($zipFile);
        exit;
    } catch (RuntimeException $e) {
        http_response_code(500);
        exit('Error: ' . htmlspecialchars($e->getMessage()));
    }
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
            $quoteHeader = sprintf('Il %s, %s ha scritto:', $orig['date'], $orig['from']);

            $prefill = [
                'to'          => $isForward ? '' : $replyAddress,
                'cc'          => $isReplyAll ? $orig['to'] : '',
                'subject'     => ($isForward ? 'Fwd: ' : 'Re: ') . preg_replace('/^(Re:|Fwd:)\s*/i', '', $orig['subject']),
                'in_reply_to' => "<{$orig['uid']}@{$_SERVER['HTTP_HOST']}>",
                'reply_to'    => '',
                'body_html'   => $isForward
                    ? '<br><br><hr><b>--- Forwarded message ---</b><br>' . $orig['body_html']
                    : '<br><br>' . htmlspecialchars($quoteHeader) . '<br><blockquote style="border-left:3px solid #ccc;margin:0;padding-left:1em;color:#666">' . $orig['body_html'] . '</blockquote>',
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
        'priority'    => $_POST['priority'] ?? 'normal',
        'request_read_receipt' => !empty($_POST['request_read_receipt']),
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

    // Require a "to" address only when actually sending (not saving a draft)
    if (!$isDraft && trim($message['to']) === '') {
        flashSet('danger', 'A recipient address is required to send a message.');
        redirect('?action=compose');
    }

    if ($isDraft) {
        $raw = $smtp->buildRaw($accountFrom['email'], $message);
        $draftsFolder = 'Drafts';
        try {
            $imap = $accountMgr->imapConnect($fromAccountId);
            $draftsFolder = findFolderName($imap->getFolders(), ['Drafts'], 'Drafts');
            $imap->appendToFolder($draftsFolder, $raw, '\\Draft');
            $imap->disconnect();
            flashSet('success', 'Draft saved.');
        } catch (RuntimeException $e) {
            flashSet('danger', 'Could not save draft: ' . $e->getMessage());
        }
        redirect('?action=inbox&folder=' . urlencode($draftsFolder));
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
        'sessions'      => (new Session())->getAllForUser($userId),
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

        case 'revoke_session':
            $token = $_POST['token'] ?? '';
            if ($token !== '') {
                (new Session())->revoke($token, $userId);
                flashSet('success', 'Session revoked.');
            }
            redirect('?action=settings&tab=security');

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

        case 'edit_account':
            $mgr = new Account();
            $editId = (int) ($_POST['account_id'] ?? 0);
            if (!$mgr->belongsToUser($editId, $userId)) {
                flashSet('danger', 'Account not found.');
                redirect('?action=settings&tab=accounts');
            }
            $existing = $mgr->get($editId);
            $smtpSsl = !empty($_POST['smtp_ssl']);
            $smtpStarttls = !empty($_POST['smtp_starttls']);
            // SSL takes precedence: if both are checked, disable STARTTLS
            if ($smtpSsl) {
                $smtpStarttls = false;
            }
            $updateData = [
                'label'         => trim($_POST['label']       ?? $existing['label']),
                'sender_name'   => trim($_POST['sender_name'] ?? $existing['sender_name'] ?? ''),
                'signature'     => $existing['signature']     ?? '',
                'email'         => trim($_POST['email']       ?? $existing['email']),
                'imap_host'     => trim($_POST['imap_host']   ?? $existing['imap_host']),
                'imap_port'     => (int) ($_POST['imap_port'] ?? $existing['imap_port']),
                'imap_ssl'      => !empty($_POST['imap_ssl']),
                'smtp_host'     => trim($_POST['smtp_host']   ?? $existing['smtp_host']),
                'smtp_port'     => (int) ($_POST['smtp_port'] ?? $existing['smtp_port']),
                'smtp_ssl'      => $smtpSsl,
                'smtp_starttls' => $smtpStarttls,
                'username'      => trim($_POST['username']    ?? $existing['username']),
            ];
            $newPassword = $_POST['password'] ?? '';
            if ($newPassword !== '') {
                $updateData['password'] = $newPassword;
            }
            $mgr->update($editId, $userId, $updateData);
            flashSet('success', 'Account updated.');
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

// ── Fix permissions ───────────────────────────────────────────────────────────
if ($action === 'fix_permissions' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (Config::fixPermissions()) {
        flashSet('success', 'Permissions have been updated.');
        $_SESSION['hide_security_banner'] = true;
    } else {
        flashSet('warning', 'Some permissions could not be updated. Please check your server configuration.');
    }
    redirect('?action=settings&tab=system');
}

// ── View Recovery Codes ───────────────────────────────────────────────────────
if ($action === 'view_recovery_codes' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    if ($password === '' && isAjax()) {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $password = $body['password'] ?? '';
    }

    $accountMgr = new Account();
    $primary = Database::getInstance()->fetch('SELECT id FROM accounts WHERE user_id = ? AND is_primary = 1', [$userId]);
    if ($primary) {
        $acc = $accountMgr->get((int)$primary['id']);
        if ($acc && $acc['password_plain'] === $password) {
            $user = Database::getInstance()->fetch('SELECT recovery_codes FROM users WHERE id = ?', [$userId]);
            $encrypted = json_decode($user['recovery_codes'] ?? '[]', true);
            $tf = new TwoFactor();
            $codes = $tf->decryptRecoveryCodes($encrypted);
            jsonResponse(['ok' => true, 'codes' => $codes]);
        }
    }
    jsonResponse(['ok' => false, 'error' => 'Invalid password.']);
}

// ── Fallback ──────────────────────────────────────────────────────────────────
redirect('?action=inbox');

ob_end_flush();

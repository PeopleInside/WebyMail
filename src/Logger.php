<?php
declare(strict_types=1);

/**
 * Structured security logging for WebyMail.
 * Logs events in JSON format to data/security.log.
 */
class Logger
{
    private string $logFile;

    public function __construct()
    {
        $path = Config::get('security_log_path', 'data/security.log');
        if (!str_starts_with($path, '/') && !str_contains($path, ':')) {
            $path = __DIR__ . '/../' . $path;
        }
        $this->logFile = $path;
        
        $dir = dirname($this->logFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
    }

    /**
     * Log a security event.
     *
     * @param string $event   Event name (e.g., 'login_success', 'login_failure', '2fa_failed')
     * @param array  $context Additional data to log
     */
    public function security(string $event, array $context = []): void
    {
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'level'     => 'SECURITY',
            'event'     => $event,
            'ip'        => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            'ua'        => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'context'   => $context
        ];

        $this->write(json_encode($logEntry));
        $this->rotate();
    }

    private function write(string $message): void
    {
        file_put_contents($this->logFile, $message . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    /**
     * Basic log rotation: if file > 5MB, rotate it.
     */
    private function rotate(): void
    {
        if (!file_exists($this->logFile)) {
            return;
        }

        if (filesize($this->logFile) > 5 * 1024 * 1024) {
            $backup = $this->logFile . '.' . date('YmdHis');
            rename($this->logFile, $backup);
            
            // Keep only last 5 rotated logs
            $dir = dirname($this->logFile);
            $files = glob($this->logFile . '.*');
            if (count($files) > 5) {
                sort($files);
                while (count($files) > 5) {
                    $old = array_shift($files);
                    if ($old) unlink($old);
                }
            }
        }
    }
}

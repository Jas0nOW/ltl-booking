<?php
if (!defined('ABSPATH')) exit;

/**
 * LazyBookings Logger
 * 
 * Privacy-safe logging with configurable levels.
 * PII (Personally Identifiable Information) is hashed or truncated automatically.
 */
class LTLB_Logger {

    const LEVEL_ERROR = 'error';
    const LEVEL_WARN = 'warn';
    const LEVEL_INFO = 'info';
    const LEVEL_DEBUG = 'debug';

    /**
     * Check if logging is enabled in settings.
     */
    private static function is_enabled(): bool {
        $settings = get_option('lazy_settings', []);
        return !empty($settings['logging_enabled']);
    }

    /**
     * Get configured log level (default: error).
     */
    private static function get_level(): string {
        $settings = get_option('lazy_settings', []);
        return $settings['log_level'] ?? self::LEVEL_ERROR;
    }

    /**
     * Check if a message of given level should be logged.
     */
    private static function should_log(string $level): bool {
        if (!self::is_enabled()) {
            return false;
        }

        $configured_level = self::get_level();
        $levels_hierarchy = [
            self::LEVEL_ERROR => 1,
            self::LEVEL_WARN => 2,
            self::LEVEL_INFO => 3,
            self::LEVEL_DEBUG => 4,
        ];

        $current = $levels_hierarchy[$configured_level] ?? 1;
        $requested = $levels_hierarchy[$level] ?? 1;

        return $requested <= $current;
    }

    /**
     * Sanitize email for logging (hash or truncate).
     */
    private static function sanitize_email(string $email): string {
        if (empty($email)) return '[empty]';
        // Hash email for privacy: first 3 chars + hash
        $hash = substr(md5($email), 0, 8);
        $prefix = substr($email, 0, 3);
        return $prefix . '***@***.' . $hash;
    }

    /**
     * Sanitize context array (remove or hash PII fields).
     */
    private static function sanitize_context(array $context): array {
        $pii_fields = ['email', 'phone', 'first_name', 'last_name', 'name'];
        
        foreach ($context as $key => $value) {
            if (in_array($key, $pii_fields, true)) {
                if ($key === 'email' && is_string($value)) {
                    $context[$key] = self::sanitize_email($value);
                } else if (is_string($value)) {
                    // Truncate and hash
                    $context[$key] = substr($value, 0, 2) . '***' . substr(md5($value), 0, 4);
                }
            }
        }

        return $context;
    }

    /**
     * Get custom log file path.
     */
    private static function get_log_file(): string {
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/ltlb-logs';
        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
            // Add .htaccess to protect log files
            file_put_contents($log_dir . '/.htaccess', 'deny from all');
        }
        return $log_dir . '/ltlb-' . date('Y-m-d') . '.log';
    }

    /**
     * Rotate log file if it exceeds max size (10MB).
     */
    private static function rotate_if_needed(string $log_file): void {
        if (!file_exists($log_file)) return;
        
        $max_size = 10 * 1024 * 1024; // 10MB
        if (filesize($log_file) > $max_size) {
            $archive = $log_file . '.' . time() . '.old';
            rename($log_file, $archive);
            
            // Keep only last 5 rotated logs
            $pattern = dirname($log_file) . '/ltlb-*.log.*.old';
            $old_logs = glob($pattern);
            if (count($old_logs) > 5) {
                usort($old_logs, function($a, $b) { return filemtime($a) - filemtime($b); });
                foreach (array_slice($old_logs, 0, -5) as $old) {
                    @unlink($old);
                }
            }
        }
    }

    /**
     * Write log message to custom log file with rotation.
     */
    private static function write(string $level, string $message, array $context = []): void {
        if (!self::should_log($level)) {
            return;
        }

        $context = self::sanitize_context($context);
        $context_str = !empty($context) ? ' | Context: ' . wp_json_encode($context) : '';
        
        $log_message = sprintf(
            "[%s] [LTLB-%s] %s%s\n",
            date('Y-m-d H:i:s'),
            strtoupper($level),
            $message,
            $context_str
        );

        $log_file = self::get_log_file();
        self::rotate_if_needed($log_file);
        
        // Write to custom log file
        error_log($log_message, 3, $log_file);
        
        // Also write to WordPress debug.log for errors
        if ($level === self::LEVEL_ERROR) {
            error_log('[LTLB] ' . $message);
        }
    }

    /**
     * Log error message.
     */
    public static function error(string $message, array $context = []): void {
        self::write(self::LEVEL_ERROR, $message, $context);
    }

    /**
     * Log warning message.
     */
    public static function warn(string $message, array $context = []): void {
        self::write(self::LEVEL_WARN, $message, $context);
    }

    /**
     * Log info message.
     */
    public static function info(string $message, array $context = []): void {
        self::write(self::LEVEL_INFO, $message, $context);
    }

    /**
     * Log debug message.
     */
    public static function debug(string $message, array $context = []): void {
        self::write(self::LEVEL_DEBUG, $message, $context);
    }
}

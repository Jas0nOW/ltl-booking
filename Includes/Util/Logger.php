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
     * Write log message to WordPress debug.log.
     */
    private static function write(string $level, string $message, array $context = []): void {
        if (!self::should_log($level)) {
            return;
        }

        $context = self::sanitize_context($context);
        $context_str = !empty($context) ? ' | Context: ' . wp_json_encode($context) : '';
        
        $log_message = sprintf(
            '[LTLB-%s] %s%s',
            strtoupper($level),
            $message,
            $context_str
        );

        error_log($log_message);
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

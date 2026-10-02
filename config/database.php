<?php
/**
 * Magal Creator Multi-Vendor Marketplace
 * PDO Database Connection Handler
 * Location: /config/database.php
 */

require_once __DIR__ . '/config.php';

class Database {
    private static ?PDO $instance = null;

    /**
     * Get or initialize the singleton PDO connection
     * @return PDO
     * @throws PDOException
     */
    public static function getConnection(): PDO {
        if (self::$instance === null) {
            if (APP_ENV === 'production' && (DB_HOST === '' || DB_NAME === '' || DB_USER === '' || DB_PASSWORD === '')) {
                error_log('Production database configuration is incomplete; set DB_HOST, DB_NAME, DB_USER, and DB_PASSWORD.');
                http_response_code(500);
                die('A system error occurred. Please contact the platform administrator.');
            }

            $dsn = sprintf(
                "mysql:host=%s;port=%s;dbname=%s;charset=%s",
                DB_HOST,
                DB_PORT,
                DB_NAME,
                DB_CHARSET
            );

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false, // Enforce true prepared statements
                PDO::ATTR_PERSISTENT         => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
            ];

            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASSWORD, $options);
            } catch (PDOException $e) {
                // Friendly message for users, hide raw connection details in production
                if (defined('APP_ENV') && APP_ENV === 'development') {
                    die("<div style='font-family:sans-serif;padding:20px;border-left:4px solid #8C3A27;background:#F7EEEB;margin:20px;'>
                        <h3 style='color:#732D1D;margin:0 0 10px 0;'>Database Connection Error</h3>
                        <p><strong>Message:</strong> " . htmlspecialchars($e->getMessage()) . "</p>
                        <p>Please ensure XAMPP MySQL is started and the database <code>" . DB_NAME . "</code> is imported using <code>schema.sql</code>.</p>
                    </div>");
                } else {
                    error_log("Database Connection Failed: " . $e->getMessage());
                    die("A system error occurred. Please contact the platform administrator.");
                }
            }
        }

        return self::$instance;
    }
}

/**
 * Global helper function to quickly obtain PDO instance
 * @return PDO
 */
function getDB(): PDO {
    return Database::getConnection();
}


<?php
/**
 * Plugin Name: bellbored
 * Author: mlzog
 * Description: Notification bell with unread count. Displays in-app notifications written by the core and other plugins.
 * License: BSD Zero Clause License
 */

function bellbored_init() {
    global $pluginManager, $config, $pdo;

    if (!isset($pluginManager)) {
        return;
    }

    $baseUrl = rtrim(base_url(), '/');
    $pluginUrl = $baseUrl . '/plugins/bellbored';
    $apiUrl = $pluginUrl . '/api.php';

    $driver = $config['db_driver'] ?? 'sqlite';

    if (isset($pdo)) {
        if ($driver === 'mysql') {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS notifications (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    user_id INT NOT NULL,
                    type VARCHAR(32) NOT NULL DEFAULT 'generic',
                    title VARCHAR(255) NOT NULL DEFAULT '',
                    message TEXT NOT NULL,
                    link VARCHAR(512) DEFAULT '',
                    is_read INT DEFAULT 0,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )
            ");
            try { $pdo->exec("CREATE INDEX idx_notifications_user ON notifications(user_id, is_read, created_at)"); } catch (Throwable $e) {}
        } else {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS notifications (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER NOT NULL,
                    type TEXT NOT NULL DEFAULT 'generic',
                    title TEXT NOT NULL DEFAULT '',
                    message TEXT NOT NULL,
                    link TEXT DEFAULT '',
                    is_read INTEGER DEFAULT 0,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )
            ");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_notifications_user ON notifications(user_id, is_read, created_at)");
        }
    }

    // Ensure the title column exists on already-installed databases (older
    // schemas may be missing it). Idempotent across MySQL and SQLite.
    if (isset($pdo)) {
        try {
            if ($driver === 'mysql') {
                $pdo->exec("ALTER TABLE notifications ADD COLUMN title VARCHAR(255) NOT NULL DEFAULT ''");
            } else {
                $pdo->exec("ALTER TABLE notifications ADD COLUMN title TEXT NOT NULL DEFAULT ''");
            }
        } catch (Throwable $e) {}
    }

    $bbVer = function($rel) use ($pluginUrl) {
        $f = __DIR__ . '/' . $rel;
        return $pluginUrl . '/' . $rel . '?v=' . (file_exists($f) ? filemtime($f) : time());
    };
    $cssUrl = $bbVer('assets/css/bellbored.css');
    $jsUrl = $bbVer('assets/js/bellbored.js');
    $csrfToken = htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES);
    $nonce = $GLOBALS['CSP_NONCE'] ?? '';

    $head = '<link href="' . $cssUrl . '" rel="stylesheet">' . "\n";
    $head .= '<script nonce="' . htmlspecialchars($nonce, ENT_QUOTES, 'UTF-8') . '">window.bellbored = window.bellbored || {};window.bellbored.apiUrl = ' . json_encode($apiUrl) . ';window.bellbored.baseUrl = ' . json_encode($baseUrl) . ';window.bellbored.csrfToken = ' . json_encode($csrfToken) . ';window.bellbored.currentUserId = ' . json_encode($_SESSION['user_id'] ?? 0) . ';window.bellbored.loggedIn = ' . json_encode(!empty($_SESSION['user_id'])) . ';</script>' . "\n";

    $footer = '<script src="' . $jsUrl . '" nonce="' . htmlspecialchars($nonce, ENT_QUOTES, 'UTF-8') . '"></script>' . "\n";

    $pluginManager->addHook('frontend_before_render', function() use ($head) {
        echo $head;
    });

    $pluginManager->addHook('footer_before_render', function() use ($footer) {
        echo $footer;
    });
}

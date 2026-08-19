<?php
/**
 * Plugin Name: bellbored
 * Version: 1.0.0
 * Author: mlzog
 * Description: Notification bell with unread count for mentions and replies
 * License: MIT License
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
                    message TEXT NOT NULL,
                    link TEXT DEFAULT '',
                    is_read INTEGER DEFAULT 0,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )
            ");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_notifications_user ON notifications(user_id, is_read, created_at)");
        }
    }

    $bbVer = function($rel) use ($pluginUrl) {
        $f = __DIR__ . '/' . $rel;
        return $pluginUrl . '/' . $rel . '?v=' . (file_exists($f) ? filemtime($f) : time());
    };
    $cssUrl = $bbVer('assets/css/bellbored.css');
    $jsUrl = $bbVer('assets/js/bellbored.js');
    $csrfToken = htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES);

    $head = '<link href="' . $cssUrl . '" rel="stylesheet">' . "\n";
    $head .= '<script>window.bellbored = window.bellbored || {};window.bellbored.apiUrl = ' . json_encode($apiUrl) . ';window.bellbored.baseUrl = ' . json_encode($baseUrl) . ';window.bellbored.csrfToken = ' . json_encode($csrfToken) . ';window.bellbored.currentUserId = ' . json_encode($_SESSION['user_id'] ?? 0) . ';window.bellbored.loggedIn = ' . json_encode(!empty($_SESSION['user_id'])) . ';</script>' . "\n";

    $footer = '<script src="' . $jsUrl . '"></script>' . "\n";
    $footer .= '<script>setTimeout(function(){window.bellbored = window.bellbored || {};window.bellbored.init && window.bellbored.init();}, 0);</script>' . "\n";

    $pluginManager->addHook('frontend_before_render', function() use ($head) {
        echo $head;
    });

    $pluginManager->addHook('footer_before_render', function() use ($footer) {
        echo $footer;
    });
}

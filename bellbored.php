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
    $nonce = App::getInstance()->cspNonce ?? '';
    $labels = [
        'markAllRead' => pt('bellbored', 'mark_all_read'),
        'markRead'    => pt('bellbored', 'mark_read'),
    ];

    $head = '<link href="' . $cssUrl . '" rel="stylesheet">' . "\n";
    $head .= '<script nonce="' . htmlspecialchars($nonce, ENT_QUOTES, 'UTF-8') . '">window.bellbored = window.bellbored || {};window.bellbored.apiUrl = ' . json_encode($apiUrl) . ';window.bellbored.baseUrl = ' . json_encode($baseUrl) . ';window.bellbored.csrfToken = ' . json_encode($csrfToken) . ';window.bellbored.currentUserId = ' . json_encode($_SESSION['user_id'] ?? 0) . ';window.bellbored.loggedIn = ' . json_encode(!empty($_SESSION['user_id'])) . ';window.bellbored.labels = ' . json_encode($labels) . ';</script>' . "\n";

    $footer = '<script src="' . $jsUrl . '" nonce="' . htmlspecialchars($nonce, ENT_QUOTES, 'UTF-8') . '"></script>' . "\n";

    $pluginManager->addHook('frontend_before_render', function() use ($head) {
        echo $head;
    });

    $pluginManager->addHook('footer_before_render', function() use ($footer) {
        echo $footer;
    });

    $pluginManager->addHook('navbar_icons', function() {
        $baseUrl = rtrim(base_url(), '/');
        $safeTitle = htmlspecialchars(t('notifications'), ENT_QUOTES, 'UTF-8');
        echo '<li class="nav-item">';
        echo '<a class="nav-link nav-icon position-relative" href="' . $baseUrl . '/notifications" title="' . $safeTitle . '" data-mobile-tab="notifications">';
        echo '<i class="fas fa-bell"></i>';
        echo '</a>';
        echo '</li>';
    });

    $pluginManager->addHook('mobile_tabbar_icons', function() {
        $baseUrl = rtrim(base_url(), '/');
        $safeTitle = htmlspecialchars(t('notifications'), ENT_QUOTES, 'UTF-8');
        echo '<a href="' . $baseUrl . '/notifications" class="mobile-tab" data-mobile-tab="notifications" title="' . $safeTitle . '">';
        echo '<i class="fas fa-bell"></i>';
        echo '</a>';
    });

    $pluginManager->addHook('mobile_stack_tabs', function() {
        $isActive = !isset($_SESSION['user_id']) ? '' : '';
        echo '<button type="button" class="mobile-stack-tab' . $isActive . '" data-tab="notifications" role="tab"><i class="fas fa-bell"></i></button>';
    });

    $pluginManager->addHook('mobile_stack_panes', function() {
        echo '<div class="mobile-stack-pane" data-pane="notifications" id="paneNotifications"><div class="mobile-stack-loading">Loading…</div></div>';
    });

    $pluginManager->registerRoute('GET', '/notifications', function() {
        bellbored_handle_page('GET');
    }, ['auth']);
    $pluginManager->registerRoute('POST', '/notifications', function() {
        bellbored_handle_page('POST');
    }, ['auth']);
}

function bellbored_handle_page(string $method): void
{
    global $pdo;

    if (!is_logged_in()) {
        die('Login required');
    }
    if ($method === 'POST' && csrf_validate_request()) {
        if (isset($_POST['do']) && $_POST['do'] === 'mark_read' && isset($_GET['id'])) {
            $id = (int)$_GET['id'];
            if ($id > 0) {
                $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?")
                    ->execute([$id, $_SESSION['user_id']]);
            }
        }
        if (isset($_POST['do']) && $_POST['do'] === 'mark_all_read') {
            $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0")
                ->execute([$_SESSION['user_id']]);
        }
        redirect(rtrim(base_url(), '/') . '/notifications');
    }
    $notifications = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
    $notifications->execute([$_SESSION['user_id']]);
    $notifications = $notifications->fetchAll();
    include __DIR__ . '/page/notifications.php';
}

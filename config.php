<?php
ob_start(); // Mencegah error "headers already sent"

define('DB_HOST', 'sql300.infinityfree.com');
define('DB_NAME', 'if0_42793474_sakura_app');
define('DB_USER', 'if0_42793474');      
define('DB_PASS', '1erlQqqn7IP');           
define('DB_CHARSET', 'utf8mb4');

define('APP_NAME', '桜 Sakura');
define('APP_URL', 'sakura-app.site.je');

// Koneksi PDO
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die(json_encode(['error' => 'Koneksi database gagal: ' . $e->getMessage()]));
        }
    }
    return $pdo;
}

// Session helper (Dioptimalkan untuk InfinityFree)
function startSecureSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        // Konfigurasi cookie agar diterima browser tanpa batasan domain yang ketat
        session_set_cookie_params([
            'lifetime' => 86400,
            'path' => '/',
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
            'httponly' => true,
            'samesite' => 'Lax'
        ]);

        // Paksa PHP simpan session di folder buatan sendiri
        $sessionPath = __DIR__ . '/sessions';
        if (!file_exists($sessionPath)) {
            @mkdir($sessionPath, 0755, true);
        }
        session_save_path($sessionPath);
        session_start();
    }
}

function isLoggedIn(): bool {
    startSecureSession();
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function getCurrentUser(): ?array {
    if (!isLoggedIn()) return null;
    $db = getDB();
    $stmt = $db->prepare("SELECT id, name, nis, email, role, avatar, bio, created_at FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch() ?: null;
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: index.php');
        exit;
    }
}

function requireAdmin(): void {
    requireLogin();
    $user = getCurrentUser();
    if (!$user || $user['role'] !== 'admin') {
        header('Location: beranda.php');
        exit;
    }
}

function sanitize(string $input): string {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}
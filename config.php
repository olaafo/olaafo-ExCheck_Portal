<?php
// ============================================================
// EntEx Portal – Database Configuration
// Update DB_HOST, DB_NAME, DB_USER, DB_PASS before deploying
// ============================================================

define('DB_HOST', 'localhost');
define('DB_NAME', 'entex_portal');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Base URL (no trailing slash) – set to your domain or XAMPP path
define('BASE_URL', 'http://localhost/entex_portal');

// Uploads directory
define('UPLOAD_DIR', __DIR__ . '/uploads/students/');
define('UPLOAD_URL', BASE_URL . '/uploads/students/');

// ============================================================
// PDO Connection
// ============================================================
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die('<div style="font-family:sans-serif;padding:20px;color:#c0392b;">
                <h2>Database Connection Error</h2>
                <p>Could not connect to the database. Please check your <code>config.php</code> settings.</p>
                <p><small>' . htmlspecialchars($e->getMessage()) . '</small></p>
            </div>');
        }
    }
    return $pdo;
}

// ============================================================
// Session helpers
// ============================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isStudentLoggedIn(): bool {
    return isset($_SESSION['student_id']) && !empty($_SESSION['student_id']);
}

function isAdminLoggedIn(): bool {
    return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
}

function requireStudent(): void {
    if (!isStudentLoggedIn()) {
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

function requireAdmin(): void {
    if (!isAdminLoggedIn()) {
        header('Location: ' . BASE_URL . '/index.php?mode=admin');
        exit;
    }
}

// ============================================================
// Settings helper
// ============================================================
function getSetting(string $key, string $default = ''): string {
    static $cache = [];
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    $db = getDB();
    $st = $db->prepare('SELECT setting_value FROM settings WHERE setting_key = ?');
    $st->execute([$key]);
    $row = $st->fetch();
    $cache[$key] = $row ? (string)$row['setting_value'] : $default;
    return $cache[$key];
}

function setSetting(string $key, string $value): void {
    $db = getDB();
    $st = $db->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
                        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
    $st->execute([$key, $value]);
}

// ============================================================
// Index ID generator
// ============================================================
function generateIndexId(): string {
    $db = getDB();
    $prefix = getSetting('index_prefix', 'JA/PS');
    $year   = date('Y');
    $len    = (int)getSetting('serial_length', '3');

    // Find the highest serial for this year
    $st = $db->prepare("SELECT index_id FROM students WHERE index_id LIKE ? ORDER BY id DESC LIMIT 1");
    $pattern = $prefix . '/' . $year . '/%';
    $st->execute([$pattern]);
    $row = $st->fetch();

    $serial = 1;
    if ($row) {
        $parts  = explode('/', $row['index_id']);
        $serial = (int)end($parts) + 1;
    }

    return $prefix . '/' . $year . '/' . str_pad($serial, $len, '0', STR_PAD_LEFT);
}

// ============================================================
// Flash message helpers
// ============================================================
function setFlash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function renderFlash(): string {
    $flash = getFlash();
    if (!$flash) return '';
    $class = $flash['type'] === 'success' ? 'alert-success' : ($flash['type'] === 'warning' ? 'alert-warning' : 'alert-error');
    return '<div class="alert ' . $class . '">' . htmlspecialchars($flash['message']) . '</div>';
}

// ============================================================
// Grade calculator
// ============================================================
function calculateGrade(float $score, string $maxScore): array {
    $key = 'grading_' . $maxScore;
    $rubrics = json_decode(getSetting($key, '[]'), true);
    foreach ($rubrics as $rubric) {
        if ($score >= $rubric['min'] && $score <= $rubric['max']) {
            return ['grade' => $rubric['grade'], 'comment' => $rubric['comment']];
        }
    }
    return ['grade' => 'N/A', 'comment' => ''];
}

function meetsCutoff(float $score, string $maxScore): bool {
    $key = 'cutoff_' . $maxScore;
    $cutoff = (float)getSetting($key, '50');
    return $score >= $cutoff;
}

// ============================================================
// Pagination helper
// ============================================================
function paginate(int $total, int $perPage, int $current): array {
    $pages = (int)ceil($total / $perPage);
    return ['total' => $total, 'per_page' => $perPage, 'current' => $current, 'pages' => $pages];
}

// ============================================================
// Safe redirect
// ============================================================
function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

// ============================================================
// CSRF helpers
// ============================================================
function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf(): void {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals(csrfToken(), $token)) {
        setFlash('error', 'Invalid request. Please try again.');
        redirect($_SERVER['HTTP_REFERER'] ?? BASE_URL . '/index.php');
    }
}

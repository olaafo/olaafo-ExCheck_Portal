<?php
require_once __DIR__ . '/config.php';

// Redirect if already logged in
if (isStudentLoggedIn()) redirect(BASE_URL . '/student/dashboard.php');
if (isAdminLoggedIn())   redirect(BASE_URL . '/admin/dashboard.php');

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $loginType = $_POST['login_type'] ?? 'student';

    if ($loginType === 'student') {
        // Student login with index number
        $indexId = trim($_POST['index_id'] ?? '');
        if (empty($indexId)) {
            $error = 'Please enter your Index Number / ID.';
        } else {
            $db = getDB();
            $st = $db->prepare('SELECT * FROM students WHERE index_id = ?');
            $st->execute([$indexId]);
            $student = $st->fetch();
            if ($student) {
                $_SESSION['student_id']   = $student['id'];
                $_SESSION['student_name'] = $student['full_name'];
                $_SESSION['student_idx']  = $student['index_id'];
                redirect(BASE_URL . '/student/dashboard.php');
            } else {
                $error = 'Invalid Index Number / ID. Please check and try again.';
            }
        }
    } elseif ($loginType === 'admin') {
        // Admin login
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        if (empty($username) || empty($password)) {
            $error = 'Please enter both username and password.';
        } else {
            $db = getDB();
            $st = $db->prepare('SELECT * FROM `admin` WHERE username = ?');
            $st->execute([$username]);
            $admin = $st->fetch();
            if ($admin && password_verify($password, $admin['password'])) {
                $_SESSION['admin_id']   = $admin['id'];
                $_SESSION['admin_name'] = $admin['full_name'];
                redirect(BASE_URL . '/admin/dashboard.php');
            } else {
                $error = 'Invalid username or password.';
            }
        }
    }
}

$schoolName = getSetting('school_name', 'EntEx Portal');
$logo       = getSetting('logo', '');
$welcomeMsg = getSetting('welcome_message', 'Welcome To Prospective Student Portal');
$mode       = $_GET['mode'] ?? 'student';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login – <?= htmlspecialchars($schoolName) ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <?php if ($logo): ?>
    <link rel="icon" type="image/png" href="<?= BASE_URL . '/uploads/' . htmlspecialchars($logo) ?>">
    <?php endif; ?>
</head>
<body>
<div class="login-page">

    <!-- TOP BAR -->
    <div class="login-topbar">
        <div class="brand">
            <?php if ($logo && file_exists(UPLOAD_DIR . '../' . $logo)): ?>
                <img src="<?= BASE_URL . '/uploads/' . htmlspecialchars($logo) ?>" alt="Logo">
            <?php endif; ?>
            <span class="brand-name"><?= htmlspecialchars($schoolName) ?></span>
        </div>
        <button class="btn-admin-toggle" id="adminToggleBtn">
            <?= $mode === 'admin' ? 'Student Login' : '🔑 Admin' ?>
        </button>
    </div>

    <!-- LOGIN CARD -->
    <div class="login-body">
        <div class="login-card">

            <!-- Card header -->
            <div class="login-card-header">
                <?php if ($logo && file_exists(UPLOAD_DIR . '../' . $logo)): ?>
                    <img class="school-logo" src="<?= BASE_URL . '/uploads/' . htmlspecialchars($logo) ?>" alt="School Logo">
                <?php else: ?>
                    <div class="school-logo-placeholder">🏫</div>
                <?php endif; ?>
                <h1><?= htmlspecialchars($schoolName) ?></h1>
                <p id="cardHeaderSub"><?= $mode === 'admin' ? 'Admin Portal Access' : 'Prospective Student Portal' ?></p>
            </div>

            <!-- Card body -->
            <div class="login-card-body">
                <?php if ($error): ?>
                    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                <?= renderFlash() ?>

                <div class="login-mode-label">
                    <span id="loginModeLabel"><?= $mode === 'admin' ? '🔐 Admin Login' : '🎓 Student Login' ?></span>
                    <span class="badge" id="loginModeBadge"><?= $mode === 'admin' ? 'Admin' : 'Student' ?></span>
                </div>

                <!-- STUDENT LOGIN FORM -->
                <div id="studentLoginForm" style="<?= $mode === 'admin' ? 'display:none' : '' ?>">
                    <form method="post" action="">
                        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                        <input type="hidden" name="login_type" value="student">
                        <div class="form-group">
                            <label for="index_id">Index Number / ID</label>
                            <input
                                type="text"
                                id="index_id"
                                name="index_id"
                                class="form-control"
                                placeholder="e.g. JA/PS/2026/001"
                                autocomplete="off"
                                value="<?= htmlspecialchars($_POST['index_id'] ?? '') ?>"
                            >
                            <p class="form-hint">Enter the index number provided at registration.</p>
                        </div>
                        <button type="submit" class="btn btn-primary btn-block btn-lg">
                            🔓 Access My Portal
                        </button>
                    </form>
                </div>

                <!-- ADMIN LOGIN FORM -->
                <div id="adminLoginForm" style="<?= $mode !== 'admin' ? 'display:none' : '' ?>">
                    <form method="post" action="?mode=admin">
                        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                        <input type="hidden" name="login_type" value="admin">
                        <div class="form-group">
                            <label for="username">Username</label>
                            <input
                                type="text"
                                id="username"
                                name="username"
                                class="form-control"
                                placeholder="Admin username"
                                autocomplete="username"
                                value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                            >
                        </div>
                        <div class="form-group">
                            <label for="password">Password</label>
                            <input
                                type="password"
                                id="password"
                                name="password"
                                class="form-control"
                                placeholder="Admin password"
                                autocomplete="current-password"
                            >
                        </div>
                        <button type="submit" class="btn btn-primary btn-block btn-lg">
                            🔐 Admin Login
                        </button>
                    </form>
                </div>
            </div>

        </div><!-- .login-card -->
    </div><!-- .login-body -->

</div><!-- .login-page -->

<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>

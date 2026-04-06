<?php
// Student sidebar + topbar partial
// Expects: $pageTitle (string), $activeMenu (string)
require_once __DIR__ . '/../config.php';
requireStudent();

$student_id = $_SESSION['student_id'];
$db = getDB();
$st = $db->prepare('SELECT s.*, b.batch_name FROM students s LEFT JOIN exam_batches b ON s.batch_id = b.id WHERE s.id = ?');
$st->execute([$student_id]);
$student = $st->fetch();

// Unread notifications count
$nst = $db->prepare('SELECT COUNT(*) FROM notifications WHERE student_id = ? AND is_read = 0');
$nst->execute([$student_id]);
$unread = (int)$nst->fetchColumn();

$schoolName = getSetting('school_name', 'EntEx Portal');
$welcomeMsg = getSetting('welcome_message', 'Welcome To Prospective Student Portal');
$logo = getSetting('logo', '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?> – <?= htmlspecialchars($schoolName) ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <?php if ($logo): ?>
    <link rel="icon" type="image/png" href="<?= BASE_URL . '/uploads/' . htmlspecialchars($logo) ?>">
    <?php endif; ?>
</head>
<body>
<div class="portal-wrapper">

<!-- SIDEBAR -->
<aside class="sidebar">
    <div class="sidebar-header">
        <div class="school-name"><?= htmlspecialchars($schoolName) ?></div>
        <div class="portal-title">Student Portal</div>
    </div>

    <div class="sidebar-profile">
        <?php if (!empty($student['photo']) && file_exists(UPLOAD_DIR . $student['photo'])): ?>
            <img class="profile-photo" src="<?= UPLOAD_URL . htmlspecialchars($student['photo']) ?>" alt="Photo">
        <?php else: ?>
            <div class="profile-photo-placeholder">👤</div>
        <?php endif; ?>
        <div class="profile-name"><?= htmlspecialchars($student['full_name']) ?></div>
        <div class="profile-id"><?= htmlspecialchars($student['index_id']) ?></div>
    </div>

    <nav class="sidebar-nav">
        <a href="<?= BASE_URL ?>/student/dashboard.php" class="<?= ($activeMenu ?? '') === 'dashboard' ? 'active' : '' ?>">
            <span class="nav-icon">🏠</span> Dashboard
        </a>
        <a href="<?= BASE_URL ?>/student/exam_schedule.php" class="<?= ($activeMenu ?? '') === 'schedule' ? 'active' : '' ?>">
            <span class="nav-icon">📅</span> My Exam Schedule
        </a>
        <a href="<?= BASE_URL ?>/student/result.php" class="<?= ($activeMenu ?? '') === 'result' ? 'active' : '' ?>">
            <span class="nav-icon">📊</span> My Result
        </a>
        <a href="<?= BASE_URL ?>/student/interview.php" class="<?= ($activeMenu ?? '') === 'interview' ? 'active' : '' ?>">
            <span class="nav-icon">🎤</span> My Interview
            <?php if ($unread > 0): ?><span class="notif-badge"><?= $unread ?></span><?php endif; ?>
        </a>
        <a href="<?= BASE_URL ?>/student/admission_status.php" class="<?= ($activeMenu ?? '') === 'admission' ? 'active' : '' ?>">
            <span class="nav-icon">🎓</span> Admission Status
        </a>
    </nav>

    <div class="sidebar-footer">
        <a href="<?= BASE_URL ?>/logout.php">🚪 Logout</a>
    </div>
</aside>

<!-- TOP BAR -->
<div class="topbar">
    <button class="topbar-menu-btn" aria-label="Toggle menu">☰</button>
    <div class="topbar-title"><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?></div>
    <div class="topbar-right">
        <span class="topbar-user">👤 <?= htmlspecialchars($student['full_name']) ?></span>
        <a class="btn btn-outline btn-sm" href="<?= BASE_URL ?>/logout.php">Logout</a>
    </div>
</div>

<!-- MAIN CONTENT -->
<main class="main-content">
<?= renderFlash() ?>

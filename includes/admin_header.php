<?php
// Admin sidebar + topbar partial
// Expects: $pageTitle (string), $activeMenu (string)
require_once __DIR__ . '/../config.php';
requireAdmin();

$adminName  = $_SESSION['admin_name'] ?? 'Admin';
$schoolName = getSetting('school_name', 'EntEx Portal');
$logo       = getSetting('logo', '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Admin') ?> – <?= htmlspecialchars($schoolName) ?> Admin</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <?php if ($logo): ?>
    <link rel="icon" type="image/png" href="<?= BASE_URL . '/uploads/' . htmlspecialchars($logo) ?>">
    <?php endif; ?>
</head>
<body>
<div class="portal-wrapper">

<!-- ADMIN SIDEBAR -->
<aside class="sidebar">
    <div class="sidebar-header">
        <div class="school-name"><?= htmlspecialchars($schoolName) ?></div>
        <div class="portal-title">Admin Panel</div>
    </div>

    <nav class="sidebar-nav" style="padding-top:16px;">
        <a href="<?= BASE_URL ?>/admin/dashboard.php" class="<?= ($activeMenu ?? '') === 'dashboard' ? 'active' : '' ?>">
            <span class="nav-icon">🏠</span> Dashboard
        </a>

        <div class="nav-divider"></div>

        <a href="<?= BASE_URL ?>/admin/exam_batches.php" class="<?= ($activeMenu ?? '') === 'batches' ? 'active' : '' ?>">
            <span class="nav-icon">📋</span> Exam Batches
        </a>
        <a href="<?= BASE_URL ?>/admin/subjects.php" class="<?= ($activeMenu ?? '') === 'subjects' ? 'active' : '' ?>">
            <span class="nav-icon">📚</span> Subjects
        </a>

        <div class="nav-divider"></div>

        <a href="<?= BASE_URL ?>/admin/register_student.php" class="<?= ($activeMenu ?? '') === 'register' ? 'active' : '' ?>">
            <span class="nav-icon">➕</span> Register Student
        </a>
        <a href="<?= BASE_URL ?>/admin/students.php" class="<?= ($activeMenu ?? '') === 'students' ? 'active' : '' ?>">
            <span class="nav-icon">👥</span> All Students
        </a>

        <div class="nav-divider"></div>

        <a href="<?= BASE_URL ?>/admin/prepare_exam_schedule.php" class="<?= ($activeMenu ?? '') === 'schedules' ? 'active' : '' ?>">
            <span class="nav-icon">📅</span> Exam Schedules
        </a>
        <a href="<?= BASE_URL ?>/admin/prepare_result.php" class="<?= ($activeMenu ?? '') === 'results' ? 'active' : '' ?>">
            <span class="nav-icon">📊</span> Prepare Result
        </a>
        <a href="<?= BASE_URL ?>/admin/schedule_interview.php" class="<?= ($activeMenu ?? '') === 'interviews' ? 'active' : '' ?>">
            <span class="nav-icon">🎤</span> Schedule Interview
        </a>
        <a href="<?= BASE_URL ?>/admin/admission_status.php" class="<?= ($activeMenu ?? '') === 'admission' ? 'active' : '' ?>">
            <span class="nav-icon">🎓</span> Admission Status
        </a>

        <div class="nav-divider"></div>

        <a href="<?= BASE_URL ?>/admin/settings.php" class="<?= ($activeMenu ?? '') === 'settings' ? 'active' : '' ?>">
            <span class="nav-icon">⚙️</span> Settings
        </a>
    </nav>

    <div class="sidebar-footer">
        <a href="<?= BASE_URL ?>/logout.php?admin=1">🚪 Logout</a>
    </div>
</aside>

<!-- TOP BAR -->
<div class="topbar">
    <button class="topbar-menu-btn" aria-label="Toggle menu">☰</button>
    <div class="topbar-title"><?= htmlspecialchars($pageTitle ?? 'Admin Dashboard') ?></div>
    <div class="topbar-right">
        <span class="topbar-user">⚙️ <?= htmlspecialchars($adminName) ?></span>
        <a class="btn btn-outline btn-sm" href="<?= BASE_URL ?>/logout.php?admin=1">Logout</a>
    </div>
</div>

<!-- MAIN CONTENT -->
<main class="main-content">
<?= renderFlash() ?>

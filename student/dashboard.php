<?php
$pageTitle  = 'Dashboard';
$activeMenu = 'dashboard';
require_once __DIR__ . '/../includes/student_header.php';

$db  = getDB();
$sid = $_SESSION['student_id'];

// Fetch student details
$st = $db->prepare('SELECT s.*, b.batch_name, b.exam_date, b.start_time, b.end_time
                    FROM students s LEFT JOIN exam_batches b ON s.batch_id = b.id
                    WHERE s.id = ?');
$st->execute([$sid]);
$student = $st->fetch();

// Unread notifications
$nst = $db->prepare('SELECT * FROM notifications WHERE student_id = ? ORDER BY created_at DESC LIMIT 5');
$nst->execute([$sid]);
$notifications = $nst->fetchAll();

// Mark all read
$db->prepare('UPDATE notifications SET is_read = 1 WHERE student_id = ?')->execute([$sid]);

// Upcoming schedule
$sched = $db->prepare('SELECT * FROM exam_schedules WHERE student_id = ? ORDER BY exam_date ASC LIMIT 1');
$sched->execute([$sid]);
$upcomingExam = $sched->fetch();

// Result status
$res = $db->prepare('SELECT COUNT(*) FROM results WHERE student_id = ? AND published = 1');
$res->execute([$sid]);
$hasResult = (int)$res->fetchColumn() > 0;

// Interview status
$ivst = $db->prepare('SELECT * FROM interview_schedules WHERE student_id = ? ORDER BY created_at DESC LIMIT 1');
$ivst->execute([$sid]);
$interview = $ivst->fetch();

// Admission status
$adst = $db->prepare('SELECT * FROM admission_status WHERE student_id = ?');
$adst->execute([$sid]);
$admission = $adst->fetch();

$welcomeMsg = getSetting('welcome_message', 'Welcome To Prospective Student Portal');
$schoolName = getSetting('school_name', 'EntEx Portal');
?>

<div class="welcome-banner">
    <h2><?= htmlspecialchars($welcomeMsg) ?></h2>
    <p>Hello, <strong><?= htmlspecialchars($student['full_name']) ?></strong>! Your Index ID:
       <strong><?= htmlspecialchars($student['index_id']) ?></strong></p>
</div>

<!-- NOTIFICATION BANNER -->
<?php foreach ($notifications as $notif): ?>
<div class="alert alert-info" style="margin-bottom:10px;">
    🔔 <?= htmlspecialchars($notif['message']) ?>
    <span style="float:right;font-size:.8rem;opacity:.7;"><?= date('d M Y', strtotime($notif['created_at'])) ?></span>
</div>
<?php endforeach; ?>

<!-- QUICK STATUS CARDS -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon red">📅</div>
        <div class="stat-info">
            <div class="stat-value"><?= $upcomingExam ? date('d M', strtotime($upcomingExam['exam_date'])) : 'N/A' ?></div>
            <div class="stat-label">Exam Date</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon <?= $hasResult ? 'green' : 'orange' ?>">📊</div>
        <div class="stat-info">
            <div class="stat-value"><?= $hasResult ? 'Available' : 'Pending' ?></div>
            <div class="stat-label">My Result</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon blue">🎤</div>
        <div class="stat-info">
            <div class="stat-value"><?= $interview ? ucfirst($interview['status']) : 'None' ?></div>
            <div class="stat-label">Interview Status</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon <?= ($admission && $admission['status'] === 'offered') ? 'green' : 'orange' ?>">🎓</div>
        <div class="stat-info">
            <div class="stat-value"><?= $admission ? ucfirst($admission['status']) : 'Pending' ?></div>
            <div class="stat-label">Admission Status</div>
        </div>
    </div>
</div>

<!-- STUDENT BIO + QUICK LINKS -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;">

    <div class="card">
        <div class="card-header">
            <div class="card-title">👤 My Profile</div>
        </div>
        <div class="card-body">
            <div style="display:flex;gap:16px;align-items:flex-start;">
                <?php if (!empty($student['photo']) && file_exists(UPLOAD_DIR . $student['photo'])): ?>
                    <img src="<?= UPLOAD_URL . htmlspecialchars($student['photo']) ?>"
                         style="width:80px;height:80px;border-radius:8px;object-fit:cover;border:2px solid #ddd;">
                <?php else: ?>
                    <div style="width:80px;height:80px;border-radius:8px;background:#f1f3f4;display:flex;align-items:center;justify-content:center;font-size:2rem;">👤</div>
                <?php endif; ?>
                <div style="flex:1;">
                    <p><strong>Name:</strong> <?= htmlspecialchars($student['full_name']) ?></p>
                    <p><strong>Index ID:</strong> <?= htmlspecialchars($student['index_id']) ?></p>
                    <p><strong>Grade Applied:</strong> <?= htmlspecialchars($student['grade_applied']) ?></p>
                    <p><strong>Gender:</strong> <?= htmlspecialchars($student['gender']) ?></p>
                    <p><strong>Age:</strong> <?= htmlspecialchars($student['age']) ?></p>
                    <p><strong>Batch:</strong> <?= $student['batch_name'] ? htmlspecialchars($student['batch_name']) : 'Individual (One-off)' ?></p>
                    <?php if ($student['forced_in']): ?>
                        <span class="forced-indicator">⚠ Force-Added</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="card-title">🔗 Quick Actions</div>
        </div>
        <div class="card-body" style="display:flex;flex-direction:column;gap:10px;">
            <a href="<?= BASE_URL ?>/student/exam_schedule.php" class="btn btn-outline">📅 View Exam Schedule</a>
            <a href="<?= BASE_URL ?>/student/result.php" class="btn btn-outline">📊 Check My Result</a>
            <a href="<?= BASE_URL ?>/student/interview.php" class="btn btn-outline">🎤 Interview Details</a>
            <a href="<?= BASE_URL ?>/student/admission_status.php" class="btn btn-outline">🎓 Admission Status</a>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

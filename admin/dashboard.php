<?php
$pageTitle  = 'Admin Dashboard';
$activeMenu = 'dashboard';
require_once __DIR__ . '/../includes/admin_header.php';

$db = getDB();

// Stats
$totalStudents  = (int)$db->query('SELECT COUNT(*) FROM students')->fetchColumn();
$totalBatches   = (int)$db->query('SELECT COUNT(*) FROM exam_batches')->fetchColumn();
$totalSubjects  = (int)$db->query('SELECT COUNT(*) FROM subjects')->fetchColumn();
$totalScheduled = (int)$db->query('SELECT COUNT(DISTINCT student_id) FROM exam_schedules')->fetchColumn();
$totalResults   = (int)$db->query('SELECT COUNT(DISTINCT student_id) FROM results WHERE published = 1')->fetchColumn();
$pendingReschedule = (int)$db->query('SELECT COUNT(*) FROM interview_schedules WHERE student_response = "requested_change" AND admin_notified = 0')->fetchColumn();

// Recent students
$recent = $db->query('SELECT s.*, b.batch_name FROM students s LEFT JOIN exam_batches b ON s.batch_id = b.id ORDER BY s.created_at DESC LIMIT 5')->fetchAll();

// Upcoming batches
$upcoming = $db->query('SELECT * FROM exam_batches WHERE exam_date >= CURDATE() ORDER BY exam_date ASC LIMIT 5')->fetchAll();

$schoolName = getSetting('school_name', 'EntEx Portal');
?>

<?php if ($pendingReschedule > 0): ?>
<div class="alert alert-warning">
    🔔 <strong><?= $pendingReschedule ?></strong> student(s) have requested interview reschedule.
    <a href="<?= BASE_URL ?>/admin/schedule_interview.php" class="btn btn-sm btn-warning" style="margin-left:12px;">Review</a>
</div>
<?php endif; ?>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon red">👥</div>
        <div class="stat-info">
            <div class="stat-value"><?= $totalStudents ?></div>
            <div class="stat-label">Total Students</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon blue">📋</div>
        <div class="stat-info">
            <div class="stat-value"><?= $totalBatches ?></div>
            <div class="stat-label">Exam Batches</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon orange">📚</div>
        <div class="stat-info">
            <div class="stat-value"><?= $totalSubjects ?></div>
            <div class="stat-label">Subjects</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon blue">📅</div>
        <div class="stat-info">
            <div class="stat-value"><?= $totalScheduled ?></div>
            <div class="stat-label">Scheduled</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green">📊</div>
        <div class="stat-info">
            <div class="stat-value"><?= $totalResults ?></div>
            <div class="stat-label">Results Published</div>
        </div>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;">

    <!-- RECENT STUDENTS -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">👥 Recent Registrations</div>
            <a href="<?= BASE_URL ?>/admin/students.php" class="btn btn-sm btn-outline">View All</a>
        </div>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr><th>Name</th><th>Index ID</th><th>Batch</th></tr>
                </thead>
                <tbody>
                    <?php if (empty($recent)): ?>
                        <tr><td colspan="3" class="text-center text-gray">No students yet</td></tr>
                    <?php else: ?>
                        <?php foreach ($recent as $s): ?>
                            <tr>
                                <td><?= htmlspecialchars($s['full_name']) ?></td>
                                <td><code><?= htmlspecialchars($s['index_id']) ?></code></td>
                                <td><?= $s['batch_name'] ? htmlspecialchars($s['batch_name']) : '<em>One-off</em>' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- UPCOMING BATCHES -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">📋 Upcoming Exam Batches</div>
            <a href="<?= BASE_URL ?>/admin/exam_batches.php" class="btn btn-sm btn-outline">Manage</a>
        </div>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr><th>Batch</th><th>Date</th><th>Time</th></tr>
                </thead>
                <tbody>
                    <?php if (empty($upcoming)): ?>
                        <tr><td colspan="3" class="text-center text-gray">No upcoming batches</td></tr>
                    <?php else: ?>
                        <?php foreach ($upcoming as $b): ?>
                            <tr>
                                <td><?= htmlspecialchars($b['batch_name']) ?></td>
                                <td><?= date('d M Y', strtotime($b['exam_date'])) ?></td>
                                <td><?= date('g:i A', strtotime($b['start_time'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

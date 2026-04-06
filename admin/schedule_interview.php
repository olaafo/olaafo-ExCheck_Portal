<?php
$pageTitle  = 'Schedule Interview';
$activeMenu = 'interviews';
require_once __DIR__ . '/../includes/admin_header.php';

$db = getDB();

// Handle schedule
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'schedule') {
    verifyCsrf();
    $sid           = (int)($_POST['student_id'] ?? 0);
    $interviewDate = $_POST['interview_date'] ?? '';
    $interviewTime = $_POST['interview_time'] ?? '';

    if (!$sid || empty($interviewDate) || empty($interviewTime)) {
        setFlash('error', 'All fields are required.');
        redirect(BASE_URL . '/admin/schedule_interview.php');
    }

    // Upsert interview schedule
    $existing = $db->prepare('SELECT id FROM interview_schedules WHERE student_id = ?');
    $existing->execute([$sid]);
    $existingRow = $existing->fetch();

    if ($existingRow) {
        $db->prepare('UPDATE interview_schedules SET interview_date = ?, interview_time = ?, status = "scheduled", student_response = "pending", admin_notified = 1 WHERE student_id = ?')
           ->execute([$interviewDate, $interviewTime, $sid]);
    } else {
        $db->prepare('INSERT INTO interview_schedules (student_id, interview_date, interview_time, admin_notified) VALUES (?,?,?,1)')
           ->execute([$sid, $interviewDate, $interviewTime]);
    }

    // Notification to student
    $db->prepare('INSERT INTO notifications (student_id, message, type) VALUES (?,?,?)')
       ->execute([$sid, "Your interview has been scheduled for " . date('d F Y', strtotime($interviewDate)) . " at " . date('g:i A', strtotime($interviewTime)) . ". Please confirm your availability.", 'interview']);

    setFlash('success', 'Interview scheduled and student notified.');
    redirect(BASE_URL . '/admin/schedule_interview.php');
}

// Handle reschedule approval
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'approve_reschedule') {
    verifyCsrf();
    $iid     = (int)($_POST['interview_id'] ?? 0);
    $newDate = $_POST['new_date'] ?? '';
    $newTime = $_POST['new_time'] ?? '';

    $db->prepare('UPDATE interview_schedules SET interview_date = ?, interview_time = ?, status = "rescheduled", student_response = "pending", admin_notified = 1 WHERE id = ?')
       ->execute([$newDate, $newTime, $iid]);

    // Get student id
    $ist = $db->prepare('SELECT student_id FROM interview_schedules WHERE id = ?');
    $ist->execute([$iid]);
    $ists = $ist->fetch();
    if ($ists) {
        $db->prepare('INSERT INTO notifications (student_id, message, type) VALUES (?,?,?)')
           ->execute([$ists['student_id'], "Your interview has been rescheduled to " . date('d F Y', strtotime($newDate)) . " at " . date('g:i A', strtotime($newTime)) . ".", 'interview']);
    }

    setFlash('success', 'Interview rescheduled and student notified.');
    redirect(BASE_URL . '/admin/schedule_interview.php');
}

// Load data
$allStudents = $db->query('SELECT s.*, iv.id AS iv_id, iv.interview_date, iv.interview_time, iv.status, iv.student_response, iv.alternate_date
                           FROM students s
                           LEFT JOIN interview_schedules iv ON iv.student_id = s.id
                           ORDER BY s.full_name ASC')->fetchAll();

$pendingReschedule = array_filter($allStudents, fn($s) => $s['student_response'] === 'requested_change');

// Qualified students (have interview date on result)
$qualifiedStudents = $db->query('SELECT DISTINCT s.id, s.full_name, s.index_id,
                                  MIN(r.interview_date) AS suggested_date,
                                  MIN(r.interview_time) AS suggested_time
                                  FROM results r
                                  JOIN students s ON s.id = r.student_id
                                  WHERE r.qualified_interview = 1 AND r.interview_date IS NOT NULL
                                  GROUP BY s.id, s.full_name, s.index_id')->fetchAll();
?>

<div class="page-header">
    <div class="page-title">🎤 Schedule Interview</div>
</div>

<?php if (!empty($pendingReschedule)): ?>
<div class="alert alert-warning">
    ⚠ <strong><?= count($pendingReschedule) ?></strong> student(s) have requested a reschedule. See below.
</div>
<?php endif; ?>

<div class="tabs">
    <button class="tab-btn active" data-target="tabSchedule">Schedule Interview</button>
    <button class="tab-btn" data-target="tabManage">Manage Interviews</button>
    <?php if (!empty($pendingReschedule)): ?>
    <button class="tab-btn" data-target="tabReschedule">
        Reschedule Requests <span class="notif-badge"><?= count($pendingReschedule) ?></span>
    </button>
    <?php endif; ?>
</div>

<!-- SCHEDULE TAB -->
<div class="tab-pane active" id="tabSchedule">
    <div class="card">
        <div class="card-header"><div class="card-title">📅 Schedule a New Interview</div></div>
        <div class="card-body">
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="action" value="schedule">
                <div class="form-group">
                    <label>Select Student <span class="text-red">*</span></label>
                    <select name="student_id" class="form-control" required>
                        <option value="">-- Choose Student --</option>
                        <?php foreach ($allStudents as $s): ?>
                            <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['full_name']) ?> (<?= htmlspecialchars($s['index_id']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Interview Date <span class="text-red">*</span></label>
                        <input type="date" name="interview_date" class="form-control" min="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Interview Time <span class="text-red">*</span></label>
                        <input type="time" name="interview_time" class="form-control" required>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary btn-lg">📅 Schedule & Notify Student</button>
            </form>

            <?php if (!empty($qualifiedStudents)): ?>
                <hr class="separator">
                <p style="font-weight:600;margin-bottom:10px;">💡 Qualified Students (from result preparation)</p>
                <div class="table-responsive">
                    <table class="table">
                        <thead><tr><th>Name</th><th>Index ID</th><th>Suggested Date</th><th>Actions</th></tr></thead>
                        <tbody>
                            <?php foreach ($qualifiedStudents as $q): ?>
                                <tr>
                                    <td><?= htmlspecialchars($q['full_name']) ?></td>
                                    <td><code><?= htmlspecialchars($q['index_id']) ?></code></td>
                                    <td>
                                        <?= $q['suggested_date'] ? date('d M Y', strtotime($q['suggested_date'])) . ' @ ' . date('g:i A', strtotime($q['suggested_time'])) : 'N/A' ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-info btn-sm"
                                                onclick="document.querySelector('select[name=student_id]').value='<?= $q['id'] ?>';
                                                         document.querySelector('input[name=interview_date]').value='<?= $q['suggested_date'] ?? '' ?>';
                                                         document.querySelector('input[name=interview_time]').value='<?= $q['suggested_time'] ?? '' ?>';
                                                         window.scrollTo(0,0);">
                                            Use Suggested
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- MANAGE INTERVIEWS TAB -->
<div class="tab-pane" id="tabManage">
    <div class="card">
        <div class="card-body" style="padding:0;">
            <div class="table-responsive">
                <table class="table">
                    <thead><tr><th>Name</th><th>Index ID</th><th>Date</th><th>Time</th><th>Status</th><th>Response</th></tr></thead>
                    <tbody>
                        <?php foreach ($allStudents as $s): ?>
                            <?php if (!$s['iv_id']) continue; ?>
                            <tr>
                                <td><?= htmlspecialchars($s['full_name']) ?></td>
                                <td><code><?= htmlspecialchars($s['index_id']) ?></code></td>
                                <td><?= date('d M Y', strtotime($s['interview_date'])) ?></td>
                                <td><?= date('g:i A', strtotime($s['interview_time'])) ?></td>
                                <td><span class="badge badge-<?= $s['status'] === 'confirmed' ? 'green' : ($s['status'] === 'cancelled' ? 'red' : 'blue') ?>"><?= ucfirst($s['status']) ?></span></td>
                                <td>
                                    <?php if ($s['student_response'] === 'confirmed'): ?>
                                        <span class="badge badge-green">✅ Confirmed</span>
                                    <?php elseif ($s['student_response'] === 'requested_change'): ?>
                                        <span class="badge badge-orange">🔄 Reschedule Req.</span>
                                    <?php else: ?>
                                        <span class="badge badge-gray">Pending</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- RESCHEDULE REQUESTS TAB -->
<?php if (!empty($pendingReschedule)): ?>
<div class="tab-pane" id="tabReschedule">
    <div class="card">
        <div class="card-header"><div class="card-title">🔄 Reschedule Requests</div></div>
        <div class="card-body">
            <?php foreach ($pendingReschedule as $s): ?>
                <div style="padding:16px;border:1.5px solid var(--gray-200);border-radius:var(--radius);margin-bottom:16px;">
                    <p><strong><?= htmlspecialchars($s['full_name']) ?></strong> <code>(<?= htmlspecialchars($s['index_id']) ?>)</code></p>
                    <p>Original: <?= date('d M Y', strtotime($s['interview_date'])) ?> @ <?= date('g:i A', strtotime($s['interview_time'])) ?></p>
                    <?php if ($s['alternate_date']): ?>
                        <p>Requested: <strong><?= date('d M Y', strtotime($s['alternate_date'])) ?></strong></p>
                    <?php endif; ?>
                    <form method="post" style="margin-top:12px;display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
                        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                        <input type="hidden" name="action" value="approve_reschedule">
                        <input type="hidden" name="interview_id" value="<?= $s['iv_id'] ?>">
                        <div class="form-group" style="margin:0;">
                            <label style="font-size:.85rem;">New Date</label>
                            <input type="date" name="new_date" class="form-control" value="<?= $s['alternate_date'] ?? '' ?>" required>
                        </div>
                        <div class="form-group" style="margin:0;">
                            <label style="font-size:.85rem;">New Time</label>
                            <input type="time" name="new_time" class="form-control" value="<?= date('H:i', strtotime($s['interview_time'])) ?>" required>
                        </div>
                        <button type="submit" class="btn btn-success">✅ Approve & Notify</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

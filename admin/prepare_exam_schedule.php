<?php
$pageTitle  = 'Prepare Exam Schedules';
$activeMenu = 'schedules';
require_once __DIR__ . '/../includes/admin_header.php';

$db = getDB();

// Handle scheduling by batch
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'schedule_batch') {
    verifyCsrf();
    $batchId = (int)($_POST['batch_id'] ?? 0);
    if (!$batchId) { setFlash('error', 'Please select a batch.'); redirect(BASE_URL . '/admin/prepare_exam_schedule.php'); }

    $batch = $db->prepare('SELECT * FROM exam_batches WHERE id = ?');
    $batch->execute([$batchId]);
    $batch = $batch->fetch();

    if (!$batch) { setFlash('error', 'Batch not found.'); redirect(BASE_URL . '/admin/prepare_exam_schedule.php'); }

    // Get all students in this batch
    $sts = $db->prepare('SELECT id FROM students WHERE batch_id = ?');
    $sts->execute([$batchId]);
    $studentIds = $sts->fetchAll(PDO::FETCH_COLUMN);

    $count = 0;
    foreach ($studentIds as $sid) {
        // Check if schedule already exists
        $check = $db->prepare('SELECT id FROM exam_schedules WHERE student_id = ? AND batch_id = ?');
        $check->execute([$sid, $batchId]);
        if ($check->fetch()) continue;

        $db->prepare('INSERT INTO exam_schedules (student_id, batch_id, exam_date, start_time, end_time) VALUES (?,?,?,?,?)')
           ->execute([$sid, $batchId, $batch['exam_date'], $batch['start_time'], $batch['end_time']]);

        // Notification
        $db->prepare('INSERT INTO notifications (student_id, message, type) VALUES (?,?,?)')
           ->execute([$sid, "Your exam has been scheduled for " . date('d F Y', strtotime($batch['exam_date'])) . " at " . date('g:i A', strtotime($batch['start_time'])) . " (" . $batch['batch_name'] . ").", 'schedule']);
        $count++;
    }

    setFlash('success', "Scheduled {$count} student(s) in batch '{$batch['batch_name']}'. Notifications sent.");
    redirect(BASE_URL . '/admin/prepare_exam_schedule.php');
}

// Handle scheduling one-off students
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'schedule_oneoff') {
    verifyCsrf();
    $studentIds = $_POST['student_ids'] ?? [];
    $examDate   = $_POST['exam_date'] ?? '';
    $startTime  = $_POST['start_time'] ?? '';
    $endTime    = $_POST['end_time'] ?? '';

    if (empty($studentIds) || empty($examDate) || empty($startTime) || empty($endTime)) {
        setFlash('error', 'Please select students and provide date/time.');
        redirect(BASE_URL . '/admin/prepare_exam_schedule.php');
    }

    $count = 0;
    foreach ($studentIds as $sid) {
        $sid = (int)$sid;
        $db->prepare('INSERT INTO exam_schedules (student_id, batch_id, exam_date, start_time, end_time) VALUES (?,NULL,?,?,?)')
           ->execute([$sid, $examDate, $startTime, $endTime]);
        $db->prepare('INSERT INTO notifications (student_id, message, type) VALUES (?,?,?)')
           ->execute([$sid, "Your exam has been scheduled for " . date('d F Y', strtotime($examDate)) . " at " . date('g:i A', strtotime($startTime)) . ".", 'schedule']);
        $count++;
    }

    setFlash('success', "Scheduled {$count} one-off student(s). Notifications sent.");
    redirect(BASE_URL . '/admin/prepare_exam_schedule.php');
}

$batches     = $db->query('SELECT * FROM exam_batches ORDER BY exam_date DESC')->fetchAll();
$oneoffStudents = $db->query("SELECT s.*, (SELECT COUNT(*) FROM exam_schedules es WHERE es.student_id = s.id) AS scheduled
                               FROM students s WHERE s.registration_type = 'oneoff' ORDER BY s.full_name ASC")->fetchAll();

// Scheduled students per batch
$batchSchedules = [];
foreach ($batches as $b) {
    $bst = $db->prepare('SELECT COUNT(*) FROM exam_schedules WHERE batch_id = ?');
    $bst->execute([$b['id']]);
    $batchSchedules[$b['id']] = (int)$bst->fetchColumn();
}
?>

<div class="page-header">
    <div class="page-title">📅 Prepare Exam Schedules</div>
</div>

<div class="tabs">
    <button class="tab-btn active" data-target="tabBatch">📋 Schedule by Batch</button>
    <button class="tab-btn" data-target="tabOneoff">📌 Schedule by One-off</button>
</div>

<!-- BATCH SCHEDULE TAB -->
<div class="tab-pane active" id="tabBatch">
    <div class="card">
        <div class="card-header">
            <div class="card-title">📋 Schedule All Students in a Batch</div>
        </div>
        <div class="card-body">
            <?php if (empty($batches)): ?>
                <div class="alert alert-info">No exam batches created yet. <a href="<?= BASE_URL ?>/admin/exam_batches.php">Create batches first.</a></div>
            <?php else: ?>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                    <input type="hidden" name="action" value="schedule_batch">
                    <div class="form-group">
                        <label>Select Exam Batch <span class="text-red">*</span></label>
                        <select name="batch_id" id="batchSelect" class="form-control" onchange="updateBatchInfo(this)">
                            <option value="">-- Choose Batch --</option>
                            <?php foreach ($batches as $b): ?>
                                <option value="<?= $b['id'] ?>"
                                        data-date="<?= date('d F Y', strtotime($b['exam_date'])) ?>"
                                        data-start="<?= date('g:i A', strtotime($b['start_time'])) ?>"
                                        data-end="<?= date('g:i A', strtotime($b['end_time'])) ?>"
                                        data-scheduled="<?= $batchSchedules[$b['id']] ?>">
                                    <?= htmlspecialchars($b['batch_name']) ?> – <?= date('d M Y', strtotime($b['exam_date'])) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div id="batchInfo" style="display:none;" class="alert alert-info">
                        <strong>Date:</strong> <span id="bInfoDate"></span> &nbsp;|&nbsp;
                        <strong>Time:</strong> <span id="bInfoTime"></span><br>
                        <strong>Already Scheduled:</strong> <span id="bInfoScheduled"></span> student(s)
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg">📅 Schedule All Students Now</button>
                </form>

                <hr class="separator">
                <p style="font-weight:600;margin-bottom:12px;">Batch Schedule Summary</p>
                <div class="table-responsive">
                    <table class="table">
                        <thead><tr><th>Batch</th><th>Date</th><th>Total Students</th><th>Scheduled</th></tr></thead>
                        <tbody>
                            <?php foreach ($batches as $b): ?>
                                <?php
                                $total = $db->prepare('SELECT COUNT(*) FROM students WHERE batch_id = ?');
                                $total->execute([$b['id']]);
                                $totalCount = (int)$total->fetchColumn();
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($b['batch_name']) ?></td>
                                    <td><?= date('d M Y', strtotime($b['exam_date'])) ?></td>
                                    <td><span class="badge badge-blue"><?= $totalCount ?></span></td>
                                    <td><span class="badge badge-<?= $batchSchedules[$b['id']] >= $totalCount && $totalCount > 0 ? 'green' : 'orange' ?>"><?= $batchSchedules[$b['id']] ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ONE-OFF SCHEDULE TAB -->
<div class="tab-pane" id="tabOneoff">
    <div class="card">
        <div class="card-header">
            <div class="card-title">📌 Schedule Individual (One-off) Students</div>
        </div>
        <div class="card-body">
            <?php if (empty($oneoffStudents)): ?>
                <div class="alert alert-info">No one-off students registered yet.</div>
            <?php else: ?>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                    <input type="hidden" name="action" value="schedule_oneoff">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Exam Date <span class="text-red">*</span></label>
                            <input type="date" name="exam_date" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Start Time <span class="text-red">*</span></label>
                            <input type="time" name="start_time" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>End Time <span class="text-red">*</span></label>
                            <input type="time" name="end_time" class="form-control" required>
                        </div>
                    </div>
                    <p class="text-gray" style="font-size:.85rem;margin-bottom:12px;">
                        ⚠ Students checked together will share the same date and time. Uncheck students with different timings and schedule them separately.
                    </p>
                    <div style="margin-bottom:12px;">
                        <label class="checkbox-label">
                            <input type="checkbox" id="selectAllStudents"> Select All
                        </label>
                    </div>
                    <div class="table-responsive">
                        <table class="table">
                            <thead><tr><th><input type="checkbox" id="selectAllStudents" style="width:auto;"></th><th>Name</th><th>Index ID</th><th>Status</th></tr></thead>
                            <tbody>
                                <?php foreach ($oneoffStudents as $s): ?>
                                    <tr>
                                        <td><input type="checkbox" name="student_ids[]" value="<?= $s['id'] ?>" class="student-check"></td>
                                        <td><?= htmlspecialchars($s['full_name']) ?></td>
                                        <td><code><?= htmlspecialchars($s['index_id']) ?></code></td>
                                        <td>
                                            <?php if ($s['scheduled'] > 0): ?>
                                                <span class="badge badge-green">Scheduled (<?= $s['scheduled'] ?>)</span>
                                            <?php else: ?>
                                                <span class="badge badge-gray">Not Scheduled</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg mt-2">📅 Schedule Selected Students</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function updateBatchInfo(sel) {
    const opt = sel.options[sel.selectedIndex];
    const info = document.getElementById('batchInfo');
    if (sel.value) {
        document.getElementById('bInfoDate').textContent = opt.dataset.date;
        document.getElementById('bInfoTime').textContent = opt.dataset.start + ' – ' + opt.dataset.end;
        document.getElementById('bInfoScheduled').textContent = opt.dataset.scheduled;
        info.style.display = 'block';
    } else {
        info.style.display = 'none';
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

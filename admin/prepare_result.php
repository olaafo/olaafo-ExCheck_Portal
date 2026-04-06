<?php
$pageTitle  = 'Prepare Exam Result';
$activeMenu = 'results';
require_once __DIR__ . '/../includes/admin_header.php';

$db = getDB();

// Handle save result for a student
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_result') {
    verifyCsrf();
    $sid      = (int)($_POST['student_id'] ?? 0);
    $subjects = $_POST['subjects'] ?? [];   // [subject_id => ['score'=>..., 'max'=>...]]
    $qualifiedInterview = isset($_POST['qualified_interview']) ? 1 : 0;
    $meetCutoff         = isset($_POST['meet_cutoff']) ? 1 : 0;
    $interviewDate      = !empty($_POST['interview_date']) ? $_POST['interview_date'] : null;
    $interviewTime      = !empty($_POST['interview_time']) ? $_POST['interview_time'] : null;
    $resit              = isset($_POST['resit']) && $_POST['resit'] == '1' ? 1 : 0;
    $resitDate          = !empty($_POST['resit_date']) ? $_POST['resit_date'] : null;

    foreach ($subjects as $subId => $data) {
        $score    = (float)($data['score'] ?? 0);
        $maxScore = $data['max_score'] ?? '100';
        $gradeInfo = calculateGrade($score, $maxScore);
        $meetscut  = meetsCutoff($score, $maxScore) ? 1 : 0;

        $db->prepare('INSERT INTO results (student_id, subject_id, score, max_score, grade, comment, meet_cutoff, qualified_interview, interview_date, interview_time, resit, resit_date)
                      VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
                      ON DUPLICATE KEY UPDATE score=VALUES(score), max_score=VALUES(max_score), grade=VALUES(grade), comment=VALUES(comment),
                      meet_cutoff=VALUES(meet_cutoff), qualified_interview=VALUES(qualified_interview), interview_date=VALUES(interview_date),
                      interview_time=VALUES(interview_time), resit=VALUES(resit), resit_date=VALUES(resit_date), updated_at=NOW()')
            ->execute([$sid, (int)$subId, $score, $maxScore, $gradeInfo['grade'], $gradeInfo['comment'],
                       $meetscut, $qualifiedInterview, $interviewDate, $interviewTime, $resit, $resitDate]);
    }

    setFlash('success', 'Result saved successfully.');
    redirect($_SERVER['HTTP_REFERER'] ?? BASE_URL . '/admin/prepare_result.php');
}

// Handle publish
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'publish') {
    verifyCsrf();
    $sid = (int)($_POST['student_id'] ?? 0);
    $db->prepare('UPDATE results SET published = 1 WHERE student_id = ?')->execute([$sid]);

    // Fetch student name for notification
    $sname = $db->prepare('SELECT full_name FROM students WHERE id = ?');
    $sname->execute([$sid]);
    $sn = $sname->fetch();

    $db->prepare('INSERT INTO notifications (student_id, message, type) VALUES (?,?,?)')
       ->execute([$sid, 'Your examination result has been published. Please login to view your result.', 'result']);

    setFlash('success', 'Result published for ' . ($sn['full_name'] ?? 'student') . '.');
    redirect($_SERVER['HTTP_REFERER'] ?? BASE_URL . '/admin/prepare_result.php');
}

// Handle publish all
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'publish_all') {
    verifyCsrf();
    $batchId = (int)($_POST['batch_id'] ?? 0);
    $type    = $_POST['result_type'] ?? 'batch';

    if ($type === 'batch' && $batchId) {
        $sids = $db->prepare('SELECT id FROM students WHERE batch_id = ?');
        $sids->execute([$batchId]);
    } else {
        $sids = $db->query("SELECT id FROM students WHERE registration_type = 'oneoff'");
    }
    $ids = $sids->fetchAll(PDO::FETCH_COLUMN);
    $count = 0;
    foreach ($ids as $sid) {
        $db->prepare('UPDATE results SET published = 1 WHERE student_id = ?')->execute([$sid]);
        $db->prepare('INSERT INTO notifications (student_id, message, type) VALUES (?,?,?)
                      ON DUPLICATE KEY UPDATE is_read = 0')
           ->execute([$sid, 'Your examination result has been published. Please login to view your result.', 'result']);
        $count++;
    }
    setFlash('success', "Published results for {$count} student(s).");
    redirect(BASE_URL . '/admin/prepare_result.php');
}

// Force register student into past batch
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'force_into_batch') {
    verifyCsrf();
    $sid     = (int)($_POST['student_id'] ?? 0);
    $batchId = (int)($_POST['force_batch_id'] ?? 0);
    if ($sid && $batchId) {
        $db->prepare('UPDATE students SET batch_id = ?, registration_type = "batch", forced_in = 1 WHERE id = ?')->execute([$batchId, $sid]);
        setFlash('success', 'Student force-added to batch.');
    }
    redirect(BASE_URL . '/admin/prepare_result.php');
}

// ---- Load data ----
$pastBatches = $db->query("SELECT b.*, COUNT(s.id) as student_count
                            FROM exam_batches b
                            LEFT JOIN students s ON s.batch_id = b.id
                            WHERE b.exam_date <= CURDATE()
                            GROUP BY b.id
                            ORDER BY b.exam_date DESC")->fetchAll();

$oneoffDoneStudents = $db->query("SELECT s.* FROM students s
                                   JOIN exam_schedules es ON es.student_id = s.id
                                   WHERE s.registration_type = 'oneoff'
                                   AND es.exam_date <= CURDATE()
                                   GROUP BY s.id
                                   ORDER BY s.full_name ASC")->fetchAll();

// Selected batch to prepare results
$selectedBatch = (int)($_GET['batch'] ?? 0);
$selectedType  = $_GET['type'] ?? 'batch';
$batchStudents = [];
$selectedBatchInfo = null;

if ($selectedType === 'batch' && $selectedBatch) {
    $selectedBatchInfo = $db->prepare('SELECT * FROM exam_batches WHERE id = ?');
    $selectedBatchInfo->execute([$selectedBatch]);
    $selectedBatchInfo = $selectedBatchInfo->fetch();

    $bst = $db->prepare('SELECT s.* FROM students s WHERE s.batch_id = ? ORDER BY s.full_name ASC');
    $bst->execute([$selectedBatch]);
    $batchStudents = $bst->fetchAll();
} elseif ($selectedType === 'oneoff') {
    $batchStudents = $oneoffDoneStudents;
}

// Get subjects per student for result form
$prepStudent = (int)($_GET['prepare'] ?? 0);
$prepStudentInfo = null;
$prepSubjects    = [];
$prepExistingResults = [];
if ($prepStudent) {
    $pst = $db->prepare('SELECT * FROM students WHERE id = ?');
    $pst->execute([$prepStudent]);
    $prepStudentInfo = $pst->fetch();
    if ($prepStudentInfo) {
        $psub = $db->prepare('SELECT sub.id, sub.subject_name FROM student_subjects ss JOIN subjects sub ON ss.subject_id = sub.id WHERE ss.student_id = ?');
        $psub->execute([$prepStudent]);
        $prepSubjects = $psub->fetchAll();

        $prst = $db->prepare('SELECT * FROM results WHERE student_id = ?');
        $prst->execute([$prepStudent]);
        foreach ($prst->fetchAll() as $r) {
            $prepExistingResults[$r['subject_id']] = $r;
        }
    }
}

$allStudents = $db->query('SELECT id, full_name, index_id FROM students ORDER BY full_name ASC')->fetchAll();
?>

<div class="page-header">
    <div class="page-title">📊 Prepare Exam Result</div>
</div>

<?php if ($prepStudentInfo): ?>
<!-- RESULT PREPARATION FORM FOR A SPECIFIC STUDENT -->
<div class="card" style="border-top:4px solid var(--red);">
    <div class="card-header">
        <div class="card-title">📝 Prepare Result: <?= htmlspecialchars($prepStudentInfo['full_name']) ?></div>
        <a href="<?= BASE_URL ?>/admin/prepare_result.php<?= $selectedType && $selectedBatch ? "?type={$selectedType}&batch={$selectedBatch}" : ($selectedType === 'oneoff' ? '?type=oneoff' : '') ?>" class="btn btn-secondary btn-sm">← Back</a>
    </div>
    <div class="card-body">
        <?php
        $existing = $prepExistingResults;
        $firstRes = reset($existing) ?: null;
        ?>

        <?php if ($prepStudentInfo['forced_in']): ?>
            <div class="alert alert-warning">⚠ This student was force-added to the batch.</div>
        <?php endif; ?>

        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="action" value="save_result">
            <input type="hidden" name="student_id" value="<?= $prepStudent ?>">

            <div style="background:var(--off-white);border-radius:var(--radius);padding:16px;margin-bottom:20px;">
                <p><strong>Name:</strong> <?= htmlspecialchars($prepStudentInfo['full_name']) ?></p>
                <p><strong>Index ID:</strong> <?= htmlspecialchars($prepStudentInfo['index_id']) ?></p>
                <p><strong>Grade:</strong> <?= htmlspecialchars($prepStudentInfo['grade_applied']) ?></p>
            </div>

            <?php if (empty($prepSubjects)): ?>
                <div class="alert alert-warning">No subjects assigned to this student. <a href="<?= BASE_URL ?>/admin/students.php">Edit student</a></div>
            <?php else: ?>
                <p style="font-weight:700;margin-bottom:12px;">📝 Subject Scores</p>

                <?php foreach ($prepSubjects as $sub): ?>
                    <?php
                    $er = $existing[$sub['id']] ?? null;
                    ?>
                    <div style="padding:16px;border:1.5px solid var(--gray-200);border-radius:var(--radius);margin-bottom:16px;">
                        <p style="font-weight:700;color:var(--red);margin-bottom:12px;"><?= htmlspecialchars($sub['subject_name']) ?></p>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Score</label>
                                <input type="number" name="subjects[<?= $sub['id'] ?>][score]" class="form-control"
                                       min="0" step="0.5"
                                       value="<?= $er ? $er['score'] : '' ?>"
                                       placeholder="Score">
                            </div>
                            <div class="form-group">
                                <label>Score out of</label>
                                <select name="subjects[<?= $sub['id'] ?>][max_score]" class="form-control">
                                    <?php foreach (['100','60','50'] as $m): ?>
                                        <option value="<?= $m ?>" <?= ($er && $er['max_score'] == $m) ? 'selected' : '' ?>>/<?= $m ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <hr class="separator">
                <p style="font-weight:700;margin-bottom:14px;">📋 Overall Assessment</p>

                <div class="form-row">
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="meet_cutoff" value="1" <?= ($firstRes && $firstRes['meet_cutoff']) ? 'checked' : '' ?>>
                            Meets Cut-off
                        </label>
                    </div>
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="qualified_interview" value="1" <?= ($firstRes && $firstRes['qualified_interview']) ? 'checked' : '' ?>>
                            Qualified for Interview
                        </label>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Interview Date</label>
                        <input type="date" name="interview_date" class="form-control"
                               value="<?= $firstRes && $firstRes['interview_date'] ? $firstRes['interview_date'] : '' ?>">
                    </div>
                    <div class="form-group">
                        <label>Interview Time</label>
                        <input type="time" name="interview_time" class="form-control"
                               value="<?= $firstRes && $firstRes['interview_time'] ? $firstRes['interview_time'] : '' ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Re-sit Required?</label>
                    <div style="display:flex;gap:20px;margin-top:6px;">
                        <label class="checkbox-label">
                            <input type="radio" name="resit" value="0" <?= (!$firstRes || !$firstRes['resit']) ? 'checked' : '' ?>>
                            No
                        </label>
                        <label class="checkbox-label">
                            <input type="radio" name="resit" value="1" <?= ($firstRes && $firstRes['resit']) ? 'checked' : '' ?>>
                            Yes
                        </label>
                    </div>
                </div>

                <div id="resitDateArea" style="<?= ($firstRes && $firstRes['resit']) ? '' : 'display:none' ?>">
                    <div class="form-group">
                        <label>Re-sit Date</label>
                        <input type="date" name="resit_date" class="form-control"
                               value="<?= $firstRes && $firstRes['resit_date'] ? $firstRes['resit_date'] : '' ?>">
                    </div>
                </div>

                <div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:20px;">
                    <button type="submit" class="btn btn-primary btn-lg">💾 Save Result</button>
                    <form method="post" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                        <input type="hidden" name="action" value="publish">
                        <input type="hidden" name="student_id" value="<?= $prepStudent ?>">
                        <button type="submit" class="btn btn-success btn-lg"
                                <?= empty($prepExistingResults) ? 'disabled title="Save result first"' : '' ?>>
                            📤 Publish Result
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<?php else: ?>

<!-- BATCH / TYPE SELECTION -->
<div class="tabs">
    <button class="tab-btn <?= $selectedType === 'batch' || !$selectedType ? 'active' : '' ?>" data-target="tabBatch">📋 By Batch</button>
    <button class="tab-btn <?= $selectedType === 'oneoff' ? 'active' : '' ?>" data-target="tabOneoff">📌 By One-off</button>
</div>

<div class="tab-pane <?= $selectedType !== 'oneoff' ? 'active' : '' ?>" id="tabBatch">
    <div class="card">
        <div class="card-header">
            <div class="card-title">📋 Select Past Exam Batch</div>
        </div>
        <div class="card-body">
            <?php if (empty($pastBatches)): ?>
                <div class="alert alert-info">No past exam batches found. Wait until the exam date has passed.</div>
            <?php else: ?>
                <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:20px;">
                    <?php foreach ($pastBatches as $b): ?>
                        <a href="?type=batch&batch=<?= $b['id'] ?>"
                           class="btn <?= $selectedBatch == $b['id'] ? 'btn-primary' : 'btn-outline' ?>">
                            <?= htmlspecialchars($b['batch_name']) ?><br>
                            <small><?= date('d M Y', strtotime($b['exam_date'])) ?></small>
                        </a>
                    <?php endforeach; ?>
                </div>

                <?php if ($selectedBatchInfo && !empty($batchStudents)): ?>
                    <hr class="separator">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:10px;">
                        <p style="font-weight:700;">Students in <?= htmlspecialchars($selectedBatchInfo['batch_name']) ?></p>
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                            <input type="hidden" name="action" value="publish_all">
                            <input type="hidden" name="result_type" value="batch">
                            <input type="hidden" name="batch_id" value="<?= $selectedBatch ?>">
                            <button type="submit" class="btn btn-success" data-confirm="Publish ALL results for this batch?">📤 Publish All Results</button>
                        </form>
                    </div>

                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr><th>#</th><th>Name</th><th>Index ID</th><th>Result Status</th><th>Actions</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($batchStudents as $i => $s): ?>
                                    <?php
                                    $resultCount = $db->prepare('SELECT COUNT(*) FROM results WHERE student_id = ?');
                                    $resultCount->execute([$s['id']]);
                                    $hasResult = (int)$resultCount->fetchColumn() > 0;
                                    $publishedCount = $db->prepare('SELECT COUNT(*) FROM results WHERE student_id = ? AND published = 1');
                                    $publishedCount->execute([$s['id']]);
                                    $isPublished = (int)$publishedCount->fetchColumn() > 0;
                                    ?>
                                    <tr>
                                        <td><?= $i + 1 ?></td>
                                        <td>
                                            <?= htmlspecialchars($s['full_name']) ?>
                                            <?php if ($s['forced_in']): ?><span class="forced-indicator">⚠ Forced</span><?php endif; ?>
                                        </td>
                                        <td><code><?= htmlspecialchars($s['index_id']) ?></code></td>
                                        <td>
                                            <?php if ($isPublished): ?>
                                                <span class="badge badge-green">Published</span>
                                            <?php elseif ($hasResult): ?>
                                                <span class="badge badge-orange">Saved (Not Published)</span>
                                            <?php else: ?>
                                                <span class="badge badge-gray">Not Prepared</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div style="display:flex;gap:6px;flex-wrap:wrap;">
                                                <a href="?type=batch&batch=<?= $selectedBatch ?>&prepare=<?= $s['id'] ?>" class="btn btn-primary btn-sm">📝 Prepare</a>
                                                <?php if ($hasResult && !$isPublished): ?>
                                                    <form method="post" style="display:inline;">
                                                        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                                        <input type="hidden" name="action" value="publish">
                                                        <input type="hidden" name="student_id" value="<?= $s['id'] ?>">
                                                        <button type="submit" class="btn btn-success btn-sm">📤 Publish</button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php elseif ($selectedBatch): ?>
                    <div class="alert alert-info">No students in this batch.</div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="tab-pane <?= $selectedType === 'oneoff' ? 'active' : '' ?>" id="tabOneoff">
    <div class="card">
        <div class="card-header">
            <div class="card-title">📌 One-off Students (Exam Completed)</div>
        </div>
        <div class="card-body" style="padding:0;">
            <?php if (empty($oneoffDoneStudents)): ?>
                <div class="empty-state">
                    <div class="empty-icon">📌</div>
                    <p>No one-off students with completed exams.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead><tr><th>#</th><th>Name</th><th>Index ID</th><th>Actions</th></tr></thead>
                        <tbody>
                            <?php foreach ($oneoffDoneStudents as $i => $s): ?>
                                <tr>
                                    <td><?= $i + 1 ?></td>
                                    <td><?= htmlspecialchars($s['full_name']) ?></td>
                                    <td><code><?= htmlspecialchars($s['index_id']) ?></code></td>
                                    <td>
                                        <a href="?type=oneoff&prepare=<?= $s['id'] ?>" class="btn btn-primary btn-sm">📝 Prepare Result</a>
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

<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

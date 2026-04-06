<?php
$pageTitle  = 'All Students';
$activeMenu = 'students';
require_once __DIR__ . '/../includes/admin_header.php';

$db = getDB();

// Handle batch reassignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reassign') {
    verifyCsrf();
    $sid     = (int)($_POST['student_id'] ?? 0);
    $batchId = !empty($_POST['new_batch_id']) ? (int)$_POST['new_batch_id'] : null;
    $regType = $batchId ? 'batch' : 'oneoff';
    $db->prepare('UPDATE students SET batch_id = ?, registration_type = ? WHERE id = ?')->execute([$batchId, $regType, $sid]);
    setFlash('success', 'Student reassigned successfully.');
    redirect(BASE_URL . '/admin/students.php');
}

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    verifyCsrf();
    $sid = (int)($_POST['student_id'] ?? 0);
    if ($sid > 0) {
        // Remove photo if exists
        $row = $db->prepare('SELECT photo FROM students WHERE id = ?');
        $row->execute([$sid]);
        $s = $row->fetch();
        if ($s && $s['photo'] && file_exists(UPLOAD_DIR . $s['photo'])) {
            unlink(UPLOAD_DIR . $s['photo']);
        }
        $db->prepare('DELETE FROM students WHERE id = ?')->execute([$sid]);
        setFlash('success', 'Student deleted.');
    }
    redirect(BASE_URL . '/admin/students.php');
}

// Search / filter
$search  = trim($_GET['q'] ?? '');
$bFilter = (int)($_GET['batch'] ?? 0);
$typeFilter = $_GET['type'] ?? '';

$where  = ['1=1'];
$params = [];
if ($search) {
    $where[] = '(s.full_name LIKE ? OR s.index_id LIKE ?)';
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}
if ($bFilter) {
    $where[] = 's.batch_id = ?';
    $params[] = $bFilter;
}
if ($typeFilter) {
    $where[] = 's.registration_type = ?';
    $params[] = $typeFilter;
}

$whereStr = implode(' AND ', $where);
$students = $db->prepare("SELECT s.*, b.batch_name FROM students s
                          LEFT JOIN exam_batches b ON s.batch_id = b.id
                          WHERE {$whereStr}
                          ORDER BY s.created_at DESC");
$students->execute($params);
$students = $students->fetchAll();

$batches = $db->query('SELECT * FROM exam_batches ORDER BY exam_date DESC')->fetchAll();

// Profile request
$profileId = (int)($_GET['profile'] ?? 0);
$profileStudent = null;
$profileSubjects = [];
$profileSchedules = [];
if ($profileId > 0) {
    $pst = $db->prepare('SELECT s.*, b.batch_name FROM students s LEFT JOIN exam_batches b ON s.batch_id = b.id WHERE s.id = ?');
    $pst->execute([$profileId]);
    $profileStudent = $pst->fetch();
    if ($profileStudent) {
        $psub = $db->prepare('SELECT sub.subject_name FROM student_subjects ss JOIN subjects sub ON ss.subject_id = sub.id WHERE ss.student_id = ?');
        $psub->execute([$profileId]);
        $profileSubjects = $psub->fetchAll(PDO::FETCH_COLUMN);
        $psch = $db->prepare('SELECT es.*, b.batch_name FROM exam_schedules es LEFT JOIN exam_batches b ON es.batch_id = b.id WHERE es.student_id = ? ORDER BY es.exam_date DESC');
        $psch->execute([$profileId]);
        $profileSchedules = $psch->fetchAll();
    }
}
?>

<div class="page-header">
    <div class="page-title">👥 All Students</div>
    <a href="<?= BASE_URL ?>/admin/register_student.php" class="btn btn-primary">➕ Register New</a>
</div>

<!-- FILTERS -->
<div class="card">
    <div class="card-body" style="padding:16px 24px;">
        <form method="get" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
            <div class="form-group" style="margin:0;flex:1;min-width:180px;">
                <label style="font-size:.85rem;">Search</label>
                <input type="text" name="q" class="form-control" placeholder="Name or Index ID" value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="form-group" style="margin:0;min-width:160px;">
                <label style="font-size:.85rem;">Filter by Batch</label>
                <select name="batch" class="form-control">
                    <option value="">All Batches</option>
                    <?php foreach ($batches as $b): ?>
                        <option value="<?= $b['id'] ?>" <?= $bFilter == $b['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($b['batch_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin:0;min-width:140px;">
                <label style="font-size:.85rem;">Type</label>
                <select name="type" class="form-control">
                    <option value="">All Types</option>
                    <option value="batch" <?= $typeFilter === 'batch' ? 'selected' : '' ?>>Batch</option>
                    <option value="oneoff" <?= $typeFilter === 'oneoff' ? 'selected' : '' ?>>One-off</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">🔍 Filter</button>
            <a href="<?= BASE_URL ?>/admin/students.php" class="btn btn-secondary">Reset</a>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body" style="padding:0;">
        <?php if (empty($students)): ?>
            <div class="empty-state">
                <div class="empty-icon">👥</div>
                <p>No students found.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Photo</th>
                            <th>Full Name</th>
                            <th>Index ID</th>
                            <th>Grade</th>
                            <th>Batch / Type</th>
                            <th>Registered</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $i => $s): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td>
                                    <?php if (!empty($s['photo']) && file_exists(UPLOAD_DIR . $s['photo'])): ?>
                                        <img src="<?= UPLOAD_URL . htmlspecialchars($s['photo']) ?>"
                                             style="width:36px;height:36px;border-radius:50%;object-fit:cover;">
                                    <?php else: ?>
                                        <div style="width:36px;height:36px;border-radius:50%;background:#f1f3f4;display:flex;align-items:center;justify-content:center;font-size:1rem;">👤</div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars($s['full_name']) ?>
                                    <?php if ($s['forced_in']): ?><span class="forced-indicator">⚠ Forced</span><?php endif; ?>
                                </td>
                                <td><code><?= htmlspecialchars($s['index_id']) ?></code></td>
                                <td><?= htmlspecialchars($s['grade_applied']) ?></td>
                                <td>
                                    <?php if ($s['batch_name']): ?>
                                        <span class="badge badge-blue"><?= htmlspecialchars($s['batch_name']) ?></span>
                                    <?php else: ?>
                                        <span class="badge badge-orange">One-off</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= date('d M Y', strtotime($s['created_at'])) ?></td>
                                <td>
                                    <div style="display:flex;gap:6px;flex-wrap:wrap;">
                                        <a href="?profile=<?= $s['id'] ?>" class="btn btn-info btn-sm">👁 Profile</a>
                                        <button class="btn btn-warning btn-sm" onclick="openReassign(<?= $s['id'] ?>, '<?= addslashes($s['full_name']) ?>')">🔄 Reassign</button>
                                        <form method="post" style="display:inline;">
                                            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="student_id" value="<?= $s['id'] ?>">
                                            <button type="submit" class="btn btn-danger btn-sm"
                                                    data-confirm="Delete student '<?= htmlspecialchars($s['full_name']) ?>'? All data will be lost.">
                                                🗑
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- PROFILE MODAL -->
<?php if ($profileStudent): ?>
<div class="modal-overlay open" id="profileModal">
    <div class="modal" style="max-width:640px;">
        <div class="modal-header">
            <div class="modal-title">👤 <?= htmlspecialchars($profileStudent['full_name']) ?></div>
            <a href="<?= BASE_URL ?>/admin/students.php" class="modal-close">&times;</a>
        </div>
        <div class="modal-body">
            <div style="display:flex;gap:20px;margin-bottom:20px;align-items:flex-start;">
                <?php if (!empty($profileStudent['photo']) && file_exists(UPLOAD_DIR . $profileStudent['photo'])): ?>
                    <img src="<?= UPLOAD_URL . htmlspecialchars($profileStudent['photo']) ?>"
                         style="width:90px;height:90px;border-radius:10px;object-fit:cover;border:2px solid #ddd;">
                <?php else: ?>
                    <div style="width:90px;height:90px;border-radius:10px;background:#f1f3f4;display:flex;align-items:center;justify-content:center;font-size:2.5rem;">👤</div>
                <?php endif; ?>
                <div>
                    <p><strong>Index ID:</strong> <code><?= htmlspecialchars($profileStudent['index_id']) ?></code></p>
                    <p><strong>Grade:</strong> <?= htmlspecialchars($profileStudent['grade_applied']) ?></p>
                    <p><strong>Age:</strong> <?= $profileStudent['age'] ?></p>
                    <p><strong>Gender:</strong> <?= htmlspecialchars($profileStudent['gender']) ?></p>
                    <p><strong>Type:</strong> <?= $profileStudent['registration_type'] === 'batch' ? 'Batch – ' . htmlspecialchars($profileStudent['batch_name'] ?? 'N/A') : 'One-off' ?></p>
                    <p><strong>Registered:</strong> <?= date('d M Y', strtotime($profileStudent['created_at'])) ?></p>
                    <?php if ($profileStudent['forced_in']): ?><span class="forced-indicator">⚠ Force-Added</span><?php endif; ?>
                </div>
            </div>

            <?php if (!empty($profileSubjects)): ?>
                <p><strong>Subjects:</strong> <?= implode(', ', array_map('htmlspecialchars', $profileSubjects)) ?></p>
            <?php endif; ?>

            <?php if (!empty($profileSchedules)): ?>
                <hr class="separator">
                <p style="font-weight:700;margin-bottom:8px;">📅 Exam Schedule History</p>
                <?php foreach ($profileSchedules as $sch): ?>
                    <div style="padding:8px 12px;background:var(--off-white);border-radius:var(--radius);margin-bottom:8px;font-size:.875rem;">
                        <strong><?= $sch['batch_name'] ?? 'Individual' ?></strong> –
                        <?= date('d M Y', strtotime($sch['exam_date'])) ?>
                        <?= date('g:i A', strtotime($sch['start_time'])) ?> – <?= date('g:i A', strtotime($sch['end_time'])) ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-gray" style="font-size:.875rem;margin-top:10px;">No exam schedule yet.</p>
            <?php endif; ?>
        </div>
        <div class="modal-footer">
            <a href="<?= BASE_URL ?>/admin/students.php" class="btn btn-secondary">Close</a>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- REASSIGN MODAL -->
<div class="modal-overlay" id="reassignModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">🔄 Reassign Student</div>
            <button class="modal-close">&times;</button>
        </div>
        <form method="post">
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="action" value="reassign">
                <input type="hidden" name="student_id" id="reassignStudentId">
                <p id="reassignStudentName" style="font-weight:700;margin-bottom:16px;"></p>
                <div class="form-group">
                    <label>Assign to Batch</label>
                    <select name="new_batch_id" class="form-control">
                        <option value="">-- Move to One-off --</option>
                        <?php foreach ($batches as $b): ?>
                            <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['batch_name']) ?> – <?= date('d M Y', strtotime($b['exam_date'])) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-primary">Reassign</button>
            </div>
        </form>
    </div>
</div>

<script>
function openReassign(id, name) {
    document.getElementById('reassignStudentId').value = id;
    document.getElementById('reassignStudentName').textContent = 'Student: ' + name;
    document.getElementById('reassignModal').classList.add('open');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

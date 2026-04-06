<?php
$pageTitle  = 'Exam Batches';
$activeMenu = 'batches';
require_once __DIR__ . '/../includes/admin_header.php';

$db = getDB();

// Handle create batch
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    verifyCsrf();
    $batchName = trim($_POST['batch_name'] ?? '');
    $examDate  = $_POST['exam_date'] ?? '';
    $startTime = $_POST['start_time'] ?? '';
    $endTime   = $_POST['end_time'] ?? '';

    if (empty($batchName) || empty($examDate) || empty($startTime) || empty($endTime)) {
        setFlash('error', 'All fields are required.');
    } else {
        try {
            $st = $db->prepare('INSERT INTO exam_batches (batch_name, exam_date, start_time, end_time) VALUES (?, ?, ?, ?)');
            $st->execute([$batchName, $examDate, $startTime, $endTime]);
            setFlash('success', "Exam batch '{$batchName}' created successfully.");
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                setFlash('error', "A batch named '{$batchName}' already exists. Use a different name (e.g., {$batchName}2).");
            } else {
                setFlash('error', 'An error occurred. Please try again.');
            }
        }
    }
    redirect(BASE_URL . '/admin/exam_batches.php');
}

// Handle delete batch
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    verifyCsrf();
    $batchId = (int)($_POST['batch_id'] ?? 0);
    if ($batchId > 0) {
        $db->prepare('DELETE FROM exam_batches WHERE id = ?')->execute([$batchId]);
        setFlash('success', 'Batch deleted successfully.');
    }
    redirect(BASE_URL . '/admin/exam_batches.php');
}

$batches = $db->query('SELECT b.*, COUNT(s.id) AS student_count
                       FROM exam_batches b
                       LEFT JOIN students s ON s.batch_id = b.id
                       GROUP BY b.id
                       ORDER BY b.exam_date DESC')->fetchAll();
?>

<div class="page-header">
    <div class="page-title">📋 Exam Batches</div>
    <button class="btn btn-primary" data-modal="createBatchModal">➕ Create Exam Batch</button>
</div>

<div class="card">
    <div class="card-body" style="padding:0;">
        <?php if (empty($batches)): ?>
            <div class="empty-state">
                <div class="empty-icon">📋</div>
                <p>No exam batches created yet.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Batch Name</th>
                            <th>Exam Date</th>
                            <th>Start Time</th>
                            <th>End Time</th>
                            <th>Students</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($batches as $i => $b): ?>
                            <?php $isPast = strtotime($b['exam_date']) < strtotime(date('Y-m-d')); ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td><strong><?= htmlspecialchars($b['batch_name']) ?></strong></td>
                                <td><?= date('d M Y', strtotime($b['exam_date'])) ?></td>
                                <td><?= date('g:i A', strtotime($b['start_time'])) ?></td>
                                <td><?= date('g:i A', strtotime($b['end_time'])) ?></td>
                                <td><span class="badge badge-blue"><?= $b['student_count'] ?></span></td>
                                <td>
                                    <?php if ($isPast): ?>
                                        <span class="badge badge-gray">Completed</span>
                                    <?php else: ?>
                                        <span class="badge badge-green">Upcoming</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="post" style="display:inline;">
                                        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="batch_id" value="<?= $b['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm"
                                                data-confirm="Delete batch '<?= htmlspecialchars($b['batch_name']) ?>'? This cannot be undone.">
                                            🗑 Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- CREATE BATCH MODAL -->
<div class="modal-overlay" id="createBatchModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">➕ Create Exam Batch</div>
            <button class="modal-close">&times;</button>
        </div>
        <form method="post">
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="action" value="create">
                <div class="form-group">
                    <label>Batch Name <span class="text-red">*</span></label>
                    <input type="text" name="batch_name" class="form-control" placeholder="e.g. Batch A" required>
                    <p class="form-hint">Each batch name must be unique (e.g., Batch A, Batch B, Batch A2).</p>
                </div>
                <div class="form-group">
                    <label>Exam Date <span class="text-red">*</span></label>
                    <input type="date" name="exam_date" class="form-control" min="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Start Time <span class="text-red">*</span></label>
                        <input type="time" name="start_time" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>End Time <span class="text-red">*</span></label>
                        <input type="time" name="end_time" class="form-control" required>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-primary">Create Batch</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

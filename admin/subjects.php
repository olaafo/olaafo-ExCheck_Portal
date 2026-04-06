<?php
$pageTitle  = 'Subjects';
$activeMenu = 'subjects';
require_once __DIR__ . '/../includes/admin_header.php';

$db = getDB();

// Handle add subject
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    verifyCsrf();
    $name = trim($_POST['subject_name'] ?? '');
    if (empty($name)) {
        setFlash('error', 'Subject name is required.');
    } else {
        try {
            $db->prepare('INSERT INTO subjects (subject_name) VALUES (?)')->execute([$name]);
            setFlash('success', "Subject '{$name}' added successfully.");
        } catch (PDOException $e) {
            setFlash('error', $e->getCode() == 23000 ? "Subject '{$name}' already exists." : 'An error occurred.');
        }
    }
    redirect(BASE_URL . '/admin/subjects.php');
}

// Handle delete subject
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    verifyCsrf();
    $id = (int)($_POST['subject_id'] ?? 0);
    if ($id > 0) {
        $db->prepare('DELETE FROM subjects WHERE id = ?')->execute([$id]);
        setFlash('success', 'Subject deleted successfully.');
    }
    redirect(BASE_URL . '/admin/subjects.php');
}

$subjects = $db->query('SELECT s.*, COUNT(ss.student_id) AS student_count
                        FROM subjects s
                        LEFT JOIN student_subjects ss ON ss.subject_id = s.id
                        GROUP BY s.id
                        ORDER BY s.subject_name ASC')->fetchAll();
?>

<div class="page-header">
    <div class="page-title">📚 Subjects</div>
    <button class="btn btn-primary" data-modal="addSubjectModal">➕ Add Subject</button>
</div>

<div class="card">
    <div class="card-body" style="padding:0;">
        <?php if (empty($subjects)): ?>
            <div class="empty-state">
                <div class="empty-icon">📚</div>
                <p>No subjects added yet. Add a subject to get started.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr><th>#</th><th>Subject Name</th><th>Students</th><th>Date Added</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($subjects as $i => $sub): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td><strong><?= htmlspecialchars($sub['subject_name']) ?></strong></td>
                                <td><span class="badge badge-blue"><?= $sub['student_count'] ?></span></td>
                                <td><?= date('d M Y', strtotime($sub['created_at'])) ?></td>
                                <td>
                                    <form method="post" style="display:inline;">
                                        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="subject_id" value="<?= $sub['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm"
                                                data-confirm="Delete subject '<?= htmlspecialchars($sub['subject_name']) ?>'? This will remove it from all students.">
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

<!-- ADD SUBJECT MODAL -->
<div class="modal-overlay" id="addSubjectModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">➕ Add New Subject</div>
            <button class="modal-close">&times;</button>
        </div>
        <form method="post">
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="action" value="add">
                <div class="form-group">
                    <label>Subject Name <span class="text-red">*</span></label>
                    <input type="text" name="subject_name" class="form-control"
                           placeholder="e.g. Mathematics, English Language" required autofocus>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-primary">Add Subject</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

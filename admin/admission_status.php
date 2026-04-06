<?php
$pageTitle  = 'Admission Status';
$activeMenu = 'admission';
require_once __DIR__ . '/../includes/admin_header.php';

$db = getDB();

// Handle update admission status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
    verifyCsrf();
    $sid     = (int)($_POST['student_id'] ?? 0);
    $status  = $_POST['status'] ?? 'pending';
    $message = trim($_POST['notification_message'] ?? '');
    $admNum  = trim($_POST['admission_number'] ?? '');
    $payConf = isset($_POST['payment_confirmed']) ? 1 : 0;

    if (!$sid) { setFlash('error', 'Please select a student.'); redirect(BASE_URL . '/admin/admission_status.php'); }

    $existing = $db->prepare('SELECT id FROM admission_status WHERE student_id = ?');
    $existing->execute([$sid]);
    $existingRow = $existing->fetch();

    if ($existingRow) {
        $db->prepare('UPDATE admission_status SET status = ?, notification_message = ?, admission_number = ?, payment_confirmed = ? WHERE student_id = ?')
           ->execute([$status, $message, $admNum ?: null, $payConf, $sid]);
    } else {
        $db->prepare('INSERT INTO admission_status (student_id, status, notification_message, admission_number, payment_confirmed) VALUES (?,?,?,?,?)')
           ->execute([$sid, $status, $message, $admNum ?: null, $payConf]);
    }

    // Notify student
    if ($status === 'offered') {
        $db->prepare('INSERT INTO notifications (student_id, message, type) VALUES (?,?,?)')
           ->execute([$sid, 'Congratulations! Your admission status has been updated. Please login to view your admission notification.', 'admission']);
    } elseif ($status === 'rejected') {
        $db->prepare('INSERT INTO notifications (student_id, message, type) VALUES (?,?,?)')
           ->execute([$sid, 'Your admission decision has been updated. Please login to view your notification.', 'admission']);
    }

    setFlash('success', 'Admission status updated and student notified.');
    redirect(BASE_URL . '/admin/admission_status.php');
}

$students = $db->query('SELECT s.*, a.status AS adm_status, a.notification_message, a.admission_number, a.payment_confirmed
                        FROM students s
                        LEFT JOIN admission_status a ON a.student_id = s.id
                        ORDER BY s.full_name ASC')->fetchAll();

$selectedStudent = null;
$selectedId = (int)($_GET['student'] ?? 0);
if ($selectedId) {
    foreach ($students as $s) {
        if ($s['id'] == $selectedId) { $selectedStudent = $s; break; }
    }
}

$schoolName = getSetting('school_name', 'EntEx Portal');
$acceptanceFeeUrl = getSetting('acceptance_fee_url', 'https://paystack.shop/pay/Acceptance-Fee');
?>

<div class="page-header">
    <div class="page-title">🎓 Admission Status</div>
</div>

<div style="display:grid;grid-template-columns:1fr 1.4fr;gap:24px;">

<!-- STUDENT LIST -->
<div class="card">
    <div class="card-header"><div class="card-title">👥 All Students</div></div>
    <div class="card-body" style="padding:0;">
        <div class="table-responsive">
            <table class="table">
                <thead><tr><th>Name</th><th>Index ID</th><th>Status</th></tr></thead>
                <tbody>
                    <?php foreach ($students as $s): ?>
                        <tr class="<?= $selectedId == $s['id'] ? 'active' : '' ?>"
                            style="<?= $selectedId == $s['id'] ? 'background:var(--red-pale);' : '' ?>cursor:pointer;"
                            onclick="window.location='?student=<?= $s['id'] ?>'">
                            <td><?= htmlspecialchars($s['full_name']) ?></td>
                            <td><code><?= htmlspecialchars($s['index_id']) ?></code></td>
                            <td>
                                <?php $adm = $s['adm_status'] ?? 'pending'; ?>
                                <span class="badge badge-<?= $adm === 'offered' ? 'green' : ($adm === 'rejected' ? 'red' : 'gray') ?>">
                                    <?= ucfirst($adm) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- FORM -->
<div class="card">
    <div class="card-header">
        <div class="card-title">📝 Update Admission Status</div>
    </div>
    <div class="card-body">
        <?php if ($selectedStudent): ?>
            <div style="background:var(--off-white);border-radius:var(--radius);padding:14px;margin-bottom:20px;">
                <p><strong><?= htmlspecialchars($selectedStudent['full_name']) ?></strong></p>
                <p style="font-size:.875rem;color:var(--gray-600);"><?= htmlspecialchars($selectedStudent['index_id']) ?></p>
            </div>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="student_id" value="<?= $selectedStudent['id'] ?>">

                <div class="form-group">
                    <label>Admission Decision <span class="text-red">*</span></label>
                    <select name="status" class="form-control">
                        <?php foreach (['pending' => 'Pending', 'offered' => 'Admission Offered', 'rejected' => 'Not Offered'] as $val => $label): ?>
                            <option value="<?= $val ?>" <?= ($selectedStudent['adm_status'] ?? 'pending') === $val ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Notification Message</label>
                    <textarea name="notification_message" class="form-control" rows="6"
                              placeholder="Type the official notification message here..."><?= htmlspecialchars($selectedStudent['notification_message'] ?? '') ?></textarea>
                    <p class="form-hint">
                        Template: "Sequel to your performance at interview, you have been offered admission in <?= htmlspecialchars($schoolName) ?>..."
                    </p>
                </div>

                <div class="form-group">
                    <label>Admission Number (assigned after fee payment)</label>
                    <input type="text" name="admission_number" class="form-control"
                           placeholder="e.g. JA/2026/001"
                           value="<?= htmlspecialchars($selectedStudent['admission_number'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="payment_confirmed" value="1"
                               <?= !empty($selectedStudent['payment_confirmed']) ? 'checked' : '' ?>>
                        Acceptance fee payment confirmed
                    </label>
                </div>

                <div class="form-group">
                    <label>Acceptance Fee Payment URL</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($acceptanceFeeUrl) ?>" readonly>
                    <p class="form-hint">Change this URL in Settings.</p>
                </div>

                <button type="submit" class="btn btn-primary btn-lg">💾 Save & Notify Student</button>
            </form>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon">🎓</div>
                <p>Click a student from the left table to update their admission status.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

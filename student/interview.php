<?php
$pageTitle  = 'My Interview';
$activeMenu = 'interview';
require_once __DIR__ . '/../includes/student_header.php';

$db  = getDB();
$sid = $_SESSION['student_id'];

// Fetch interview schedule
$ist = $db->prepare('SELECT * FROM interview_schedules WHERE student_id = ? ORDER BY created_at DESC LIMIT 1');
$ist->execute([$sid]);
$interview = $ist->fetch();

// Handle student response
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $interview) {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'confirm') {
        $db->prepare('UPDATE interview_schedules SET student_response = "confirmed" WHERE id = ?')
           ->execute([$interview['id']]);
        // Create notification for admin
        setFlash('success', 'You have confirmed your interview. We look forward to seeing you!');
        redirect(BASE_URL . '/student/interview.php');

    } elseif ($action === 'request_change') {
        $altDate = $_POST['alternate_date'] ?? '';
        if (empty($altDate)) {
            setFlash('error', 'Please select your preferred alternate date.');
        } else {
            $db->prepare('UPDATE interview_schedules SET student_response = "requested_change", alternate_date = ?, admin_notified = 0 WHERE id = ?')
               ->execute([$altDate, $interview['id']]);
            setFlash('success', 'Your request for a reschedule has been submitted. The admin will contact you.');
            redirect(BASE_URL . '/student/interview.php');
        }
    }
}
?>

<div class="page-header">
    <div class="page-title">🎤 My Interview</div>
</div>

<?php if (!$interview): ?>
    <div class="card">
        <div class="card-body">
            <div class="empty-state">
                <div class="empty-icon">🎤</div>
                <p>No interview has been scheduled for you yet.</p>
                <p class="text-gray mt-1">Once the admin schedules your interview, it will appear here.</p>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="card">
        <div class="card-header">
            <div class="card-title">📅 Scheduled Interview</div>
            <span class="badge <?= $interview['status'] === 'confirmed' ? 'badge-green' : ($interview['status'] === 'cancelled' ? 'badge-red' : 'badge-blue') ?>">
                <?= ucfirst($interview['status']) ?>
            </span>
        </div>
        <div class="card-body">
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:20px;margin-bottom:24px;">
                <div>
                    <p class="text-gray" style="font-size:.8rem;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;">Interview Date</p>
                    <p style="font-size:1.3rem;font-weight:700;color:var(--red);">
                        <?= date('l, d F Y', strtotime($interview['interview_date'])) ?>
                    </p>
                </div>
                <div>
                    <p class="text-gray" style="font-size:.8rem;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;">Interview Time</p>
                    <p style="font-size:1.3rem;font-weight:700;">
                        <?= date('g:i A', strtotime($interview['interview_time'])) ?>
                    </p>
                </div>
                <div>
                    <p class="text-gray" style="font-size:.8rem;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;">Your Response</p>
                    <p style="font-size:1rem;font-weight:600;">
                        <?php
                        $resp = $interview['student_response'];
                        if ($resp === 'confirmed') echo '✅ Confirmed';
                        elseif ($resp === 'requested_change') echo '🔄 Reschedule Requested';
                        else echo '⏳ Pending Response';
                        ?>
                    </p>
                </div>
            </div>

            <?php if ($interview['alternate_date'] && $interview['student_response'] === 'requested_change'): ?>
                <div class="alert alert-warning">
                    🔄 You requested to reschedule to:
                    <strong><?= date('d F Y', strtotime($interview['alternate_date'])) ?></strong>.
                    Please wait for admin to confirm.
                </div>
            <?php endif; ?>

            <?php if ($interview['status'] !== 'cancelled' && $interview['student_response'] === 'pending'): ?>
                <hr class="separator">
                <p style="font-weight:600;margin-bottom:16px;">Please respond to your scheduled interview:</p>
                <form method="post" style="display:flex;gap:16px;flex-wrap:wrap;align-items:flex-start;">
                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

                    <!-- Confirm availability -->
                    <button type="submit" name="action" value="confirm" class="btn btn-success">
                        ✅ Confirm Availability
                    </button>

                    <!-- Request change -->
                    <div style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap;">
                        <div class="form-group" style="margin:0;">
                            <label for="alternate_date" style="font-size:.85rem;">Preferred Alternate Date</label>
                            <input type="date" id="alternate_date" name="alternate_date" class="form-control"
                                   min="<?= date('Y-m-d', strtotime('+1 day')) ?>"
                                   value="<?= htmlspecialchars($_POST['alternate_date'] ?? '') ?>">
                        </div>
                        <button type="submit" name="action" value="request_change" class="btn btn-warning">
                            📅 Request Another Day
                        </button>
                    </div>
                </form>
            <?php elseif ($interview['student_response'] === 'confirmed'): ?>
                <div class="alert alert-success">
                    ✅ You have confirmed this interview. We look forward to seeing you on
                    <strong><?= date('d F Y', strtotime($interview['interview_date'])) ?></strong>
                    at <strong><?= date('g:i A', strtotime($interview['interview_time'])) ?></strong>.
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

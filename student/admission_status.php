<?php
$pageTitle  = 'Admission Status';
$activeMenu = 'admission';
require_once __DIR__ . '/../includes/student_header.php';

$db  = getDB();
$sid = $_SESSION['student_id'];

// Fetch admission status
$ast = $db->prepare('SELECT * FROM admission_status WHERE student_id = ?');
$ast->execute([$sid]);
$admission = $ast->fetch();

$schoolName     = getSetting('school_name', 'EntEx Portal');
$acceptanceFeeUrl = getSetting('acceptance_fee_url', 'https://paystack.shop/pay/Acceptance-Fee');
?>

<div class="page-header">
    <div class="page-title">🎓 Admission Status</div>
</div>

<?php if (!$admission || $admission['status'] === 'pending'): ?>
    <div class="card">
        <div class="card-body">
            <div class="empty-state">
                <div class="empty-icon">🎓</div>
                <p>Your admission status has not been updated yet.</p>
                <p class="text-gray mt-1">Please check back after your interview is completed.</p>
            </div>
        </div>
    </div>

<?php elseif ($admission['status'] === 'offered'): ?>
    <div class="card" style="border-top: 4px solid #27ae60;">
        <div class="card-header" style="background:#eafaf1;">
            <div class="card-title" style="color:#1e8449;">🎉 Admission Offered!</div>
            <span class="badge badge-green">Offered</span>
        </div>
        <div class="card-body">
            <?php if ($admission['notification_message']): ?>
                <div style="font-size:1rem;line-height:1.8;white-space:pre-line;margin-bottom:24px;padding:20px;background:var(--off-white);border-radius:var(--radius);border-left:4px solid #27ae60;">
                    <?= nl2br(htmlspecialchars($admission['notification_message'])) ?>
                </div>
            <?php else: ?>
                <div style="padding:20px;background:var(--off-white);border-radius:var(--radius);border-left:4px solid #27ae60;margin-bottom:24px;">
                    <p>Dear <strong><?= htmlspecialchars($_SESSION['student_name'] ?? 'Prospective Student') ?></strong>,</p>
                    <br>
                    <p>Sequel to your performance at the interview, you have been offered admission in
                    <strong><?= htmlspecialchars($schoolName) ?></strong>.</p>
                    <br>
                    <p>Please accept my congratulations on this achievement.</p>
                </div>
            <?php endif; ?>

            <?php if (!$admission['payment_confirmed']): ?>
                <div class="alert alert-info">
                    💳 To secure your admission, please proceed to pay your <strong>Acceptance Fee</strong>.
                    Once payment is confirmed, you will be assigned an admission number.
                </div>
                <a href="<?= htmlspecialchars($acceptanceFeeUrl) ?>" target="_blank" class="btn btn-success btn-lg">
                    💰 Proceed to Pay Acceptance Fee
                </a>
            <?php else: ?>
                <div class="alert alert-success">
                    ✅ Your acceptance fee payment has been confirmed!
                </div>
                <?php if ($admission['admission_number']): ?>
                    <div style="text-align:center;padding:20px;border:2px solid #27ae60;border-radius:var(--radius);background:#eafaf1;">
                        <p style="font-size:.9rem;color:#1e8449;margin-bottom:6px;">Your Admission Number</p>
                        <p style="font-size:2rem;font-weight:700;color:#1e8449;"><?= htmlspecialchars($admission['admission_number']) ?></p>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

<?php elseif ($admission['status'] === 'rejected'): ?>
    <div class="card" style="border-top: 4px solid var(--red);">
        <div class="card-header" style="background:var(--red-pale);">
            <div class="card-title" style="color:var(--red-dark);">Admission Decision</div>
            <span class="badge badge-red">Not Offered</span>
        </div>
        <div class="card-body">
            <?php if ($admission['notification_message']): ?>
                <div style="font-size:1rem;line-height:1.8;white-space:pre-line;padding:20px;background:var(--off-white);border-radius:var(--radius);border-left:4px solid var(--red);">
                    <?= nl2br(htmlspecialchars($admission['notification_message'])) ?>
                </div>
            <?php else: ?>
                <div style="padding:20px;background:var(--off-white);border-radius:var(--radius);border-left:4px solid var(--red);">
                    <p>We regret to inform you that we are unable to offer you admission at this time.
                    Thank you for your interest in <strong><?= htmlspecialchars($schoolName) ?></strong>.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

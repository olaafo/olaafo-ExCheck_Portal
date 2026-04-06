<?php
$pageTitle  = 'My Result';
$activeMenu = 'result';
require_once __DIR__ . '/../includes/student_header.php';

$db  = getDB();
$sid = $_SESSION['student_id'];

// Fetch student details
$st = $db->prepare('SELECT s.*, b.batch_name FROM students s LEFT JOIN exam_batches b ON s.batch_id = b.id WHERE s.id = ?');
$st->execute([$sid]);
$student = $st->fetch();

// Fetch published results with subjects
$rst = $db->prepare('SELECT r.*, sub.subject_name
                     FROM results r
                     JOIN subjects sub ON r.subject_id = sub.id
                     WHERE r.student_id = ? AND r.published = 1
                     ORDER BY sub.subject_name ASC');
$rst->execute([$sid]);
$results = $rst->fetchAll();

$schoolName = getSetting('school_name', 'EntEx Portal');

// Determine overall qualification (all subjects must meet cutoff)
$qualifiedInterview = false;
$interviewDate      = null;
$interviewTime      = null;
$anyResit           = false;
$resitDate          = null;

if (!empty($results)) {
    $allMeetCutoff = true;
    foreach ($results as $r) {
        if (!$r['meet_cutoff']) $allMeetCutoff = false;
        if ($r['qualified_interview']) {
            $qualifiedInterview = true;
            $interviewDate = $r['interview_date'];
            $interviewTime = $r['interview_time'];
        }
        if ($r['resit']) {
            $anyResit = true;
            $resitDate = $r['resit_date'];
        }
    }
}
?>

<div class="page-header">
    <div class="page-title">📊 My Examination Result</div>
    <?php if (!empty($results)): ?>
        <button onclick="window.print()" class="btn btn-secondary btn-sm no-print">🖨 Print Result</button>
    <?php endif; ?>
</div>

<?php if (empty($results)): ?>
    <div class="card">
        <div class="card-body">
            <div class="empty-state">
                <div class="empty-icon">📊</div>
                <p>Your result has not been published yet.</p>
                <p class="text-gray mt-1">Please check back later or contact the admin.</p>
            </div>
        </div>
    </div>
<?php else: ?>

    <!-- RESULT REPORT CARD -->
    <div class="report-card">
        <div class="report-card-header">
            <h2>🏫 <?= htmlspecialchars($schoolName) ?></h2>
            <p>Common Entrance Examination Result</p>
            <?php if ($student['batch_name']): ?>
                <p style="margin-top:4px;opacity:.8;"><?= htmlspecialchars($student['batch_name']) ?></p>
            <?php endif; ?>
        </div>

        <div class="report-bio">
            <?php if (!empty($student['photo']) && file_exists(UPLOAD_DIR . $student['photo'])): ?>
                <img class="bio-photo" src="<?= UPLOAD_URL . htmlspecialchars($student['photo']) ?>" alt="Photo">
            <?php else: ?>
                <div style="width:80px;height:80px;border-radius:8px;background:#f1f3f4;display:flex;align-items:center;justify-content:center;font-size:2rem;border:2px solid #ddd;">👤</div>
            <?php endif; ?>
            <div class="bio-details">
                <p><strong>Name:</strong> <?= htmlspecialchars($student['full_name']) ?></p>
                <p><strong>Index ID:</strong> <strong style="color:var(--red)"><?= htmlspecialchars($student['index_id']) ?></strong></p>
                <p><strong>Grade Applied:</strong> <?= htmlspecialchars($student['grade_applied']) ?></p>
                <p><strong>Gender:</strong> <?= htmlspecialchars($student['gender']) ?></p>
            </div>
        </div>

        <!-- SCORES TABLE -->
        <div class="report-scores">
            <p style="font-weight:700;margin-bottom:10px;color:var(--gray-800);">📝 Subject Scores</p>
            <?php foreach ($results as $r): ?>
                <div class="score-row">
                    <div class="subject-name"><?= htmlspecialchars($r['subject_name']) ?></div>
                    <div class="score-val">
                        <span style="font-size:1.1rem;font-weight:700;">
                            <?= number_format($r['score'], 0) ?>/<?= $r['max_score'] ?>
                        </span>
                        <span class="badge <?= in_array($r['grade'], ['A','B']) ? 'badge-green' : ($r['grade'] === 'F' ? 'badge-red' : 'badge-blue') ?>">
                            <?= htmlspecialchars($r['grade'] ?? 'N/A') ?>
                        </span>
                        <span class="text-gray" style="font-size:.85rem;"><?= htmlspecialchars($r['comment'] ?? '') ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- QUALIFICATION STATUS -->
        <?php if ($qualifiedInterview): ?>
            <div style="margin:0 28px 16px;padding:14px 18px;background:#eafaf1;border-left:4px solid #27ae60;border-radius:var(--radius);">
                🎉 <strong>Congratulations!</strong> You qualified for Interview.
            </div>
            <div class="qualification-box qualified">
                ✅ QUALIFIED FOR INTERVIEW
            </div>
            <?php if ($interviewDate): ?>
                <div class="alert alert-info" style="margin:0 28px 20px;">
                    🎤 <strong>Interview Date:</strong>
                    <?= date('l, d F Y', strtotime($interviewDate)) ?>
                    <?php if ($interviewTime): ?>
                        at <?= date('g:i A', strtotime($interviewTime)) ?>
                    <?php endif; ?>
                    <br>
                    <span style="font-size:.875rem;">Please visit the <a href="<?= BASE_URL ?>/student/interview.php">Interview page</a> to confirm your availability.</span>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="qualification-box not-qualified">
                ❌ NOT QUALIFIED FOR INTERVIEW
            </div>
        <?php endif; ?>

        <?php if ($anyResit): ?>
            <div class="alert alert-warning" style="margin:0 28px 16px;">
                🔄 <strong>Re-sit Required.</strong>
                <?= $resitDate ? 'Date: ' . date('d F Y', strtotime($resitDate)) : '' ?>
            </div>
        <?php endif; ?>

        <div class="report-footer">
            <span>Generated: <?= date('d F Y, g:i A') ?></span>
            <span>Index ID: <?= htmlspecialchars($student['index_id']) ?></span>
        </div>
    </div>

<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

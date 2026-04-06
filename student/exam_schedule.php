<?php
$pageTitle  = 'My Exam Schedule';
$activeMenu = 'schedule';
require_once __DIR__ . '/../includes/student_header.php';

$db  = getDB();
$sid = $_SESSION['student_id'];

// Fetch all exam schedules for this student
$st = $db->prepare('SELECT es.*, b.batch_name
                    FROM exam_schedules es
                    LEFT JOIN exam_batches b ON es.batch_id = b.id
                    WHERE es.student_id = ?
                    ORDER BY es.exam_date ASC');
$st->execute([$sid]);
$schedules = $st->fetchAll();

// Fetch subjects for this student
$sst = $db->prepare('SELECT s.subject_name FROM student_subjects ss
                     JOIN subjects s ON ss.subject_id = s.id
                     WHERE ss.student_id = ?');
$sst->execute([$sid]);
$subjects = $sst->fetchAll(PDO::FETCH_COLUMN);
?>

<div class="page-header">
    <div class="page-title">📅 My Exam Schedule</div>
</div>

<?php if (empty($schedules)): ?>
    <div class="card">
        <div class="card-body">
            <div class="empty-state">
                <div class="empty-icon">📅</div>
                <p>No exam schedule has been assigned to you yet.</p>
                <p class="text-gray mt-1">Please check back later or contact the admin.</p>
            </div>
        </div>
    </div>
<?php else: ?>
    <?php foreach ($schedules as $sched): ?>
        <?php
        $isPast   = strtotime($sched['exam_date']) < strtotime(date('Y-m-d'));
        $isToday  = $sched['exam_date'] === date('Y-m-d');
        ?>
        <div class="card">
            <div class="card-header">
                <div class="card-title">
                    <?= $sched['batch_name'] ? '📋 ' . htmlspecialchars($sched['batch_name']) : '📌 Individual Exam' ?>
                    <?php if ($isToday): ?>
                        <span class="badge badge-green">TODAY</span>
                    <?php elseif ($isPast): ?>
                        <span class="badge badge-gray">Completed</span>
                    <?php else: ?>
                        <span class="badge badge-blue">Upcoming</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:20px;">
                    <div>
                        <p class="text-gray" style="font-size:.8rem;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;">Exam Date</p>
                        <p style="font-size:1.2rem;font-weight:700;color:var(--red);">
                            <?= date('l, d F Y', strtotime($sched['exam_date'])) ?>
                        </p>
                    </div>
                    <div>
                        <p class="text-gray" style="font-size:.8rem;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;">Start Time</p>
                        <p style="font-size:1.2rem;font-weight:700;">
                            <?= date('g:i A', strtotime($sched['start_time'])) ?>
                        </p>
                    </div>
                    <div>
                        <p class="text-gray" style="font-size:.8rem;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;">End Time</p>
                        <p style="font-size:1.2rem;font-weight:700;">
                            <?= date('g:i A', strtotime($sched['end_time'])) ?>
                        </p>
                    </div>
                </div>

                <?php if (!empty($subjects)): ?>
                    <hr class="separator">
                    <p style="font-weight:600;margin-bottom:10px;">📚 Subjects to Write:</p>
                    <div style="display:flex;flex-wrap:wrap;gap:8px;">
                        <?php foreach ($subjects as $sub): ?>
                            <span class="badge badge-red"><?= htmlspecialchars($sub) ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ($isToday): ?>
                    <div class="alert alert-info mt-2">
                        📢 Today is your exam day! Report to your exam centre on time.
                    </div>
                <?php elseif (!$isPast): ?>
                    <div class="alert alert-warning mt-2">
                        ⏰ Please ensure you are present on the scheduled date and time.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

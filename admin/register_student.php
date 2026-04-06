<?php
$pageTitle  = 'Register Student';
$activeMenu = 'register';
require_once __DIR__ . '/../includes/admin_header.php';

$db = getDB();

// Handle CSV upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'csv') {
    verifyCsrf();
    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        setFlash('error', 'Please upload a valid CSV file.');
        redirect(BASE_URL . '/admin/register_student.php');
    }
    $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
    $header = fgetcsv($handle); // skip header row
    $count  = 0;
    $errors = [];
    $batchId = !empty($_POST['csv_batch_id']) ? (int)$_POST['csv_batch_id'] : null;
    $regType = $batchId ? 'batch' : 'oneoff';

    while (($row = fgetcsv($handle)) !== false) {
        if (count($row) < 5) continue;
        [$fullName, $gradeApplied, $age, $gender, $subjectsList] = array_map('trim', $row);
        if (empty($fullName)) continue;
        $indexId = generateIndexId();
        try {
            $st = $db->prepare('INSERT INTO students (full_name, grade_applied, age, gender, index_id, registration_type, batch_id) VALUES (?,?,?,?,?,?,?)');
            $st->execute([$fullName, $gradeApplied, (int)$age, $gender, $indexId, $regType, $batchId]);
            $sid = $db->lastInsertId();
            // Assign subjects
            foreach (explode('|', $subjectsList) as $subName) {
                $subName = trim($subName);
                if (!$subName) continue;
                $sub = $db->prepare('SELECT id FROM subjects WHERE subject_name = ?');
                $sub->execute([$subName]);
                $subRow = $sub->fetch();
                if ($subRow) {
                    $db->prepare('INSERT IGNORE INTO student_subjects (student_id, subject_id) VALUES (?,?)')->execute([$sid, $subRow['id']]);
                }
            }
            $count++;
        } catch (PDOException $e) {
            $errors[] = "Row skipped ({$fullName}): " . $e->getMessage();
        }
    }
    fclose($handle);
    $msg = "{$count} student(s) imported successfully.";
    if ($errors) $msg .= ' Errors: ' . implode('; ', array_slice($errors, 0, 3));
    setFlash($errors ? 'warning' : 'success', $msg);
    redirect(BASE_URL . '/admin/register_student.php');
}

// Handle individual registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'register') {
    verifyCsrf();
    $fullName    = trim($_POST['full_name'] ?? '');
    $grade       = trim($_POST['grade_applied'] ?? '');
    $age         = (int)($_POST['age'] ?? 0);
    $gender      = $_POST['gender'] ?? '';
    $subjects    = $_POST['subjects'] ?? [];
    $regType     = $_POST['registration_type'] ?? 'batch';
    $batchId     = $regType === 'batch' ? ((int)($_POST['batch_id'] ?? 0) ?: null) : null;
    $forcedBatch = (int)($_POST['forced_batch'] ?? 0) ?: null;
    $forcedIn    = $forcedBatch ? 1 : 0;
    if ($forcedBatch) { $batchId = $forcedBatch; $regType = 'batch'; }

    if (empty($fullName) || empty($grade) || !$age || empty($gender)) {
        setFlash('error', 'Please fill in all required fields.');
        redirect(BASE_URL . '/admin/register_student.php');
    }

    $indexId = generateIndexId();

    // Handle photo upload
    $photoName = null;
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $ext  = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','gif','webp'];
        if (!in_array($ext, $allowed)) {
            setFlash('error', 'Invalid photo format. Use JPG, PNG, or GIF.');
            redirect(BASE_URL . '/admin/register_student.php');
        }
        $photoName = uniqid('photo_') . '.' . $ext;
        if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
        move_uploaded_file($_FILES['photo']['tmp_name'], UPLOAD_DIR . $photoName);
    }

    try {
        $st = $db->prepare('INSERT INTO students (full_name, grade_applied, age, gender, index_id, photo, registration_type, batch_id, forced_in)
                            VALUES (?,?,?,?,?,?,?,?,?)');
        $st->execute([$fullName, $grade, $age, $gender, $indexId, $photoName, $regType, $batchId, $forcedIn]);
        $sid = $db->lastInsertId();

        foreach ($subjects as $subId) {
            $db->prepare('INSERT IGNORE INTO student_subjects (student_id, subject_id) VALUES (?,?)')->execute([$sid, (int)$subId]);
        }

        setFlash('success', "Student '{$fullName}' registered successfully. Index ID: {$indexId}");
    } catch (PDOException $e) {
        setFlash('error', 'Registration failed: ' . $e->getMessage());
    }
    redirect(BASE_URL . '/admin/register_student.php');
}

$batches  = $db->query('SELECT * FROM exam_batches ORDER BY exam_date DESC')->fetchAll();
$subjects = $db->query('SELECT * FROM subjects ORDER BY subject_name ASC')->fetchAll();

// Check if any past batch (for force-add warning)
$pastBatches = $db->query("SELECT * FROM exam_batches WHERE exam_date < CURDATE() ORDER BY exam_date DESC")->fetchAll();
?>

<div class="page-header">
    <div class="page-title">➕ Register Prospective Student</div>
</div>

<div style="display:grid;grid-template-columns:2fr 1fr;gap:24px;">

<!-- REGISTRATION FORM -->
<div class="card">
    <div class="card-header">
        <div class="card-title">👤 Prospective Student Registration</div>
    </div>
    <div class="card-body">
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="action" value="register">

            <div class="form-row">
                <div class="form-group">
                    <label>Full Name <span class="text-red">*</span></label>
                    <input type="text" name="full_name" class="form-control" placeholder="Full name of student" required>
                </div>
                <div class="form-group">
                    <label>Grade Applied For <span class="text-red">*</span></label>
                    <input type="text" name="grade_applied" class="form-control" placeholder="e.g. Grade 1, JSS 1" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Age <span class="text-red">*</span></label>
                    <input type="number" name="age" class="form-control" min="3" max="25" placeholder="Age" required>
                </div>
                <div class="form-group">
                    <label>Gender <span class="text-red">*</span></label>
                    <select name="gender" class="form-control" required>
                        <option value="">-- Select --</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>Registration Type <span class="text-red">*</span></label>
                <select name="registration_type" id="registrationType" class="form-control">
                    <option value="batch">Batch (assign to scheduled exam batch)</option>
                    <option value="oneoff">One-off (individual scheduling)</option>
                </select>
            </div>

            <div id="batchSelectArea">
                <div class="form-group">
                    <label>Exam Batch</label>
                    <select name="batch_id" class="form-control">
                        <option value="">-- Select Batch (optional) --</option>
                        <?php foreach ($batches as $b): ?>
                            <option value="<?= $b['id'] ?>">
                                <?= htmlspecialchars($b['batch_name']) ?> – <?= date('d M Y', strtotime($b['exam_date'])) ?>
                                <?= strtotime($b['exam_date']) < strtotime(date('Y-m-d')) ? '(Past)' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div id="oneoffDateArea" style="display:none;">
                <div class="alert alert-info">
                    ℹ️ One-off students will be individually scheduled via the <strong>Prepare Exam Schedules</strong> menu.
                </div>
            </div>

            <!-- Force into past batch -->
            <?php if (!empty($pastBatches)): ?>
            <div id="forcedBatchArea" style="display:none;">
                <div class="alert alert-warning">
                    ⚠️ <strong>Warning:</strong> The selected batch has already done its exam.
                    You can force-add this student to a past batch for result preparation.
                </div>
                <div class="form-group">
                    <label>Force into Past Batch</label>
                    <select name="forced_batch" class="form-control">
                        <option value="">-- Do not force --</option>
                        <?php foreach ($pastBatches as $pb): ?>
                            <option value="<?= $pb['id'] ?>"><?= htmlspecialchars($pb['batch_name']) ?> – <?= date('d M Y', strtotime($pb['exam_date'])) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <?php endif; ?>

            <div class="form-group">
                <label>Subjects (select all that apply)</label>
                <?php if (empty($subjects)): ?>
                    <p class="text-gray">No subjects added yet. <a href="<?= BASE_URL ?>/admin/subjects.php">Add subjects first.</a></p>
                <?php else: ?>
                    <div class="checkbox-group">
                        <?php foreach ($subjects as $sub): ?>
                            <label class="checkbox-label">
                                <input type="checkbox" name="subjects[]" value="<?= $sub['id'] ?>">
                                <?= htmlspecialchars($sub['subject_name']) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label>Photo (optional)</label>
                <input type="file" id="studentPhoto" name="photo" class="form-control" accept="image/*">
                <img id="photoPreview" src="" style="display:none;width:80px;height:80px;object-fit:cover;border-radius:8px;margin-top:8px;border:2px solid #ddd;" alt="Preview">
                <p class="form-hint">Index Number will be auto-generated by the system.</p>
            </div>

            <button type="submit" class="btn btn-primary btn-lg">✅ Register Student</button>
        </form>
    </div>
</div>

<!-- CSV UPLOAD -->
<div>
    <div class="card">
        <div class="card-header">
            <div class="card-title">📂 Bulk CSV Upload</div>
        </div>
        <div class="card-body">
            <p style="font-size:.875rem;color:var(--gray-600);margin-bottom:16px;">
                Upload a CSV file to register multiple students at once.
            </p>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="action" value="csv">
                <div class="form-group">
                    <label>Assign to Batch (optional)</label>
                    <select name="csv_batch_id" class="form-control">
                        <option value="">-- One-off / No Batch --</option>
                        <?php foreach ($batches as $b): ?>
                            <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['batch_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label id="csvLabel">Choose CSV File</label>
                    <input type="file" id="csvUpload" name="csv_file" class="form-control" accept=".csv" required>
                </div>
                <button type="submit" class="btn btn-primary btn-block">📤 Upload CSV</button>
            </form>
            <hr class="separator">
            <p style="font-size:.8rem;color:var(--gray-600);">
                <strong>CSV Format (no header):</strong><br>
                <code>Full Name, Grade, Age, Gender, Subject1|Subject2</code>
            </p>
            <p style="font-size:.8rem;color:var(--gray-600);margin-top:8px;">
                Example row:<br>
                <code>John Doe, Grade 5, 10, Male, Mathematics|English</code>
            </p>
        </div>
    </div>
</div>

</div><!-- grid -->

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

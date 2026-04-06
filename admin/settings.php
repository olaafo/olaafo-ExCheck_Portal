<?php
$pageTitle  = 'Settings';
$activeMenu = 'settings';
require_once __DIR__ . '/../includes/admin_header.php';

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $action = $_POST['action'] ?? '';

    if ($action === 'general') {
        setSetting('school_name', trim($_POST['school_name'] ?? ''));
        setSetting('welcome_message', trim($_POST['welcome_message'] ?? ''));
        setSetting('acceptance_fee_url', trim($_POST['acceptance_fee_url'] ?? ''));
        setSetting('index_prefix', trim($_POST['index_prefix'] ?? 'JA/PS'));
        setSetting('serial_length', (string)max(1, (int)($_POST['serial_length'] ?? 3)));

        // Logo upload
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $ext     = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','gif','svg','webp'];
            if (in_array($ext, $allowed)) {
                $logoDir = __DIR__ . '/../uploads/';
                if (!is_dir($logoDir)) mkdir($logoDir, 0755, true);
                $logoName = 'school_logo.' . $ext;
                move_uploaded_file($_FILES['logo']['tmp_name'], $logoDir . $logoName);
                setSetting('logo', $logoName);
            } else {
                setFlash('error', 'Invalid logo format.');
                redirect(BASE_URL . '/admin/settings.php');
            }
        }

        setFlash('success', 'General settings saved.');

    } elseif ($action === 'grading') {
        foreach (['100', '60', '50'] as $max) {
            $grades = $_POST["grades_{$max}"] ?? [];
            $built  = [];
            foreach ($grades as $g) {
                if (!empty($g['min']) && !empty($g['grade'])) {
                    $built[] = [
                        'min'     => (int)$g['min'],
                        'max'     => (int)$g['max'],
                        'grade'   => strtoupper(trim($g['grade'])),
                        'comment' => trim($g['comment'] ?? ''),
                    ];
                }
            }
            if (!empty($built)) setSetting("grading_{$max}", json_encode($built));
            setSetting("cutoff_{$max}", (string)(int)($_POST["cutoff_{$max}"] ?? 50));
        }
        setFlash('success', 'Grading rubrics saved.');

    } elseif ($action === 'password') {
        $adminId   = $_SESSION['admin_id'];
        $current   = $_POST['current_password'] ?? '';
        $newPass   = $_POST['new_password'] ?? '';
        $confirmP  = $_POST['confirm_password'] ?? '';

        $admin = $db->prepare('SELECT password FROM `admin` WHERE id = ?');
        $admin->execute([$adminId]);
        $admin = $admin->fetch();

        if (!$admin || !password_verify($current, $admin['password'])) {
            setFlash('error', 'Current password is incorrect.');
        } elseif (strlen($newPass) < 6) {
            setFlash('error', 'New password must be at least 6 characters.');
        } elseif ($newPass !== $confirmP) {
            setFlash('error', 'Passwords do not match.');
        } else {
            $db->prepare('UPDATE `admin` SET password = ? WHERE id = ?')->execute([password_hash($newPass, PASSWORD_BCRYPT), $adminId]);
            setFlash('success', 'Password changed successfully.');
        }
    }

    redirect(BASE_URL . '/admin/settings.php');
}

$settings = [
    'school_name'       => getSetting('school_name', 'EntEx Portal'),
    'welcome_message'   => getSetting('welcome_message', 'Welcome To Prospective Student Portal'),
    'acceptance_fee_url'=> getSetting('acceptance_fee_url', 'https://paystack.shop/pay/Acceptance-Fee'),
    'index_prefix'      => getSetting('index_prefix', 'JA/PS'),
    'serial_length'     => getSetting('serial_length', '3'),
    'logo'              => getSetting('logo', ''),
];

$grading = [];
foreach (['100','60','50'] as $max) {
    $grading[$max] = json_decode(getSetting("grading_{$max}", '[]'), true);
    $grading["cutoff_{$max}"] = getSetting("cutoff_{$max}", $max === '100' ? '50' : ($max === '60' ? '30' : '25'));
}
?>

<div class="page-header">
    <div class="page-title">⚙️ Settings</div>
</div>

<div class="tabs">
    <button class="tab-btn active" data-target="tabGeneral">🏫 General</button>
    <button class="tab-btn" data-target="tabGrading">📊 Grading Rubrics</button>
    <button class="tab-btn" data-target="tabPassword">🔐 Change Password</button>
</div>

<!-- GENERAL SETTINGS -->
<div class="tab-pane active" id="tabGeneral">
    <div class="card">
        <div class="card-header"><div class="card-title">🏫 General Settings</div></div>
        <div class="card-body">
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="action" value="general">

                <div class="form-group">
                    <label>School Name</label>
                    <input type="text" name="school_name" class="form-control"
                           value="<?= htmlspecialchars($settings['school_name']) ?>"
                           placeholder="Your school/organisation name">
                    <p class="form-hint">This name appears throughout the portal.</p>
                </div>

                <div class="form-group">
                    <label>Student Portal Welcome Message</label>
                    <input type="text" name="welcome_message" class="form-control"
                           value="<?= htmlspecialchars($settings['welcome_message']) ?>">
                </div>

                <div class="form-group">
                    <label>Acceptance Fee Payment URL</label>
                    <input type="url" name="acceptance_fee_url" class="form-control"
                           value="<?= htmlspecialchars($settings['acceptance_fee_url']) ?>"
                           placeholder="https://paystack.shop/pay/...">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Index Number Prefix</label>
                        <input type="text" name="index_prefix" class="form-control"
                               value="<?= htmlspecialchars($settings['index_prefix']) ?>"
                               placeholder="e.g. JA/PS">
                        <p class="form-hint">Format: PREFIX/YEAR/SERIAL e.g. JA/PS/2026/001</p>
                    </div>
                    <div class="form-group">
                        <label>Serial Number Length</label>
                        <input type="number" name="serial_length" class="form-control"
                               min="1" max="10"
                               value="<?= htmlspecialchars($settings['serial_length']) ?>">
                        <p class="form-hint">Pads with leading zeros (e.g., 3 = 001)</p>
                    </div>
                </div>

                <div class="form-group">
                    <label>School Logo</label>
                    <?php if ($settings['logo']): ?>
                        <div style="margin-bottom:10px;">
                            <img src="<?= BASE_URL . '/uploads/' . htmlspecialchars($settings['logo']) ?>"
                                 style="height:60px;border-radius:8px;border:2px solid #ddd;">
                        </div>
                    <?php endif; ?>
                    <input type="file" name="logo" class="form-control" accept="image/*">
                    <p class="form-hint">Upload JPG, PNG, SVG. Recommended: Square, 200×200px.</p>
                </div>

                <button type="submit" class="btn btn-primary btn-lg">💾 Save General Settings</button>
            </form>
        </div>
    </div>
</div>

<!-- GRADING RUBRICS -->
<div class="tab-pane" id="tabGrading">
    <div class="card">
        <div class="card-header"><div class="card-title">📊 Grading Rubrics & Cut-off Scores</div></div>
        <div class="card-body">
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="action" value="grading">

                <?php foreach (['100','60','50'] as $max): ?>
                    <div style="margin-bottom:28px;">
                        <p style="font-weight:700;font-size:1.05rem;color:var(--red);margin-bottom:12px;">Out of /<?= $max ?></p>
                        <div class="form-group">
                            <label>Cut-off Score (Minimum to pass)</label>
                            <input type="number" name="cutoff_<?= $max ?>" class="form-control"
                                   value="<?= htmlspecialchars($grading["cutoff_{$max}"]) ?>"
                                   min="0" max="<?= $max ?>" style="max-width:120px;">
                        </div>
                        <p style="font-weight:600;margin-bottom:8px;">Grade Bands</p>
                        <div class="table-responsive">
                            <table style="width:100%;border-collapse:collapse;font-size:.9rem;">
                                <thead>
                                    <tr style="background:var(--red);color:#fff;">
                                        <th style="padding:8px 12px;text-align:left;">Min</th>
                                        <th style="padding:8px 12px;text-align:left;">Max</th>
                                        <th style="padding:8px 12px;text-align:left;">Grade</th>
                                        <th style="padding:8px 12px;text-align:left;">Comment</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $bands = $grading[$max];
                                    // Ensure at least 5 rows
                                    while (count($bands) < 5) $bands[] = ['min'=>'','max'=>'','grade'=>'','comment'=>''];
                                    foreach ($bands as $bi => $band):
                                    ?>
                                    <tr>
                                        <td style="padding:4px;"><input type="number" name="grades_<?= $max ?>[<?= $bi ?>][min]" class="form-control" value="<?= $band['min'] ?>" min="0" max="<?= $max ?>"></td>
                                        <td style="padding:4px;"><input type="number" name="grades_<?= $max ?>[<?= $bi ?>][max]" class="form-control" value="<?= $band['max'] ?>" min="0" max="<?= $max ?>"></td>
                                        <td style="padding:4px;"><input type="text"   name="grades_<?= $max ?>[<?= $bi ?>][grade]" class="form-control" value="<?= htmlspecialchars($band['grade']) ?>" maxlength="3" placeholder="A"></td>
                                        <td style="padding:4px;"><input type="text"   name="grades_<?= $max ?>[<?= $bi ?>][comment]" class="form-control" value="<?= htmlspecialchars($band['comment']) ?>" placeholder="Excellent"></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php if ($max !== '50'): ?><hr class="separator"><?php endif; ?>
                <?php endforeach; ?>

                <button type="submit" class="btn btn-primary btn-lg">💾 Save Grading Rubrics</button>
            </form>
        </div>
    </div>
</div>

<!-- CHANGE PASSWORD -->
<div class="tab-pane" id="tabPassword">
    <div class="card" style="max-width:480px;">
        <div class="card-header"><div class="card-title">🔐 Change Admin Password</div></div>
        <div class="card-body">
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="action" value="password">
                <div class="form-group">
                    <label>Current Password</label>
                    <input type="password" name="current_password" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>New Password</label>
                    <input type="password" name="new_password" class="form-control" minlength="6" required>
                    <p class="form-hint">Minimum 6 characters.</p>
                </div>
                <div class="form-group">
                    <label>Confirm New Password</label>
                    <input type="password" name="confirm_password" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-primary">🔐 Change Password</button>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

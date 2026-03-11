<?php
// Keep your existing includes for compatibility. Ensure one of these provides $pdo.
// If not, require config.php directly.
require 'insert.php';
require 'update.php';
require 'delete.php';
require 'select.php';
// if $pdo is not defined by the above includes, uncomment the next line:
// require 'config.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

$message = '';
$errors = [];

/* ====================
   Handle Delete (via GET)
   ==================== */
if (isset($_GET['delete'])) {
    $delId = (int) $_GET['delete'];
    if ($delId > 0) {
        try {
            $pdo->beginTransaction();

            // If you prefer to delete consultations only, adjust accordingly.
            $stmt = $pdo->prepare("DELETE FROM consultations WHERE patient_id = ?");
            $stmt->execute([$delId]);

            $stmt2 = $pdo->prepare("DELETE FROM patients WHERE patient_id = ?");
            $stmt2->execute([$delId]);

            $pdo->commit();
            $message = 'Patient and related consultations deleted.';
            // redirect to avoid accidental resubmission on refresh
            header('Location: landing.php');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log('Delete error: ' . $e->getMessage());
            $errors[] = 'Failed to delete the patient. See server logs.';
        }
    } else {
        $errors[] = 'Invalid patient id for deletion.';
    }
}

/* ====================
   Prepare Edit mode (prefill form)
   ==================== */
$editMode = false;
$editPatient = null;
$editConsultation = null;
if (isset($_GET['edit'])) {
    $editId = (int) $_GET['edit'];
    if ($editId > 0) {
        // fetch patient
        $stmt = $pdo->prepare("SELECT * FROM patients WHERE patient_id = ? LIMIT 1");
        $stmt->execute([$editId]);
        $editPatient = $stmt->fetch(PDO::FETCH_ASSOC);

        // fetch latest consultation for this patient (if any)
        $stmt = $pdo->prepare("SELECT * FROM consultations WHERE patient_id = ? ORDER BY consultation_id DESC LIMIT 1");
        $stmt->execute([$editId]);
        $editConsultation = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($editPatient) {
            $editMode = true;
        } else {
            $errors[] = 'Patient not found for editing.';
        }
    }
}

/* ====================
   Handle Add (combined insert)
   ==================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_all'])) {
    $full_name = trim($_POST['full_name'] ?? '');
    $age = (int)($_POST['age'] ?? 0);
    $gender = trim($_POST['gender'] ?? '');
    $contact_number = trim($_POST['contact_number'] ?? '');

    $doctor_name = trim($_POST['doctor_name'] ?? '');
    $consultation_date = trim($_POST['consultation_date'] ?? '');
    $diagnosis = trim($_POST['diagnosis'] ?? '');
    $treatment = trim($_POST['treatment'] ?? '');

    if ($full_name === '') $errors[] = 'Full name is required.';
    if ($age <= 0) $errors[] = 'Provide a valid age.';
    if ($gender === '') $errors[] = 'Gender is required.';
    if ($contact_number === '') $errors[] = 'Contact number is required.';
    if ($doctor_name === '') $errors[] = 'Doctor name is required.';
    if ($consultation_date === '') $errors[] = 'Consultation date is required.';

    if ($consultation_date !== '') {
        $d = DateTime::createFromFormat('Y-m-d', $consultation_date);
        if (!($d && $d->format('Y-m-d') === $consultation_date)) {
            $errors[] = 'Consultation date must be in YYYY-MM-DD format.';
        }
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("INSERT INTO patients (full_name, age, gender, contact_number) VALUES (?, ?, ?, ?)");
            $stmt->execute([$full_name, $age, $gender, $contact_number]);
            $patient_id = $pdo->lastInsertId();

            if (!$patient_id) throw new Exception('Could not get new patient id.');

            $stmt2 = $pdo->prepare("INSERT INTO consultations (patient_id, doctor_name, consultation_date, diagnosis, treatment) VALUES (?, ?, ?, ?, ?)");
            $stmt2->execute([$patient_id, $doctor_name, $consultation_date, $diagnosis, $treatment]);

            $pdo->commit();
            $message = 'Patient and consultation added.';
            // avoid re-post on refresh
            header('Location: landing.php');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log('Add error: ' . $e->getMessage());
            $errors[] = 'Failed to add patient/consultation. See logs.';
        }
    }
}

/* ====================
   Handle Update (edit patient + latest consultation)
   ==================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_all'])) {
    // patient id to update
    $patient_id = (int)($_POST['patient_id'] ?? 0);
    if ($patient_id <= 0) {
        $errors[] = 'Invalid patient ID for update.';
    } else {
        $full_name = trim($_POST['full_name'] ?? '');
        $age = (int)($_POST['age'] ?? 0);
        $gender = trim($_POST['gender'] ?? '');
        $contact_number = trim($_POST['contact_number'] ?? '');

        $doctor_name = trim($_POST['doctor_name'] ?? '');
        $consultation_date = trim($_POST['consultation_date'] ?? '');
        $diagnosis = trim($_POST['diagnosis'] ?? '');
        $treatment = trim($_POST['treatment'] ?? '');

        if ($full_name === '') $errors[] = 'Full name is required.';
        if ($age <= 0) $errors[] = 'Provide a valid age.';
        if ($gender === '') $errors[] = 'Gender is required.';
        if ($contact_number === '') $errors[] = 'Contact number is required.';
        if ($doctor_name === '') $errors[] = 'Doctor name is required.';
        if ($consultation_date === '') $errors[] = 'Consultation date is required.';

        if ($consultation_date !== '') {
            $d = DateTime::createFromFormat('Y-m-d', $consultation_date);
            if (!($d && $d->format('Y-m-d') === $consultation_date)) {
                $errors[] = 'Consultation date must be in YYYY-MM-DD format.';
            }
        }

        if (empty($errors)) {
            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare("UPDATE patients SET full_name = ?, age = ?, gender = ?, contact_number = ? WHERE patient_id = ?");
                $stmt->execute([$full_name, $age, $gender, $contact_number, $patient_id]);

                $stmt = $pdo->prepare("SELECT consultation_id FROM consultations WHERE patient_id = ? ORDER BY consultation_id DESC LIMIT 1");
                $stmt->execute([$patient_id]);
                $c = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($c && isset($c['consultation_id'])) {
                    $stmt2 = $pdo->prepare("UPDATE consultations SET doctor_name = ?, consultation_date = ?, diagnosis = ?, treatment = ? WHERE consultation_id = ?");
                    $stmt2->execute([$doctor_name, $consultation_date, $diagnosis, $treatment, $c['consultation_id']]);
                } else {
                    $stmt2 = $pdo->prepare("INSERT INTO consultations (patient_id, doctor_name, consultation_date, diagnosis, treatment) VALUES (?, ?, ?, ?, ?)");
                    $stmt2->execute([$patient_id, $doctor_name, $consultation_date, $diagnosis, $treatment]);
                }

                $pdo->commit();
                $message = 'Patient and consultation updated.';
                header('Location: landing.php');
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                error_log('Update error: ' . $e->getMessage());
                $errors[] = 'Failed to update. See server logs.';
            }
        }
    }
}

$stmt = $pdo->query("
    SELECT p.patient_id, p.full_name, p.age, p.gender, p.contact_number,
           c.consultation_id, c.doctor_name, c.consultation_date, c.diagnosis, c.treatment
    FROM patients p
    LEFT JOIN consultations c
      ON c.patient_id = p.patient_id
      AND c.consultation_id = (
          SELECT MAX(consultation_id) FROM consultations c2 WHERE c2.patient_id = p.patient_id
      )
    ORDER BY p.patient_id DESC
");
$rows = $stmt->fetchAll();

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Clinic Management</title>
  <style>
    * { box-sizing: border-box; margin:0; padding:0; }
    body { font-family: -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto, sans-serif; background:#f5f5f5; color:#111; padding:24px; }
    .container { max-width:980px; margin:0 auto; }
    .card { background:#fff; border:1px solid #e6e6e6; border-radius:8px; padding:16px; margin-bottom:14px; }
    .form-grid { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
    label { font-size:13px; color:#444; margin-bottom:6px; display:block; }
    input, select { padding:8px 10px; border-radius:6px; border:1px solid #ddd; background:#fafafa; width:100%; }
    .form-actions { grid-column:1 / -1; margin-top:8px; display:flex; gap:8px; }
    .btn { padding:8px 12px; border-radius:6px; border:none; cursor:pointer; }
    .btn-primary { background:#2563eb; color:#fff; }
    .btn-edit { background:#10b981; color:#fff; text-decoration:none; padding:6px 10px; border-radius:6px; }
    .btn-delete { background:#ef4444; color:#fff; text-decoration:none; padding:6px 10px; border-radius:6px; }
    table { width:100%; border-collapse:collapse; margin-top:8px; }
    th, td { padding:10px 8px; border-bottom:1px solid #f0f0f0; text-align:left; font-size:13px; }
    .muted { color:#888; font-size:13px; }
    @media(max-width:700px){ .form-grid { grid-template-columns:1fr } }
  </style>
</head>
<body>
  <div class="container">
    <h1 style="margin-bottom:12px">Clinic Management</h1>

    <?php if ($message): ?>
      <div style="background:#ecfdf5;color:#065f46;padding:10px;border:1px solid #bbf7d0;border-radius:6px;margin-bottom:12px;"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
      <div style="background:#fff1f2;color:#7f1d1d;padding:10px;border:1px solid #fecaca;border-radius:6px;margin-bottom:12px;">
        <ul style="margin-left:18px">
        <?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <div class="card">
      <h2 style="font-size:14px;margin-bottom:10px"><?= $editMode ? 'Edit Patient' : 'Add Patient' ?></h2>

      <form method="POST" novalidate>
        <div class="form-grid">
          <input type="hidden" name="patient_id" value="<?= $editMode ? (int)$editPatient['patient_id'] : '' ?>">

          <div>
            <label>Full name</label>
            <input type="text" name="full_name" required value="<?= htmlspecialchars($_POST['full_name'] ?? ($editMode ? $editPatient['full_name'] : '')) ?>">
          </div>

          <div>
            <label>Age</label>
            <input type="number" name="age" required value="<?= htmlspecialchars($_POST['age'] ?? ($editMode ? $editPatient['age'] : '')) ?>">
          </div>

          <div>
            <label>Gender</label>
            <select name="gender" required>
              <option value="">Select</option>
              <option value="Male" <?= (($_POST['gender'] ?? ($editMode ? $editPatient['gender'] : '')) === 'Male') ? 'selected' : '' ?>>Male</option>
              <option value="Female" <?= (($_POST['gender'] ?? ($editMode ? $editPatient['gender'] : '')) === 'Female') ? 'selected' : '' ?>>Female</option>
            </select>
          </div>

          <div>
            <label>Contact number</label>
            <input type="text" name="contact_number" required value="<?= htmlspecialchars($_POST['contact_number'] ?? ($editMode ? $editPatient['contact_number'] : '')) ?>">
          </div>

          <div>
            <label>Doctor name</label>
            <input type="text" name="doctor_name" required value="<?= htmlspecialchars($_POST['doctor_name'] ?? ($editMode && $editConsultation ? $editConsultation['doctor_name'] : '')) ?>">
          </div>

          <div>
            <label>Consultation date</label>
            <input type="date" name="consultation_date" required value="<?= htmlspecialchars($_POST['consultation_date'] ?? ($editMode && $editConsultation ? $editConsultation['consultation_date'] : '')) ?>">
          </div>

          <div>
            <label>Diagnosis</label>
            <input type="text" name="diagnosis" value="<?= htmlspecialchars($_POST['diagnosis'] ?? ($editMode && $editConsultation ? $editConsultation['diagnosis'] : '')) ?>">
          </div>

          <div>
            <label>Treatment</label>
            <input type="text" name="treatment" value="<?= htmlspecialchars($_POST['treatment'] ?? ($editMode && $editConsultation ? $editConsultation['treatment'] : '')) ?>">
          </div>

          <div class="form-actions">
            <?php if ($editMode): ?>
              <button class="btn btn-primary" type="submit" name="update_all">Save changes</button>
              <a class="btn" href="landing.php">Cancel</a>
            <?php else: ?>
              <button class="btn btn-primary" type="submit" name="add_all">Add Patient</button>
              <button class="btn" type="reset">Clear</button>
            <?php endif; ?>
          </div>
        </div>
      </form>
    </div>

    <!-- Patients table -->
    <div class="card">
      <h2 style="font-size:14px;margin-bottom:10px">All Patients & Latest Consultation</h2>

      <?php if (empty($rows)): ?>
        <div class="muted" style="padding:12px">No patients yet.</div>
      <?php else: ?>
        <table>
          <thead>
            <tr>
              <th>Patient ID</th>
              <th>Full name</th>
              <th>Age</th>
              <th>Gender</th>
              <th>Contact</th>
              <th>Consult Date</th>
              <th>Doctor</th>
              <th>Diagnosis</th>
              <th>Treatment</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $r): ?>
              <tr>
                <td><?= (int)$r['patient_id'] ?></td>
                <td><?= htmlspecialchars($r['full_name']) ?></td>
                <td><?= htmlspecialchars($r['age']) ?></td>
                <td><?= htmlspecialchars($r['gender']) ?></td>
                <td><?= htmlspecialchars($r['contact_number']) ?></td>
                <td><?= htmlspecialchars($r['consultation_date'] ?? '—') ?></td>
                <td><?= htmlspecialchars($r['doctor_name'] ?? '—') ?></td>
                <td><?= htmlspecialchars($r['diagnosis'] ?? '—') ?></td>
                <td><?= htmlspecialchars($r['treatment'] ?? '—') ?></td>
                <td>
                  <a class="btn-edit" href="landing.php?edit=<?= (int)$r['patient_id'] ?>">Edit</a>
                  <a class="btn-delete" href="landing.php?delete=<?= (int)$r['patient_id'] ?>" onclick="return confirm('Delete this patient and all consultations?')">Delete</a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

  </div>
</body>
</html>
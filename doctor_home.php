<?php
session_start();
include 'db.php';
include_once 'appointment_helpers.php';

date_default_timezone_set('Africa/Nairobi');

// Prevent the browser from showing this page from cache after logout
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'doctor') {
    header("Location: login.php");
    exit;
}

$doctor_id = (int) $_SESSION['user_id'];
refresh_appointment_statuses($conn);
$today = date('Y-m-d');
$times = ['08:00', '08:30', '09:00', '09:30', '10:00', '10:30', '11:00', '11:30', '12:00', '12:30', '13:00', '13:30', '14:00', '14:30', '15:00', '15:30'];
$error_msg = "";

// Is this doctor free at the given date and time? ($ignore_id = the appointment being moved)
function doctor_slot_free($conn, $doctor_id, $date, $time, $ignore_id) {
    $stmt = $conn->prepare("SELECT id FROM appointments WHERE doctor_id = ? AND appointment_date = ? AND appointment_time = ? AND status NOT LIKE 'Cancelled%' AND id <> ?");
    $stmt->bind_param("issi", $doctor_id, $date, $time, $ignore_id);
    $stmt->execute();
    $stmt->store_result();
    return $stmt->num_rows == 0;
}

// ---------- Appointment actions: accept / reschedule / not available ----------
if (isset($_POST['appointment_action'])) {
    $appt_id = (int) $_POST['appt_id'];
    $action  = $_POST['appointment_action'];

    // Only this doctor's own appointments can be changed
    $stmt = $conn->prepare("SELECT id, patient_id, status, appointment_date, appointment_time FROM appointments WHERE id = ? AND doctor_id = ?");
    $stmt->bind_param("ii", $appt_id, $doctor_id);
    $stmt->execute();
    $appt = $stmt->get_result()->fetch_assoc();

    if (!$appt) {
        $error_msg = "Appointment not found.";
    } elseif (strpos($appt['status'], 'Cancelled') === 0) {
        $error_msg = "This appointment is already cancelled.";
    } elseif ($appt['status'] == 'Done' && $action != 'feedback') {
        $error_msg = "This appointment is already marked as done.";
    } elseif ($action == 'accept') {
        $stmt = $conn->prepare("UPDATE appointments SET status = 'Confirmed' WHERE id = ? AND doctor_id = ? AND status = 'Booked'");
        $stmt->bind_param("ii", $appt_id, $doctor_id);
        $stmt->execute();
        header("Location: doctor_home.php?page=schedule&msg=accepted");
        exit;
    } elseif ($action == 'unavailable') {
        // Starts with "Cancelled" so the slot becomes free again for other patients
        $stmt = $conn->prepare("UPDATE appointments SET status = 'Cancelled by doctor' WHERE id = ? AND doctor_id = ?");
        $stmt->bind_param("ii", $appt_id, $doctor_id);
        $stmt->execute();
        header("Location: doctor_home.php?page=schedule&msg=unavailable");
        exit;
    } elseif ($action == 'done') {
        $starts = strtotime($appt['appointment_date'] . ' ' . $appt['appointment_time']);
        if (!in_array($appt['status'], ['Booked', 'Confirmed'])) {
            $error_msg = "Only a booked or confirmed appointment can be marked as done.";
        } elseif ($starts > time()) {
            $error_msg = "You can only mark an appointment as done once it has started.";
        } else {
            $stmt = $conn->prepare("UPDATE appointments SET status = 'Done' WHERE id = ? AND doctor_id = ?");
            $stmt->bind_param("ii", $appt_id, $doctor_id);
            $stmt->execute();
            header("Location: doctor_home.php?page=schedule&msg=done");
            exit;
        }
    } elseif ($action == 'feedback') {
        $text = isset($_POST['feedback_text']) ? trim($_POST['feedback_text']) : '';
        if ($appt['status'] != 'Done') {
            $error_msg = "Feedback can only be given after the appointment is marked as done.";
        } elseif ($text == '') {
            $error_msg = "Please write some feedback for the patient.";
        } else {
            $text = substr($text, 0, 1000);
            try {
                $stmt = $conn->prepare("UPDATE appointments SET doctor_feedback = ?, feedback_date = NOW() WHERE id = ? AND doctor_id = ?");
                $stmt->bind_param("sii", $text, $appt_id, $doctor_id);
                $stmt->execute();
                header("Location: doctor_home.php?page=schedule&msg=feedback");
                exit;
            } catch (mysqli_sql_exception $e) {
                $error_msg = "Feedback could not be saved. Please run the SQL that adds the doctor_feedback column.";
            }
        }
    } elseif ($action == 'reschedule') {
        $new_date = isset($_POST['new_date']) ? $_POST['new_date'] : '';
        $new_time = isset($_POST['new_time']) ? $_POST['new_time'] : '';

        $d = DateTime::createFromFormat('Y-m-d', $new_date);
        $valid_date = $d && $d->format('Y-m-d') === $new_date;

        if (!$valid_date || !in_array($new_time, $times) || $new_date < $today || ($new_date == $today && $new_time <= date('H:i'))) {
            $error_msg = "Please choose a valid new date and time.";
        } elseif (!doctor_slot_free($conn, $doctor_id, $new_date, $new_time, $appt_id)) {
            $error_msg = "You already have an appointment at that date and time.";
        } else {
            // The patient must not have another appointment at that time either
            $stmt = $conn->prepare("SELECT id FROM appointments WHERE patient_id = ? AND appointment_date = ? AND appointment_time = ? AND status NOT LIKE 'Cancelled%' AND id <> ?");
            $stmt->bind_param("issi", $appt['patient_id'], $new_date, $new_time, $appt_id);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows > 0) {
                $error_msg = "The patient already has another appointment at that time.";
            } else {
                $stmt = $conn->prepare("UPDATE appointments SET appointment_date = ?, appointment_time = ?, status = 'Rescheduled' WHERE id = ? AND doctor_id = ?");
                $stmt->bind_param("ssii", $new_date, $new_time, $appt_id, $doctor_id);
                try {
                    $ok = $stmt->execute();
                } catch (mysqli_sql_exception $e) {
                    $ok = false;
                }
                if ($ok) {
                    header("Location: doctor_home.php?page=schedule&msg=rescheduled");
                    exit;
                }
                $error_msg = "Could not reschedule. That slot may have just been taken.";
            }
        }
    }
}

// ---------- Save a treatment record ----------
if (isset($_POST['save_treatment'])) {
    $patient_id = (int) $_POST['patient_id'];
    $diagnosis  = trim($_POST['diagnosis']);
    $notes      = trim($_POST['notes']);

    // The patient must have an appointment with this doctor
    $stmt = $conn->prepare("SELECT id FROM appointments WHERE doctor_id = ? AND patient_id = ? LIMIT 1");
    $stmt->bind_param("ii", $doctor_id, $patient_id);
    $stmt->execute();
    $stmt->store_result();

    if ($patient_id == 0 || $diagnosis == "" || $notes == "") {
        $error_msg = "Please choose a patient and fill in the diagnosis and notes.";
        $_GET['page'] = 'patients';
    } elseif ($stmt->num_rows == 0) {
        $error_msg = "That patient has no appointment with you.";
        $_GET['page'] = 'patients';
    } else {
        $stmt = $conn->prepare("INSERT INTO treatments (patient_id, doctor_id, treatment_date, diagnosis, notes) VALUES (?, ?, CURDATE(), ?, ?)");
        $stmt->bind_param("iiss", $patient_id, $doctor_id, $diagnosis, $notes);
        $stmt->execute();
        header("Location: doctor_home.php?page=patients&msg=treatment");
        exit;
    }
}

$page = isset($_GET['page']) ? $_GET['page'] : 'schedule';
$pending_count = count_doctor_pending($conn, $doctor_id);

$messages = [
    'accepted'    => 'Appointment accepted.',
    'rescheduled' => 'Appointment rescheduled. The patient will see the new date and time.',
    'unavailable' => 'Appointment marked as not available. The slot is free again.',
    'done'        => 'Appointment marked as done. You can now add feedback for the patient.',
    'feedback'    => 'Feedback saved. The patient can now see it.',
    'treatment'   => 'Treatment record saved.'
];
$success_msg = (isset($_GET['msg']) && isset($messages[$_GET['msg']])) ? $messages[$_GET['msg']] : "";

// ---------- Schedule data ----------
$filter_date = isset($_GET['filter_date']) ? $_GET['filter_date'] : '';
$fd = DateTime::createFromFormat('Y-m-d', $filter_date);
if (!$fd || $fd->format('Y-m-d') !== $filter_date) { $filter_date = ''; }
$show_all = isset($_GET['view']) && $_GET['view'] == 'all';

$sql = "SELECT a.*, u.full_name AS patient_name FROM appointments a JOIN users u ON a.patient_id = u.id WHERE a.doctor_id = ?";
if ($filter_date != '') {
    $sql .= " AND a.appointment_date = ? ORDER BY a.appointment_time ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $doctor_id, $filter_date);
} elseif ($show_all) {
    $sql .= " ORDER BY a.appointment_date DESC, a.appointment_time ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $doctor_id);
} else {
    $sql .= " AND a.appointment_date >= ? ORDER BY a.appointment_date ASC, a.appointment_time ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $doctor_id, $today);
}
$stmt->execute();
$appointments = $stmt->get_result();

// ---------- Patients who have booked with this doctor ----------
$stmt = $conn->prepare("SELECT DISTINCT u.id, u.full_name FROM appointments a JOIN users u ON a.patient_id = u.id WHERE a.doctor_id = ? ORDER BY u.full_name ASC");
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$my_patients = $stmt->get_result();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Doctor Dashboard - BookMyMed</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>

<div class="navbar">
    <a href="doctor_home.php" class="logo">+BookMyMed</a>
    <div>
        <a href="doctor_home.php?page=schedule" class="<?php echo ($page=='schedule')?'active':''; ?>">My Schedule<?php if ($pending_count > 0): ?><span style="display:inline-block; min-width:20px; padding:1px 7px; border-radius:999px; background:#0b7f72; color:#fff; font-size:11px; font-weight:700; text-align:center; margin-left:4px;"><?php echo $pending_count; ?></span><?php endif; ?></a>
        <a href="doctor_home.php?page=patients" class="<?php echo ($page=='patients')?'active':''; ?>">Patients</a>
        <span style="margin-left: 20px; font-size: 14px;"><?php echo htmlspecialchars($_SESSION['full_name']); ?> (Doctor)</span>
        <a href="logout.php" style="margin-left: 10px; font-weight: bold;">Logout</a>
    </div>
</div>

<div class="admin-container">

    <?php if ($success_msg != ""): ?>
        <div class="alert alert-success" style="margin-top:20px;"><?php echo htmlspecialchars($success_msg); ?></div>
    <?php endif; ?>
    <?php if ($error_msg != ""): ?>
        <div class="alert alert-error" style="margin-top:20px;"><?php echo htmlspecialchars($error_msg); ?></div>
    <?php endif; ?>

    <?php if ($page == 'schedule'): ?>
        <h2 style="margin-bottom:20px;">My appointments</h2>

        <form method="GET" action="doctor_home.php" class="inline-form">
            <input type="hidden" name="page" value="schedule">
            <div>
                <label style="font-size:12px; font-weight:bold; display:block; margin-bottom:4px;">Filter by date</label>
                <input type="date" name="filter_date" value="<?php echo htmlspecialchars($filter_date); ?>" style="max-width:220px;">
            </div>
            <button type="submit" style="background:#3182ce; color:white; border:none; padding:10px 30px; cursor:pointer;">Show</button>
            <a href="doctor_home.php?page=schedule" class="btn-small btn-blue" style="padding:11px 20px;">Upcoming</a>
            <a href="doctor_home.php?page=schedule&view=all" class="btn-small btn-blue" style="padding:11px 20px;">All appointments</a>
        </form>

        <p style="font-size:13px; color:#718096;">
            <?php
            if ($filter_date != '') { echo "Showing " . date('l, d M Y', strtotime($filter_date)); }
            elseif ($show_all) { echo "Showing all appointments"; }
            else { echo "Showing upcoming appointments (today onwards)"; }
            ?>
        </p>

        <table class="data-table">
            <tr>
                <th>Date</th>
                <th>Time</th>
                <th>Patient</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
            <?php if ($appointments->num_rows == 0): ?>
                <tr><td colspan="5">No appointments found.</td></tr>
            <?php else: ?>
                <?php while($row = $appointments->fetch_assoc()):
                    $is_cancelled = strpos($row['status'], 'Cancelled') === 0;
                    $is_done = ($row['status'] == 'Done');
                    $started = strtotime($row['appointment_date'] . ' ' . $row['appointment_time']) <= time();
                ?>
                <tr>
                    <td><?php echo date('D, d M Y', strtotime($row['appointment_date'])); ?></td>
                    <td><?php echo date('H:i', strtotime($row['appointment_time'])); ?></td>
                    <td><?php echo htmlspecialchars($row['patient_name']); ?></td>
                    <td>
                        <span style="font-weight:bold; color: <?php echo $is_cancelled ? '#c53030' : '#38a169'; ?>;">
                            <?php echo htmlspecialchars($row['status']); ?>
                        </span>
                        <?php if ($is_done): ?><br><small><?php echo !empty($row['doctor_feedback']) ? 'Feedback sent' : 'No feedback yet'; ?></small><?php endif; ?>
                    </td>
                    <td>
                        <?php if ($is_cancelled): ?>
                            -
                        <?php elseif ($is_done): ?>
                            <details class="reschedule">
                                <summary class="btn-small btn-green"><?php echo !empty($row['doctor_feedback']) ? 'Edit feedback' : 'Add feedback'; ?></summary>
                                <form method="POST" action="doctor_home.php?page=schedule">
                                    <input type="hidden" name="appt_id" value="<?php echo $row['id']; ?>">
                                    <textarea name="feedback_text" rows="4" maxlength="1000" required placeholder="Feedback for the patient" style="width:230px; padding:8px 12px; border:1.5px solid #cfe5e0; border-radius:12px; font-family:inherit; font-size:13px;"><?php echo isset($row['doctor_feedback']) ? htmlspecialchars($row['doctor_feedback']) : ''; ?></textarea>
                                    <button type="submit" name="appointment_action" value="feedback" class="btn-small btn-green">Save feedback</button>
                                </form>
                            </details>
                        <?php elseif ($started): ?>
                            <?php if ($row['status'] == 'Booked' || $row['status'] == 'Confirmed'): ?>
                            <form method="POST" action="doctor_home.php?page=schedule" style="display:inline;">
                                <input type="hidden" name="appt_id" value="<?php echo $row['id']; ?>">
                                <button type="submit" name="appointment_action" value="done" class="btn-small btn-green" onclick="return confirm('Confirm that you have seen this patient and mark the appointment as Done?');">Mark as Done</button>
                            </form>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        <?php else: ?>
                            <?php if ($row['status'] == 'Booked'): ?>
                            <form method="POST" action="doctor_home.php?page=schedule" style="display:inline;">
                                <input type="hidden" name="appt_id" value="<?php echo $row['id']; ?>">
                                <button type="submit" name="appointment_action" value="accept" class="btn-small btn-green">Accept</button>
                            </form>
                            <?php endif; ?>

                            <details class="reschedule">
                                <summary class="btn-small btn-blue">Reschedule</summary>
                                <form method="POST" action="doctor_home.php?page=schedule">
                                    <input type="hidden" name="appt_id" value="<?php echo $row['id']; ?>">
                                    <input type="date" name="new_date" min="<?php echo $today; ?>" value="<?php echo $row['appointment_date']; ?>" required>
                                    <select name="new_time" required>
                                        <?php foreach ($times as $t): ?>
                                            <option value="<?php echo $t; ?>" <?php echo ($t == substr($row['appointment_time'], 0, 5)) ? 'selected' : ''; ?>><?php echo $t; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" name="appointment_action" value="reschedule" class="btn-small btn-green">Save new time</button>
                                </form>
                            </details>

                            <form method="POST" action="doctor_home.php?page=schedule" style="display:inline;">
                                <input type="hidden" name="appt_id" value="<?php echo $row['id']; ?>">
                                <button type="submit" name="appointment_action" value="unavailable" class="btn-small btn-red" onclick="return confirm('Mark this appointment as not available? The patient will see it as cancelled.');">Not available</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php endif; ?>
        </table>
    <?php endif; ?>

    <?php if ($page == 'patients'): ?>
        <h2 style="margin-bottom:20px;">Patients &amp; Add Treatment</h2>

        <div style="display:flex; gap:20px; flex-wrap:wrap;">
            <div style="flex:1; min-width:300px; background:white; border:1px solid #cbd5e0; padding:24px;">
                <h3>Add Treatment Record</h3>
                <form method="POST" action="doctor_home.php?page=patients" style="margin-top:15px;">
                    <label style="font-size:13px; font-weight:bold; display:block; margin-bottom:6px;">Select Patient</label>
                    <select name="patient_id" required style="width:100%; padding:10px; margin-bottom:15px; border:1px solid #cbd5e0;">
                        <option value="">-- Choose Patient --</option>
                        <?php while($p = $my_patients->fetch_assoc()): ?>
                            <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['full_name']); ?></option>
                        <?php endwhile; ?>
                    </select>

                    <label style="font-size:13px; font-weight:bold; display:block; margin-bottom:6px;">Diagnosis</label>
                    <input type="text" name="diagnosis" placeholder="e.g. Mild hypertension" required style="width:100%; padding:10px; margin-bottom:15px; border:1px solid #cbd5e0;">

                    <label style="font-size:13px; font-weight:bold; display:block; margin-bottom:6px;">Treatment / Notes</label>
                    <textarea name="notes" rows="5" placeholder="e.g. Lifestyle changes, recheck in 3 months" required style="width:100%; padding:10px; margin-bottom:15px; border:1px solid #cbd5e0; font-family:inherit;"></textarea>

                    <button type="submit" name="save_treatment" style="background:#3182ce; color:white; border:none; padding:12px 30px; cursor:pointer; font-weight:bold; width:100%;">Save Treatment Record</button>
                </form>
            </div>

            <div style="flex:1; min-width:300px; background:white; border:1px solid #cbd5e0; padding:24px;">
                <h3>My Patients</h3>
                <p style="font-size:14px; color:#718096; margin-top:8px;">Patients who have booked appointments with you will appear in the dropdown menu.</p>
                <table class="data-table">
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                    </tr>
                    <?php
                    $my_patients->data_seek(0);
                    if ($my_patients->num_rows == 0): ?>
                        <tr><td colspan="2">No patients yet.</td></tr>
                    <?php else: ?>
                        <?php while($p = $my_patients->fetch_assoc()): ?>
                        <tr>
                            <td>P-<?php echo str_pad($p['id'], 4, '0', STR_PAD_LEFT); ?></td>
                            <td><?php echo htmlspecialchars($p['full_name']); ?></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    <?php endif; ?>

</div>
</body>
</html>

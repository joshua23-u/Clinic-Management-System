<?php
session_start();
include 'db.php';
include_once 'appointment_helpers.php';

// Use local time for "today" and "passed" slots (change if your timezone differs)
date_default_timezone_set('Africa/Nairobi');

// Patients may cancel or reschedule only if the appointment is at least this many hours away
$change_limit_hours = 24;

function can_change_appointment($date, $time, $limit_hours) {
    return (strtotime($date . ' ' . $time) - time()) >= $limit_hours * 3600;
}

function get_my_appointment($conn, $appt_id, $patient_id) {
    $stmt = $conn->prepare("SELECT id, doctor_id, patient_id, appointment_date, appointment_time, status FROM appointments WHERE id = ? AND patient_id = ?");
    $stmt->bind_param("ii", $appt_id, $patient_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// Prevent the browser from showing this page from cache after logout
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'patient') {
    header("Location: login.php");
    exit;
}

$patient_id = (int) $_SESSION['user_id'];
refresh_appointment_statuses($conn);

// Ratings need an appointment_id column in the feedback table (see the SQL)
$feedback_ready = $conn->query("SHOW COLUMNS FROM feedback LIKE 'appointment_id'")->num_rows > 0;
$message = "";
$error_msg = "";

if (isset($_POST['update_profile'])) {
    $new_name = trim($_POST['full_name']);
    $new_phone = trim($_POST['phone']);
    
    $stmt = $conn->prepare("UPDATE users SET full_name=?, phone=? WHERE id=?");
    $stmt->bind_param("ssi", $new_name, $new_phone, $patient_id);
    if ($stmt->execute()) {
        $_SESSION['full_name'] = $new_name;
        $message = "Profile updated successfully!";
    } else {
        $error_msg = "Failed to update profile.";
    }
}

if (isset($_POST['change_password'])) {
    $current = $_POST['current_password'];
    $new = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];

    $res = $conn->query("SELECT password FROM users WHERE id=$patient_id")->fetch_assoc();
    
    if (!password_verify($current, $res['password'])) {
        $error_msg = "Current password is incorrect.";
    } elseif ($new !== $confirm) {
        $error_msg = "New passwords do not match.";
    } elseif (strlen($new) < 8) {
        $error_msg = "Password must be at least 8 characters.";
    } elseif (!preg_match('/[A-Z]/', $new)) {
        $error_msg = "Password must contain at least one uppercase letter.";
    } elseif (!preg_match('/[0-9]/', $new)) {
        $error_msg = "Password must contain at least one number.";
    } elseif (!preg_match('/[^a-zA-Z0-9]/', $new)) {
        $error_msg = "Password must contain at least one special character.";
    } else {
        $hashed = password_hash($new, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password=? WHERE id=?");
        $stmt->bind_param("si", $hashed, $patient_id);
        $stmt->execute();
        $message = "Password updated successfully!";
    }
}

if (isset($_POST['book_appointment'])) {
    $doc_id = (int) $_POST['doctor_id'];
    $date   = isset($_POST['appointment_date']) ? $_POST['appointment_date'] : '';
    $time   = isset($_POST['appointment_time']) ? $_POST['appointment_time'] : '';

    // Validate the date and time on the server
    $d = DateTime::createFromFormat('Y-m-d', $date);
    $valid_date = $d && $d->format('Y-m-d') === $date;
    $valid_time = preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time);

    if (!$valid_date || !$valid_time || $date < date('Y-m-d') || ($date == date('Y-m-d') && $time <= date('H:i'))) {
        $error_msg = "Please choose a valid date and time.";
    } else {
        // 1. Is the slot already taken? (cancelled bookings do not count)
        $stmt = $conn->prepare("SELECT id FROM appointments WHERE doctor_id = ? AND appointment_date = ? AND appointment_time = ? AND status NOT LIKE 'Cancelled%'");
        $stmt->bind_param("iss", $doc_id, $date, $time);
        $stmt->execute();
        $stmt->store_result();
        $slot_taken = $stmt->num_rows > 0;

        // 2. Does this patient already have another appointment at the same time?
        $stmt = $conn->prepare("SELECT id FROM appointments WHERE patient_id = ? AND appointment_date = ? AND appointment_time = ? AND status NOT LIKE 'Cancelled%'");
        $stmt->bind_param("iss", $patient_id, $date, $time);
        $stmt->execute();
        $stmt->store_result();
        $patient_busy = $stmt->num_rows > 0;

        if ($slot_taken) {
            $error_msg = "Slot no longer available, please choose another.";
        } elseif ($patient_busy) {
            $error_msg = "You already have an appointment at that time. Please choose a different time.";
        } else {
            $stmt = $conn->prepare("INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, status) VALUES (?, ?, ?, ?, 'Booked')");
            $stmt->bind_param("iiss", $patient_id, $doc_id, $date, $time);
            try {
                $ok = $stmt->execute();
            } catch (mysqli_sql_exception $e) {
                $ok = false; // e.g. the database unique rule blocked a simultaneous booking
            }
            if ($ok) {
                header("Location: patient_home.php?page=bookings&booked=1");
                exit;
            }
            $error_msg = "Sorry, that slot was just booked by someone else. Please choose another.";
        }
    }
}

// ---------- Rate a finished appointment (once per appointment) ----------
if (isset($_POST['submit_rating'])) {
    $appt_id  = (int) $_POST['appointment_id'];
    $rating   = (int) $_POST['rating'];
    $comments = isset($_POST['comments']) ? substr(trim($_POST['comments']), 0, 500) : '';
    $appt = get_my_appointment($conn, $appt_id, $patient_id);

    if (!$feedback_ready) {
        $error_msg = "Ratings are not set up yet. Please run the SQL that adds appointment_id to the feedback table.";
    } elseif (!$appt || $appt['status'] != 'Done') {
        $error_msg = "You can rate an appointment only after the doctor marks it as done.";
    } elseif ($rating < 1 || $rating > 5) {
        $error_msg = "Please choose a rating from 1 to 5.";
    } else {
        $stmt = $conn->prepare("SELECT id FROM feedback WHERE appointment_id = ?");
        $stmt->bind_param("i", $appt_id);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $error_msg = "You have already rated this appointment.";
        } else {
            $stmt = $conn->prepare("INSERT INTO feedback (appointment_id, patient_id, doctor_id, rating, comments, feedback_date) VALUES (?, ?, ?, ?, ?, CURDATE())");
            $stmt->bind_param("iiiis", $appt_id, $patient_id, $appt['doctor_id'], $rating, $comments);
            try {
                $ok = $stmt->execute();
            } catch (mysqli_sql_exception $e) {
                $ok = false;
            }
            if ($ok) {
                header("Location: patient_home.php?page=bookings&msg=rated");
                exit;
            }
            $error_msg = "Your rating could not be saved. Please try again.";
        }
    }
}

// ---------- Cancel (not allowed within the time limit) ----------
if (isset($_POST['cancel_appointment'])) {
    $appt_id = (int) $_POST['appointment_id'];
    $appt = get_my_appointment($conn, $appt_id, $patient_id);

    if (!$appt || strpos($appt['status'], 'Cancelled') === 0) {
        $error_msg = "Appointment not found or already cancelled.";
    } elseif ($appt['status'] != 'Rescheduled' && !can_change_appointment($appt['appointment_date'], $appt['appointment_time'], $change_limit_hours)) {
        $error_msg = "Appointments can only be cancelled at least $change_limit_hours hours before the start time.";
    } else {
        $stmt = $conn->prepare("UPDATE appointments SET status='Cancelled by patient' WHERE id=? AND patient_id=?");
        $stmt->bind_param("ii", $appt_id, $patient_id);
        $stmt->execute();
        header("Location: patient_home.php?page=bookings&cancelled=1");
        exit;
    }
}

// ---------- Accept or decline a new time proposed by the doctor ----------
if (isset($_POST['respond_reschedule'])) {
    $appt_id  = (int) $_POST['appointment_id'];
    $response = $_POST['respond_reschedule'];
    $appt = get_my_appointment($conn, $appt_id, $patient_id);

    if (!$appt || $appt['status'] != 'Rescheduled') {
        $error_msg = "There is no rescheduled appointment to respond to.";
    } elseif ($response == 'accept') {
        $stmt = $conn->prepare("UPDATE appointments SET status='Confirmed' WHERE id=? AND patient_id=?");
        $stmt->bind_param("ii", $appt_id, $patient_id);
        $stmt->execute();
        header("Location: patient_home.php?page=bookings&msg=accepted");
        exit;
    } elseif ($response == 'decline') {
        // Starts with "Cancelled" so the slot is free again
        $stmt = $conn->prepare("UPDATE appointments SET status='Cancelled by patient' WHERE id=? AND patient_id=?");
        $stmt->bind_param("ii", $appt_id, $patient_id);
        $stmt->execute();
        header("Location: patient_home.php?page=bookings&msg=declined");
        exit;
    }
}

// ---------- Patient picks a different date and time ----------
if (isset($_POST['reschedule_appointment'])) {
    $appt_id  = (int) $_POST['appt_id'];
    $new_date = isset($_POST['appointment_date']) ? $_POST['appointment_date'] : '';
    $new_time = isset($_POST['appointment_time']) ? $_POST['appointment_time'] : '';
    $appt = get_my_appointment($conn, $appt_id, $patient_id);

    $d = DateTime::createFromFormat('Y-m-d', $new_date);
    $valid_date = $d && $d->format('Y-m-d') === $new_date;
    $valid_time = preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $new_time);

    if (!$appt || strpos($appt['status'], 'Cancelled') === 0) {
        $error_msg = "Appointment not found or already cancelled.";
    } elseif ($appt['status'] != 'Rescheduled' && !can_change_appointment($appt['appointment_date'], $appt['appointment_time'], $change_limit_hours)) {
        $error_msg = "Appointments can only be rescheduled at least $change_limit_hours hours before the start time.";
    } elseif (!$valid_date || !$valid_time || $new_date < date('Y-m-d') || ($new_date == date('Y-m-d') && $new_time <= date('H:i'))) {
        $error_msg = "Please choose a valid new date and time.";
    } elseif ($new_date == $appt['appointment_date'] && $new_time == substr($appt['appointment_time'], 0, 5)) {
        $error_msg = "That is already the current time. Please choose a different one.";
    } else {
        // Is the doctor free at the new time?
        $stmt = $conn->prepare("SELECT id FROM appointments WHERE doctor_id = ? AND appointment_date = ? AND appointment_time = ? AND status NOT LIKE 'Cancelled%' AND id <> ?");
        $stmt->bind_param("issi", $appt['doctor_id'], $new_date, $new_time, $appt_id);
        $stmt->execute();
        $stmt->store_result();
        $slot_taken = $stmt->num_rows > 0;

        // Is the patient free at the new time?
        $stmt = $conn->prepare("SELECT id FROM appointments WHERE patient_id = ? AND appointment_date = ? AND appointment_time = ? AND status NOT LIKE 'Cancelled%' AND id <> ?");
        $stmt->bind_param("issi", $patient_id, $new_date, $new_time, $appt_id);
        $stmt->execute();
        $stmt->store_result();
        $patient_busy = $stmt->num_rows > 0;

        if ($slot_taken) {
            $error_msg = "That slot is no longer available, please choose another.";
        } elseif ($patient_busy) {
            $error_msg = "You already have an appointment at that time. Please choose a different time.";
        } else {
            // Back to "Booked": the doctor needs to accept the new time
            $stmt = $conn->prepare("UPDATE appointments SET appointment_date = ?, appointment_time = ?, status = 'Booked' WHERE id = ? AND patient_id = ?");
            $stmt->bind_param("ssii", $new_date, $new_time, $appt_id, $patient_id);
            try {
                $ok = $stmt->execute();
            } catch (mysqli_sql_exception $e) {
                $ok = false;
            }
            if ($ok) {
                header("Location: patient_home.php?page=bookings&msg=rescheduled");
                exit;
            }
            $error_msg = "Sorry, that slot was just taken. Please choose another.";
        }
    }
}

$page = isset($_GET['page']) ? $_GET['page'] : 'find';

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
if ($search != '') {
    $like = "%$search%";
    $stmt = $conn->prepare("SELECT * FROM users WHERE role='doctor' AND (full_name LIKE ? OR specialty LIKE ? OR locality LIKE ?)");
    $stmt->bind_param("sss", $like, $like, $like);
} else {
    $stmt = $conn->prepare("SELECT * FROM users WHERE role='doctor'");
}
$stmt->execute();
$doctors = $stmt->get_result();

$rating_cols = $feedback_ready ? ", f.rating AS my_rating" : ", NULL AS my_rating";
$rating_join = $feedback_ready ? " LEFT JOIN feedback f ON f.appointment_id = a.id" : "";
$stmt = $conn->prepare("
    SELECT a.*, d.full_name as doctor_name, d.specialty $rating_cols
    FROM appointments a
    JOIN users d ON a.doctor_id = d.id $rating_join
    WHERE a.patient_id = ?
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$bookings = $stmt->get_result();

$proposal_count = count_patient_proposals($conn, $patient_id);

$my_info = $conn->query("SELECT * FROM users WHERE id=$patient_id")->fetch_assoc();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Patient Dashboard - BookMyMed</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>

<div class="navbar">
    <a href="patient_home.php" class="logo">+BookMyMed</a>
    <div>
        <a href="patient_home.php?page=find" class="<?php echo ($page=='find')?'active':''; ?>">Find a Doctor</a>
        <a href="patient_home.php?page=bookings" class="<?php echo ($page=='bookings')?'active':''; ?>">My Bookings<?php if ($proposal_count > 0): ?><span style="display:inline-block; min-width:20px; padding:1px 7px; border-radius:999px; background:#0b7f72; color:#fff; font-size:11px; font-weight:700; text-align:center; margin-left:4px;"><?php echo $proposal_count; ?></span><?php endif; ?></a>
        <a href="patient_home.php?page=records" class="<?php echo ($page=='records')?'active':''; ?>">My Records</a>
        <a href="patient_home.php?page=profile" class="<?php echo ($page=='profile')?'active':''; ?>">My Profile</a>
        <span style="margin-left: 20px; font-size: 14px;"><?php echo htmlspecialchars($_SESSION['full_name']); ?> (Patient)</span>
        <a href="logout.php" style="margin-left: 10px; font-weight: bold;">Logout</a>
    </div>
</div>

<div class="admin-container">
    
    <?php if (isset($_GET['booked'])): ?>
        <div class="alert alert-success" style="margin-top:20px;">Appointment booked successfully!</div>
    <?php endif; ?>
    <?php if (isset($_GET['cancelled'])): ?>
        <div class="alert alert-error" style="margin-top:20px;">Appointment cancelled.</div>
    <?php endif; ?>
    <?php
    $flash_map = [
        'accepted'    => 'You accepted the new time. Your appointment is confirmed.',
        'declined'    => 'You declined the new time and the appointment was cancelled.',
        'rescheduled' => 'Your new time was sent to the doctor for acceptance.',
        'rated'       => 'Thank you! Your rating was saved.'
    ];
    if (isset($_GET['msg']) && isset($flash_map[$_GET['msg']])): ?>
        <div class="alert alert-success" style="margin-top:20px;"><?php echo $flash_map[$_GET['msg']]; ?></div>
    <?php endif; ?>
    <?php if ($message != ""): ?>
        <div class="alert alert-success" style="margin-top:20px;"><?php echo $message; ?></div>
    <?php endif; ?>
    <?php if ($error_msg != ""): ?>
        <div class="alert alert-error" style="margin-top:20px;"><?php echo $error_msg; ?></div>
    <?php endif; ?>

    <?php if ($page == 'find'): ?>
        <h2 style="margin-bottom:20px;">Find a doctor</h2>
        
        <form method="GET" action="patient_home.php" class="inline-form" style="background:#f7fafc; padding:15px; border:1px solid #e2e8f0;">
            <input type="hidden" name="page" value="find">
            <div style="flex:1;">
                <label style="font-size:12px; font-weight:bold;">Search</label>
                <input type="text" name="search" placeholder="Search by name, specialty, or locality" value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <button type="submit" style="background:#3182ce; color:white; border:none; padding:10px 30px; cursor:pointer;">Search</button>
        </form>

        <p style="font-size:13px; color:#718096; margin-top:20px;"><?php echo $doctors->num_rows; ?> doctors found</p>

        <?php while($doc = $doctors->fetch_assoc()): ?>
            <div class="doctor-card">
                <div class="doctor-info">
                    <div class="doctor-avatar">photo</div>
                    <div class="doctor-details">
                        <h3>Dr. <?php echo htmlspecialchars($doc['full_name']); ?></h3>
                        <p><?php echo htmlspecialchars($doc['specialty']); ?> | <?php echo htmlspecialchars($doc['locality']); ?></p>
                    </div>
                </div>
                <div style="text-align:right;">
                    <p class="doctor-avail">Available Today</p>
                    <a href="patient_home.php?page=book&doctor_id=<?php echo $doc['id']; ?>" class="btn-small btn-blue" style="padding:10px 20px; margin-top:5px; display:inline-block; border-radius:2px;">View Slots</a>
                </div>
            </div>
        <?php endwhile; ?>
    <?php endif; ?>

    <?php if ($page == 'book'):
        $doc_id = isset($_GET['doctor_id']) ? (int) $_GET['doctor_id'] : 0;

        $stmt = $conn->prepare("SELECT * FROM users WHERE id = ? AND role = 'doctor'");
        $stmt->bind_param("i", $doc_id);
        $stmt->execute();
        $doc_info = $stmt->get_result()->fetch_assoc();

        $today    = date('Y-m-d');
        $max_date = date('Y-m-d', strtotime('+60 days'));

        // The day the patient picked (defaults to tomorrow)
        $selected_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d', strtotime('+1 day'));
        $d = DateTime::createFromFormat('Y-m-d', $selected_date);
        if (!$d || $d->format('Y-m-d') !== $selected_date || $selected_date < $today || $selected_date > $max_date) {
            $selected_date = date('Y-m-d', strtotime('+1 day'));
        }

        $times = ['08:00', '08:30', '09:00', '09:30', '10:00', '10:30', '11:00', '11:30', '12:00', '12:30', '13:00', '13:30', '14:00', '14:30', '15:00', '15:30'];

        // Times already taken for this doctor on that day (cancelled bookings free the slot again)
        $booked_times = [];
        $my_times = [];
        if ($doc_info) {
            $stmt = $conn->prepare("SELECT appointment_time, patient_id FROM appointments WHERE doctor_id = ? AND appointment_date = ? AND status NOT LIKE 'Cancelled%'");
            $stmt->bind_param("is", $doc_id, $selected_date);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($r = $res->fetch_assoc()) {
                $t = substr($r['appointment_time'], 0, 5);
                $booked_times[] = $t;
                if ($r['patient_id'] == $patient_id) { $my_times[] = $t; }
            }
        }

        // Count the slots still open
        $available = 0;
        foreach ($times as $t) {
            if (!in_array($t, $booked_times) && !($selected_date == $today && $t <= date('H:i'))) { $available++; }
        }
    ?>
        <a href="patient_home.php?page=find" style="color:#3182ce; text-decoration:none; font-size:14px;">&larr; Back to results</a>

    <?php if (!$doc_info): ?>
        <p style="margin-top:20px;">Doctor not found.</p>
    <?php else: ?>

        <div class="doctor-card" style="margin-top:20px;">
            <div class="doctor-info">
                <div class="doctor-avatar">photo</div>
                <div class="doctor-details">
                    <h3>Dr. <?php echo htmlspecialchars($doc_info['full_name']); ?></h3>
                    <p><?php echo htmlspecialchars($doc_info['specialty']); ?> | <?php echo htmlspecialchars($doc_info['locality']); ?></p>
                </div>
            </div>
        </div>

        <h3 style="margin-top:30px; margin-bottom:10px;">1. Choose a date</h3>
        <form method="GET" action="patient_home.php" class="inline-form" style="max-width:320px;">
            <input type="hidden" name="page" value="book">
            <input type="hidden" name="doctor_id" value="<?php echo $doc_id; ?>">
            <input type="date" name="date" value="<?php echo $selected_date; ?>" min="<?php echo $today; ?>" max="<?php echo $max_date; ?>" onchange="this.form.submit()">
            <noscript><button type="submit" style="background:#3182ce; color:white; border:none; padding:10px 20px; cursor:pointer;">Show slots</button></noscript>
        </form>
        <p style="font-size:14px; color:#4a5568; margin-bottom:20px;">
            <?php echo date('l, d M Y', strtotime($selected_date)); ?> &middot;
            <strong><?php echo $available; ?> of <?php echo count($times); ?> slots available</strong>
        </p>

        <h3 style="margin-top:20px; margin-bottom:10px;">2. Choose a time slot</h3>

        <div class="slot-legend">
            <span><i></i> Available</span>
            <span><i class="sel"></i> Selected</span>
            <span><i class="off"></i> Booked or passed</span>
            <span><i class="mine"></i> Your booking</span>
        </div>

        <form method="POST" action="patient_home.php">
            <input type="hidden" name="doctor_id" value="<?php echo $doc_id; ?>">
            <input type="hidden" name="appointment_date" value="<?php echo $selected_date; ?>">

            <div class="slots-grid">
                <?php
                foreach ($times as $t):
                    $is_mine   = in_array($t, $my_times);
                    $is_booked = in_array($t, $booked_times);
                    $is_past   = ($selected_date == $today && $t <= date('H:i'));
                ?>
                    <?php if ($is_mine): ?>
                        <div class="slot booked mine"><?php echo $t; ?> (your booking)</div>
                    <?php elseif ($is_booked): ?>
                        <div class="slot booked"><?php echo $t; ?> (booked)</div>
                    <?php elseif ($is_past): ?>
                        <div class="slot booked"><?php echo $t; ?> (passed)</div>
                    <?php else: ?>
                        <label>
                            <input type="radio" name="appointment_time" value="<?php echo $t; ?>" required style="display:none;" onchange="this.parentElement.parentElement.querySelectorAll('.slot').forEach(el=>el.classList.remove('active')); this.nextElementSibling.classList.add('active');">
                            <div class="slot"><?php echo $t; ?></div>
                        </label>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>

            <div style="background:#f7fafc; padding:20px; border:1px solid #e2e8f0;">
                <h3>Booking summary</h3>
                <p style="font-size:14px; margin-top:10px;">
                    Doctor: Dr. <?php echo htmlspecialchars($doc_info['full_name']); ?><br>
                    Date: <?php echo date('d M Y', strtotime($selected_date)); ?><br>
                    Patient: <?php echo htmlspecialchars($_SESSION['full_name']); ?>
                </p>
                <?php if ($available > 0): ?>
                    <button type="submit" name="book_appointment" style="background:#3182ce; color:white; border:none; padding:12px 30px; cursor:pointer; margin-top:15px; font-weight:bold;">Confirm Booking</button>
                <?php else: ?>
                    <div class="alert alert-error" style="margin-top:15px;">No slots are available on this day. Please pick another date.</div>
                <?php endif; ?>
            </div>
        </form>

    <?php endif; ?>
    <?php endif; ?>

    <?php if ($page == 'reschedule'):
        $appt_id = isset($_GET['appt_id']) ? (int) $_GET['appt_id'] : 0;

        $stmt = $conn->prepare("SELECT a.*, d.full_name AS doctor_name, d.specialty, d.locality FROM appointments a JOIN users d ON a.doctor_id = d.id WHERE a.id = ? AND a.patient_id = ?");
        $stmt->bind_param("ii", $appt_id, $patient_id);
        $stmt->execute();
        $appt = $stmt->get_result()->fetch_assoc();

        $problem = "";
        if (!$appt || strpos($appt['status'], 'Cancelled') === 0) {
            $problem = "Appointment not found or already cancelled.";
        } elseif ($appt['status'] != 'Rescheduled' && !can_change_appointment($appt['appointment_date'], $appt['appointment_time'], $change_limit_hours)) {
            $problem = "Appointments can only be rescheduled at least $change_limit_hours hours before the start time.";
        }
    ?>
        <a href="patient_home.php?page=bookings" style="color:#3182ce; text-decoration:none; font-size:14px;">&larr; Back to my bookings</a>

    <?php if ($problem != ""): ?>
        <p style="margin-top:20px;"><?php echo $problem; ?></p>
    <?php else:
        $doc_id   = (int) $appt['doctor_id'];
        $today    = date('Y-m-d');
        $max_date = date('Y-m-d', strtotime('+60 days'));

        $selected_date = isset($_GET['date']) ? $_GET['date'] : ($appt['appointment_date'] >= $today ? $appt['appointment_date'] : date('Y-m-d', strtotime('+1 day')));
        $d = DateTime::createFromFormat('Y-m-d', $selected_date);
        if (!$d || $d->format('Y-m-d') !== $selected_date || $selected_date < $today || $selected_date > $max_date) {
            $selected_date = date('Y-m-d', strtotime('+1 day'));
        }

        $times = ['08:00', '08:30', '09:00', '09:30', '10:00', '10:30', '11:00', '11:30', '12:00', '12:30', '13:00', '13:30', '14:00', '14:30', '15:00', '15:30'];

        $booked_times = [];
        $my_times = [];
        $current_time_here = "";
        $stmt = $conn->prepare("SELECT id, appointment_time, patient_id FROM appointments WHERE doctor_id = ? AND appointment_date = ? AND status NOT LIKE 'Cancelled%'");
        $stmt->bind_param("is", $doc_id, $selected_date);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $t = substr($r['appointment_time'], 0, 5);
            if ($r['id'] == $appt_id) { $current_time_here = $t; }
            $booked_times[] = $t;
            if ($r['patient_id'] == $patient_id) { $my_times[] = $t; }
        }

        $available = 0;
        foreach ($times as $t) {
            if (!in_array($t, $booked_times) && !($selected_date == $today && $t <= date('H:i'))) { $available++; }
        }
    ?>
        <h2 style="margin-top:20px;">Choose a new date and time</h2>

        <div class="doctor-card" style="margin-top:20px;">
            <div class="doctor-info">
                <div class="doctor-avatar">photo</div>
                <div class="doctor-details">
                    <h3>Dr. <?php echo htmlspecialchars($appt['doctor_name']); ?></h3>
                    <p><?php echo htmlspecialchars($appt['specialty']); ?> | <?php echo htmlspecialchars($appt['locality']); ?></p>
                    <p>Current appointment: <?php echo date('D, d M Y', strtotime($appt['appointment_date'])); ?> at <?php echo date('H:i', strtotime($appt['appointment_time'])); ?>
                    <?php if ($appt['status'] == 'Rescheduled'): ?> (new time proposed by the doctor)<?php endif; ?></p>
                </div>
            </div>
        </div>

        <h3 style="margin-top:30px; margin-bottom:10px;">1. Choose a date</h3>
        <form method="GET" action="patient_home.php" class="inline-form" style="max-width:320px;">
            <input type="hidden" name="page" value="reschedule">
            <input type="hidden" name="appt_id" value="<?php echo $appt_id; ?>">
            <input type="date" name="date" value="<?php echo $selected_date; ?>" min="<?php echo $today; ?>" max="<?php echo $max_date; ?>" onchange="this.form.submit()">
            <noscript><button type="submit" style="background:#3182ce; color:white; border:none; padding:10px 20px; cursor:pointer;">Show slots</button></noscript>
        </form>
        <p style="font-size:14px; color:#4a5568; margin-bottom:20px;">
            <?php echo date('l, d M Y', strtotime($selected_date)); ?> &middot;
            <strong><?php echo $available; ?> of <?php echo count($times); ?> slots available</strong>
        </p>

        <h3 style="margin-top:20px; margin-bottom:10px;">2. Choose a time slot</h3>
        <div class="slot-legend">
            <span><i></i> Available</span>
            <span><i class="sel"></i> Selected</span>
            <span><i class="off"></i> Booked or passed</span>
            <span><i class="mine"></i> Your booking</span>
        </div>

        <form method="POST" action="patient_home.php?page=reschedule&appt_id=<?php echo $appt_id; ?>&date=<?php echo $selected_date; ?>">
            <input type="hidden" name="appt_id" value="<?php echo $appt_id; ?>">
            <input type="hidden" name="appointment_date" value="<?php echo $selected_date; ?>">

            <div class="slots-grid">
                <?php foreach ($times as $t):
                    $is_current = ($t == $current_time_here);
                    $is_mine    = in_array($t, $my_times);
                    $is_booked  = in_array($t, $booked_times);
                    $is_past    = ($selected_date == $today && $t <= date('H:i'));
                ?>
                    <?php if ($is_current): ?>
                        <div class="slot booked mine"><?php echo $t; ?> (current time)</div>
                    <?php elseif ($is_mine): ?>
                        <div class="slot booked mine"><?php echo $t; ?> (your booking)</div>
                    <?php elseif ($is_booked): ?>
                        <div class="slot booked"><?php echo $t; ?> (booked)</div>
                    <?php elseif ($is_past): ?>
                        <div class="slot booked"><?php echo $t; ?> (passed)</div>
                    <?php else: ?>
                        <label>
                            <input type="radio" name="appointment_time" value="<?php echo $t; ?>" required style="display:none;" onchange="this.parentElement.parentElement.querySelectorAll('.slot').forEach(el=>el.classList.remove('active')); this.nextElementSibling.classList.add('active');">
                            <div class="slot"><?php echo $t; ?></div>
                        </label>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>

            <div style="background:#f7fafc; padding:20px; border:1px solid #e2e8f0;">
                <h3>Reschedule summary</h3>
                <p style="font-size:14px; margin-top:10px;">
                    Doctor: Dr. <?php echo htmlspecialchars($appt['doctor_name']); ?><br>
                    New date: <?php echo date('d M Y', strtotime($selected_date)); ?><br>
                    The doctor will need to accept the new time.
                </p>
                <?php if ($available > 0): ?>
                    <button type="submit" name="reschedule_appointment" style="background:#3182ce; color:white; border:none; padding:12px 30px; cursor:pointer; margin-top:15px; font-weight:bold;">Confirm new time</button>
                <?php else: ?>
                    <div class="alert alert-error" style="margin-top:15px;">No slots are available on this day. Please pick another date.</div>
                <?php endif; ?>
            </div>
        </form>

    <?php endif; ?>
    <?php endif; ?>

    <?php if ($page == 'bookings'): ?>
        <h2 style="margin-bottom:8px;">My bookings</h2>
        <p style="font-size:13px; color:#718096; margin-bottom:12px;">
            You can cancel or reschedule an appointment up to <?php echo $change_limit_hours; ?> hours before it starts.
            If the doctor proposes a new time, you can accept it, decline it, or choose a different date.
        </p>
        <table class="data-table">
            <tr>
                <th>Date</th>
                <th>Time</th>
                <th>Doctor</th>
                <th>Specialty</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
            <?php if ($bookings->num_rows == 0): ?>
                <tr><td colspan="6">You have no bookings yet.</td></tr>
            <?php else: ?>
                <?php while($row = $bookings->fetch_assoc()):
                    $is_cancelled = strpos($row['status'], 'Cancelled') === 0;
                    $is_proposal  = ($row['status'] == 'Rescheduled');
                    $starts_at    = strtotime($row['appointment_date'] . ' ' . $row['appointment_time']);
                    $is_past      = $starts_at < time();
                    $can_change   = can_change_appointment($row['appointment_date'], $row['appointment_time'], $change_limit_hours);
                ?>
                <tr>
                    <td><?php echo date('d M Y', strtotime($row['appointment_date'])); ?></td>
                    <td><?php echo date('H:i', strtotime($row['appointment_time'])); ?></td>
                    <td>Dr. <?php echo htmlspecialchars($row['doctor_name']); ?></td>
                    <td><?php echo htmlspecialchars($row['specialty']); ?></td>
                    <td>
                        <span style="font-weight:bold; color: <?php echo $is_cancelled ? '#c53030' : '#38a169'; ?>;">
                            <?php echo htmlspecialchars($row['status']); ?>
                        </span>
                        <?php if ($is_proposal): ?><br><small>The doctor proposed this new time.</small><?php endif; ?>
                        <?php if ($row['status'] == 'Done' && !empty($row['doctor_feedback'])): ?>
                            <br><small><strong>Doctor's feedback:</strong> <?php echo nl2br(htmlspecialchars($row['doctor_feedback'])); ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($is_cancelled || $row['status'] == 'Missed' || ($is_past && $row['status'] != 'Done')): ?>
                            -
                        <?php elseif ($row['status'] == 'Done'): ?>
                            <?php if (!$feedback_ready): ?>
                                -
                            <?php elseif ($row['my_rating'] !== null): ?>
                                <small>You rated: <?php echo str_repeat('★', (int) $row['my_rating']) . str_repeat('☆', 5 - (int) $row['my_rating']); ?></small>
                            <?php else: ?>
                                <details class="reschedule">
                                    <summary class="btn-small btn-green">Rate this visit</summary>
                                    <form method="POST" action="patient_home.php?page=bookings">
                                        <input type="hidden" name="appointment_id" value="<?php echo $row['id']; ?>">
                                        <select name="rating" required>
                                            <option value="">Choose rating</option>
                                            <option value="5">5 - Excellent</option>
                                            <option value="4">4 - Good</option>
                                            <option value="3">3 - Average</option>
                                            <option value="2">2 - Poor</option>
                                            <option value="1">1 - Very poor</option>
                                        </select>
                                        <textarea name="comments" rows="3" maxlength="500" placeholder="Comments (optional)" style="width:230px; padding:8px 12px; border:1.5px solid #cfe5e0; border-radius:12px; font-family:inherit; font-size:13px;"></textarea>
                                        <button type="submit" name="submit_rating" class="btn-small btn-green">Submit rating</button>
                                    </form>
                                </details>
                            <?php endif; ?>
                        <?php elseif ($is_proposal): ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="appointment_id" value="<?php echo $row['id']; ?>">
                                <button type="submit" name="respond_reschedule" value="accept" class="btn-small btn-green">Accept</button>
                                <button type="submit" name="respond_reschedule" value="decline" class="btn-small btn-red" onclick="return confirm('Decline the new time? The appointment will be cancelled.');">Decline</button>
                            </form>
                            <a href="patient_home.php?page=reschedule&appt_id=<?php echo $row['id']; ?>" class="btn-small btn-blue">Choose another date</a>
                        <?php elseif ($can_change): ?>
                            <a href="patient_home.php?page=reschedule&appt_id=<?php echo $row['id']; ?>" class="btn-small btn-blue">Reschedule</a>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="appointment_id" value="<?php echo $row['id']; ?>">
                                <button type="submit" name="cancel_appointment" class="btn-small btn-red" onclick="return confirm('Cancel this appointment?');">Cancel</button>
                            </form>
                        <?php else: ?>
                            <small>Less than <?php echo $change_limit_hours; ?> hours away: cannot be changed online.</small>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php endif; ?>
        </table>
    <?php endif; ?>

    <?php if ($page == 'records'):
        $stmt = $conn->prepare("SELECT t.*, d.full_name AS doctor_name, d.specialty FROM treatments t JOIN users d ON t.doctor_id = d.id WHERE t.patient_id = ? ORDER BY t.treatment_date DESC, t.id DESC");
        $stmt->bind_param("i", $patient_id);
        $stmt->execute();
        $records = $stmt->get_result();
    ?>
        <h2 style="margin-bottom:8px;">My treatment records</h2>
        <p style="font-size:13px; color:#718096; margin-bottom:12px;">Diagnoses and notes your doctors have recorded after your visits.</p>
        <table class="data-table">
            <tr>
                <th>Date</th>
                <th>Doctor</th>
                <th>Diagnosis</th>
                <th>Treatment / notes</th>
            </tr>
            <?php if ($records->num_rows == 0): ?>
                <tr><td colspan="4">No treatment records yet.</td></tr>
            <?php else: ?>
                <?php while($t = $records->fetch_assoc()): ?>
                <tr>
                    <td><?php echo date('d M Y', strtotime($t['treatment_date'])); ?></td>
                    <td>Dr. <?php echo htmlspecialchars($t['doctor_name']); ?><br><small><?php echo htmlspecialchars($t['specialty']); ?></small></td>
                    <td><?php echo htmlspecialchars($t['diagnosis']); ?></td>
                    <td><?php echo nl2br(htmlspecialchars($t['notes'])); ?></td>
                </tr>
                <?php endwhile; ?>
            <?php endif; ?>
        </table>
    <?php endif; ?>

    <?php if ($page == 'profile'): ?>
        <h2 style="margin-bottom:20px;">My Profile</h2>
        
        <div style="display:flex; gap:20px; flex-wrap:wrap;">
            <div style="flex:1; background:white; border:1px solid #cbd5e0; padding:20px;">
                <h3>Profile Details</h3>
                <form method="POST" action="patient_home.php?page=profile" style="margin-top:15px;">
                    <label style="font-size:13px; font-weight:bold; display:block;">Full Name</label>
                    <input type="text" name="full_name" value="<?php echo htmlspecialchars($my_info['full_name']); ?>" required style="width:100%; padding:10px; margin-bottom:15px; border:1px solid #cbd5e0;">

                    <label style="font-size:13px; font-weight:bold; display:block;">Phone</label>
                    <input type="text" name="phone" value="<?php echo htmlspecialchars($my_info['phone']); ?>" required style="width:100%; padding:10px; margin-bottom:15px; border:1px solid #cbd5e0;">

                    <label style="font-size:13px; font-weight:bold; display:block;">Email (Cannot be changed)</label>
                    <input type="email" value="<?php echo htmlspecialchars($my_info['email']); ?>" disabled style="width:100%; padding:10px; margin-bottom:15px; border:1px solid #cbd5e0; background:#edf2f7;">

                    <button type="submit" name="update_profile" style="background:#3182ce; color:white; border:none; padding:12px 30px; cursor:pointer; font-weight:bold; width:100%;">Save Profile</button>
                </form>
            </div>

            <div style="flex:1; background:white; border:1px solid #cbd5e0; padding:20px;">
                <h3>Change Password</h3>
                <form method="POST" action="patient_home.php?page=profile" style="margin-top:15px;">
                    <label style="font-size:13px; font-weight:bold; display:block;">Current Password</label>
                    <input type="password" name="current_password" required style="width:100%; padding:10px; margin-bottom:15px; border:1px solid #cbd5e0;">

                    <label style="font-size:13px; font-weight:bold; display:block;">New Password (min 8 chars, 1 Upper, 1 Number, 1 Symbol)</label>
                    <input type="password" name="new_password" required style="width:100%; padding:10px; margin-bottom:15px; border:1px solid #cbd5e0;">

                    <label style="font-size:13px; font-weight:bold; display:block;">Confirm New Password</label>
                    <input type="password" name="confirm_password" required style="width:100%; padding:10px; margin-bottom:15px; border:1px solid #cbd5e0;">

                    <button type="submit" name="change_password" style="background:#3182ce; color:white; border:none; padding:12px 30px; cursor:pointer; font-weight:bold; width:100%;">Update Password</button>
                </form>
            </div>
        </div>
    <?php endif; ?>

</div>
</body>
</html>

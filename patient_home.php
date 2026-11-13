<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'patient') {
    header("Location: login.php");
    exit;
}

$patient_id = $_SESSION['user_id'];
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
    $doc_id = $_POST['doctor_id'];
    $date = $_POST['appointment_date'];
    $time = $_POST['appointment_time'];
    
    $check = $conn->query("SELECT id FROM appointments WHERE doctor_id=$doc_id AND appointment_date='$date' AND appointment_time='$time'");
    
    if ($check->num_rows > 0) {
        $error_msg = "Slot not longer available, please choose another.";
    } else {
        $stmt = $conn->prepare("INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, status) VALUES (?, ?, ?, ?, 'Booked')");
        $stmt->bind_param("iiss", $patient_id, $doc_id, $date, $time);
        $stmt->execute();
        header("Location: patient_home.php?page=bookings&booked=1");
        exit;
    }
}

if (isset($_POST['cancel_appointment'])) {
    $appt_id = $_POST['appointment_id'];
    $stmt = $conn->prepare("UPDATE appointments SET status='Cancelled by patient' WHERE id=? AND patient_id=?");
    $stmt->bind_param("ii", $appt_id, $patient_id);
    $stmt->execute();
    header("Location: patient_home.php?page=bookings&cancelled=1");
    exit;
}

$page = isset($_GET['page']) ? $_GET['page'] : 'find';

$search = isset($_GET['search']) ? $_GET['search'] : '';
$sql = "SELECT * FROM users WHERE role='doctor'";
if ($search != '') { 
    $sql .= " AND (full_name LIKE '%$search%' OR specialty LIKE '%$search%' OR locality LIKE '%$search%')"; 
}
$doctors = $conn->query($sql);

$bookings = $conn->query("
    SELECT a.*, d.full_name as doctor_name, d.specialty 
    FROM appointments a 
    JOIN users d ON a.doctor_id = d.id 
    WHERE a.patient_id = $patient_id 
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
");

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
        <a href="patient_home.php?page=bookings" class="<?php echo ($page=='bookings')?'active':''; ?>">My Bookings</a>
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
        $doc_id = $_GET['doctor_id'];
        $doc_info = $conn->query("SELECT * FROM users WHERE id=$doc_id")->fetch_assoc();
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
    ?>
        <a href="patient_home.php?page=find" style="color:#3182ce; text-decoration:none; font-size:14px;">&larr; Back to results</a>
        
        <div class="doctor-card" style="margin-top:20px;">
            <div class="doctor-info">
                <div class="doctor-avatar">photo</div>
                <div class="doctor-details">
                    <h3>Dr. <?php echo htmlspecialchars($doc_info['full_name']); ?></h3>
                    <p><?php echo htmlspecialchars($doc_info['specialty']); ?> | <?php echo htmlspecialchars($doc_info['locality']); ?></p>
                </div>
            </div>
        </div>

        <h3 style="margin-top:30px; margin-bottom:10px;">1. Choose a date (Tomorrow)</h3>
        <p style="font-size:14px; color:#4a5568; margin-bottom:20px;"><?php echo date('l, d M Y', strtotime('+1 day')); ?></p>
        
        <h3 style="margin-top:20px; margin-bottom:10px;">2. Choose a time slot</h3>
        <form method="POST" action="patient_home.php">
            <input type="hidden" name="doctor_id" value="<?php echo $doc_id; ?>">
            <input type="hidden" name="appointment_date" value="<?php echo $tomorrow; ?>">
            
            <div class="slots-grid">
                <?php 
                $times = ['08:00', '08:30', '09:00', '09:30', '10:00', '10:30', '11:00', '11:30', '12:00', '12:30', '13:00', '13:30', '14:00', '14:30', '15:00', '15:30'];
                foreach ($times as $t): 
                    $check = $conn->query("SELECT id FROM appointments WHERE doctor_id=$doc_id AND appointment_date='$tomorrow' AND appointment_time='$t'");
                    $is_booked = $check->num_rows > 0;
                ?>
                    <?php if ($is_booked): ?>
                        <div class="slot booked"><?php echo $t; ?> (booked)</div>
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
                    Date: <?php echo date('d M Y', strtotime('+1 day')); ?><br>
                    Patient: <?php echo $_SESSION['full_name']; ?>
                </p>
                <button type="submit" name="book_appointment" style="background:#3182ce; color:white; border:none; padding:12px 30px; cursor:pointer; margin-top:15px; font-weight:bold;">Confirm Booking</button>
            </div>
        </form>
    <?php endif; ?>

    <?php if ($page == 'bookings'): ?>
        <h2 style="margin-bottom:20px;">My bookings</h2>
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
                <?php while($row = $bookings->fetch_assoc()): ?>
                <tr>
                    <td><?php echo date('d M Y', strtotime($row['appointment_date'])); ?></td>
                    <td><?php echo date('H:i', strtotime($row['appointment_time'])); ?></td>
                    <td>Dr. <?php echo htmlspecialchars($row['doctor_name']); ?></td>
                    <td><?php echo htmlspecialchars($row['specialty']); ?></td>
                    <td>
                        <span style="font-weight:bold; color: <?php echo ($row['status']=='Booked')?'#38a169':'#c53030'; ?>;">
                            <?php echo $row['status']; ?>
                        </span>
                    </td>
                    <td>
                        <?php if($row['status'] == 'Booked'): ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="appointment_id" value="<?php echo $row['id']; ?>">
                                <button type="submit" name="cancel_appointment" class="btn-small btn-red" onclick="return confirm('Cancel this appointment?');">Cancel</button>
                            </form>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
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
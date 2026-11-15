<?php
session_start();
include 'db.php';


if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'doctor') {
    header("Location: login.php");
    exit;
}

$doctor_id = $_SESSION['user_id'];


if (isset($_POST['update_appointment'])) {
    $appt_id = $_POST['appointment_id'];
    $action = $_POST['action'];
    
    if ($action == 'cancel') {
        $stmt = $conn->prepare("UPDATE appointments SET status='Cancelled by doctor' WHERE id=? AND doctor_id=?");
        $stmt->bind_param("ii", $appt_id, $doctor_id);
        $stmt->execute();
    } else {
        $new_date = $_POST['new_date'];
        $new_time = $_POST['new_time'];
        $stmt = $conn->prepare("UPDATE appointments SET appointment_date=?, appointment_time=?, status='Rescheduled by doctor' WHERE id=? AND doctor_id=?");
        $stmt->bind_param("ssii", $new_date, $new_time, $appt_id, $doctor_id);
        $stmt->execute();
    }
    header("Location: doctor_home.php?page=schedule&updated=1");
    exit;
}


if (isset($_POST['add_treatment'])) {
    $pat_id = $_POST['patient_id'];
    $diag = $_POST['diagnosis'];
    $notes = $_POST['notes'];
    $today = date('Y-m-d');
    
    $stmt = $conn->prepare("INSERT INTO treatments (patient_id, doctor_id, treatment_date, diagnosis, notes) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("iisss", $pat_id, $doctor_id, $today, $diag, $notes);
    $stmt->execute();
    header("Location: doctor_home.php?page=patients&treated=1");
    exit;
}

$page = isset($_GET['page']) ? $_GET['page'] : 'schedule';
$filter_date = isset($_GET['filter_date']) ? $_GET['filter_date'] : date('Y-m-d');


$appts = $conn->query("
    SELECT a.*, u.full_name as patient_name, u.id as p_id 
    FROM appointments a 
    JOIN users u ON a.patient_id = u.id 
    WHERE a.doctor_id = $doctor_id AND a.appointment_date = '$filter_date'
    ORDER BY a.appointment_time ASC
");


$patients = $conn->query("
    SELECT DISTINCT u.id, u.full_name 
    FROM appointments a 
    JOIN users u ON a.patient_id = u.id 
    WHERE a.doctor_id = $doctor_id
");
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
        <a href="doctor_home.php?page=schedule" class="<?php echo ($page=='schedule')?'active':''; ?>">My Schedule</a>
        <a href="doctor_home.php?page=patients" class="<?php echo ($page=='patients')?'active':''; ?>">Patients</a>
        <span style="margin-left: 20px; font-size: 14px;">Dr. <?php echo htmlspecialchars($_SESSION['full_name']); ?> (Doctor)</span>
        <a href="logout.php" style="margin-left: 10px; font-weight: bold;">Logout</a>
    </div>
</div>

<div class="admin-container">
    
    <?php if (isset($_GET['updated'])): ?>
        <div class="alert alert-success" style="margin-top:20px;">Appointment updated successfully.</div>
    <?php endif; ?>
    <?php if (isset($_GET['treated'])): ?>
        <div class="alert alert-success" style="margin-top:20px;">Treatment record saved successfully.</div>
    <?php endif; ?>

   
    <?php if ($page == 'schedule'): ?>
        <h2 style="margin-bottom:20px;">My appointments</h2>
        
        <form method="GET" action="doctor_home.php" class="inline-form" style="background:#f7fafc; padding:15px; border:1px solid #e2e8f0;">
            <input type="hidden" name="page" value="schedule">
            <div>
                <label style="font-size:12px; font-weight:bold;">Select date</label>
                <input type="date" name="filter_date" value="<?php echo $filter_date; ?>">
            </div>
            <button type="submit" style="background:#3182ce; color:white; border:none; padding:10px 30px; cursor:pointer;">Show</button>
        </form>

        <table class="data-table">
            <tr>
                <th>Time</th>
                <th>Patient</th>
                <th>Reason</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
            <?php if ($appts->num_rows == 0): ?>
                <tr><td colspan="5">No appointments for this date.</td></tr>
            <?php else: ?>
                <?php while($row = $appts->fetch_assoc()): ?>
                <tr>
                    <td><?php echo date('H:i', strtotime($row['appointment_time'])); ?></td>
                    <td><?php echo htmlspecialchars($row['patient_name']); ?></td>
                    <td>Consultation</td>
                    <td>
                        <span style="font-weight:bold; color: <?php echo ($row['status']=='Booked')?'#38a169':'#c53030'; ?>;">
                            <?php echo $row['status']; ?>
                        </span>
                    </td>
                    <td>
                        <?php if($row['status'] == 'Booked'): ?>
                            <a href="doctor_home.php?page=change&id=<?php echo $row['id']; ?>" class="btn-small btn-blue">Change</a>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php endif; ?>
        </table>
    <?php endif; ?>

    
    <?php if ($page == 'change'): 
        $appt_id = $_GET['id'];
        $appt = $conn->query("SELECT a.*, u.full_name as pat_name FROM appointments a JOIN users u ON a.patient_id = u.id WHERE a.id=$appt_id AND a.doctor_id=$doctor_id")->fetch_assoc();
    ?>
        <a href="doctor_home.php?page=schedule" style="color:#3182ce; text-decoration:none; font-size:14px;">&larr; Back to schedule</a>
        
        <h2 style="margin-top:20px;">Change appointment</h2>
        <div style="background:#f7fafc; padding:15px; border:1px solid #e2e8f0; margin-bottom:20px;">
            <p style="font-size:14px;">Patient: <b><?php echo htmlspecialchars($appt['pat_name']); ?></b></p>
            <p style="font-size:14px;">Current appointment: <b><?php echo date('d M Y, H:i', strtotime($appt['appointment_date'] . ' ' . $appt['appointment_time'])); ?></b></p>
        </div>

        <form method="POST" action="doctor_home.php">
            <input type="hidden" name="appointment_id" value="<?php echo $appt_id; ?>">
            
            <h3 style="margin-bottom:10px;">1. What do you want to do?</h3>
            <label style="margin-right:20px;"><input type="radio" name="action" value="reschedule" required onchange="document.getElementById('rescheduleBox').style.display='block'"> Reschedule to a new slot</label>
            <label><input type="radio" name="action" value="cancel" required onchange="document.getElementById('rescheduleBox').style.display='none'"> Cancel this appointment</label>

            <div id="rescheduleBox" style="display:none; margin-top:20px; background:#f7fafc; padding:20px; border:1px solid #e2e8f0;">
                <h3>2. Choose a new date and time</h3>
                <div style="display:flex; gap:10px; margin-top:10px;">
                    <input type="date" name="new_date" min="<?php echo date('Y-m-d'); ?>" value="<?php echo date('Y-m-d'); ?>" style="padding:10px;">
                    <select name="new_time" style="padding:10px;">
                        <!-- UPDATED TIME SLOTS HERE (8 AM to 4 PM) -->
                        <option value="08:00">08:00 AM</option>
                        <option value="08:30">08:30 AM</option>
                        <option value="09:00">09:00 AM</option>
                        <option value="09:30">09:30 AM</option>
                        <option value="10:00">10:00 AM</option>
                        <option value="10:30">10:30 AM</option>
                        <option value="11:00">11:00 AM</option>
                        <option value="11:30">11:30 AM</option>
                        <option value="12:00">12:00 PM</option>
                        <option value="12:30">12:30 PM</option>
                        <option value="13:00">01:00 PM</option>
                        <option value="13:30">01:30 PM</option>
                        <option value="14:00">02:00 PM</option>
                        <option value="14:30">02:30 PM</option>
                        <option value="15:00">03:00 PM</option>
                        <option value="15:30">03:30 PM</option>
                    </select>
                </div>
            </div>

            <div style="margin-top:20px; text-align:right;">
                <button type="submit" name="update_appointment" style="background:#3182ce; color:white; border:none; padding:12px 30px; cursor:pointer; font-weight:bold;">Confirm Change</button>
            </div>
        </form>
    <?php endif; ?>

  
    <?php if ($page == 'patients'): ?>
        <h2 style="margin-bottom:20px;">Patients & Add Treatment</h2>
        
        <div style="display:flex; gap:20px;">
            
            <div style="flex:1; background:white; border:1px solid #cbd5e0; padding:20px;">
                <h3>Add Treatment Record</h3>
                <form method="POST" action="doctor_home.php">
                    <label style="font-size:13px; font-weight:bold; margin-top:15px; display:block;">Select Patient</label>
                    <select name="patient_id" required style="width:100%; padding:10px; margin-bottom:15px; border:1px solid #cbd5e0;">
                        <option value="">-- Choose Patient --</option>
                        <?php while($p = $patients->fetch_assoc()): ?>
                            <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['full_name']); ?></option>
                        <?php endwhile; ?>
                    </select>

                    <label style="font-size:13px; font-weight:bold; display:block;">Diagnosis</label>
                    <input type="text" name="diagnosis" required placeholder="e.g. Mild hypertension" style="width:100%; padding:10px; margin-bottom:15px; border:1px solid #cbd5e0;">

                    <label style="font-size:13px; font-weight:bold; display:block;">Treatment / Notes</label>
                    <textarea name="notes" required placeholder="e.g. Lifestyle changes, recheck in 3 months" style="width:100%; padding:10px; margin-bottom:15px; border:1px solid #cbd5e0; height:100px;"></textarea>

                    <button type="submit" name="add_treatment" style="background:#3182ce; color:white; border:none; padding:12px 30px; cursor:pointer; font-weight:bold; width:100%;">Save Treatment Record</button>
                </form>
            </div>

            
            <div style="flex:1; background:white; border:1px solid #cbd5e0; padding:20px;">
                <h3>My Patients</h3>
                <p style="font-size:13px; color:#718096; margin-top:10px;">Patients who have booked appointments with you will appear in the dropdown menu.</p>
                <table class="data-table" style="margin-top:20px;">
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                    </tr>
                    <?php 
                    $list = $conn->query("SELECT DISTINCT u.id, u.full_name FROM appointments a JOIN users u ON a.patient_id = u.id WHERE a.doctor_id = $doctor_id");
                    while($row = $list->fetch_assoc()): ?>
                    <tr>
                        <td>P-<?php echo str_pad($row['id'], 4, '0', STR_PAD_LEFT); ?></td>
                        <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                    </tr>
                    <?php endwhile; ?>
                </table>
            </div>
        </div>
    <?php endif; ?>

</div>
</body>
</html>
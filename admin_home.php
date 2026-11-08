<?php
session_start();
include 'db.php';


// if the user is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit;
}

//  For CRUD Operations
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
   
    if (isset($_POST['save_doctor'])) {
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $specialty = trim($_POST['specialty']);
        $locality = trim($_POST['locality']);
        $edit_id = $_POST['edit_id'];

        if ($edit_id == "") {
           
            $hashed = password_hash('password123', PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (full_name, email, phone, password, role, specialty, locality) VALUES (?, ?, 'N/A', ?, 'doctor', ?, ?)");
            $stmt->bind_param("sssss", $name, $email, $hashed, $specialty, $locality);
            $stmt->execute();
        } else {
           
            $stmt = $conn->prepare("UPDATE users SET full_name=?, email=?, specialty=?, locality=? WHERE id=?");
            $stmt->bind_param("ssssi", $name, $email, $specialty, $locality, $edit_id);
            $stmt->execute();
        }
        header("Location: admin_home.php?page=doctors");
        exit;
    }

    
    if (isset($_POST['delete_doctor'])) {
        $id = $_POST['delete_id'];
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'doctor'");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        header("Location: admin_home.php?page=doctors");
        exit;
    }
}


$page = isset($_GET['page']) ? $_GET['page'] : 'doctors';

$total_doctors = $conn->query("SELECT COUNT(*) as c FROM users WHERE role='doctor'")->fetch_assoc()['c'];
$total_patients = $conn->query("SELECT COUNT(*) as c FROM users WHERE role='patient'")->fetch_assoc()['c'];

$todays_appointments = $conn->query("SELECT COUNT(*) as c FROM appointments WHERE appointment_date = CURDATE()")->fetch_assoc()['c'];
$total_feedback = $conn->query("SELECT COUNT(*) as c FROM feedback")->fetch_assoc()['c'];

?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard - BookMyMed</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>

<div class="navbar">
    <a href="admin_home.php" class="logo">+BookMyMed</a>
    <div>
        <a href="admin_home.php?page=doctors">Doctors</a>
        <a href="admin_home.php?page=patients">Patients</a>
        <a href="admin_home.php?page=appointments">Appointments</a>
        <span style="margin-left: 20px; font-size: 14px;">Admin | Logout</span>
        <a href="logout.php" style="margin-left: 10px; font-weight: bold;">Logout</a>
    </div>
</div>

<div class="admin-container">

    <div class="summary-cards">
        <div class="summary-card">
            <h2><?php echo $total_doctors; ?></h2>
            <p>Doctors</p>
        </div>
        <div class="summary-card">
            <h2><?php echo $total_patients; ?></h2>
            <p>Patients</p>
        </div>
        <div class="summary-card">
            <h2><?php echo $todays_appointments; ?></h2>
            <p>Today's appointments</p>
        </div>
        <div class="summary-card">
            <h2><?php echo $total_feedback; ?></h2>
            <p>New feedback</p>
        </div>
    </div>

    <div class="admin-tabs">
        <a href="admin_home.php?page=doctors" class="<?php echo ($page=='doctors')?'active':''; ?>">Doctor Records</a>
        <a href="admin_home.php?page=patients" class="<?php echo ($page=='patients')?'active':''; ?>">Patient Lookup</a>
        <a href="admin_home.php?page=appointments" class="<?php echo ($page=='appointments')?'active':''; ?>">Appointments & Feedback</a>
    </div>

    <?php if ($page == 'doctors'):
        
        $edit_doc = null;
        if (isset($_GET['edit'])) {
            $edit_id = $_GET['edit'];
            $edit_doc = $conn->query("SELECT * FROM users WHERE id=$edit_id AND role='doctor'")->fetch_assoc();
        }

        $search = isset($_GET['search']) ? $_GET['search'] : '';
        $sql = "SELECT * FROM users WHERE role='doctor'";
        if ($search != '') {
            $sql .= " AND (full_name LIKE '%$search%' OR specialty LIKE '%$search%')";
        }
        $doctors = $conn->query($sql);
        ?>

        <h3>Search doctors</h3>
        <form method="GET" action="admin_home.php" class="inline-form">
            <input type="hidden" name="page" value="doctors">
            <input type="text" name="search" placeholder="Name or specialty" value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit" style="background:#3182ce; color:white; border:none; padding:10px 20px; cursor:pointer;">Search</button>
            <a href="admin_home.php?page=doctors&add=1" style="background:#3182ce; color:white; padding:10px 20px; text-decoration:none; font-size:14px;">+ Add Doctor</a>
        </form>

        <table class="data-table">
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Specialty</th>
                <th>Locality</th>
                <th>Email</th>
                <th>Actions</th>
            </tr>
            <?php while($row = $doctors->fetch_assoc()): ?>
            <tr>
                <td>D-<?php echo str_pad($row['id'], 3, '0', STR_PAD_LEFT); ?></td>
                <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                <td><?php echo htmlspecialchars($row['specialty']); ?></td>
                <td><?php echo htmlspecialchars($row['locality']); ?></td>
                <td><?php echo htmlspecialchars($row['email']); ?></td>
                <td>
                    <a href="admin_home.php?page=doctors&edit=<?php echo $row['id']; ?>" class="btn-small btn-blue">Edit</a>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="delete_id" value="<?php echo $row['id']; ?>">
                        <button type="submit" name="delete_doctor" class="btn-small btn-red" onclick="return confirm('Delete this doctor?');">Delete</button>
                    </form>
                </td>
            </tr>
            <?php endwhile; ?>
        </table>

        
        <?php if (isset($_GET['edit']) || isset($_GET['add'])): ?>
        <div class="admin-form-box">
            <h3><?php echo $edit_doc ? 'Edit Doctor' : 'Add New Doctor'; ?></h3>
            <form method="POST" action="admin_home.php">
                <input type="hidden" name="edit_id" value="<?php echo $edit_doc ? $edit_doc['id'] : ''; ?>">
                
                <div class="form-row">
                    <div>
                        <label>Name</label>
                        <input type="text" name="name" required value="<?php echo $edit_doc ? htmlspecialchars($edit_doc['full_name']) : ''; ?>">
                    </div>
                    <div>
                        <label>Specialty</label>
                        <input type="text" name="specialty" required value="<?php echo $edit_doc ? htmlspecialchars($edit_doc['specialty']) : ''; ?>">
                    </div>
                    <div>
                        <label>Locality</label>
                        <input type="text" name="locality" required value="<?php echo $edit_doc ? htmlspecialchars($edit_doc['locality']) : ''; ?>">
                    </div>
                    <div>
                        <label>Email / login</label>
                        <input type="email" name="email" required value="<?php echo $edit_doc ? htmlspecialchars($edit_doc['email']) : ''; ?>">
                    </div>
                </div>
                <button type="submit" name="save_doctor" style="background:#3182ce; color:white; border:none; padding:12px 30px; cursor:pointer; float:right;">Save Doctor</button>
                <div style="clear:both;"></div>
            </form>
        </div>
        <?php endif; ?>

    <?php endif; ?>


    <?php if ($page == 'patients'): 

        $search_patient = isset($_GET['search_patient']) ? $_GET['search_patient'] : '';
        $patient_data = null;
        $patient_treatments = null;

        if ($search_patient != '') {
            $search_id = str_replace('P-', '', $search_patient);
            
            $sql = "SELECT * FROM users WHERE role='patient' AND (id = '$search_id' OR full_name LIKE '%$search_patient%')";
            $result = $conn->query($sql);
            
            if ($result->num_rows > 0) {
                $patient_data = $result->fetch_assoc();
                $p_id = $patient_data['id'];
                $patient_treatments = $conn->query("
                    SELECT t.*, u.full_name as doctor_name 
                    FROM treatments t 
                    JOIN users u ON t.doctor_id = u.id 
                    WHERE t.patient_id = $p_id 
                    ORDER BY t.treatment_date DESC
                ");
            }
        }
        ?>

        <h3>Patient lookup</h3>
        <form method="GET" action="admin_home.php" class="inline-form">
            <input type="hidden" name="page" value="patients">
            <input type="text" name="search_patient" placeholder="e.g. P-1042 or Jane" value="<?php echo htmlspecialchars($search_patient); ?>" style="max-width:400px;">
            <button type="submit" style="background:#3182ce; color:white; border:none; padding:10px 30px; cursor:pointer;">Search</button>
        </form>

        <?php if ($patient_data): ?>
            <div style="background:white; border:1px solid #cbd5e0; padding:20px; margin-top:20px;">
                <h2 style="margin-bottom:5px;"><?php echo htmlspecialchars($patient_data['full_name']); ?></h2>
                <p style="font-size:13px; color:#718096;">
                    Patient ID: P-<?php echo str_pad($patient_data['id'], 4, '0', STR_PAD_LEFT); ?> | 
                    Phone: <?php echo htmlspecialchars($patient_data['phone']); ?> | 
                    Email: <?php echo htmlspecialchars($patient_data['email']); ?>
                </p>
            </div>

            <h3 style="margin-top:30px;">Past treatments</h3>
            <table class="data-table">
                <tr>
                    <th>Date</th>
                    <th>Doctor</th>
                    <th>Diagnosis</th>
                    <th>Treatment / notes</th>
                </tr>
                <?php if ($patient_treatments->num_rows == 0): ?>
                    <tr><td colspan="4">No treatments found for this patient.</td></tr>
                <?php else: ?>
                    <?php while($t = $patient_treatments->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo date('d M Y', strtotime($t['treatment_date'])); ?></td>
                        <td><?php echo htmlspecialchars($t['doctor_name']); ?></td>
                        <td><?php echo htmlspecialchars($t['diagnosis']); ?></td>
                        <td><?php echo htmlspecialchars($t['notes']); ?></td>
                    </tr>
                    <?php endwhile; ?>
                <?php endif; ?>
            </table>
        <?php elseif ($search_patient != ''): ?>
            <p style="margin-top:20px; color:#c53030;">No patient found matching "<?php echo htmlspecialchars($search_patient); ?>".</p>
        <?php endif; ?>

    <?php endif; ?>

    <?php if ($page == 'appointments'): ?>
        
        <?php
       
        $filter_date = isset($_GET['filter_date']) ? $_GET['filter_date'] : date('Y-m-d');
        
        $appts = $conn->query("
            SELECT a.*, p.full_name as patient_name, d.full_name as doctor_name, d.specialty 
            FROM appointments a
            JOIN users p ON a.patient_id = p.id
            JOIN users d ON a.doctor_id = d.id
            WHERE a.appointment_date = '$filter_date'
            ORDER BY a.appointment_time ASC
        ");

        $feedbacks = $conn->query("
            SELECT f.*, p.full_name as patient_name, d.full_name as doctor_name 
            FROM feedback f
            JOIN users p ON f.patient_id = p.id
            JOIN users d ON f.doctor_id = d.id
            ORDER BY f.feedback_date DESC
        ");
        ?>

        <h3>Appointments by date</h3>
        <form method="GET" action="admin_home.php" class="inline-form">
            <input type="hidden" name="page" value="appointments">
            <input type="date" name="filter_date" value="<?php echo $filter_date; ?>" style="max-width:200px;">
            <button type="submit" style="background:#3182ce; color:white; border:none; padding:10px 30px; cursor:pointer;">Show</button>
        </form>

        <table class="data-table">
            <tr>
                <th>Time</th>
                <th>Patient</th>
                <th>Doctor</th>
                <th>Specialty</th>
                <th>Status</th>
            </tr>
            <?php if ($appts->num_rows == 0): ?>
                <tr><td colspan="5">No appointments found for this date.</td></tr>
            <?php else: ?>
                <?php while($row = $appts->fetch_assoc()): ?>
                <tr>
                    <td><?php echo date('H:i', strtotime($row['appointment_time'])); ?></td>
                    <td><?php echo htmlspecialchars($row['patient_name']); ?></td>
                    <td><?php echo htmlspecialchars($row['doctor_name']); ?></td>
                    <td><?php echo htmlspecialchars($row['specialty']); ?></td>
                    <td><?php echo htmlspecialchars($row['status']); ?></td>
                </tr>
                <?php endwhile; ?>
            <?php endif; ?>
        </table>

        <h3 style="margin-top:40px;">Patient feedback</h3>
        <table class="data-table">
            <tr>
                <th>Date</th>
                <th>Patient</th>
                <th>Doctor</th>
                <th>Rating</th>
                <th>Comments</th>
            </tr>
            <?php if ($feedbacks->num_rows == 0): ?>
                <tr><td colspan="5">No feedback submitted yet.</td></tr>
            <?php else: ?>
                <?php while($f = $feedbacks->fetch_assoc()): ?>
                <tr>
                    <td><?php echo date('d M Y', strtotime($f['feedback_date'])); ?></td>
                    <td><?php echo htmlspecialchars($f['patient_name']); ?></td>
                    <td><?php echo htmlspecialchars($f['doctor_name']); ?></td>
                    <td><?php echo str_repeat('★', $f['rating']) . str_repeat('☆', 5 - $f['rating']); ?></td>
                    <td><?php echo htmlspecialchars($f['comments']); ?></td>
                </tr>
                <?php endwhile; ?>
            <?php endif; ?>
        </table>

    <?php endif; ?>

</div>

</body>
</html>
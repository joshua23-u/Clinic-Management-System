<?php
session_start();
include 'db.php';

if (isset($_SESSION['user_id'])) {
    header("Location: " . $_SESSION['role'] . "_home.php");
    exit;
}

$error = ""; $success = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $full_name = trim($_POST['full_name']);
    $email     = trim($_POST['email']);
    $phone     = trim($_POST['phone']);
    $password  = $_POST['password'];
    $confirm   = $_POST['confirm_password'];
    $role      = $_POST['role'];
    $specialty = isset($_POST['specialty']) ? trim($_POST['specialty']) : "";
    $locality  = isset($_POST['locality']) ? trim($_POST['locality']) : "";

    if ($full_name == "" || $email == "" || $phone == "" || $password == "") {
        $error = "Please fill in all required fields.";
    } elseif ($password != $confirm) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters.";
    } elseif ($role == "doctor" && ($specialty == "" || $locality == "")) {
        $error = "Doctors must provide a specialty and locality.";
    } else {
        $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $error = "That email is already registered.";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            if ($role == 'patient') { $specialty = NULL; $locality = NULL; }

            $stmt = $conn->prepare("INSERT INTO users (full_name, email, phone, password, role, specialty, locality) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssss", $full_name, $email, $phone, $hashed_password, $role, $specialty, $locality);

            if ($stmt->execute()) {
                $success = "Account created successfully! You can now log in.";
            } else {
                $error = "Something went wrong. Please try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Register - BookMyMed</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<div class="navbar">
    <a href="login.php" class="logo">+BookMyMed</a>
    <div>
        <a href="login.php">Login</a>
        <a href="register.php">Register</a>
    </div>
</div>

<div class="page-header">
    <h1>Book your doctor appointment online</h1>
</div>

<div class="split-layout">
    <div class="auth-card">
        <h2>Already Registered?</h2>
        <p style="font-size:14px; color:#4a5568; margin-bottom:20px;">
            If you already have an account, please log in to manage your appointments and view treatment notes.
        </p>
        <a href="login.php" style="display:block; text-align:center; background:#3182ce; color:white; padding:12px; text-decoration:none; font-size:14px; font-weight:bold;">
            Go to Login Page
        </a>
    </div>

    <div class="auth-card">
        <h2>New Patient? Register</h2>
        <?php if ($error != "") { echo "<div class='alert alert-error'>$error</div>"; } ?>
        <?php if ($success != "") { echo "<div class='alert alert-success'>$success</div>"; } ?>

        <form method="POST" action="register.php">
            <label>I am a:</label>
            <select name="role" id="role" onchange="toggleDoctorFields()">
                <option value="patient">Patient</option>
                <option value="doctor">Doctor</option>
            </select>

            <label>Full name</label>
            <input type="text" name="full_name" placeholder="e.g. Jane Wanjiru" required>

            <label>Contact number</label>
            <input type="text" name="phone" placeholder="+254 7xx xxx xxx" required>

            <label>Email</label>
            <input type="email" name="email" placeholder="you@example.com" required>

            <div id="doctorFields" style="display:none;">
                <label>Specialty</label>
                <input type="text" name="specialty" placeholder="e.g. Cardiologist">
                <label>Locality</label>
                <input type="text" name="locality" placeholder="e.g. Westlands">
            </div>

            <div class="password-row">
                <div>
                    <label>Password</label>
                    <input type="password" name="password" placeholder="min 8 characters" required>
                </div>
                <div>
                    <label>Confirm password</label>
                    <input type="password" name="confirm_password" placeholder="repeat password" required>
                </div>
            </div>
            <button type="submit">Create Account</button>
        </form>
    </div>
</div>

<script>
function toggleDoctorFields() {
    var role = document.getElementById('role').value;
    document.getElementById('doctorFields').style.display = (role == 'doctor') ? 'block' : 'none';
}
toggleDoctorFields();
</script>
</body>
</html>
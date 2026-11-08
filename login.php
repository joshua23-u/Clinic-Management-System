<?php
session_start();
include 'db.php';

if (isset($_SESSION['user_id'])) {
    header("Location: " . $_SESSION['role'] . "_home.php");
    exit;
}

$error = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if ($email == "" || $password == "") {
        $error = "Please enter your email and password.";
    } else {
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];
                header("Location: " . $user['role'] . "_home.php");
                exit;
            } else {
                $error = "Invalid password.";
            }
        } else {
            $error = "No account found with that email.";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Login - BookMyMed</title>
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
    <h1>Book your Doctor's Appointment online</h1>
</div>

<div class="split-layout">
    <div class="auth-card">
        <h2>Log In</h2>
        <?php if ($error != "") { echo "<div class='alert alert-error'>$error</div>"; } ?>
        <form method="POST" action="login.php">
            <label>Email / Username</label>
            <input type="email" name="email" placeholder="you@example.com" required>
            <label>Password</label>
            <input type="password" name="password" placeholder="••••••••" required>
            <button type="submit">Log in</button>
        </form>
        <div class="helper-text">
            <p>Patients, Doctors and Admins all log in here. The system opens the correct dashboard for the role.</p>
        </div>
    </div>

    <div class="auth-card">
        <h2>New Patient? Register</h2>
        <p style="font-size:14px; color:#4a5568; margin-bottom:20px;">
            Create an account to book appointments, view your treatment history, and manage bookings.
        </p>
        <a href="register.php" style="display:block; text-align:center; background:#3182ce; color:white; padding:12px; text-decoration:none; font-size:14px; font-weight:bold;">
            Go to Registration Page
        </a>
    </div>
</div>
</body>
</html>
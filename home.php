<?php
session_start();

// Where the main buttons should go depending on who is looking at the page
$logged_in = isset($_SESSION['user_id']);
if ($logged_in) {
    $dashboard_link = $_SESSION['role'] . "_home.php";
    $appointment_link = ($_SESSION['role'] == 'patient') ? "patient_home.php?page=find" : $dashboard_link;
} else {
    $dashboard_link = "login.php";
    $appointment_link = "register.php";
}

// Optional: drop a doctor photo at images/doctor.png (transparent PNG works best) and it replaces the illustration
$has_photo = file_exists(__DIR__ . '/images/doctor.png');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>BookMyMed - Find a doctor and book an appointment</title>
    <link rel="stylesheet" href="home.css">
</head>
<body>

<div class="page">

    <!-- NAVBAR -->
    <header class="nav">
        <a href="index.php" class="brand">
            <span class="brand-icon"></span>BookMyMed
        </a>
        <nav class="nav-links">
            <a href="index.php" class="active">Home</a>
            <a href="#how">How it works</a>
            <a href="#about">About</a>
            <a href="#contact">Contact</a>
        </nav>
        <?php if ($logged_in): ?>
            <a href="<?php echo htmlspecialchars($dashboard_link); ?>" class="btn-login">My Dashboard</a>
        <?php else: ?>
            <a href="login.php" class="btn-login">Login</a>
        <?php endif; ?>
    </header>

    <!-- HERO -->
    <section class="hero">
        <div class="hero-text">
            <h1>Find a <span class="underline">Doctor</span> And Book An <span class="accent">Appointment</span></h1>
            <p>Search doctors by name, specialty or locality, pick a time that suits you, and manage your bookings and treatment notes in one place.</p>
            <div class="hero-actions">
                <a href="<?php echo htmlspecialchars($appointment_link); ?>" class="btn-primary">Make Appointment</a>
                <a href="#how" class="btn-ghost">
                    <span class="circle-icon">
                        <svg width="12" height="12" viewBox="0 0 12 12"><path d="M2 1.5 L10 6 L2 10.5 Z" fill="#fff"/></svg>
                    </span>
                    See how it works
                </a>
            </div>
        </div>

        <div class="hero-visual">
            <!-- Blob and floating shapes -->
            <svg viewBox="0 0 520 460" preserveAspectRatio="xMidYMax meet" aria-hidden="true">
                <path d="M130 30 C 260 -20, 470 0, 505 120 C 535 230, 505 335, 480 460 L 175 460 C 90 390, 35 300, 55 210 C 72 130, 40 75, 130 30 Z" fill="#0b7f72"/>
                <ellipse cx="55" cy="80" rx="26" ry="18" fill="#0b7f72" transform="rotate(-25 55 80)"/>
                <ellipse cx="470" cy="190" rx="12" ry="9" fill="#fff"/>
                <ellipse cx="500" cy="330" rx="20" ry="13" fill="#0b7f72"/>
                <ellipse cx="25" cy="300" rx="10" ry="8" fill="#0b7f72"/>
            </svg>

            <?php if ($has_photo): ?>
                <img src="images/doctor.png" alt="Smiling doctor" class="doctor-photo">
            <?php else: ?>
                <!-- Placeholder doctor illustration -->
                <svg viewBox="0 0 520 460" preserveAspectRatio="xMidYMax meet" aria-label="Doctor illustration" role="img">
                    <rect x="280" y="265" width="40" height="60" rx="16" fill="#d9a183"/>
                    <path d="M140 460 C140 365 195 322 300 312 C405 322 460 365 460 460 Z" fill="#ffffff"/>
                    <path d="M262 318 L300 392 L338 318 Z" fill="#d6eeea"/>
                    <path d="M262 318 L292 410 M338 318 L308 410" stroke="#cfe5e0" stroke-width="3" fill="none"/>
                    <path d="M245 215 C225 262 232 300 252 312 L262 240 Z" fill="#3b2a24"/>
                    <ellipse cx="300" cy="215" rx="55" ry="65" fill="#e8b595"/>
                    <path d="M240 218 C232 140 288 118 332 134 C368 150 366 205 360 228 C350 192 330 172 300 170 C270 172 250 192 240 218 Z" fill="#3b2a24"/>
                    <circle cx="278" cy="222" r="4.5" fill="#3b2a24"/>
                    <circle cx="322" cy="222" r="4.5" fill="#3b2a24"/>
                    <path d="M280 252 C292 266 308 266 320 252" stroke="#9c4b3b" stroke-width="4" fill="none" stroke-linecap="round"/>
                    <path d="M263 322 C248 385 270 425 300 425 C330 425 352 385 337 322" fill="none" stroke="#12332f" stroke-width="5" stroke-linecap="round"/>
                    <circle cx="300" cy="430" r="9" fill="#cfe5e0" stroke="#12332f" stroke-width="4"/>
                </svg>
            <?php endif; ?>

            <span class="heart-badge" aria-hidden="true">
                <svg width="20" height="20" viewBox="0 0 24 24"><path d="M12 21s-8-5.2-8-11a4.5 4.5 0 0 1 8-2.8A4.5 4.5 0 0 1 20 10c0 5.800-8 11-8 11z" fill="#0b7f72"/></svg>
            </span>
            <span class="dots" aria-hidden="true"></span>
        </div>
    </section>

    <!-- HOW IT WORKS -->
    <section class="how" id="how">
        <span class="plus plus-a" aria-hidden="true"></span>
        <span class="plus plus-b" aria-hidden="true"></span>

        <h2>How it Works?</h2>
        <p class="section-sub">4 steps to get your appointment</p>

        <div class="steps">
            <div class="step">
                <div class="step-icon">
                    <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#0b7f72" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.500-3.500"/></svg>
                </div>
                <h3>Search Doctor</h3>
                <p>Look up doctors by name, specialty or locality.</p>
            </div>
            <div class="step">
                <div class="step-icon">
                    <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#0b7f72" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.400 3.600-7 8-7s8 2.600 8 7"/></svg>
                </div>
                <h3>Check Doctor Profile</h3>
                <p>See each doctor's specialty and where they practise.</p>
            </div>
            <div class="step">
                <div class="step-icon">
                    <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#0b7f72" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="16" rx="3"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>
                </div>
                <h3>Schedule Appointment</h3>
                <p>Pick an open time slot and confirm your booking.</p>
            </div>
            <div class="step">
                <div class="step-icon">
                    <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#0b7f72" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 4h6a2 2 0 0 1 2 2v14H7V6a2 2 0 0 1 2-2z"/><path d="M10 12l2 2 3-4"/></svg>
                </div>
                <h3>Get Treated</h3>
                <p>Visit your doctor and review treatment notes afterwards.</p>
            </div>
        </div>
    </section>

    <!-- ABOUT -->
    <section class="about" id="about">
        <div class="about-text">
            <h2>Healthcare booking, made simple</h2>
            <p>BookMyMed connects patients with doctors so you can skip the phone calls and queues. Create an account, find the right doctor and book a slot in a few clicks.</p>
            <a href="<?php echo htmlspecialchars($appointment_link); ?>" class="btn-primary">
                <?php echo $logged_in ? 'Go to my account' : 'Create a free account'; ?>
            </a>
        </div>
        <ul class="about-list">
            <li><span class="tick">&#10003;</span><div><strong>Find the right doctor</strong><br>Filter by specialty and locality.</div></li>
            <li><span class="tick">&#10003;</span><div><strong>See open slots instantly</strong><br>Booked times are marked so you never double-book.</div></li>
            <li><span class="tick">&#10003;</span><div><strong>Manage everything in one place</strong><br>View, cancel and track your appointments and treatment notes.</div></li>
        </ul>
    </section>

    <!-- FOOTER / CONTACT -->
    <footer class="footer" id="contact">
        <div>
            <a href="index.php" class="brand brand-light"><span class="brand-icon"></span>BookMyMed</a>
            <p>Book your doctor's appointment online.</p>
        </div>
        <div class="footer-links">
            <a href="login.php">Login</a>
            <a href="register.php">Register</a>
            <a href="mailto:contact@bookmymed.example">contact@bookmymed.example</a>
        </div>
    </footer>

</div>

</body>
</html>
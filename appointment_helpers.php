<?php
// appointment_helpers.php - shared appointment rules for the admin, doctor and patient pages.
// Include with:  include_once 'appointment_helpers.php';

date_default_timezone_set('Africa/Nairobi');

// How long after its start time an unanswered appointment is closed automatically
const NO_ACTION_GRACE_HOURS = 24;

// Closes appointments nobody acted on. Run on every dashboard load.
function refresh_appointment_statuses($conn) {
    $cutoff = date('Y-m-d H:i:s', time() - NO_ACTION_GRACE_HOURS * 3600);

    // Booked or rescheduled, but nobody responded in time (starts with "Cancelled", so the slot is free)
    $stmt = $conn->prepare("UPDATE appointments SET status = 'Cancelled (no response)' WHERE status IN ('Booked', 'Rescheduled') AND TIMESTAMP(appointment_date, appointment_time) < ?");
    $stmt->bind_param("s", $cutoff);
    $stmt->execute();

    // Confirmed, but the doctor never marked it as Done
    $stmt = $conn->prepare("UPDATE appointments SET status = 'Missed' WHERE status = 'Confirmed' AND TIMESTAMP(appointment_date, appointment_time) < ?");
    $stmt->bind_param("s", $cutoff);
    $stmt->execute();
}

// Number of new times proposed by doctors that this patient has not answered yet
function count_patient_proposals($conn, $patient_id) {
    $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM appointments WHERE patient_id = ? AND status = 'Rescheduled'");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    return (int) $stmt->get_result()->fetch_assoc()['c'];
}

// Number of upcoming bookings this doctor has not accepted yet
function count_doctor_pending($conn, $doctor_id) {
    $now = date('Y-m-d H:i:s');
    $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM appointments WHERE doctor_id = ? AND status = 'Booked' AND TIMESTAMP(appointment_date, appointment_time) >= ?");
    $stmt->bind_param("is", $doctor_id, $now);
    $stmt->execute();
    return (int) $stmt->get_result()->fetch_assoc()['c'];
}
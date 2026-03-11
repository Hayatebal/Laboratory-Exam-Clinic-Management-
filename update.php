<?php 
require "config.php";

if (isset($_POST['update'])) {

    $full_name = $_POST['full_name'];
    $age = $_POST['age'];
    $gender = $_POST['gender'];
    $contact_number = $_POST['contact_number'];

    // Update patients table
    $stmt = $pdo->prepare("UPDATE patients SET full_name = ?, age = ?, gender = ?, contact_number = ? WHERE patient_id = ?");
    $stmt->execute([$full_name, $age, $gender, $contact_number, $patient_id]);

    // Update consultations table
    $stmt2 = $pdo->prepare("UPDATE consultations SET doctor_name = ?, consultation_date = ?, diagnosis = ?, treatment = ? WHERE patient_id = ?");
    $stmt2->execute([$doctor_name, $consultation_date, $diagnosis, $treatment, $patient_id]);

    header("Location: landing.php");
    exit;
}
?>
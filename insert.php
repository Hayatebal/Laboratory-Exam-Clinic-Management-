<?php
require 'config.php';

if(isset($_POST['add'])){

$full_name = $_POST['full_name'];
$age = $_POST['age'];
$gender = $_POST['gender'];
$contact_number = $_POST['contact_number'];

$doctor_name = $_POST['doctor_name'];
$consultation_date = $_POST['consultation_date'];
$diagnosis = $_POST['diagnosis'];
$treatment = $_POST['treatment'];

/* insert patient */

$stmt = $pdo->prepare("
INSERT INTO patients (full_name, age, gender, contact_number)
VALUES (?, ?, ?, ?)
");

$stmt->execute([$full_name,$age,$gender,$contact_number]);

$patient_id = $pdo->lastInsertId();

/* insert consultation */

$stmt2 = $pdo->prepare("
INSERT INTO consultations
(patient_id, doctor_name, consultation_date, diagnosis, treatment)
VALUES (?, ?, ?, ?, ?)
");

$stmt2->execute([
$patient_id,
$doctor_name,
$consultation_date,
$diagnosis,
$treatment
]);

echo "<p style='color:green'>Patient and consultation added successfully</p>";

}
?>
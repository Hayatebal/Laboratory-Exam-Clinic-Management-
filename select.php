<?php
require 'config.php';

$stmt = $pdo->query("
    SELECT 
        u.patient_id,
        u.full_name,
        u.age,
        u.gender,
        u.contact_number
    FROM patients u
    ORDER BY u.patient_id DESC
");

$patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
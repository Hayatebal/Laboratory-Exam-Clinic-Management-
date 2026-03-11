<?php
require 'config.php';

if (isset($_GET['delete'])) {
    $users_id = $_GET['delete'];

    $stmt = $pdo->prepare('DELETE FROM patients WHERE patient_id = ?');
    $stmt->execute([$users_id]);

    echo "Patient Info deleted successfully";
}
?>
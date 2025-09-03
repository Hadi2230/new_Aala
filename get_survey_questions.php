<?php
session_start();
include 'config.php';

header('Content-Type: application/json');

if (!isset($_GET['survey_id'])) {
    echo json_encode([]);
    exit();
}

$survey_id = $_GET['survey_id'];
$stmt = $pdo->prepare("SELECT * FROM survey_questions WHERE survey_id = ? ORDER BY id");
$stmt->execute([$survey_id]);
$questions = $stmt->fetchAll();

echo json_encode($questions, JSON_UNESCAPED_UNICODE);
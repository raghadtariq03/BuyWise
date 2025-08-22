<?php
require_once 'config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Invalid request method"]);
    exit;
}

if (!isset($_SESSION['UserID']) || !in_array($_SESSION['type'], [2, 3, 'company'])) {
    http_response_code(401);
    echo json_encode(["status" => "unauthorized", "message" => "Not allowed"]);
    exit;
}

$UserID = intval($_SESSION['UserID']);

$stmt = $con->prepare("UPDATE notifications SET is_read = 1 WHERE recipient_id = ?");
$stmt->bind_param("i", $UserID);

if ($stmt->execute()) {
    echo json_encode(["status" => "success"]);
} else {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Failed to update notifications"]);
}
<?php
require_once "config.php";
header('Content-Type: application/json');


// Ensure user is authenticated
if (!isset($_SESSION['UserID'])) {
    echo json_encode(['status' => 'error', 'message' => 'Authentication required']);
    exit;
}

// Determine language
$lang = $_SESSION['lang'] ?? 'en';
$nameField = ($lang === 'ar') ? 'CategoryName_ar' : 'CategoryName_en';

// Fetch categories
$sql = "SELECT CategoryID, $nameField AS CategoryName FROM categories WHERE Status = 1 ORDER BY $nameField ASC";
$result = mysqli_query($con, $sql);

if (!$result) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to fetch categories']);
    exit;
}

// Prepare response
$categories = [];
while ($row = mysqli_fetch_assoc($result)) {
    $categories[] = [
        'CategoryID' => (int)$row['CategoryID'],
        'CategoryName' => $row['CategoryName']
    ];
}

echo json_encode([
    'status' => 'success',
    'categories' => $categories
]);
exit;

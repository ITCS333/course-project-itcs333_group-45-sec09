<?php
/**
 * Authentication Handler for Login Form
 * 
 * This API accepts POST JSON:
 * {
 *    "email": "...",
 *    "password": "..."
 * }
 * 
 * If valid → creates session and returns JSON success.
 * If invalid → returns error JSON.
 */

// -------------------------------------------------------------
// 1. Start Session
// -------------------------------------------------------------
session_start();

// -------------------------------------------------------------
// 2. Set Response Type
// -------------------------------------------------------------
header("Content-Type: application/json; charset=utf-8");

// If needed for testing you can uncomment:
// header("Access-Control-Allow-Origin: *");
// header("Access-Control-Allow-Headers: Content-Type");
// header("Access-Control-Allow-Methods: POST, OPTIONS");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// -------------------------------------------------------------
// 3. Allow ONLY POST Requests
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        "success" => false,
        "message" => "Invalid request method. POST required."
    ]);
    exit;
}

// -------------------------------------------------------------
// 4. Read JSON Body
// -------------------------------------------------------------
$rawData = file_get_contents("php://input");
$data = json_decode($rawData, true);

if (!is_array($data)) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid JSON format."
    ]);
    exit;
}

// -------------------------------------------------------------
// 5. Validate Required Fields
// -------------------------------------------------------------
if (empty($data['email']) || empty($data['password'])) {
    echo json_encode([
        "success" => false,
        "message" => "Email and password are required."
    ]);
    exit;
}

$email = trim($data['email']);
$password = $data['password'];

// -------------------------------------------------------------
// 6. Server-Side Validation
// -------------------------------------------------------------
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid email format."
    ]);
    exit;
}

if (strlen($password) < 8) {
    echo json_encode([
        "success" => false,
        "message" => "Password must be at least 8 characters."
    ]);
    exit;
}

// -------------------------------------------------------------
// 7. Connect to Database
// -------------------------------------------------------------
require_once __DIR__ . "/../../admin/api/Database.php";
$dbClass = new Database();
$db = $dbClass->getConnection();

// -------------------------------------------------------------
// 8. Query Database for User
// -------------------------------------------------------------
try {
    $sql = "SELECT id, student_id, name, email, password 
            FROM students 
            WHERE email = :email 
            LIMIT 1";

    $stmt = $db->prepare($sql);
    $stmt->bindValue(":email", $email, PDO::PARAM_STR);
    $stmt->execute();

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // No user found
    if (!$user) {
        echo json_encode([
            "success" => false,
            "message" => "Invalid email or password"
        ]);
        exit;
    }

    // Verify password
    if (!password_verify($password, $user['password'])) {
        echo json_encode([
            "success" => false,
            "message" => "Invalid email or password"
        ]);
        exit;
    }

    // ---------------------------------------------------------
    // 9. SUCCESS — Create Session
    // ---------------------------------------------------------
    $_SESSION['logged_in'] = true;
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['student_id'] = $user['student_id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email'] = $user['email'];

    echo json_encode([
        "success" => true,
        "message" => "Login successful",
        "user" => [
            "id" => $user['id'],
            "student_id" => $user['student_id'],
            "name" => $user['name'],
            "email" => $user['email']
        ]
    ]);
    exit;

} catch (PDOException $e) {
    error_log("Login DB Error: " . $e->getMessage());

    echo json_encode([
        "success" => false,
        "message" => "Server error. Please try again later."
    ]);
    exit;
}

?>

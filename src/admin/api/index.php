<?php
/**
 * manage_users.php
 * Fully compatible with your manage_users.js + password change
 */

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit;
}

// -------------------------------------------------------------
// DB CONNECTION
// -------------------------------------------------------------
$host = "localhost";
$user = "admin";
$pass = "password123";
$db   = "course";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "DB connection failed"]);
    exit;
}

$method = $_SERVER["REQUEST_METHOD"];
$input  = json_decode(file_get_contents("php://input"), true);
if (!is_array($input)) $input = [];

// -------------------------------------------------------------
// Utility functions
// -------------------------------------------------------------
function studentIdFromEmail($email) { return explode("@", $email)[0]; }
function emailPattern($student_id) { return $student_id . "@%"; }


// -------------------------------------------------------------
// 1) Change password handler
// -------------------------------------------------------------
if (isset($_GET["action"]) && $_GET["action"] === "change_password") {

    if (empty($input["student_id"]) || empty($input["current_password"]) || empty($input["new_password"])) {
        echo json_encode(["success" => false, "message" => "Missing fields"]);
        exit;
    }

    $student_id = trim($input["student_id"]);
    $current_password = $input["current_password"];
    $new_password = $input["new_password"];

    // Fetch user by email pattern
    $sql = "SELECT password FROM users WHERE email LIKE :email LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(":email", emailPattern($student_id));
    $stmt->execute();

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(["success" => false, "message" => "Student not found"]);
        exit;
    }

    // Verify current password
    if (!password_verify($current_password, $user["password"])) {
        echo json_encode(["success" => false, "message" => "Current password incorrect"]);
        exit;
    }

    // Validate new password
    if (strlen($new_password) < 8) {
        echo json_encode(["success" => false, "message" => "New password must be at least 8 characters"]);
        exit;
    }

    // Hash new password
    $newHash = password_hash($new_password, PASSWORD_DEFAULT);

    // Update password
    $update = $pdo->prepare("UPDATE users SET password = :pw WHERE email LIKE :email");
    $update->bindValue(":pw", $newHash);
    $update->bindValue(":email", emailPattern($student_id));

    if ($update->execute()) {
        echo json_encode(["success" => true, "message" => "Password updated successfully"]);
    } else {
        echo json_encode(["success" => false, "message" => "Failed to update password"]);
    }

    exit;
}


// -------------------------------------------------------------
// 2) GET → Load all students
// -------------------------------------------------------------
if ($method === "GET") {

    $stmt = $pdo->prepare("SELECT id, name, email FROM users WHERE is_admin = 0 ORDER BY name ASC");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as &$r) {
        $r["student_id"] = studentIdFromEmail($r["email"]);
    }

    echo json_encode(["success" => true, "data" => $rows]);
    exit;
}


// -------------------------------------------------------------
// 3) POST → Add student
// -------------------------------------------------------------
if ($method === "POST") {

    if (!isset($input["student_id"], $input["name"], $input["email"], $input["password"])) {
        echo json_encode(["success" => false, "message" => "Missing required fields"]);
        exit;
    }

    $student_id = trim($input["student_id"]);
    $name       = trim($input["name"]);
    $email      = trim($input["email"]);
    $password   = password_hash(trim($input["password"]), PASSWORD_DEFAULT);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(["success" => false, "message" => "Invalid email"]);
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password, is_admin) VALUES (?, ?, ?, 0)");
        $stmt->execute([$name, $email, $password]);
        echo json_encode(["success" => true]);
    } catch (PDOException $e) {
        echo json_encode(["success" => false, "message" => "Email already exists"]);
    }

    exit;
}


// -------------------------------------------------------------
// 4) PUT → Edit student
// -------------------------------------------------------------
if ($method === "PUT") {

    if (!isset($input["student_id"])) {
        echo json_encode(["success" => false, "message" => "student_id required"]);
        exit;
    }

    $student_id = trim($input["student_id"]);
    $new_name   = trim($input["name"]);
    $new_email  = trim($input["email"]);

    if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(["success" => false, "message" => "Invalid email"]);
        exit;
    }

    $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ? WHERE email LIKE ?");
    $stmt->execute([$new_name, $new_email, emailPattern($student_id)]);

    echo json_encode(["success" => true]);
    exit;
}


// -------------------------------------------------------------
// 5) DELETE → Delete student
// -------------------------------------------------------------
if ($method === "DELETE") {

    if (!isset($_GET["student_id"])) {
        echo json_encode(["success" => false, "message" => "student_id required"]);
        exit;
    }

    $student_id = trim($_GET["student_id"]);

    $stmt = $pdo->prepare("DELETE FROM users WHERE email LIKE ?");
    $stmt->execute([emailPattern($student_id)]);

    echo json_encode(["success" => true]);
    exit;
}


// -------------------------------------------------------------
// 6) Invalid method
// -------------------------------------------------------------
echo json_encode(["success" => false, "message" => "Invalid request"]);
exit;

?>

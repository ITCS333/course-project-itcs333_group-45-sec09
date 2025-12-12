<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
header("Content-Type: application/json");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["success" => false, "message" => "POST request required."]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

if (!$data) {
    echo json_encode(["success" => false, "message" => "Invalid JSON received."]);
    exit;
}

$email = trim($data["email"] ?? "");
$password = trim($data["password"] ?? "");

if (!$email || !$password) {
    echo json_encode(["success" => false, "message" => "Email and password required."]);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(["success" => false, "message" => "Invalid email format."]);
    exit;
}

require_once __DIR__ . "/../../admin/api/database.php";
$dbClass = new Database();
$db = $dbClass->getConnection();

try {
    $sql = "SELECT id, name, email, password, is_admin
            FROM users
            WHERE email = :email
            LIMIT 1";

    $stmt = $db->prepare($sql);
    $stmt->bindValue(":email", $email);
    $stmt->execute();

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(["success" => false, "message" => "Invalid email or password."]);
        exit;
    }

    // FIXED — secure hashed password verification
    if (!password_verify($password, $user["password"])) {
        echo json_encode(["success" => false, "message" => "Invalid email or password."]);
        exit;
    }

    // Success
    $_SESSION["logged_in"] = true;
    $_SESSION["user_id"] = $user["id"];
    $_SESSION["user_name"] = $user["name"];
    $_SESSION["user_email"] = $user["email"];
    $_SESSION["is_admin"] = $user["is_admin"];

    
    echo json_encode([
        "success" => true,
        "message" => "Login successful!",
        "user" => [
            "id" => $user["id"],
            "name" => $user["name"],
            "email" => $user["email"],
            "is_admin" => $user["is_admin"]
        ]
    ]);

} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => "PHP ERROR: " . $e->getMessage()
    ]);
}

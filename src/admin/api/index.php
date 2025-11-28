<?php
/**
 * Student Management API
 * 
 * RESTful API for CRUD operations on 'students' table.
 */

// -------------------------------------------------------------
// HEADERS & CORS
// -------------------------------------------------------------
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit;
}

// -------------------------------------------------------------
// DB CONNECTION
// -------------------------------------------------------------
require_once __DIR__ . "/Database.php";

$database = new Database();
$db       = $database->getConnection();

// -------------------------------------------------------------
// REQUEST DATA
// -------------------------------------------------------------
$method    = $_SERVER["REQUEST_METHOD"];
$rawInput  = file_get_contents("php://input");
$inputData = json_decode($rawInput, true);
if (!is_array($inputData)) {
    $inputData = [];
}

// -------------------------------------------------------------
// MAIN FUNCTIONS
// -------------------------------------------------------------

/**
 * GET /students
 * Optional query params:
 *  - search: search term (name, student_id, email)
 *  - sort:   name | student_id | email
 *  - order:  asc | desc
 */
function getStudents(PDO $db)
{
    $search = isset($_GET["search"]) ? trim($_GET["search"]) : "";
    $sort   = isset($_GET["sort"]) ? $_GET["sort"] : "";
    $order  = isset($_GET["order"]) ? strtolower($_GET["order"]) : "asc";

    $allowedSortFields = ["name", "student_id", "email"];
    $allowedOrder      = ["asc", "desc"];

    $sql    = "SELECT student_id, name, email, created_at FROM students";
    $params = [];

    if ($search !== "") {
        $sql             .= " WHERE name LIKE :term OR student_id LIKE :term OR email LIKE :term";
        $params[":term"] = "%" . $search . "%";
    }

    if (in_array($sort, $allowedSortFields, true)) {
        $order = in_array($order, $allowedOrder, true) ? $order : "asc";
        $sql  .= " ORDER BY {$sort} {$order}";
    }

    $stmt = $db->prepare($sql);

    if (isset($params[":term"])) {
        $stmt->bindValue(":term", $params[":term"], PDO::PARAM_STR);
    }

    $stmt->execute();
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendResponse([
        "success" => true,
        "data"    => $students
    ]);
}

/**
 * GET /students?student_id=STU123
 */
function getStudentById(PDO $db, $studentId)
{
    $sql  = "SELECT student_id, name, email, created_at FROM students WHERE student_id = :student_id LIMIT 1";
    $stmt = $db->prepare($sql);
    $stmt->bindValue(":student_id", $studentId, PDO::PARAM_STR);
    $stmt->execute();

    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($student) {
        sendResponse([
            "success" => true,
            "data"    => $student
        ]);
    } else {
        sendResponse([
            "success" => false,
            "message" => "Student not found."
        ], 404);
    }
}

/**
 * POST /students
 * JSON body:
 *  - student_id
 *  - name
 *  - email
 *  - password
 */
function createStudent(PDO $db, array $data)
{
    // Required fields
    $required = ["student_id", "name", "email", "password"];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            sendResponse([
                "success" => false,
                "message" => "Missing required field: {$field}."
            ], 400);
        }
    }

    $studentId = sanitizeInput($data["student_id"]);
    $name      = sanitizeInput($data["name"]);
    $email     = sanitizeInput($data["email"]);
    $password  = $data["password"];

    if (!validateEmail($email)) {
        sendResponse([
            "success" => false,
            "message" => "Invalid email format."
        ], 400);
    }

    // Check duplicates
    $checkSql  = "SELECT id, student_id, email FROM students WHERE student_id = :student_id OR email = :email LIMIT 1";
    $checkStmt = $db->prepare($checkSql);
    $checkStmt->bindValue(":student_id", $studentId, PDO::PARAM_STR);
    $checkStmt->bindValue(":email", $email, PDO::PARAM_STR);
    $checkStmt->execute();

    if ($existing = $checkStmt->fetch(PDO::FETCH_ASSOC)) {
        $msg = "Student already exists.";
        if ($existing["student_id"] === $studentId) {
            $msg = "A student with this student_id already exists.";
        } elseif ($existing["email"] === $email) {
            $msg = "A student with this email already exists.";
        }

        sendResponse([
            "success" => false,
            "message" => $msg
        ], 409);
    }

    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    $insertSql = "INSERT INTO students (student_id, name, email, password)
                  VALUES (:student_id, :name, :email, :password)";
    $stmt      = $db->prepare($insertSql);
    $stmt->bindValue(":student_id", $studentId, PDO::PARAM_STR);
    $stmt->bindValue(":name", $name, PDO::PARAM_STR);
    $stmt->bindValue(":email", $email, PDO::PARAM_STR);
    $stmt->bindValue(":password", $hashedPassword, PDO::PARAM_STR);

    if ($stmt->execute()) {
        sendResponse([
            "success" => true,
            "message" => "Student created successfully."
        ], 201);
    } else {
        sendResponse([
            "success" => false,
            "message" => "Failed to create student."
        ], 500);
    }
}

/**
 * PUT /students
 * JSON body:
 *  - student_id (required)
 *  - name (optional)
 *  - email (optional)
 */
function updateStudent(PDO $db, array $data)
{
    if (empty($data["student_id"])) {
        sendResponse([
            "success" => false,
            "message" => "student_id is required."
        ], 400);
    }

    $studentId = sanitizeInput($data["student_id"]);

    // Check if student exists
    $checkSql  = "SELECT id, email FROM students WHERE student_id = :student_id LIMIT 1";
    $checkStmt = $db->prepare($checkSql);
    $checkStmt->bindValue(":student_id", $studentId, PDO::PARAM_STR);
    $checkStmt->execute();

    $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
    if (!$existing) {
        sendResponse([
            "success" => false,
            "message" => "Student not found."
        ], 404);
    }

    $fields = [];
    $params = [":student_id" => $studentId];

    if (isset($data["name"]) && $data["name"] !== "") {
        $fields[]            = "name = :name";
        $params[":name"]     = sanitizeInput($data["name"]);
    }

    if (isset($data["email"]) && $data["email"] !== "") {
        $newEmail = sanitizeInput($data["email"]);
        if (!validateEmail($newEmail)) {
            sendResponse([
                "success" => false,
                "message" => "Invalid email format."
            ], 400);
        }

        // Check email uniqueness
        $emailCheckSql  = "SELECT id FROM students WHERE email = :email AND student_id <> :student_id LIMIT 1";
        $emailCheckStmt = $db->prepare($emailCheckSql);
        $emailCheckStmt->bindValue(":email", $newEmail, PDO::PARAM_STR);
        $emailCheckStmt->bindValue(":student_id", $studentId, PDO::PARAM_STR);
        $emailCheckStmt->execute();

        if ($emailCheckStmt->fetch(PDO::FETCH_ASSOC)) {
            sendResponse([
                "success" => false,
                "message" => "Another student with this email already exists."
            ], 409);
        }

        $fields[]         = "email = :email";
        $params[":email"] = $newEmail;
    }

    if (empty($fields)) {
        sendResponse([
            "success" => false,
            "message" => "No fields to update."
        ], 400);
    }

    $sql  = "UPDATE students SET " . implode(", ", $fields) . " WHERE student_id = :student_id";
    $stmt = $db->prepare($sql);

    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_STR);
    }

    if ($stmt->execute()) {
        sendResponse([
            "success" => true,
            "message" => "Student updated successfully."
        ]);
    } else {
        sendResponse([
            "success" => false,
            "message" => "Failed to update student."
        ], 500);
    }
}

/**
 * DELETE /students?student_id=STU123
 * or JSON body: { "student_id": "STU123" }
 */
function deleteStudent(PDO $db, $studentId)
{
    if (empty($studentId)) {
        sendResponse([
            "success" => false,
            "message" => "student_id is required."
        ], 400);
    }

    // Check if student exists
    $checkSql  = "SELECT id FROM students WHERE student_id = :student_id LIMIT 1";
    $checkStmt = $db->prepare($checkSql);
    $checkStmt->bindValue(":student_id", $studentId, PDO::PARAM_STR);
    $checkStmt->execute();

    if (!$checkStmt->fetch(PDO::FETCH_ASSOC)) {
        sendResponse([
            "success" => false,
            "message" => "Student not found."
        ], 404);
    }

    // Delete
    $deleteSql  = "DELETE FROM students WHERE student_id = :student_id";
    $deleteStmt = $db->prepare($deleteSql);
    $deleteStmt->bindValue(":student_id", $studentId, PDO::PARAM_STR);

    if ($deleteStmt->execute()) {
        sendResponse([
            "success" => true,
            "message" => "Student deleted successfully."
        ]);
    } else {
        sendResponse([
            "success" => false,
            "message" => "Failed to delete student."
        ], 500);
    }
}

/**
 * POST /students?action=change_password
 * JSON body:
 *  - student_id
 *  - current_password
 *  - new_password
 */
function changePassword(PDO $db, array $data)
{
    $required = ["student_id", "current_password", "new_password"];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            sendResponse([
                "success" => false,
                "message" => "Missing required field: {$field}."
            ], 400);
        }
    }

    $studentId       = sanitizeInput($data["student_id"]);
    $currentPassword = $data["current_password"];
    $newPassword     = $data["new_password"];

    if (strlen($newPassword) < 8) {
        sendResponse([
            "success" => false,
            "message" => "New password must be at least 8 characters."
        ], 400);
    }

    // Get current password hash
    $sql  = "SELECT password FROM students WHERE student_id = :student_id LIMIT 1";
    $stmt = $db->prepare($sql);
    $stmt->bindValue(":student_id", $studentId, PDO::PARAM_STR);
    $stmt->execute();

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        sendResponse([
            "success" => false,
            "message" => "Student not found."
        ], 404);
    }

    $hash = $row["password"];
    if (!password_verify($currentPassword, $hash)) {
        sendResponse([
            "success" => false,
            "message" => "Current password is incorrect."
        ], 401);
    }

    $newHash   = password_hash($newPassword, PASSWORD_DEFAULT);
    $updateSql = "UPDATE students SET password = :password WHERE student_id = :student_id";
    $update    = $db->prepare($updateSql);
    $update->bindValue(":password", $newHash, PDO::PARAM_STR);
    $update->bindValue(":student_id", $studentId, PDO::PARAM_STR);

    if ($update->execute()) {
        sendResponse([
            "success" => true,
            "message" => "Password updated successfully."
        ]);
    } else {
        sendResponse([
            "success" => false,
            "message" => "Failed to update password."
        ], 500);
    }
}

// -------------------------------------------------------------
// MAIN ROUTER
// -------------------------------------------------------------
try {
    if ($method === "GET") {
        if (isset($_GET["student_id"]) && $_GET["student_id"] !== "") {
            getStudentById($db, $_GET["student_id"]);
        } else {
            getStudents($db);
        }

    } elseif ($method === "POST") {
        $action = isset($_GET["action"]) ? $_GET["action"] : "";

        if ($action === "change_password") {
            changePassword($db, $inputData);
        } else {
            createStudent($db, $inputData);
        }

    } elseif ($method === "PUT") {
        updateStudent($db, $inputData);

    } elseif ($method === "DELETE") {
        $studentId = null;

        if (isset($_GET["student_id"]) && $_GET["student_id"] !== "") {
            $studentId = $_GET["student_id"];
        } elseif (isset($inputData["student_id"])) {
            $studentId = $inputData["student_id"];
        }

        deleteStudent($db, $studentId);

    } else {
        sendResponse([
            "success" => false,
            "message" => "Method not allowed."
        ], 405);
    }
} catch (PDOException $e) {
    sendResponse([
        "success" => false,
        "message" => "Database error occurred."
    ], 500);
} catch (Exception $e) {
    sendResponse([
        "success" => false,
        "message" => "Server error occurred."
    ], 500);
}

// -------------------------------------------------------------
// HELPER FUNCTIONS
// -------------------------------------------------------------
function sendResponse($data, int $statusCode = 200)
{
    http_response_code($statusCode);
    echo json_encode($data);
    exit;
}

function validateEmail($email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function sanitizeInput($data): string
{
    $data = trim($data);
    $data = strip_tags($data);
    $data = htmlspecialchars($data, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
    return $data;
}

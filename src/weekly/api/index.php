<?php

// Start session at the very beginning
session_start();

// Set response headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include database connection
require_once '../common/Database.php';

// Initialize database
$database = new Database();
$db = $database->getConnection();

// Get request method
$method = $_SERVER['REQUEST_METHOD'];

// Get request body
$requestBody = file_get_contents('php://input');
$data = json_decode($requestBody, true);

// Get resource type
$resource = isset($_GET['resource']) ? $_GET['resource'] : 'weeks';

// Initialize session data for tracking user activity
if (!isset($_SESSION['user_activity'])) {
    $_SESSION['user_activity'] = [];
}

// Store last access time in session
$_SESSION['last_access'] = time();


// ============================================================
// WEEKS FUNCTIONS
// ============================================================

function getAllWeeks($db) {
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    $sort = isset($_GET['sort']) ? $_GET['sort'] : 'start_date';
    $order = isset($_GET['order']) ? $_GET['order'] : 'asc';
    
    // Track user activity in session
    $_SESSION['user_activity'][] = [
        'action' => 'view_all_weeks',
        'timestamp' => time()
    ];
    
    // Validate sort field
    $allowedSorts = ['title', 'start_date', 'created_at'];
    if (!isValidSortField($sort, $allowedSorts)) {
        $sort = 'start_date';
    }
    
    // Validate order
    $order = strtolower($order);
    if (!in_array($order, ['asc', 'desc'])) {
        $order = 'asc';
    }
    
    // Build SQL query
    $sql = "SELECT week_id, title, start_date, description, links, created_at FROM weeks";
    
    if (!empty($search)) {
        $sql .= " WHERE title LIKE ? OR description LIKE ?";
    }
    
    $sql .= " ORDER BY $sort $order";
    
    try {
        $stmt = $db->prepare($sql);
        
        if (!empty($search)) {
            $searchParam = "%$search%";
            $stmt->bindParam(1, $searchParam);
            $stmt->bindParam(2, $searchParam);
        }
        
        $stmt->execute();
        $weeks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Decode JSON links
        foreach ($weeks as &$week) {
            $week['links'] = json_decode($week['links'], true);
        }
        
        sendResponse([
            'success' => true,
            'data' => $weeks
        ]);
        
    } catch (PDOException $e) {
        sendError('Error fetching weeks', 500);
    }
}

function getWeekById($db, $weekId) {
    if (empty($weekId)) {
        sendError('Week ID is required', 400);
        return;
    }
    
    // Track in session
    $_SESSION['user_activity'][] = [
        'action' => 'view_week',
        'week_id' => $weekId,
        'timestamp' => time()
    ];
    
    $sql = "SELECT week_id, title, start_date, description, links, created_at FROM weeks WHERE week_id = ?";
    
    try {
        $stmt = $db->prepare($sql);
        $stmt->bindParam(1, $weekId);
        $stmt->execute();
        
        $week = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($week) {
            $week['links'] = json_decode($week['links'], true);
            sendResponse([
                'success' => true,
                'data' => $week
            ]);
        } else {
            sendError('Week not found', 404);
        }
        
    } catch (PDOException $e) {
        sendError('Error fetching week', 500);
    }
}

function createWeek($db, $data) {
    // Validate required fields
    if (empty($data['week_id']) || empty($data['title']) || 
        empty($data['start_date']) || empty($data['description'])) {
        sendError('Missing required fields: week_id, title, start_date, description', 400);
        return;
    }
    
    // Sanitize inputs
    $weekId = sanitizeInput($data['week_id']);
    $title = sanitizeInput($data['title']);
    $startDate = sanitizeInput($data['start_date']);
    $description = sanitizeInput($data['description']);
    
    // Validate date format
    if (!validateDate($startDate)) {
        sendError('Invalid date format. Use YYYY-MM-DD', 400);
        return;
    }
    
    // Check if week_id already exists
    $checkSql = "SELECT week_id FROM weeks WHERE week_id = ?";
    try {
        $checkStmt = $db->prepare($checkSql);
        $checkStmt->bindParam(1, $weekId);
        $checkStmt->execute();
        
        if ($checkStmt->fetch()) {
            sendError('Week ID already exists', 409);
            return;
        }
    } catch (PDOException $e) {
        sendError('Error checking week ID', 500);
        return;
    }
    
    // Process links
    $links = isset($data['links']) && is_array($data['links']) 
        ? json_encode($data['links']) 
        : json_encode([]);
    
    // Insert new week
    $sql = "INSERT INTO weeks (week_id, title, start_date, description, links) VALUES (?, ?, ?, ?, ?)";
    
    try {
        $stmt = $db->prepare($sql);
        $stmt->bindParam(1, $weekId);
        $stmt->bindParam(2, $title);
        $stmt->bindParam(3, $startDate);
        $stmt->bindParam(4, $description);
        $stmt->bindParam(5, $links);
        
        if ($stmt->execute()) {
            // Track in session
            $_SESSION['user_activity'][] = [
                'action' => 'create_week',
                'week_id' => $weekId,
                'timestamp' => time()
            ];
            
            sendResponse([
                'success' => true,
                'message' => 'Week created successfully',
                'data' => [
                    'week_id' => $weekId,
                    'title' => $title,
                    'start_date' => $startDate,
                    'description' => $description,
                    'links' => json_decode($links, true)
                ]
            ], 201);
        } else {
            sendError('Failed to create week', 500);
        }
        
    } catch (PDOException $e) {
        sendError('Database error', 500);
    }
}

function updateWeek($db, $data) {
    if (empty($data['week_id'])) {
        sendError('Week ID is required', 400);
        return;
    }
    
    $weekId = sanitizeInput($data['week_id']);
    
    // Check if week exists
    $checkSql = "SELECT week_id FROM weeks WHERE week_id = ?";
    try {
        $checkStmt = $db->prepare($checkSql);
        $checkStmt->bindParam(1, $weekId);
        $checkStmt->execute();
        
        if (!$checkStmt->fetch()) {
            sendError('Week not found', 404);
            return;
        }
    } catch (PDOException $e) {
        sendError('Error checking week', 500);
        return;
    }
    
    // Build dynamic update query
    $setClauses = [];
    $values = [];
    
    if (isset($data['title'])) {
        $setClauses[] = "title = ?";
        $values[] = sanitizeInput($data['title']);
    }
    
    if (isset($data['start_date'])) {
        if (!validateDate($data['start_date'])) {
            sendError('Invalid date format. Use YYYY-MM-DD', 400);
            return;
        }
        $setClauses[] = "start_date = ?";
        $values[] = sanitizeInput($data['start_date']);
    }
    
    if (isset($data['description'])) {
        $setClauses[] = "description = ?";
        $values[] = sanitizeInput($data['description']);
    }
    
    if (isset($data['links'])) {
        $setClauses[] = "links = ?";
        $values[] = json_encode($data['links']);
    }
    
    if (empty($setClauses)) {
        sendError('No fields to update', 400);
        return;
    }
    
    $setClauses[] = "updated_at = CURRENT_TIMESTAMP";
    
    $sql = "UPDATE weeks SET " . implode(', ', $setClauses) . " WHERE week_id = ?";
    $values[] = $weekId;
    
    try {
        $stmt = $db->prepare($sql);
        
        foreach ($values as $index => $value) {
            $stmt->bindValue($index + 1, $value);
        }
        
        if ($stmt->execute()) {
            // Track in session
            $_SESSION['user_activity'][] = [
                'action' => 'update_week',
                'week_id' => $weekId,
                'timestamp' => time()
            ];
            
            // Return updated week
            getWeekById($db, $weekId);
        } else {
            sendError('Failed to update week', 500);
        }
        
    } catch (PDOException $e) {
        sendError('Database error', 500);
    }
}

function deleteWeek($db, $weekId) {
    if (empty($weekId)) {
        sendError('Week ID is required', 400);
        return;
    }
    
    // Check if week exists
    $checkSql = "SELECT week_id FROM weeks WHERE week_id = ?";
    try {
        $checkStmt = $db->prepare($checkSql);
        $checkStmt->bindParam(1, $weekId);
        $checkStmt->execute();
        
        if (!$checkStmt->fetch()) {
            sendError('Week not found', 404);
            return;
        }
    } catch (PDOException $e) {
        sendError('Error checking week', 500);
        return;
    }
    
    // Delete associated comments first
    $deleteCommentsSql = "DELETE FROM comments WHERE week_id = ?";
    try {
        $stmt = $db->prepare($deleteCommentsSql);
        $stmt->bindParam(1, $weekId);
        $stmt->execute();
    } catch (PDOException $e) {
        sendError('Error deleting comments', 500);
        return;
    }
    
    // Delete week
    $deleteWeekSql = "DELETE FROM weeks WHERE week_id = ?";
    try {
        $stmt = $db->prepare($deleteWeekSql);
        $stmt->bindParam(1, $weekId);
        
        if ($stmt->execute()) {
            // Track in session
            $_SESSION['user_activity'][] = [
                'action' => 'delete_week',
                'week_id' => $weekId,
                'timestamp' => time()
            ];
            
            sendResponse([
                'success' => true,
                'message' => 'Week and associated comments deleted successfully'
            ]);
        } else {
            sendError('Failed to delete week', 500);
        }
        
    } catch (PDOException $e) {
        sendError('Database error', 500);
    }
}


// ============================================================
// COMMENTS FUNCTIONS
// ============================================================

function getCommentsByWeek($db, $weekId) {
    if (empty($weekId)) {
        sendError('Week ID is required', 400);
        return;
    }
    
    // Track in session
    $_SESSION['user_activity'][] = [
        'action' => 'view_comments',
        'week_id' => $weekId,
        'timestamp' => time()
    ];
    
    $sql = "SELECT id, week_id, author, text, created_at FROM comments WHERE week_id = ? ORDER BY created_at ASC";
    
    try {
        $stmt = $db->prepare($sql);
        $stmt->bindParam(1, $weekId);
        $stmt->execute();
        
        $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        sendResponse([
            'success' => true,
            'data' => $comments
        ]);
        
    } catch (PDOException $e) {
        sendError('Error fetching comments', 500);
    }
}

function createComment($db, $data) {
    if (empty($data['week_id']) || empty($data['author']) || empty($data['text'])) {
        sendError('Missing required fields: week_id, author, text', 400);
        return;
    }
    
    $weekId = sanitizeInput($data['week_id']);
    $author = sanitizeInput($data['author']);
    $text = sanitizeInput($data['text']);
    
    if (empty($text)) {
        sendError('Comment text cannot be empty', 400);
        return;
    }
    
    // Check if week exists
    $checkSql = "SELECT week_id FROM weeks WHERE week_id = ?";
    try {
        $checkStmt = $db->prepare($checkSql);
        $checkStmt->bindParam(1, $weekId);
        $checkStmt->execute();
        
        if (!$checkStmt->fetch()) {
            sendError('Week not found', 404);
            return;
        }
    } catch (PDOException $e) {
        sendError('Error checking week', 500);
        return;
    }
    
    // Insert comment
    $sql = "INSERT INTO comments (week_id, author, text) VALUES (?, ?, ?)";
    
    try {
        $stmt = $db->prepare($sql);
        $stmt->bindParam(1, $weekId);
        $stmt->bindParam(2, $author);
        $stmt->bindParam(3, $text);
        
        if ($stmt->execute()) {
            $commentId = $db->lastInsertId();
            
            // Track in session
            $_SESSION['user_activity'][] = [
                'action' => 'create_comment',
                'week_id' => $weekId,
                'comment_id' => $commentId,
                'timestamp' => time()
            ];
            
            sendResponse([
                'success' => true,
                'message' => 'Comment created successfully',
                'data' => [
                    'id' => $commentId,
                    'week_id' => $weekId,
                    'author' => $author,
                    'text' => $text
                ]
            ], 201);
        } else {
            sendError('Failed to create comment', 500);
        }
        
    } catch (PDOException $e) {
        sendError('Database error', 500);
    }
}

function deleteComment($db, $commentId) {
    if (empty($commentId)) {
        sendError('Comment ID is required', 400);
        return;
    }
    
    // Check if comment exists
    $checkSql = "SELECT id FROM comments WHERE id = ?";
    try {
        $checkStmt = $db->prepare($checkSql);
        $checkStmt->bindParam(1, $commentId);
        $checkStmt->execute();
        
        if (!$checkStmt->fetch()) {
            sendError('Comment not found', 404);
            return;
        }
    } catch (PDOException $e) {
        sendError('Error checking comment', 500);
        return;
    }
    
    // Delete comment
    $sql = "DELETE FROM comments WHERE id = ?";
    
    try {
        $stmt = $db->prepare($sql);
        $stmt->bindParam(1, $commentId);
        
        if ($stmt->execute()) {
            // Track in session
            $_SESSION['user_activity'][] = [
                'action' => 'delete_comment',
                'comment_id' => $commentId,
                'timestamp' => time()
            ];
            
            sendResponse([
                'success' => true,
                'message' => 'Comment deleted successfully'
            ]);
        } else {
            sendError('Failed to delete comment', 500);
        }
        
    } catch (PDOException $e) {
        sendError('Database error', 500);
    }
}


// ============================================================
// ROUTING
// ============================================================

try {
    if ($resource === 'weeks') {
        if ($method === 'GET') {
            if (isset($_GET['week_id'])) {
                getWeekById($db, $_GET['week_id']);
            } else {
                getAllWeeks($db);
            }
        } elseif ($method === 'POST') {
            createWeek($db, $data);
        } elseif ($method === 'PUT') {
            updateWeek($db, $data);
        } elseif ($method === 'DELETE') {
            $weekId = isset($_GET['week_id']) ? $_GET['week_id'] : $data['week_id'];
            deleteWeek($db, $weekId);
        } else {
            sendError('Method not allowed', 405);
        }
    } elseif ($resource === 'comments') {
        if ($method === 'GET') {
            if (isset($_GET['week_id'])) {
                getCommentsByWeek($db, $_GET['week_id']);
            } else {
                sendError('Week ID is required', 400);
            }
        } elseif ($method === 'POST') {
            createComment($db, $data);
        } elseif ($method === 'DELETE') {
            $commentId = isset($_GET['id']) ? $_GET['id'] : $data['id'];
            deleteComment($db, $commentId);
        } else {
            sendError('Method not allowed', 405);
        }
    } else {
        sendError('Invalid resource. Use "weeks" or "comments"', 400);
    }
    
} catch (PDOException $e) {
    error_log($e->getMessage());
    sendError('Database error occurred', 500);
} catch (Exception $e) {
    error_log($e->getMessage());
    sendError('An error occurred', 500);
}


// ============================================================
// HELPER FUNCTIONS
// ============================================================

function sendResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data);
    exit();
}

function sendError($message, $statusCode = 400) {
    sendResponse([
        'success' => false,
        'error' => $message
    ], $statusCode);
}

function validateDate($date) {
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

function sanitizeInput($data) {
    $data = trim($data);
    $data = strip_tags($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

function isValidSortField($field, $allowedFields) {
    return in_array($field, $allowedFields);
}

?>

<?php
/**
 * Course Resources API
 */

// ============================================================================
// HEADERS AND INITIALIZATION
// ============================================================================

// TODO: Set headers for JSON response and CORS
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// TODO: Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// TODO: Include the database connection class
require_once '../config/Database.php';

// TODO: Get the PDO database connection
$database = new Database();
$db = $database->getConnection();

// TODO: Get the HTTP request method
$method = $_SERVER['REQUEST_METHOD'];

// TODO: Get the request body for POST and PUT requests
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// TODO: Parse query parameters
$action = isset($_GET['action']) ? $_GET['action'] : null;
$id = isset($_GET['id']) ? $_GET['id'] : null;
$resource_id = isset($_GET['resource_id']) ? $_GET['resource_id'] : null;
$comment_id = isset($_GET['comment_id']) ? $_GET['comment_id'] : null;

// ============================================================================
// RESOURCE FUNCTIONS
// ============================================================================

function getAllResources($db) {
    // TODO: Initialize the base SQL query
    $sql = "SELECT id, title, description, link, created_at FROM resources";
    
    // TODO: Check if search parameter exists
    if (isset($_GET['search'])) {
        $sql .= " WHERE title LIKE :search OR description LIKE :search";
    }
    
    // TODO: Check if sort parameter exists and validate it
    $sort = 'created_at';
    if (isset($_GET['sort']) && in_array($_GET['sort'], ['title', 'created_at'])) {
        $sort = $_GET['sort'];
    }
    
    // TODO: Check if order parameter exists and validate it
    $order = 'desc';
    if (isset($_GET['order']) && in_array(strtolower($_GET['order']), ['asc', 'desc'])) {
        $order = strtoupper($_GET['order']);
    }
    
    // TODO: Add ORDER BY clause to query
    $sql .= " ORDER BY $sort $order";
    
    // TODO: Prepare the SQL query using PDO
    $stmt = $db->prepare($sql);
    
    // TODO: If search parameter was used, bind the search parameter
    if (isset($_GET['search'])) {
        $search = '%' . $_GET['search'] . '%';
        $stmt->bindParam(':search', $search);
    }
    
    // TODO: Execute the query
    $stmt->execute();
    
    // TODO: Fetch all results as an associative array
    $resources = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // TODO: Return JSON response with success status and data
    sendResponse(['success' => true, 'data' => $resources]);
}

function getResourceById($db, $resourceId) {
    // TODO: Validate that resource ID is provided and is numeric
    if (!$resourceId || !is_numeric($resourceId)) {
        sendResponse(['success' => false, 'message' => 'Invalid resource ID'], 400);
    }
    
    // TODO: Prepare SQL query to select resource by id
    $sql = "SELECT id, title, description, link, created_at FROM resources WHERE id = ?";
    $stmt = $db->prepare($sql);
    
    // TODO: Bind the resource_id parameter
    $stmt->bindParam(1, $resourceId);
    
    // TODO: Execute the query
    $stmt->execute();
    
    // TODO: Fetch the result as an associative array
    $resource = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // TODO: Check if resource exists
    if ($resource) {
        sendResponse(['success' => true, 'data' => $resource]);
    } else {
        sendResponse(['success' => false, 'message' => 'Resource not found'], 404);
    }
}

function createResource($db, $data) {
    // TODO: Validate required fields
    $validation = validateRequiredFields($data, ['title', 'link']);
    if (!$validation['valid']) {
        sendResponse(['success' => false, 'message' => 'Missing required fields'], 400);
    }
    
    // TODO: Sanitize input data
    $title = sanitizeInput(trim($data['title']));
    $link = trim($data['link']);
    
    // TODO: Validate URL format for link
    if (!validateUrl($link)) {
        sendResponse(['success' => false, 'message' => 'Invalid URL'], 400);
    }
    
    // TODO: Set default value for description if not provided
    $description = isset($data['description']) ? sanitizeInput(trim($data['description'])) : '';
    
    // TODO: Prepare INSERT query
    $sql = "INSERT INTO resources (title, description, link) VALUES (?, ?, ?)";
    $stmt = $db->prepare($sql);
    
    // TODO: Bind parameters
    $stmt->bindParam(1, $title);
    $stmt->bindParam(2, $description);
    $stmt->bindParam(3, $link);
    
    // TODO: Execute the query
    $stmt->execute();
    
    // TODO: Check if insert was successful
    if ($stmt->rowCount() > 0) {
        $lastId = $db->lastInsertId();
        sendResponse(['success' => true, 'message' => 'Resource created', 'id' => $lastId], 201);
    } else {
        sendResponse(['success' => false, 'message' => 'Failed to create resource'], 500);
    }
}

function updateResource($db, $data) {
    // TODO: Validate that resource ID is provided
    if (!isset($data['id'])) {
        sendResponse(['success' => false, 'message' => 'Resource ID required'], 400);
    }
    
    $resourceId = $data['id'];
    
    // TODO: Check if resource exists
    $checkSql = "SELECT id FROM resources WHERE id = ?";
    $checkStmt = $db->prepare($checkSql);
    $checkStmt->bindParam(1, $resourceId);
    $checkStmt->execute();
    if (!$checkStmt->fetch()) {
        sendResponse(['success' => false, 'message' => 'Resource not found'], 404);
    }
    
    // TODO: Build UPDATE query dynamically based on provided fields
    $fields = [];
    $values = [];
    
    if (isset($data['title'])) {
        $fields[] = 'title = ?';
        $values[] = sanitizeInput(trim($data['title']));
    }
    if (isset($data['description'])) {
        $fields[] = 'description = ?';
        $values[] = sanitizeInput(trim($data['description']));
    }
    if (isset($data['link'])) {
        // TODO: If link is being updated, validate URL format
        if (!validateUrl($data['link'])) {
            sendResponse(['success' => false, 'message' => 'Invalid URL'], 400);
        }
        $fields[] = 'link = ?';
        $values[] = trim($data['link']);
    }
    
    // TODO: If no fields to update, return error response with 400 status
    if (empty($fields)) {
        sendResponse(['success' => false, 'message' => 'No fields to update'], 400);
    }
    
    // TODO: Build the complete UPDATE SQL query
    $sql = "UPDATE resources SET " . implode(', ', $fields) . " WHERE id = ?";
    $values[] = $resourceId;
    
    // TODO: Prepare the query
    $stmt = $db->prepare($sql);
    
    // TODO: Bind parameters dynamically
    foreach ($values as $index => $value) {
        $stmt->bindValue($index + 1, $value);
    }
    
    // TODO: Execute the query
    $stmt->execute();
    
    // TODO: Check if update was successful
    if ($stmt->rowCount() > 0) {
        sendResponse(['success' => true, 'message' => 'Resource updated']);
    } else {
        sendResponse(['success' => false, 'message' => 'Failed to update resource'], 500);
    }
}

function deleteResource($db, $resourceId) {
    // TODO: Validate that resource ID is provided and is numeric
    if (!$resourceId || !is_numeric($resourceId)) {
        sendResponse(['success' => false, 'message' => 'Invalid resource ID'], 400);
    }
    
    // TODO: Check if resource exists
    $checkSql = "SELECT id FROM resources WHERE id = ?";
    $checkStmt = $db->prepare($checkSql);
    $checkStmt->bindParam(1, $resourceId);
    $checkStmt->execute();
    if (!$checkStmt->fetch()) {
        sendResponse(['success' => false, 'message' => 'Resource not found'], 404);
    }
    
    // TODO: Begin a transaction (for data integrity)
    $db->beginTransaction();
    
    try {
        // TODO: First, delete all associated comments
        $deleteCommentsSql = "DELETE FROM comments WHERE resource_id = ?";
        $deleteCommentsStmt = $db->prepare($deleteCommentsSql);
        
        // TODO: Bind resource_id and execute
        $deleteCommentsStmt->bindParam(1, $resourceId);
        $deleteCommentsStmt->execute();
        
        // TODO: Then, delete the resource
        $deleteResourceSql = "DELETE FROM resources WHERE id = ?";
        $deleteResourceStmt = $db->prepare($deleteResourceSql);
        
        // TODO: Bind resource_id and execute
        $deleteResourceStmt->bindParam(1, $resourceId);
        $deleteResourceStmt->execute();
        
        // TODO: Commit the transaction
        $db->commit();
        
        // TODO: Return success response with 200 status
        sendResponse(['success' => true, 'message' => 'Resource deleted']);
        
    } catch (Exception $e) {
        // TODO: Rollback the transaction on error
        $db->rollBack();
        
        // TODO: Return error response with 500 status
        sendResponse(['success' => false, 'message' => 'Failed to delete resource'], 500);
    }
}

// ============================================================================
// COMMENT FUNCTIONS
// ============================================================================

function getCommentsByResourceId($db, $resourceId) {
    // TODO: Validate that resource_id is provided and is numeric
    if (!$resourceId || !is_numeric($resourceId)) {
        sendResponse(['success' => false, 'message' => 'Invalid resource ID'], 400);
    }
    
    // TODO: Prepare SQL query to select comments for the resource
    $sql = "SELECT id, resource_id, author, text, created_at FROM comments WHERE resource_id = ? ORDER BY created_at ASC";
    $stmt = $db->prepare($sql);
    
    // TODO: Bind the resource_id parameter
    $stmt->bindParam(1, $resourceId);
    
    // TODO: Execute the query
    $stmt->execute();
    
    // TODO: Fetch all results as an associative array
    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // TODO: Return success response with comments data
    sendResponse(['success' => true, 'data' => $comments]);
}

function createComment($db, $data) {
    // TODO: Validate required fields
    $validation = validateRequiredFields($data, ['resource_id', 'author', 'text']);
    if (!$validation['valid']) {
        sendResponse(['success' => false, 'message' => 'Missing required fields'], 400);
    }
    
    // TODO: Validate that resource_id is numeric
    if (!is_numeric($data['resource_id'])) {
        sendResponse(['success' => false, 'message' => 'Invalid resource ID'], 400);
    }
    
    $resourceId = $data['resource_id'];
    
    // TODO: Check if the resource exists
    $checkSql = "SELECT id FROM resources WHERE id = ?";
    $checkStmt = $db->prepare($checkSql);
    $checkStmt->bindParam(1, $resourceId);
    $checkStmt->execute();
    if (!$checkStmt->fetch()) {
        sendResponse(['success' => false, 'message' => 'Resource not found'], 404);
    }
    
    // TODO: Sanitize input data
    $author = sanitizeInput(trim($data['author']));
    $text = sanitizeInput(trim($data['text']));
    
    // TODO: Prepare INSERT query
    $sql = "INSERT INTO comments (resource_id, author, text) VALUES (?, ?, ?)";
    $stmt = $db->prepare($sql);
    
    // TODO: Bind parameters
    $stmt->bindParam(1, $resourceId);
    $stmt->bindParam(2, $author);
    $stmt->bindParam(3, $text);
    
    // TODO: Execute the query
    $stmt->execute();
    
    // TODO: Check if insert was successful
    if ($stmt->rowCount() > 0) {
        $lastId = $db->lastInsertId();
        sendResponse(['success' => true, 'message' => 'Comment created', 'id' => $lastId], 201);
    } else {
        sendResponse(['success' => false, 'message' => 'Failed to create comment'], 500);
    }
}

function deleteComment($db, $commentId) {
    // TODO: Validate that comment_id is provided and is numeric
    if (!$commentId || !is_numeric($commentId)) {
        sendResponse(['success' => false, 'message' => 'Invalid comment ID'], 400);
    }
    
    // TODO: Check if comment exists
    $checkSql = "SELECT id FROM comments WHERE id = ?";
    $checkStmt = $db->prepare($checkSql);
    $checkStmt->bindParam(1, $commentId);
    $checkStmt->execute();
    if (!$checkStmt->fetch()) {
        sendResponse(['success' => false, 'message' => 'Comment not found'], 404);
    }
    
    // TODO: Prepare DELETE query
    $sql = "DELETE FROM comments WHERE id = ?";
    $stmt = $db->prepare($sql);
    
    // TODO: Bind the comment_id parameter
    $stmt->bindParam(1, $commentId);
    
    // TODO: Execute the query
    $stmt->execute();
    
    // TODO: Check if delete was successful
    if ($stmt->rowCount() > 0) {
        sendResponse(['success' => true, 'message' => 'Comment deleted']);
    } else {
        sendResponse(['success' => false, 'message' => 'Failed to delete comment'], 500);
    }
}

// ============================================================================
// MAIN REQUEST ROUTER
// ============================================================================

try {
    // TODO: Route the request based on HTTP method and action parameter
    
    if ($method === 'GET') {
        // TODO: Check the action parameter to determine which function to call
        
        // TODO: If action is 'comments', get comments for a resource
        if ($action === 'comments') {
            getCommentsByResourceId($db, $resource_id);
        }
        // TODO: If id parameter exists, get single resource
        elseif (isset($_GET['id'])) {
            getResourceById($db, $id);
        }
        // TODO: Otherwise, get all resources
        else {
            getAllResources($db);
        }
        
    } elseif ($method === 'POST') {
        // TODO: Check the action parameter to determine which function to call
        
        // TODO: If action is 'comment', create a new comment
        if ($action === 'comment') {
            createComment($db, $data);
        }
        // TODO: Otherwise, create a new resource
        else {
            createResource($db, $data);
        }
        
    } elseif ($method === 'PUT') {
        // TODO: Update a resource
        updateResource($db, $data);
        
    } elseif ($method === 'DELETE') {
        // TODO: Check the action parameter to determine which function to call
        
        // TODO: If action is 'delete_comment', delete a comment
        if ($action === 'delete_comment') {
            $commentIdToDelete = $comment_id ? $comment_id : (isset($data['comment_id']) ? $data['comment_id'] : null);
            deleteComment($db, $commentIdToDelete);
        }
        // TODO: Otherwise, delete a resource
        else {
            $resourceIdToDelete = $id ? $id : (isset($data['id']) ? $data['id'] : null);
            deleteResource($db, $resourceIdToDelete);
        }
        
    } else {
        // TODO: Return error for unsupported methods
        sendResponse(['success' => false, 'message' => 'Method not allowed'], 405);
    }
    
} catch (PDOException $e) {
    // TODO: Handle database errors
    error_log($e->getMessage());
    sendResponse(['success' => false, 'message' => 'Database error'], 500);
    
} catch (Exception $e) {
    // TODO: Handle general errors
    error_log($e->getMessage());
    sendResponse(['success' => false, 'message' => 'Server error'], 500);
}

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

function sendResponse($data, $statusCode = 200) {
    // TODO: Set HTTP response code using http_response_code()
    http_response_code($statusCode);
    
    // TODO: Ensure data is an array
    if (!is_array($data)) {
        $data = ['data' => $data];
    }
    
    // TODO: Echo JSON encoded data
    echo json_encode($data, JSON_PRETTY_PRINT);
    
    // TODO: Exit to prevent further execution
    exit;
}

function validateUrl($url) {
    // TODO: Use filter_var with FILTER_VALIDATE_URL
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

function sanitizeInput($data) {
    // TODO: Trim whitespace using trim()
    $data = trim($data);
    
    // TODO: Strip HTML tags using strip_tags()
    $data = strip_tags($data);
    
    // TODO: Convert special characters using htmlspecialchars()
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    
    // TODO: Return sanitized data
    return $data;
}

function validateRequiredFields($data, $requiredFields) {
    // TODO: Initialize empty array for missing fields
    $missing = [];
    
    // TODO: Loop through required fields
    foreach ($requiredFields as $field) {
        // TODO: Check if each field exists in data and is not empty
        if (!isset($data[$field]) || empty(trim($data[$field]))) {
            $missing[] = $field;
        }
    }
    
    // TODO: Return result array
    return ['valid' => count($missing) === 0, 'missing' => $missing];
}

?>

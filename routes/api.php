<?php

require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../controllers/SubmissionController.php";

header("Content-Type: application/json");

$database = new Database();
$db = $database->connect();
$controller = new SubmissionController($db);

$method = $_SERVER["REQUEST_METHOD"];
$path = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);

// Route: POST /submissions
if ($method === "POST" && $path === "/submissions") {
    $controller->createSubmission();
    exit;
}

// Route: GET /forms/{form_id}/submissions
if ($method === "GET" && preg_match("#^/forms/([^/]+)/submissions$#", $path, $matches)) {
    $formId = urldecode($matches[1]);
    $controller->getSubmissions($formId);
    exit;
}

// Fallback Route Not Found
http_response_code(404);
echo json_encode(["success" => false, "message" => "Route not found"]);
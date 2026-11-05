<?php

class SubmissionController {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    // POST /submissions
    public function createSubmission() {
        $raw = file_get_contents("php://input");
        
        try {
            $input = json_decode($raw, false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Invalid JSON"]);
            return;
        }

        // Validate required fields
        if (
            !is_object($input) ||
            !isset($input->form_id) || !is_string($input->form_id) || trim($input->form_id) === "" ||
            !isset($input->idempotency_key) || !is_string($input->idempotency_key) || trim($input->idempotency_key) === "" ||
            !isset($input->email) || !is_string($input->email) || !filter_var($input->email, FILTER_VALIDATE_EMAIL) ||
            !isset($input->data) || !is_object($input->data)
        ) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Invalid input or validation failed"]);
            return;
        

        $formId = trim($input->form_id);
        $key = trim($input->idempotency_key);
        $email = $input->email;
        
        try {
            $data = json_encode($input->data, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Invalid JSON payload format inside data"]);
            return;
        }

        try {
            $sql = "INSERT INTO submissions (form_id, idempotency_key, email, data) VALUES (?, ?, ?, ?)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$formId, $key, $email, $data]);

            http_response_code(201);
            echo json_encode([
                "success" => true,
                "duplicate" => false,
                "message" => "Submission created",
                "id" => $this->db->lastInsertId()
            ]);

        } catch (PDOException $e) {
            // Check for MySQL Duplicate Entry Error (1062 or SQLSTATE 23000)
            if (isset($e->errorInfo[1]) && (int)$e->errorInfo[1] === 1062) {
                $stmt = $this->db->prepare("SELECT * FROM submissions WHERE idempotency_key = ?");
                $stmt->execute([$key]);
                $existing = $stmt->fetch();

                if ($existing) {
                    if (isset($existing['data'])) {
                        $existing['data'] = json_decode($existing['data']);
                    }
                    http_response_code(200);
                    echo json_encode([
                        "success" => true,
                        "duplicate" => true,
                        "message" => "Existing submission returned",
                        "submission" => $existing
                    ]);
                    return;
                }
            }

            http_response_code(500);
            echo json_encode(["success" => false, "message" => "Database insert error"]);
        }
    }

    // GET /forms/{form_id}/submissions
    public function getSubmissions($formId) {
        $page = max(1, (int)($_GET["page"] ?? 1));
        $limit = 10;
        $offset = ($page - 1) * $limit;

        try {
            // Count total records for pagination
            $countStmt = $this->db->prepare("SELECT COUNT(*) FROM submissions WHERE form_id = ?");
            $countStmt->execute([$formId]);
            $total = (int)$countStmt->fetchColumn();

            // Fetch paginated dataset
            $sql = "SELECT * FROM submissions WHERE form_id = ? ORDER BY id DESC LIMIT $limit OFFSET $offset";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$formId]);
            $records = $stmt->fetchAll();

            // Decode JSON data column back to objects for clean JSON response
            foreach ($records as &$record) {
                if (isset($record['data'])) {
                    $record['data'] = json_decode($record['data']);
                }
            }

            http_response_code(200);
            echo json_encode([
                "success" => true,
                "data" => $records,
                "pagination" => [
                    "current_page" => $page,
                    "per_page" => $limit,
                    "total" => $total,
                    "last_page" => (int)ceil($total / $limit)
                ]
            ]);

        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(["success" => false, "message" => "Unable to retrieve submissions"]);
        }
    }
}}
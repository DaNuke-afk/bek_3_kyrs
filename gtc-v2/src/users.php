<?php
include 'config.php';

$action = $_GET['action'] ?? '';

try {
    if ($action === 'get_balance') {
        if (!isset($_GET['user_id'])) throw new Exception("No user_id provided");
        
        $user_id = intval($_GET['user_id']);
        $stmt = $conn->prepare("SELECT balance FROM users WHERE id = ?");
        if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
        
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            echo json_encode(["balance" => 0.00]);
        } else {
            $row = $result->fetch_assoc();
            echo json_encode(["balance" => floatval($row['balance'])]);
        }
    }
    elseif ($action === 'topup' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data) throw new Exception("Invalid JSON input");
        
        $amount = floatval($data['amount']);
        $user_id = intval($data['user_id']);

        if ($amount <= 0) throw new Exception("Invalid amount");

        $stmt = $conn->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
        $stmt->bind_param("di", $amount, $user_id);

        if ($stmt->execute()) {
            echo json_encode(["status" => "success", "message" => "Balance updated"]);
        } else {
            throw new Exception("Database error");
        }
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>
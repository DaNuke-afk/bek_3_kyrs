<?php
include 'config.php';

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        $whereClause = "";
        if (isset($_GET['owner_id'])) {
            $owner_id = intval($_GET['owner_id']);
            $whereClause = "WHERE items.owner_id = $owner_id";
        }
        
        $sql = "SELECT items.*, users.username as owner_name 
                FROM items 
                LEFT JOIN users ON items.owner_id = users.id 
                $whereClause
                ORDER BY items.id DESC";
        
        $result = $conn->query($sql);
        if (!$result) throw new Exception($conn->error);

        $items = [];
        while ($row = $result->fetch_assoc()) {
            $row['price'] = floatval($row['price']);
            $row['id'] = intval($row['id']);
            $row['owner_id'] = intval($row['owner_id']);
            $row['image_url'] = isset($row['image_url']) ? $row['image_url'] : 'assets/default.png';
            $items[] = $row;
        }
        echo json_encode($items);
    }

    elseif ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) throw new Exception("Invalid JSON");

        $name = $conn->real_escape_string($data['name']);
        $type = $conn->real_escape_string($data['type'] ?? 'rifles');
        $rarity = $conn->real_escape_string($data['rarity'] ?? 'common');
        $wear = $conn->real_escape_string($data['wear'] ?? 'Field-Tested');
        $price = floatval($data['price'] ?? 0);
        $owner_id = intval($data['owner_id']);
        
        $image_url = (isset($data['image_url']) && !empty($data['image_url'])) 
            ? $conn->real_escape_string($data['image_url']) 
            : 'assets/default.png';

        $checkCol = $conn->query("SHOW COLUMNS FROM items LIKE 'image_url'");
        
        if ($checkCol && $checkCol->num_rows > 0) {
            $stmt = $conn->prepare("INSERT INTO items (name, type, rarity, wear, price, owner_id, image_url) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssdis", $name, $type, $rarity, $wear, $price, $owner_id, $image_url);
        } else {
            $stmt = $conn->prepare("INSERT INTO items (name, type, rarity, wear, price, owner_id) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssdi", $name, $type, $rarity, $wear, $price, $owner_id);
        }

        if ($stmt->execute()) {
            echo json_encode(["status" => "success", "message" => "Item created", "item_id" => $stmt->insert_id]);
        } else {
            throw new Exception($stmt->error);
        }
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>
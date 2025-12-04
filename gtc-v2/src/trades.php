<?php
include 'config.php';

$action = $_GET['action'] ?? '';

if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $user_id = intval($data['user_id']);
    $my_items_ids = $data['my_items'] ?? [];
    $market_items_ids = $data['market_items'] ?? [];

    if (empty($my_items_ids) && empty($market_items_ids)) {
        echo json_encode(["status" => "error", "message" => "No items selected"]);
        exit;
    }

    $conn->begin_transaction();

    try {
        $my_total = 0;
        $market_total = 0;

        if (!empty($my_items_ids)) {
            $ids_placeholder = implode(',', array_fill(0, count($my_items_ids), '?'));
            $stmt = $conn->prepare("SELECT id, price, owner_id FROM items WHERE id IN ($ids_placeholder)");
            $stmt->bind_param(str_repeat('i', count($my_items_ids)), ...$my_items_ids);
            $stmt->execute();
            $res = $stmt->get_result();
            
            while($row = $res->fetch_assoc()) {
                if ($row['owner_id'] != $user_id) throw new Exception("Item ownership error");
                $my_total += floatval($row['price']);
            }
        }

        if (!empty($market_items_ids)) {
            $ids_placeholder = implode(',', array_fill(0, count($market_items_ids), '?'));
            $stmt = $conn->prepare("SELECT id, price, owner_id FROM items WHERE id IN ($ids_placeholder)");
            $stmt->bind_param(str_repeat('i', count($market_items_ids)), ...$market_items_ids);
            $stmt->execute();
            $res = $stmt->get_result();

            while($row = $res->fetch_assoc()) {
                if ($row['owner_id'] == $user_id) throw new Exception("Cannot buy own item");
                $market_total += floatval($row['price']);
            }
        }

        $diff = $market_total - $my_total;

        $stmt = $conn->prepare("SELECT balance FROM users WHERE id = ? FOR UPDATE");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $user_data = $stmt->get_result()->fetch_assoc();
        $current_balance = floatval($user_data['balance']);

        if ($diff > 0) {
            if ($current_balance < $diff) {
                throw new Exception("Insufficient funds");
            }
            $conn->query("UPDATE users SET balance = balance - $diff WHERE id = $user_id");
            $conn->query("UPDATE users SET balance = balance + $diff WHERE id != $user_id LIMIT 1"); 
        } elseif ($diff < 0) {
            $profit = abs($diff);
            $conn->query("UPDATE users SET balance = balance + $profit WHERE id = $user_id");
            $conn->query("UPDATE users SET balance = balance - $profit WHERE id != $user_id LIMIT 1");
        }

        if (!empty($my_items_ids)) {
            $ids = implode(',', $my_items_ids);
            $conn->query("UPDATE items SET owner_id = 2 WHERE id IN ($ids)");
        }
        if (!empty($market_items_ids)) {
            $ids = implode(',', $market_items_ids);
            $conn->query("UPDATE items SET owner_id = $user_id WHERE id IN ($ids)");
        }

        $items_json = json_encode([
            'user_gave' => $my_items_ids,
            'user_received' => $market_items_ids
        ]);

        $desc = "Trade: Given $" . $my_total . ", Received $" . $market_total;
        $stmt = $conn->prepare("INSERT INTO trades (user_id, description, amount_change, items_json) VALUES (?, ?, ?, ?)");
        $neg_diff = $diff * -1; 
        $stmt->bind_param("isds", $user_id, $desc, $neg_diff, $items_json);
        $stmt->execute();

        $conn->commit();
        echo json_encode(["status" => "success", "message" => "Trade completed"]);

    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
}
elseif ($action === 'list') {
    $stmt = $conn->query("SELECT * FROM trades ORDER BY trade_date DESC LIMIT 20");
    echo json_encode($stmt->fetch_all(MYSQLI_ASSOC));
}
elseif ($action === 'revert' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $trade_id = intval($data['trade_id']);

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("SELECT user_id, amount_change, items_json FROM trades WHERE id = ?");
        $stmt->bind_param("i", $trade_id);
        $stmt->execute();
        $res = $stmt->get_result();
        
        if ($res->num_rows === 0) throw new Exception("Trade not found");
        
        $trade = $res->fetch_assoc();
        $amount = floatval($trade['amount_change']);
        $user_id = intval($trade['user_id']);
        $items_data = json_decode($trade['items_json'], true);

        if ($amount < 0) {
            $refund = abs($amount);
            $conn->query("UPDATE users SET balance = balance + $refund WHERE id = $user_id");
            $conn->query("UPDATE users SET balance = balance - $refund WHERE id != $user_id LIMIT 1");
        } elseif ($amount > 0) {
            $conn->query("UPDATE users SET balance = balance - $amount WHERE id = $user_id");
            $conn->query("UPDATE users SET balance = balance + $amount WHERE id != $user_id LIMIT 1");
        }

        if ($items_data) {
            $user_gave = $items_data['user_gave'] ?? [];
            $user_received = $items_data['user_received'] ?? [];

            if (!empty($user_gave)) {
                $ids = implode(',', $user_gave);
                $conn->query("UPDATE items SET owner_id = $user_id WHERE id IN ($ids)");
            }

            if (!empty($user_received)) {
                $ids = implode(',', $user_received);
                $conn->query("UPDATE items SET owner_id = 2 WHERE id IN ($ids)");
            }
        }

        $stmt = $conn->prepare("DELETE FROM trades WHERE id = ?");
        $stmt->bind_param("i", $trade_id);
        $stmt->execute();

        $conn->commit();
        echo json_encode(["status" => "success", "message" => "Trade cancelled, items and funds returned"]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
}
?>
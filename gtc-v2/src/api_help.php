<?php
header('Content-Type: application/json');

$api = [
    "GET /items.php" => "Список всех предметов",
    "GET /trades.php?action=list" => "Список всех обменов",
    "POST /trades.php?action=create" => "Создать новый обмен (параметры: item_id, from_user, to_user)"
];

echo json_encode([
    "message" => "API обмена внутриигровыми предметами",
    "available_endpoints" => $api
]);
?>

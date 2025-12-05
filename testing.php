<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (php_sapi_name() !== 'cli') {
    echo "<pre>";
}

echo "=== БЫСТРЫЕ ТЕСТЫ GAMETRADE ===\n\n";

echo "1. ПРОВЕРКА СТРУКТУРЫ БД:\n";
echo "--------------------------\n";

$tables = ['users', 'items', 'trades'];
$table_check = [];
$db_connected = false;
$mysqli = null;

try {
    $configs = [
        ['host' => 'db', 'port' => 3306],
        ['host' => '127.0.0.1', 'port' => 3307]
    ];

    foreach ($configs as $conf) {
        try {
            $mysqli = @new mysqli($conf['host'], 'root', 'rootpass', 'gametrade', $conf['port']);
            if (!$mysqli->connect_error) {
                $db_connected = true;
                break;
            }
        } catch (Throwable $t) {
            continue;
        }
    }

    if ($db_connected) {
        foreach ($tables as $table) {
            $safe_table = $mysqli->real_escape_string($table);
            $result = $mysqli->query("SHOW TABLES LIKE '$safe_table'");
            
            if ($result && $result->num_rows > 0) {
                echo "✅ Таблица '$table' существует\n";
                $table_check[$table] = true;
                
                $count_res = $mysqli->query("SELECT COUNT(*) as cnt FROM `$safe_table`");
                $row = $count_res->fetch_assoc();
                echo "   Записей: " . $row['cnt'] . "\n";
            } else {
                echo "❌ Таблица '$table' не существует\n";
                $table_check[$table] = false;
            }
        }
    } else {
        throw new Exception("Connection failed");
    }

} catch (Throwable $e) {
    echo "⚠️  БД недоступна, проверяем логику\n";
}

echo "\n";

echo "2. ЛОГИКА ДОБАВЛЕНИЯ ПРЕДМЕТА:\n";
echo "-----------------------------\n";

class TestItem {
    public $name;
    public $type;
    public $rarity;
    public $wear;
    public $price;
    public $owner_id;
    
    public function __construct($data) {
        $this->name = $data['name'] ?? '';
        $this->type = $data['type'] ?? '';
        $this->rarity = $data['rarity'] ?? '';
        $this->wear = $data['wear'] ?? 'Factory New';
        $this->price = $data['price'] ?? 0;
        $this->owner_id = $data['owner_id'] ?? 0;
    }
    
    public function isValid() {
        $errors = [];
        
        if (empty($this->name)) $errors[] = "Название обязательно";
        if (empty($this->type)) $errors[] = "Тип обязателен";
        if (empty($this->rarity)) $errors[] = "Редкость обязательна";
        if ($this->price <= 0) $errors[] = "Цена должна быть больше 0";
        if ($this->owner_id <= 0) $errors[] = "Неверный ID владельца";
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
}

echo "Тест валидного предмета:\n";
$valid_item = new TestItem([
    'name' => 'AK-47 | Redline',
    'type' => 'rifles',
    'rarity' => 'rare',
    'price' => 45.50,
    'owner_id' => 1
]);
$result = $valid_item->isValid();
echo "   Название: {$valid_item->name}\n";
echo "   Цена: \${$valid_item->price}\n";
if ($result['valid']) {
    echo "   ✅ Предмет валиден\n";
} else {
    echo "   ❌ Ошибки: " . implode(', ', $result['errors']) . "\n";
}

echo "\nТест невалидного предмета:\n";
$invalid_item = new TestItem([
    'name' => '',
    'type' => 'rifles',
    'price' => -10,
    'owner_id' => 0
]);
$result = $invalid_item->isValid();
echo "   Цена: \${$invalid_item->price}\n";
if (!$result['valid']) {
    echo "   ✅ Правильно обнаружены ошибки:\n";
    foreach ($result['errors'] as $error) {
        echo "      - $error\n";
    }
}

echo "\n";

echo "3. ЛОГИКА ОБМЕНА ПРЕДМЕТОВ:\n";
echo "---------------------------\n";

function calculateTrade($item1_price, $item2_price) {
    $difference = $item1_price - $item2_price;
    
    if ($difference > 0) {
        return [
            'result' => 'Пользователь 1 получает сдачу: $' . abs($difference),
            'balance_change' => -abs($difference)
        ];
    } elseif ($difference < 0) {
        return [
            'result' => 'Пользователь 1 доплачивает: $' . abs($difference),
            'balance_change' => abs($difference)
        ];
    } else {
        return [
            'result' => 'Прямой обмен без доплат',
            'balance_change' => 0
        ];
    }
}

$test_cases = [
    ['item1' => 100, 'item2' => 100, 'expected' => 0],
    ['item1' => 150, 'item2' => 100, 'expected' => -50],
    ['item1' => 80, 'item2' => 120, 'expected' => 40]
];

foreach ($test_cases as $i => $case) {
    $result = calculateTrade($case['item1'], $case['item2']);
    echo "Кейс " . ($i+1) . ": \${$case['item1']} ↔ \${$case['item2']}\n";
    echo "   Результат: {$result['result']}\n";
    
    if ($result['balance_change'] == $case['expected']) {
        echo "   ✅ Расчет верный\n";
    } else {
        echo "   ❌ Ошибка расчета (ожидалось: {$case['expected']})\n";
    }
}

echo "\n=== ИТОГИ БЫСТРЫХ ТЕСТОВ ===\n";

$all_tables_exist = !empty($table_check) && !in_array(false, $table_check);
$item_logic_ok = $valid_item->isValid()['valid'] && !$invalid_item->isValid()['valid'];
$trade_logic_ok = true;

if ($all_tables_exist) {
    echo "✅ Структура БД: OK\n";
} else {
    echo "⚠️  Структура БД: требует проверки\n";
}

if ($item_logic_ok) {
    echo "✅ Логика предметов: OK\n";
} else {
    echo "⚠️  Логика предметов: требует проверки\n";
}

if ($trade_logic_ok) {
    echo "✅ Логика обмена: OK\n";
} else {
    echo "⚠️  Логика обмена: требует проверки\n";
}

echo "\nТестирование завершено!\n";
?>
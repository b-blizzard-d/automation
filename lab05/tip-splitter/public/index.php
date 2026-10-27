<?php

// composer на сервере нет, поэтому подключаю класс напрямую
require __DIR__ . '/../src/BillSplitter.php';

use App\BillSplitter;

$sum = $_GET['sum'] ?? '';
$people = $_GET['people'] ?? 2;
$tip = $_GET['tip'] ?? 10;
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Калькулятор чаевых</title>
</head>
<body>
<h1>Калькулятор чаевых</h1>

<form method="get">
    <p>Сумма счёта: <input name="sum" value="<?= htmlspecialchars($sum) ?>"></p>
    <p>Сколько человек: <input name="people" value="<?= htmlspecialchars($people) ?>"></p>
    <p>Чаевые, %: <input name="tip" value="<?= htmlspecialchars($tip) ?>"></p>
    <p><button>Посчитать</button></p>
</form>

<?php
if ($sum !== '') {
    try {
        $bill = new BillSplitter((float) $sum, (int) $people);
        echo '<p>Чаевые: ' . $bill->tip((int) $tip) . ' лей</p>';
        echo '<p>Каждый платит: ' . $bill->perPerson((int) $tip) . ' лей</p>';
    } catch (InvalidArgumentException $e) {
        echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
    }
}
?>
</body>
</html>

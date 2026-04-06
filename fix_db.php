<?php
require_once 'config.php';

echo "Start Fix...<br>";

$conn->query("ALTER TABLE odontogram_tindakan MODIFY COLUMN id INT NOT NULL AUTO_INCREMENT");
echo "odontogram_tindakan OK<br>";

$conn->query("ALTER TABLE invoice MODIFY COLUMN id INT NOT NULL AUTO_INCREMENT");
echo "invoice OK<br>";

$conn->query("ALTER TABLE invoice_items MODIFY COLUMN id INT NOT NULL AUTO_INCREMENT");
echo "invoice_items OK<br>";

echo "DONE";
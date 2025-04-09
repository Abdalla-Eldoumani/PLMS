<?php
require_once 'includes/db.php';

$db = new Database();

echo "<h1>Database Check</h1>";

try {
    // Check parking lots
    $lots = $db->query("SELECT * FROM parking_lots")->fetchAll();
    echo "<h2>Parking Lots (" . count($lots) . ")</h2>";
    echo "<pre>";
    print_r($lots);
    echo "</pre>";

    // Check parking slots
    $slots = $db->query("SELECT * FROM parking_slots")->fetchAll();
    echo "<h2>Parking Slots (" . count($slots) . ")</h2>";
    echo "<pre>";
    print_r($slots);
    echo "</pre>";

    // Check database tables
    $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "<h2>Database Tables</h2>";
    echo "<pre>";
    print_r($tables);
    echo "</pre>";

} catch (PDOException $e) {
    echo "<h2>Error</h2>";
    echo "<pre>";
    echo $e->getMessage();
    echo "</pre>";
} 
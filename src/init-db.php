<?php
require_once 'includes/db.php';

$db = new Database();

echo "<h1>Database Initialization</h1>";

try {
    // Insert parking lots
    $lots = [
        ['Lot A', 'Near Science B Building', 50],
        ['Lot B', 'Near Engineering Building', 50],
        ['Lot C', 'Near MacEwan Hall', 50],
        ['Lot D', 'Near Olympic Oval', 50],
        ['Lot E', 'Near TFDL', 50]
    ];
    
    $insertedLots = 0;
    foreach ($lots as $lot) {
        $result = $db->query(
            "INSERT INTO parking_lots (name, location, total_slots) VALUES (?, ?, ?)",
            $lot
        );
        $insertedLots++;
    }
    
    echo "<p>Inserted {$insertedLots} parking lots.</p>";
    
    // Get the lot IDs
    $lotIds = $db->query("SELECT lot_id FROM parking_lots")->fetchAll(PDO::FETCH_COLUMN);
    
    // Insert parking slots for each lot
    $insertedSlots = 0;
    foreach ($lotIds as $lotId) {
        // Compact spots (30)
        for ($i = 1; $i <= 30; $i++) {
            $slotNumber = $lotId == 1 ? "A{$i}" : ($lotId == 2 ? "B{$i}" : ($lotId == 3 ? "C{$i}" : ($lotId == 4 ? "D{$i}" : "E{$i}")));
            $db->query(
                "INSERT INTO parking_slots (lot_id, slot_number, status, type, hourly_rate) VALUES (?, ?, 'Available', 'Compact', 2.50)",
                [$lotId, $slotNumber]
            );
            $insertedSlots++;
        }
        
        // Large spots (15)
        for ($i = 31; $i <= 45; $i++) {
            $slotNumber = $lotId == 1 ? "A{$i}" : ($lotId == 2 ? "B{$i}" : ($lotId == 3 ? "C{$i}" : ($lotId == 4 ? "D{$i}" : "E{$i}")));
            $db->query(
                "INSERT INTO parking_slots (lot_id, slot_number, status, type, hourly_rate) VALUES (?, ?, 'Available', 'Large', 3.00)",
                [$lotId, $slotNumber]
            );
            $insertedSlots++;
        }
        
        // Handicapped spots (5)
        for ($i = 46; $i <= 50; $i++) {
            $slotNumber = $lotId == 1 ? "A{$i}" : ($lotId == 2 ? "B{$i}" : ($lotId == 3 ? "C{$i}" : ($lotId == 4 ? "D{$i}" : "E{$i}")));
            $db->query(
                "INSERT INTO parking_slots (lot_id, slot_number, status, type, hourly_rate) VALUES (?, ?, 'Available', 'Handicapped', 2.00)",
                [$lotId, $slotNumber]
            );
            $insertedSlots++;
        }
    }
    
    echo "<p>Inserted {$insertedSlots} parking slots.</p>";
    
    // Verify the data
    $lots = $db->query("SELECT * FROM parking_lots")->fetchAll();
    echo "<h2>Parking Lots (" . count($lots) . ")</h2>";
    echo "<pre>";
    print_r($lots);
    echo "</pre>";
    
    $slots = $db->query("SELECT * FROM parking_slots")->fetchAll();
    echo "<h2>Parking Slots (" . count($slots) . ")</h2>";
    echo "<p>Showing first 10 slots:</p>";
    echo "<pre>";
    print_r(array_slice($slots, 0, 10));
    echo "</pre>";
    
    echo "<p><a href='index.php'>Go to Homepage</a></p>";
    
} catch (PDOException $e) {
    echo "<h2>Error</h2>";
    echo "<pre>";
    echo $e->getMessage();
    echo "</pre>";
} 
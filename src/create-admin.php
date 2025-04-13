<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

$db = new Database();

echo "<h1>Admin User Setup</h1>";

try {
    // Check if admin user already exists
    $result = $db->query("SELECT * FROM users WHERE email = ?", ['admin@ucalgary.ca']);
    
    if ($result->rowCount() > 0) {
        echo "<p>Admin user already exists with email 'admin@ucalgary.ca'.</p>";
    } else {
        // Insert the admin user
        $auth = new Auth();
        $result = $auth->register(
            'Administrator', 
            'admin@ucalgary.ca', 
            '403-555-1234', 
            'admin123', 
            'admin'
        );
        
        if ($result['success']) {
            $adminId = $result['user_id'];
            
            // Update admin role to Super
            $db->query("UPDATE admins SET role = 'Super' WHERE user_id = ?", [$adminId]);
            
            // Assign all parking lots to this admin
            $lots = $db->query("SELECT lot_id FROM parking_lots")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($lots as $lotId) {
                $db->query(
                    "INSERT INTO admin_parking_lots (admin_id, lot_id) VALUES (?, ?)",
                    [$adminId, $lotId]
                );
            }
            
            echo "<p>Created admin user successfully:</p>";
            echo "<ul>";
            echo "<li><strong>Email:</strong> admin@ucalgary.ca</li>";
            echo "<li><strong>Password:</strong> admin123</li>";
            echo "<li><strong>Role:</strong> Super Admin</li>";
            echo "<li><strong>Parking Lots:</strong> All lots assigned</li>";
            echo "</ul>";
        } else {
            echo "<p>Error creating admin user: " . $result['message'] . "</p>";
        }
    }
    
    // Create a regular admin as well
    $result = $db->query("SELECT * FROM users WHERE email = ?", ['lotadmin@ucalgary.ca']);
    
    if ($result->rowCount() > 0) {
        echo "<p>Regular admin user already exists with email 'lotadmin@ucalgary.ca'.</p>";
    } else {
        // Insert the admin user
        $auth = new Auth();
        $result = $auth->register(
            'Lot Administrator', 
            'lotadmin@ucalgary.ca', 
            '403-555-5678', 
            'admin123', 
            'admin'
        );
        
        if ($result['success']) {
            $adminId = $result['user_id'];
            
            // Update admin role to Regular
            $db->query("UPDATE admins SET role = 'Regular' WHERE user_id = ?", [$adminId]);
            
            // Assign some parking lots to this admin
            $lots = $db->query("SELECT lot_id FROM parking_lots LIMIT 2")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($lots as $lotId) {
                $db->query(
                    "INSERT INTO admin_parking_lots (admin_id, lot_id) VALUES (?, ?)",
                    [$adminId, $lotId]
                );
            }
            
            echo "<p>Created regular admin user successfully:</p>";
            echo "<ul>";
            echo "<li><strong>Email:</strong> lotadmin@ucalgary.ca</li>";
            echo "<li><strong>Password:</strong> admin123</li>";
            echo "<li><strong>Role:</strong> Regular Admin</li>";
            echo "<li><strong>Parking Lots:</strong> First 2 lots assigned</li>";
            echo "</ul>";
        } else {
            echo "<p>Error creating regular admin user: " . $result['message'] . "</p>";
        }
    }
    
    echo "<p><a href='login.php'>Go to Login Page</a></p>";
    
} catch (PDOException $e) {
    echo "<h2>Error</h2>";
    echo "<pre>";
    echo $e->getMessage();
    echo "</pre>";
}
?> 
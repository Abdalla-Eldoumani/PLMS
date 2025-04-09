<?php
echo "<h1>Database Connection Test</h1>";

// Display PHP version
echo "<h2>PHP Version</h2>";
echo "<p>" . phpversion() . "</p>";

// Check if PDO is available
echo "<h2>PDO Extension</h2>";
if (extension_loaded('pdo')) {
    echo "<p>PDO is installed</p>";
    echo "<h3>Available PDO Drivers:</h3>";
    echo "<pre>";
    print_r(PDO::getAvailableDrivers());
    echo "</pre>";
} else {
    echo "<p>PDO is NOT installed</p>";
}

// Check if MySQL PDO driver is available
echo "<h2>MySQL PDO Driver</h2>";
if (extension_loaded('pdo_mysql')) {
    echo "<p>PDO MySQL driver is installed</p>";
} else {
    echo "<p>PDO MySQL driver is NOT installed</p>";
}

// Try to connect to the database
echo "<h2>Database Connection Test</h2>";
try {
    $host = "localhost";
    $user = "root";
    $pass = "";
    $dbname = "parking_management";
    
    // Check if environment variables are set
    if (getenv('MYSQL_HOST')) $host = getenv('MYSQL_HOST');
    if (getenv('MYSQL_USER')) $user = getenv('MYSQL_USER');
    if (getenv('MYSQL_PASSWORD')) $pass = getenv('MYSQL_PASSWORD');
    if (getenv('MYSQL_DATABASE')) $dbname = getenv('MYSQL_DATABASE');
    
    echo "<p>Attempting to connect to database with these parameters:</p>";
    echo "<ul>";
    echo "<li>Host: " . htmlspecialchars($host) . "</li>";
    echo "<li>User: " . htmlspecialchars($user) . "</li>";
    echo "<li>Database: " . htmlspecialchars($dbname) . "</li>";
    echo "</ul>";
    
    $conn = new PDO("mysql:host={$host};dbname={$dbname}", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<p style='color: green; font-weight: bold;'>Database connection successful!</p>";
    
    // Check if the database exists
    $result = $conn->query("SELECT DATABASE()")->fetch(PDO::FETCH_COLUMN);
    echo "<p>Current database: " . htmlspecialchars($result) . "</p>";
    
    // Check if the tables exist
    $tables = $conn->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "<h3>Tables in database:</h3>";
    echo "<pre>";
    print_r($tables);
    echo "</pre>";
    
    // Check if there's data in the parking_lots table
    $lots = $conn->query("SELECT * FROM parking_lots")->fetchAll(PDO::FETCH_ASSOC);
    echo "<h3>Parking Lots:</h3>";
    echo "<p>Number of lots: " . count($lots) . "</p>";
    if (count($lots) > 0) {
        echo "<pre>";
        print_r($lots);
        echo "</pre>";
    } else {
        echo "<p>No parking lots found in the database.</p>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red; font-weight: bold;'>Database connection failed: " . htmlspecialchars($e->getMessage()) . "</p>";
    
    // Check if the database exists
    try {
        $conn = new PDO("mysql:host={$host}", $user, $pass);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $databases = $conn->query("SHOW DATABASES")->fetchAll(PDO::FETCH_COLUMN);
        echo "<h3>Available databases:</h3>";
        echo "<pre>";
        print_r($databases);
        echo "</pre>";
        
        if (in_array($dbname, $databases)) {
            echo "<p>The database '{$dbname}' exists but there might be an issue with permissions or the database structure.</p>";
        } else {
            echo "<p>The database '{$dbname}' does not exist. You need to create it first.</p>";
        }
    } catch (PDOException $e2) {
        echo "<p>Could not connect to MySQL server: " . htmlspecialchars($e2->getMessage()) . "</p>";
    }
}

echo "<p><a href='init-db.php'>Initialize Database</a></p>";
echo "<p><a href='index.php'>Go to Homepage</a></p>"; 
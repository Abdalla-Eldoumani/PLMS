<?php
echo "<h1>Run SQL Initialization</h1>";

// Get the path to the SQL file
$sqlFile = __DIR__ . '/../scripts/init.sql';

if (!file_exists($sqlFile)) {
    echo "<p style='color: red;'>SQL file not found at: " . htmlspecialchars($sqlFile) . "</p>";
    exit;
}

echo "<p>SQL file found at: " . htmlspecialchars($sqlFile) . "</p>";

// Read the SQL file
$sql = file_get_contents($sqlFile);

if ($sql === false) {
    echo "<p style='color: red;'>Failed to read SQL file.</p>";
    exit;
}

echo "<p>SQL file read successfully. Size: " . strlen($sql) . " bytes.</p>";

// Try to connect to the database
try {
    $host = "localhost";
    $user = "root";
    $pass = "";
    
    // Check if environment variables are set
    if (getenv('MYSQL_HOST')) $host = getenv('MYSQL_HOST');
    if (getenv('MYSQL_USER')) $user = getenv('MYSQL_USER');
    if (getenv('MYSQL_PASSWORD')) $pass = getenv('MYSQL_PASSWORD');
    
    echo "<p>Connecting to MySQL server...</p>";
    
    $conn = new PDO("mysql:host={$host}", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<p style='color: green;'>Connected to MySQL server successfully.</p>";
    
    // Split the SQL into individual statements
    $statements = explode(';', $sql);
    $successCount = 0;
    $errorCount = 0;
    
    echo "<h2>Executing SQL Statements</h2>";
    echo "<p>Total statements: " . count($statements) . "</p>";
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (empty($statement)) continue;
        
        try {
            $conn->exec($statement);
            $successCount++;
        } catch (PDOException $e) {
            $errorCount++;
            echo "<p style='color: red;'>Error executing statement: " . htmlspecialchars($e->getMessage()) . "</p>";
            echo "<pre>" . htmlspecialchars($statement) . "</pre>";
        }
    }
    
    echo "<h2>Execution Results</h2>";
    echo "<p>Successful statements: " . $successCount . "</p>";
    echo "<p>Failed statements: " . $errorCount . "</p>";
    
    if ($errorCount == 0) {
        echo "<p style='color: green; font-weight: bold;'>SQL initialization completed successfully!</p>";
    } else {
        echo "<p style='color: orange; font-weight: bold;'>SQL initialization completed with errors.</p>";
    }
    
    // Verify the data
    echo "<h2>Verifying Data</h2>";
    
    // Check if the database exists
    $databases = $conn->query("SHOW DATABASES")->fetchAll(PDO::FETCH_COLUMN);
    if (in_array('parking_management', $databases)) {
        echo "<p>The database 'parking_management' exists.</p>";
        
        // Connect to the database
        $conn = new PDO("mysql:host={$host};dbname=parking_management", $user, $pass);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
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
        
        // Check if there's data in the parking_slots table
        $slots = $conn->query("SELECT * FROM parking_slots")->fetchAll(PDO::FETCH_ASSOC);
        echo "<h3>Parking Slots:</h3>";
        echo "<p>Number of slots: " . count($slots) . "</p>";
        if (count($slots) > 0) {
            echo "<p>Showing first 10 slots:</p>";
            echo "<pre>";
            print_r(array_slice($slots, 0, 10));
            echo "</pre>";
        } else {
            echo "<p>No parking slots found in the database.</p>";
        }
    } else {
        echo "<p>The database 'parking_management' does not exist.</p>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red; font-weight: bold;'>Database connection failed: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<p><a href='index.php'>Go to Homepage</a></p>"; 
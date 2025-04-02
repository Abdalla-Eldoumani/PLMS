<?php
require_once 'db.php';

class Auth {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    public function register($name, $email, $phone, $password, $userType, $licensePlate = null, $subscriptionType = null) {
        // Check if email already exists
        $query = "SELECT user_id FROM users WHERE email = ?";
        $result = $this->db->query($query, [$email]);
        
        if ($result->rowCount() > 0) {
            return ['success' => false, 'message' => 'Email already registered'];
        }
        
        // Hash password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        // Begin transaction
        $this->db->query("START TRANSACTION");
        
        try {
            // Insert user
            $query = "INSERT INTO users (name, email, phone, password) VALUES (?, ?, ?, ?)";
            $this->db->query($query, [$name, $email, $phone, $hashedPassword]);
            
            $userId = $this->db->getLastInsertId();
            
            // Create customer or admin record based on user type
            if ($userType == 'admin') {
                $query = "INSERT INTO admins (user_id, role) VALUES (?, 'Regular')";
                $this->db->query($query, [$userId]);
            } else {
                $subType = $subscriptionType ?? 'Daily';
                $query = "INSERT INTO customers (user_id, license_plate, subscription_type) VALUES (?, ?, ?)";
                $this->db->query($query, [$userId, $licensePlate, $subType]);
            }
            
            // Commit transaction
            $this->db->query("COMMIT");
            
            return ['success' => true, 'user_id' => $userId, 'user_type' => $userType];
        } catch (Exception $e) {
            // Rollback on error
            $this->db->query("ROLLBACK");
            return ['success' => false, 'message' => 'Registration failed: ' . $e->getMessage()];
        }
    }
    
    public function login($email, $password) {
        // Get user by email
        $query = "SELECT u.user_id, u.name, u.email, u.password, 
                 CASE 
                    WHEN a.user_id IS NOT NULL THEN 'admin' 
                    WHEN c.user_id IS NOT NULL THEN 'customer' 
                    ELSE 'unknown' 
                 END as user_type
                 FROM users u
                 LEFT JOIN admins a ON u.user_id = a.user_id
                 LEFT JOIN customers c ON u.user_id = c.user_id
                 WHERE u.email = ?";
                 
        $result = $this->db->query($query, [$email]);
        
        if ($result->rowCount() === 0) {
            return ['success' => false, 'message' => 'Invalid email or password'];
        }
        
        $user = $result->fetch();
        
        // Verify password
        if (!password_verify($password, $user['password'])) {
            return ['success' => false, 'message' => 'Invalid email or password'];
        }
        
        // Set session data
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['name'] = $user['name'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['user_type'] = $user['user_type'];
        
        return [
            'success' => true, 
            'user_id' => $user['user_id'], 
            'user_type' => $user['user_type']
        ];
    }
    
    public function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
    
    public function isAdmin() {
        return $this->isLoggedIn() && $_SESSION['user_type'] === 'admin';
    }
    
    public function isCustomer() {
        return $this->isLoggedIn() && $_SESSION['user_type'] === 'customer';
    }
    
    public function logout() {
        // Unset all session variables
        $_SESSION = [];
        
        // Destroy the session
        session_destroy();
        
        return true;
    }
    
    public function getCurrentUserId() {
        return $_SESSION['user_id'] ?? null;
    }
}
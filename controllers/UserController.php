<?php
// Required dependencies
require_once __DIR__ . '/../vendor/autoload.php'; // Composer autoloader for dependencies
require_once __DIR__ . '/../models/User.php';     // User model
require_once __DIR__ . '/../utils/JWTUtils.php';  // JWT utility functions
require_once __DIR__ . '/../config/database.php'; // Database configuration
require_once __DIR__ . '/../core/Model.php';      // Base model class

// Import required JWT classes
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Database\Capsule\Manager as DB;

class UserController
{
    // Secret key for JWT token encryption/decryption
    private $secretKey = 'your_secret_key_here';

    /**
     * Handle user registration
     * POST request with username and password
     */
    public function signUp()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            try {
                // Validate required fields
                if (empty($_POST['username']) || empty($_POST['password'])) {
                    throw new Exception('Username and Password are required!');
                }

                // Check for duplicate username
                $existingUser = User::where('username', $_POST['username'])->first();
                if ($existingUser) {
                    throw new Exception('Username already exists!');
                }

                // Create new user with hashed password
                $user = User::create([
                    'username' => $_POST['username'],
                    'password' => password_hash($_POST['password'], PASSWORD_BCRYPT),
                    'role' => 'user' // Default role for new users
                ]);

                // Generate authentication token
                $token = $this->generateToken($user->id, $user->username);

                // Set secure HTTP-only cookie with token
                setcookie('auth_token', $token, [
                    'expires' => time() + (60 * 60), // 1 hour expiration
                    'path' => '/',
                    'secure' => true,     // Only send over HTTPS
                    'httponly' => true,   // Not accessible via JavaScript
                    'samesite' => 'Strict' // CSRF protection
                ]);

                // Save token to user record
                $user->jwt_token = $token;
                $user->save();

                // Redirect to login page
                header('Location: ../../blog-site/public/index.php');
                exit;

            } catch (Exception $e) {
                echo json_encode(['error' => $e->getMessage()]);
                return;
            }
        }
    }
    
    /**
     * Generate JWT token with user information
     * @param int $userId User's ID
     * @param string $username User's username
     * @return string JWT token
     */
    private function generateToken($userId, $username)
    {
        $payload = [
            'id' => $userId,
            'username' => $username,
            'iat' => time(),           // Issued at timestamp
            'exp' => time() + 3600     // Expires in 1 hour
        ];

        return JWT::encode($payload, $this->secretKey, JWTUtility::getAlgorithm());
    }

    /**
     * Handle user login
     * @param array $credentials Username and password
     */
    public function login($credentials)
    {
        try {
            // Find user by username
            $user = User::where('username', $credentials['username'])->first();

            // Verify password
            if (!$user || !$user->verifyPassword($credentials['password'])) {
                throw new Exception('Invalid credentials');
            }

            // Generate new token with user info and role
            $token = JWT::encode([
                'id' => $user->id,
                'username' => $user->username,
                'role' => $user->role ?? 'user',
                'exp' => time() + (60 * 60)
            ], $this->secretKey, 'HS256');

            // Set secure cookie with token
            setcookie('auth_token', $token, [
                'expires' => time() + (60 * 60),
                'path' => '/',
                'secure' => true,
                'httponly' => true,
                'samesite' => 'Strict'
            ]);

            // Update user's token in database
            $user->jwt_token = $token;
            $user->save();

            // Redirect based on user role
            if ($user->role === 'admin') {
                header('Location: ../../blog-site/views/admin.php');
            } else {
                header('Location: ../../blog-site/views/user.php');
            }
            exit;

        } catch (Exception $e) {
            header('Location: ../../blog-site/public/index.php?error=' . urlencode($e->getMessage()));
            exit;
        }
    }

    /**
     * Check if user has required role
     * @param string $requiredRole Role to check for
     * @return mixed True if authorized, JSON error if not
     */
    public function checkRole($requiredRole) 
    {
        $token = $_COOKIE['auth_token'] ?? null;
        
        if (!$token) {
            http_response_code(401);
            return json_encode(['message' => 'Unauthorized']);
        }

        try {
            // Decode and verify token
            $decoded = JWT::decode($token, new Key($this->secretKey, 'HS256'));
            
            // Check if user has required role
            if ($decoded->role !== $requiredRole) {
                http_response_code(403);
                return json_encode(['message' => 'Forbidden - Insufficient permissions']);
            }
            
            return true;
        } catch (Exception $e) {
            http_response_code(401);
            return json_encode(['message' => 'Invalid token']);
        }
    }

    /**
     * Handle user logout
     * Clears session, cookies, and redirects to login
     */
    public function logout()
    {
        try {
            // Expire and remove auth cookie
            setcookie('auth_token', '', time() - 3600, '/');
            
            // Clear PHP session data
            session_start();
            session_unset();
            session_destroy();
            
            // Redirect to login page
            header('Location: ../../blog-site/public/index.php');
            exit;
        } catch (Exception $e) {
            // Log error and redirect with error message
            error_log("Logout error: " . $e->getMessage());
            header('Location: ../../blog-site/public/index.php?error=' . urlencode('Error during logout'));
            exit;
        }
    }

    /**
     * Verify user's token and return user object
     * @return mixed User object if valid, null if not
     */
    public function verifyToken()
    {
        $token = $_COOKIE['auth_token'] ?? null;
        
        if (!$token) {
            return null;
        }

        try {
            // Decode token
            $decoded = JWT::decode($token, new Key($this->secretKey, 'HS256'));
            
            // Verify user exists and token matches
            $user = User::find($decoded->id);
            if (!$user || $user->jwt_token !== $token) {
                return null;
            }

            return $user;
        } catch (Exception $e) {
            return null;
        }
    }
}
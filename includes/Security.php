<?php
/**
 * Security Handler - XSS, CSRF, SQL Injection Protection
 */
require_once __DIR__ . '/Database.php';

class Security {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Generate CSRF Token
     */
    public function generateCSRFToken() {
        if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
            $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
        }
        return $_SESSION[CSRF_TOKEN_NAME];
    }
    
    /**
     * Verify CSRF Token
     */
    public function verifyCSRFToken($token) {
        if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
            return false;
        }
        return hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
    }
    
    /**
     * Sanitize Input (XSS Protection)
     */
    public function sanitize($input) {
        if (is_array($input)) {
            return array_map([$this, 'sanitize'], $input);
        }
        
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Validate Email
     */
    public function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }
    
    /**
     * Validate URL
     */
    public function validateURL($url) {
        return filter_var($url, FILTER_VALIDATE_URL);
    }
    
    /**
     * Rate Limiting
     */
    public function checkRateLimit($identifier, $maxRequests = null, $period = null) {
        $maxRequests = $maxRequests ?? RATE_LIMIT_REQUESTS;
        $period = $period ?? RATE_LIMIT_PERIOD;

        if (!$this->db->isConnected()) {
            $key = 'rate_limit_' . $identifier;
            if (!isset($_SESSION[$key])) {
                $_SESSION[$key] = [];
            }

            // remove expired timestamps
            $_SESSION[$key] = array_filter($_SESSION[$key], function ($timestamp) use ($period) {
                return ($timestamp + $period) >= time();
            });

            if (count($_SESSION[$key]) >= $maxRequests) {
                return false;
            }

            $_SESSION[$key][] = time();
            return true;
        }

        $sql = "SELECT COUNT(*) as count FROM rate_limits
                WHERE identifier = ? AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)";

        $result = $this->db->fetchOne($sql, [$identifier, $period]);
        $currentCount = $result['count'] ?? 0;

        if ($currentCount >= $maxRequests) {
            return false;
        }

        // Log this request
        $this->db->insert('rate_limits', [
            'identifier' => $identifier,
            'ip_address' => $this->getClientIP(),
            'created_at' => date('Y-m-d H:i:s')
        ]);

        // Clean old entries
        $this->db->query("DELETE FROM rate_limits WHERE created_at < DATE_SUB(NOW(), INTERVAL ? SECOND)", [$period]);

        return true;
    }
    
    /**
     * Get Client IP Address
     */
    public function getClientIP() {
        $ip = '';
        
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        }
        
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }
    
    /**
     * Hash Password
     */
    public function hashPassword($password) {
        return password_hash($password, PASSWORD_ARGON2ID);
    }
    
    /**
     * Verify Password
     */
    public function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    /**
     * Generate Secure Token
     */
    public function generateToken($length = 32) {
        return bin2hex(random_bytes($length));
    }
    
    /**
     * Encrypt Data
     */
    public function encrypt($data) {
        $method = 'AES-256-CBC';
        $key = hash('sha256', SECRET_KEY);
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($method));
        
        $encrypted = openssl_encrypt($data, $method, $key, 0, $iv);
        return base64_encode($encrypted . '::' . $iv);
    }
    
    /**
     * Decrypt Data
     */
    public function decrypt($data) {
        $method = 'AES-256-CBC';
        $key = hash('sha256', SECRET_KEY);
        
        list($encrypted, $iv) = explode('::', base64_decode($data), 2);
        return openssl_decrypt($encrypted, $method, $key, 0, $iv);
    }
    
    /**
     * Create JWT Token
     */
    public function createJWT($payload, $expiresIn = 3600) {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $payload['exp'] = time() + $expiresIn;
        $payload['iat'] = time();
        
        $base64UrlHeader = $this->base64UrlEncode($header);
        $base64UrlPayload = $this->base64UrlEncode(json_encode($payload));
        
        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, SECRET_KEY, true);
        $base64UrlSignature = $this->base64UrlEncode($signature);
        
        return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
    }
    
    /**
     * Verify JWT Token
     */
    public function verifyJWT($token) {
        $parts = explode('.', $token);
        
        if (count($parts) !== 3) {
            return false;
        }
        
        list($base64UrlHeader, $base64UrlPayload, $base64UrlSignature) = $parts;
        
        $signature = $this->base64UrlDecode($base64UrlSignature);
        $expectedSignature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, SECRET_KEY, true);
        
        if (!hash_equals($signature, $expectedSignature)) {
            return false;
        }
        
        $payload = json_decode($this->base64UrlDecode($base64UrlPayload), true);
        
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return false; // Token expired
        }
        
        return $payload;
    }
    
    /**
     * Base64 URL Encode
     */
    private function base64UrlEncode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    
    /**
     * Base64 URL Decode
     */
    private function base64UrlDecode($data) {
        return base64_decode(strtr($data, '-_', '+/'));
    }
    
    /**
     * Check if IP is blocked
     */
    public function isIPBlocked($ip = null) {
        $ip = $ip ?? $this->getClientIP();
        
        $sql = "SELECT COUNT(*) as count FROM blocked_ips WHERE ip_address = ? AND (expires_at IS NULL OR expires_at > NOW())";
        $result = $this->db->fetchOne($sql, [$ip]);
        
        return ($result['count'] ?? 0) > 0;
    }
    
    /**
     * Block IP Address
     */
    public function blockIP($ip, $reason = '', $duration = null) {
        $expiresAt = $duration ? date('Y-m-d H:i:s', time() + $duration) : null;
        
        return $this->db->insert('blocked_ips', [
            'ip_address' => $ip,
            'reason' => $reason,
            'expires_at' => $expiresAt,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Log Security Event
     */
    public function logEvent($event, $details = []) {
        return $this->db->insert('security_logs', [
            'event_type' => $event,
            'ip_address' => $this->getClientIP(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'details' => json_encode($details),
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
}
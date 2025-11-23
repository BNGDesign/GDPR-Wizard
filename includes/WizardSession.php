<?php
/**
 * Wizard Session Management
 * Handles temporary wizard data for guests and permanent storage for logged-in users
 */

class WizardSession {
    private $db;
    private $sessionId;
    private $userId;
    private $isLoggedIn;
    private $useSessionStorage = false;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->sessionId = session_id();
        $this->userId = $_SESSION['wp_user_id'] ?? null;
        $this->isLoggedIn = !empty($this->userId);

        if (!$this->db->isConnected()) {
            $this->useSessionStorage = true;
            if (!isset($_SESSION['wizard_fallback'])) {
                $_SESSION['wizard_fallback'] = [];
            }
        }

        // Auto-cleanup old guest sessions (older than 1 hour)
        $this->cleanupGuestSessions();
    }
    
    /**
     * Save wizard step data
     */
    public function saveStepData($step, $data) {
        $serializedData = json_encode($data);

        if ($this->useSessionStorage) {
            $_SESSION['wizard_fallback'][$this->sessionId][$step] = $data;
            return true;
        }

        if ($this->isLoggedIn) {
            // Permanent storage for logged-in users
            $existing = $this->db->fetchOne(
                "SELECT id FROM wizard_sessions WHERE user_id = ? AND step_number = ?",
                [$this->userId, $step]
            );
            
            if ($existing) {
                return $this->db->update('wizard_sessions', [
                    'step_data' => $serializedData,
                    'updated_at' => date('Y-m-d H:i:s')
                ], [
                    'id' => $existing['id']
                ]);
            } else {
                return $this->db->insert('wizard_sessions', [
                    'user_id' => $this->userId,
                    'session_id' => $this->sessionId,
                    'step_number' => $step,
                    'step_data' => $serializedData,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
            }
        } else {
            // Temporary storage for guests
            $existing = $this->db->fetchOne(
                "SELECT id FROM wizard_sessions WHERE session_id = ? AND step_number = ? AND user_id IS NULL",
                [$this->sessionId, $step]
            );
            
            if ($existing) {
                return $this->db->update('wizard_sessions', [
                    'step_data' => $serializedData,
                    'updated_at' => date('Y-m-d H:i:s')
                ], [
                    'id' => $existing['id']
                ]);
            } else {
                return $this->db->insert('wizard_sessions', [
                    'session_id' => $this->sessionId,
                    'step_number' => $step,
                    'step_data' => $serializedData,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
            }
        }
    }
    
    /**
     * Get wizard step data
     */
    public function getStepData($step) {
        if ($this->useSessionStorage) {
            return $_SESSION['wizard_fallback'][$this->sessionId][$step] ?? null;
        }

        if ($this->isLoggedIn) {
            $result = $this->db->fetchOne(
                "SELECT step_data FROM wizard_sessions WHERE user_id = ? AND step_number = ?",
                [$this->userId, $step]
            );
        } else {
            $result = $this->db->fetchOne(
                "SELECT step_data FROM wizard_sessions WHERE session_id = ? AND step_number = ? AND user_id IS NULL",
                [$this->sessionId, $step]
            );
        }
        
        if ($result && !empty($result['step_data'])) {
            return json_decode($result['step_data'], true);
        }
        
        return null;
    }
    
    /**
     * Get all wizard data
     */
    public function getAllData() {
        if ($this->useSessionStorage) {
            return $_SESSION['wizard_fallback'][$this->sessionId] ?? [];
        }

        if ($this->isLoggedIn) {
            $results = $this->db->fetchAll(
                "SELECT step_number, step_data FROM wizard_sessions WHERE user_id = ? ORDER BY step_number",
                [$this->userId]
            );
        } else {
            $results = $this->db->fetchAll(
                "SELECT step_number, step_data FROM wizard_sessions WHERE session_id = ? AND user_id IS NULL ORDER BY step_number",
                [$this->sessionId]
            );
        }
        
        $allData = [];
        foreach ($results as $row) {
            $allData[$row['step_number']] = json_decode($row['step_data'], true);
        }
        
        return $allData;
    }
    
    /**
     * Clear wizard session
     */
    public function clearSession() {
        if ($this->useSessionStorage) {
            unset($_SESSION['wizard_fallback'][$this->sessionId]);
            return true;
        }

        if ($this->isLoggedIn) {
            return $this->db->delete('wizard_sessions', ['user_id' => $this->userId]);
        } else {
            return $this->db->delete('wizard_sessions', ['session_id' => $this->sessionId]);
        }
    }
    
    /**
     * Save completed GDPR document
     */
    public function saveGDPRDocument($data, $format = 'html') {
        if (!$this->isLoggedIn) {
            return false; // Only logged-in users can save documents
        }

        if ($this->useSessionStorage) {
            return false;
        }

        $wizardData = $this->getAllData();
        
        return $this->db->insert('gdpr_documents', [
            'user_id' => $this->userId,
            'company_name' => $data['company_name'] ?? '',
            'domain' => $data['domain'] ?? '',
            'document_format' => $format,
            'wizard_data' => json_encode($wizardData),
            'generated_content' => $data['content'] ?? '',
            'fingerprint' => $data['fingerprint'] ?? '',
            'watermark_data' => $data['watermark'] ?? '',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Get user's saved GDPR documents
     */
    public function getUserDocuments() {
        if (!$this->isLoggedIn) {
            return [];
        }

        if ($this->useSessionStorage) {
            return [];
        }
        
        return $this->db->fetchAll(
            "SELECT id, company_name, domain, document_format, created_at, updated_at 
             FROM gdpr_documents 
             WHERE user_id = ? 
             ORDER BY created_at DESC",
            [$this->userId]
        );
    }
    
    /**
     * Get specific GDPR document
     */
    public function getDocument($documentId) {
        if (!$this->isLoggedIn) {
            return null;
        }

        if ($this->useSessionStorage) {
            return null;
        }
        
        return $this->db->fetchOne(
            "SELECT * FROM gdpr_documents WHERE id = ? AND user_id = ?",
            [$documentId, $this->userId]
        );
    }
    
    /**
     * Update existing GDPR document
     */
    public function updateDocument($documentId, $data) {
        if (!$this->isLoggedIn) {
            return false;
        }

        if ($this->useSessionStorage) {
            return false;
        }
        
        return $this->db->update('gdpr_documents', [
            'generated_content' => $data['content'] ?? '',
            'wizard_data' => json_encode($data['wizard_data'] ?? []),
            'updated_at' => date('Y-m-d H:i:s')
        ], [
            'id' => $documentId,
            'user_id' => $this->userId
        ]);
    }
    
    /**
     * Delete GDPR document
     */
    public function deleteDocument($documentId) {
        if (!$this->isLoggedIn) {
            return false;
        }
        
        return $this->db->delete('gdpr_documents', [
            'id' => $documentId,
            'user_id' => $this->userId
        ]);
    }
    
    /**
     * Cleanup old guest sessions (older than 1 hour)
     */
    private function cleanupGuestSessions() {
        $sql = "DELETE FROM wizard_sessions 
                WHERE user_id IS NULL 
                AND updated_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)";
        
        $this->db->query($sql);
    }
    
    /**
     * Transfer guest session to user account after login
     */
    public function transferToUser($userId) {
        $sql = "UPDATE wizard_sessions 
                SET user_id = ?, updated_at = NOW() 
                WHERE session_id = ? AND user_id IS NULL";
        
        return $this->db->query($sql, [$userId, $this->sessionId]);
    }
    
    /**
     * Check if wizard is completed
     */
    public function isCompleted() {
        $allData = $this->getAllData();
        return count($allData) >= 6; // At least steps 1-6 must be completed
    }
    
    /**
     * Get completion percentage
     */
    public function getCompletionPercentage() {
        $allData = $this->getAllData();
        $completedSteps = count($allData);
        return round(($completedSteps / 6) * 100);
    }
}
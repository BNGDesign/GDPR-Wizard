<?php
/**
 * 7-Layer Watermark & Fingerprint System
 * E-recht24 level protection
 */

class WatermarkSystem {
    private $db;
    private $zeroWidthChars = [
        "\u{200B}", // Zero-width space
        "\u{200C}", // Zero-width non-joiner
        "\u{200D}", // Zero-width joiner
        "\u{FEFF}"  // Zero-width no-break space
    ];
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * LAYER 1: HTML Fingerprint Comment
     */
    public function generateFingerprint($data) {
        $uniqueString = json_encode($data) . time() . SECRET_KEY;
        return hash('sha256', $uniqueString);
    }
    
    public function addHTMLFingerprint($html, $fingerprint) {
        $comment = "<!-- gdprwiz-fp: {$fingerprint} -->\n";
        return $comment . $html;
    }
    
    /**
     * LAYER 2: Micro Word Variation Pattern
     * Subtle variations in common words
     */
    public function addWordVariations($html, $fingerprint) {
        $patterns = [
            'data' => ['data', 'information', 'details'],
            'process' => ['process', 'handle', 'manage'],
            'collect' => ['collect', 'gather', 'obtain'],
            'personal' => ['personal', 'individual', 'user']
        ];
        
        // Use fingerprint to determine which variations to use
        $seed = hexdec(substr($fingerprint, 0, 8));
        srand($seed);
        
        foreach ($patterns as $word => $variations) {
            $replacement = $variations[array_rand($variations)];
            // Replace only first occurrence to create subtle differences
            $html = preg_replace('/\b' . $word . '\b/i', $replacement, $html, 1);
        }
        
        srand(); // Reset random seed
        
        return $html;
    }
    
    /**
     * LAYER 3: Zero-Width Unicode Watermark
     * Invisible characters encoding the fingerprint
     */
    public function addZeroWidthWatermark($html, $fingerprint) {
        if (!ZERO_WIDTH_WATERMARK) {
            return $html;
        }
        
        $encoded = $this->encodeToZeroWidth($fingerprint);
        
        // Insert at the end of the document
        return $html . $encoded;
    }
    
    private function encodeToZeroWidth($text) {
        $binary = '';
        for ($i = 0; $i < strlen($text); $i++) {
            $binary .= str_pad(decbin(ord($text[$i])), 8, '0', STR_PAD_LEFT);
        }
        
        $encoded = '';
        for ($i = 0; $i < strlen($binary); $i++) {
            if ($binary[$i] === '0') {
                $encoded .= $this->zeroWidthChars[0];
            } else {
                $encoded .= $this->zeroWidthChars[1];
            }
        }
        
        return $encoded;
    }
    
    public function decodeZeroWidth($encoded) {
        $binary = '';
        for ($i = 0; $i < mb_strlen($encoded); $i++) {
            $char = mb_substr($encoded, $i, 1);
            if ($char === $this->zeroWidthChars[0]) {
                $binary .= '0';
            } elseif ($char === $this->zeroWidthChars[1]) {
                $binary .= '1';
            }
        }
        
        $text = '';
        for ($i = 0; $i < strlen($binary); $i += 8) {
            $byte = substr($binary, $i, 8);
            $text .= chr(bindec($byte));
        }
        
        return $text;
    }
    
    /**
     * LAYER 4: Crypto Letter-Spacing Watermark
     * Subtle spacing variations
     */
    public function addCryptoSpacing($html, $fingerprint) {
        $style = '<style>';
        $style .= '.gdpr-document-preview { letter-spacing: 0.01em; }';
        
        // Encode fingerprint in letter-spacing variations
        $seed = hexdec(substr($fingerprint, 0, 8));
        $spacingValue = 0.01 + ($seed % 10) * 0.001;
        
        $style .= '.preview-section:nth-child(even) { letter-spacing: ' . $spacingValue . 'em; }';
        $style .= '</style>';
        
        return str_replace('</head>', $style . '</head>', $html);
    }
    
    /**
     * LAYER 5: Database Tracking
     * Store fingerprint and metadata
     */
    public function trackDocument($fingerprint, $userId, $wizardData, $metadata = []) {
        return $this->db->insert('watermark_tracking', [
            'fingerprint' => $fingerprint,
            'user_id' => $userId,
            'wizard_data_hash' => hash('sha256', json_encode($wizardData)),
            'ip_address' => (new Security())->getClientIP(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'metadata' => json_encode($metadata),
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * LAYER 6: Rate Limiting & DIFF Tracking
     */
    public function checkDuplicateRequest($userId, $dataHash, $timeWindow = 300) {
        $sql = "SELECT COUNT(*) as count 
                FROM watermark_tracking 
                WHERE user_id = ? 
                AND wizard_data_hash = ? 
                AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)";
        
        $result = $this->db->fetchOne($sql, [$userId, $dataHash, $timeWindow]);
        
        return ($result['count'] ?? 0) > 0;
    }
    
    /**
     * LAYER 7: Domain Verification Endpoint
     * Verify document ownership
     */
    public function createVerificationToken($fingerprint, $domain) {
        $token = hash('sha256', $fingerprint . $domain . SECRET_KEY);
        
        $this->db->insert('verification_tokens', [
            'fingerprint' => $fingerprint,
            'domain' => $domain,
            'token' => $token,
            'created_at' => date('Y-m-d H:i:s'),
            'expires_at' => date('Y-m-d H:i:s', time() + 86400 * 30) // 30 days
        ]);
        
        return $token;
    }
    
    public function verifyToken($token) {
        $sql = "SELECT * FROM verification_tokens 
                WHERE token = ? 
                AND expires_at > NOW()";
        
        return $this->db->fetchOne($sql, [$token]);
    }
    
    /**
     * Main Embed Function - Combines All Layers
     */
    public function embedWatermark($html, $fingerprint, $userId = null, $wizardData = []) {
        // Layer 1: HTML Fingerprint
        $html = $this->addHTMLFingerprint($html, $fingerprint);
        
        // Layer 2: Word Variations
        $html = $this->addWordVariations($html, $fingerprint);
        
        // Layer 3: Zero-Width Watermark
        $html = $this->addZeroWidthWatermark($html, $fingerprint);
        
        // Layer 4: Crypto Spacing
        $html = $this->addCryptoSpacing($html, $fingerprint);
        
        // Layer 5: Database Tracking
        if ($userId) {
            $this->trackDocument($fingerprint, $userId, $wizardData);
        }
        
        return $html;
    }
    
    /**
     * Extract Fingerprint from Document
     */
    public function extractFingerprint($html) {
        // Try to extract from HTML comment
        if (preg_match('/<!-- gdprwiz-fp: ([a-f0-9]{64}) -->/', $html, $matches)) {
            return $matches[1];
        }
        
        // Try to extract from zero-width characters
        $zeroWidthContent = '';
        for ($i = 0; $i < mb_strlen($html); $i++) {
            $char = mb_substr($html, $i, 1);
            if (in_array($char, $this->zeroWidthChars)) {
                $zeroWidthContent .= $char;
            }
        }
        
        if (!empty($zeroWidthContent)) {
            return $this->decodeZeroWidth($zeroWidthContent);
        }
        
        return null;
    }
    
    /**
     * Verify Document Authenticity
     */
    public function verifyDocument($html) {
        $fingerprint = $this->extractFingerprint($html);
        
        if (!$fingerprint) {
            return [
                'authentic' => false,
                'reason' => 'No fingerprint found'
            ];
        }
        
        // Check database
        $sql = "SELECT * FROM watermark_tracking WHERE fingerprint = ?";
        $tracking = $this->db->fetchOne($sql, [$fingerprint]);
        
        if (!$tracking) {
            return [
                'authentic' => false,
                'reason' => 'Fingerprint not found in database'
            ];
        }
        
        return [
            'authentic' => true,
            'fingerprint' => $fingerprint,
            'user_id' => $tracking['user_id'],
            'created_at' => $tracking['created_at'],
            'ip_address' => $tracking['ip_address']
        ];
    }
    
    /**
     * Detect Content Theft
     * Compare two documents for similarity
     */
    public function detectTheft($originalHtml, $suspectHtml) {
        $original = strip_tags($originalHtml);
        $suspect = strip_tags($suspectHtml);
        
        similar_text($original, $suspect, $percent);
        
        return [
            'similarity' => $percent,
            'is_theft' => $percent > 80,
            'original_fingerprint' => $this->extractFingerprint($originalHtml),
            'suspect_fingerprint' => $this->extractFingerprint($suspectHtml)
        ];
    }
    
    /**
     * Web Crawler Detection
     * Check if document has been found on other websites
     */
    public function logCrawlerDetection($fingerprint, $detectedUrl, $detectedContent) {
        return $this->db->insert('crawler_detections', [
            'fingerprint' => $fingerprint,
            'detected_url' => $detectedUrl,
            'detected_content_hash' => hash('sha256', $detectedContent),
            'detected_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Get Watermark Statistics
     */
    public function getStatistics($userId = null) {
        if ($userId) {
            $sql = "SELECT 
                        COUNT(*) as total_documents,
                        COUNT(DISTINCT fingerprint) as unique_fingerprints
                    FROM watermark_tracking 
                    WHERE user_id = ?";
            return $this->db->fetchOne($sql, [$userId]);
        }
        
        $sql = "SELECT 
                    COUNT(*) as total_documents,
                    COUNT(DISTINCT user_id) as total_users,
                    COUNT(DISTINCT fingerprint) as unique_fingerprints
                FROM watermark_tracking";
        
        return $this->db->fetchOne($sql);
    }
}
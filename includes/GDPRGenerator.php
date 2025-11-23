<?php
/**
 * GDPR Document Generator
 * Creates GDPR-compliant privacy policy documents
 */

class GDPRGenerator {
    private $lang;
    private $db;
    private $watermark;
    private $dbConnected;

    public function __construct($language, $database) {
        $this->lang = $language;
        $this->db = $database;
        $this->dbConnected = $database->isConnected();
        $this->watermark = new WatermarkSystem();
    }
    
    /**
     * Generate Preview HTML
     */
    public function generatePreview($wizardData) {
        $html = '<div class="gdpr-document-preview">';
        
        // Step 1: Company Information
        if (isset($wizardData[1])) {
            $html .= $this->generateCompanySection($wizardData[1]);
        }
        
        // Step 2: Data Collection
        if (isset($wizardData[2])) {
            $html .= $this->generateDataCollectionSection($wizardData[2]);
        }
        
        // Step 3: Services
        if (isset($wizardData[3])) {
            $html .= $this->generateServicesSection($wizardData[3]);
        }
        
        // Step 4: Legal Basis
        if (isset($wizardData[4])) {
            $html .= $this->generateLegalBasisSection($wizardData[4]);
        }
        
        // Step 5: Data Controller
        if (isset($wizardData[5])) {
            $html .= $this->generateControllerSection($wizardData[5]);
        }
        
        // General GDPR Sections
        $html .= $this->generateGeneralSections($wizardData);
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Generate Company Section
     */
    private function generateCompanySection($data) {
        $companyName = htmlspecialchars($data['company_name'] ?? '');
        $websiteName = htmlspecialchars($data['website_name'] ?? '');
        $domain = htmlspecialchars($data['domain'] ?? '');
        
        $html = '<div class="preview-section">';
        $html .= '<h4>' . $this->lang->get('preview_company_info') . '</h4>';
        $html .= '<div class="gdpr-intro">';
        $html .= '<p><strong>' . $companyName . '</strong> (' . $websiteName . ') operates the website <strong>' . $domain . '</strong>.</p>';
        $html .= '<p>This privacy policy explains how we collect, use, and protect your personal data in accordance with the General Data Protection Regulation (GDPR).</p>';
        $html .= '</div>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Generate Data Collection Section
     */
    private function generateDataCollectionSection($data) {
        if (empty($data['data_types'])) {
            return '';
        }
        
        $html = '<div class="preview-section">';
        $html .= '<h4>' . $this->lang->get('preview_data_collection') . '</h4>';
        $html .= '<p>We collect and process the following types of personal data:</p>';
        $html .= '<ul>';
        
        foreach ($data['data_types'] as $dataType) {
            $typeInfo = $this->getDataTypeInfo($dataType);
            $html .= '<li><strong>' . $typeInfo['name'] . ':</strong> ' . $typeInfo['description'] . '</li>';
        }
        
        $html .= '</ul>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Generate Services Section
     */
    private function generateServicesSection($data) {
        if (empty($data['services'])) {
            return '';
        }
        
        $html = '<div class="preview-section">';
        $html .= '<h4>' . $this->lang->get('preview_third_party_services') . '</h4>';
        $html .= '<p>We use the following third-party services that may process your personal data:</p>';
        
        foreach ($data['services'] as $serviceKey) {
            $serviceInfo = $this->getServiceInfo($serviceKey);
            
            if ($serviceInfo) {
                $html .= '<div class="service-gdpr-block">';
                $html .= '<h5>' . $serviceInfo['name'] . '</h5>';
                $html .= '<p>' . $serviceInfo['gdpr_text'] . '</p>';
                
                // Add service-specific fields if provided
                $html .= '<ul>';
                foreach ($serviceInfo['fields'] as $field) {
                    $fieldKey = 'service_' . $serviceKey . '_' . $field['key'];
                    if (isset($data[$fieldKey]) && !empty($data[$fieldKey])) {
                        $html .= '<li><strong>' . $field['label'] . ':</strong> ' . htmlspecialchars($data[$fieldKey]) . '</li>';
                    }
                }
                $html .= '</ul>';
                $html .= '</div>';
            }
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Generate Legal Basis Section
     */
    private function generateLegalBasisSection($data) {
        if (empty($data['legal_basis'])) {
            return '';
        }
        
        $html = '<div class="preview-section">';
        $html .= '<h4>' . $this->lang->get('preview_legal_basis') . '</h4>';
        $html .= '<p>The legal basis for processing your personal data is:</p>';
        $html .= '<ul>';
        
        foreach ($data['legal_basis'] as $basis) {
            $basisInfo = $this->getLegalBasisInfo($basis);
            $html .= '<li><strong>' . $basisInfo['title'] . ':</strong> ' . $basisInfo['description'] . '</li>';
        }
        
        $html .= '</ul>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Generate Data Controller Section
     */
    private function generateControllerSection($data) {
        $html = '<div class="preview-section">';
        $html .= '<h4>' . $this->lang->get('preview_data_controller') . '</h4>';
        $html .= '<p>The data controller responsible for your personal data is:</p>';
        $html .= '<div class="controller-info">';
        $html .= '<p><strong>' . htmlspecialchars($data['controller_name'] ?? '') . '</strong><br>';
        $html .= nl2br(htmlspecialchars($data['controller_address'] ?? '')) . '<br>';
        $html .= 'Email: ' . htmlspecialchars($data['controller_email'] ?? '') . '<br>';
        
        if (!empty($data['controller_phone'])) {
            $html .= 'Phone: ' . htmlspecialchars($data['controller_phone']) . '<br>';
        }
        
        if (!empty($data['dpo_email'])) {
            $html .= '<br>Data Protection Officer: ' . htmlspecialchars($data['dpo_email']);
        }
        
        $html .= '</p></div>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Generate General GDPR Sections
     */
    private function generateGeneralSections($wizardData) {
        $html = '';
        
        // User Rights (GDPR Articles 12-22)
        $html .= '<div class="preview-section">';
        $html .= '<h4>Your Rights Under GDPR</h4>';
        $html .= '<p>You have the following rights regarding your personal data:</p>';
        $html .= '<ul>';
        $html .= '<li><strong>Right to Access (Art. 15):</strong> You can request a copy of your personal data.</li>';
        $html .= '<li><strong>Right to Rectification (Art. 16):</strong> You can request correction of inaccurate data.</li>';
        $html .= '<li><strong>Right to Erasure (Art. 17):</strong> You can request deletion of your data under certain conditions.</li>';
        $html .= '<li><strong>Right to Restrict Processing (Art. 18):</strong> You can request limitation of data processing.</li>';
        $html .= '<li><strong>Right to Data Portability (Art. 20):</strong> You can receive your data in a structured format.</li>';
        $html .= '<li><strong>Right to Object (Art. 21):</strong> You can object to certain types of processing.</li>';
        $html .= '<li><strong>Right to Lodge a Complaint:</strong> You can file a complaint with a supervisory authority.</li>';
        $html .= '</ul>';
        $html .= '</div>';
        
        // Data Security
        $html .= '<div class="preview-section">';
        $html .= '<h4>Data Security</h4>';
        $html .= '<p>We implement appropriate technical and organizational measures to protect your personal data against unauthorized access, alteration, disclosure, or destruction. This includes:</p>';
        $html .= '<ul>';
        $html .= '<li>SSL/TLS encryption for data transmission</li>';
        $html .= '<li>Secure server infrastructure</li>';
        $html .= '<li>Regular security audits</li>';
        $html .= '<li>Access controls and authentication</li>';
        $html .= '<li>Data backup and disaster recovery procedures</li>';
        $html .= '</ul>';
        $html .= '</div>';
        
        // Data Retention
        $html .= '<div class="preview-section">';
        $html .= '<h4>Data Retention</h4>';
        $html .= '<p>We retain your personal data only as long as necessary for the purposes stated in this privacy policy or as required by law. After the retention period, data will be securely deleted or anonymized.</p>';
        $html .= '</div>';
        
        // Cookies
        $html .= '<div class="preview-section">';
        $html .= '<h4>Cookies and Tracking Technologies</h4>';
        $html .= '<p>Our website uses cookies and similar tracking technologies. Cookies are small text files stored on your device. We use:</p>';
        $html .= '<ul>';
        $html .= '<li><strong>Essential Cookies:</strong> Necessary for website functionality</li>';
        $html .= '<li><strong>Analytics Cookies:</strong> Help us understand how visitors use our website</li>';
        $html .= '<li><strong>Marketing Cookies:</strong> Used to deliver relevant advertisements</li>';
        $html .= '</ul>';
        $html .= '<p>You can control cookies through your browser settings.</p>';
        $html .= '</div>';
        
        // International Transfers
        $html .= '<div class="preview-section">';
        $html .= '<h4>International Data Transfers</h4>';
        $html .= '<p>Some of our service providers may be located outside the European Economic Area (EEA). In such cases, we ensure appropriate safeguards are in place, including:</p>';
        $html .= '<ul>';
        $html .= '<li>Standard Contractual Clauses (SCC) approved by the European Commission</li>';
        $html .= '<li>Adequacy decisions for countries with equivalent data protection</li>';
        $html .= '<li>EU-US Data Privacy Framework compliance where applicable</li>';
        $html .= '</ul>';
        $html .= '</div>';
        
        // Changes to Policy
        $html .= '<div class="preview-section">';
        $html .= '<h4>Changes to This Privacy Policy</h4>';
        $html .= '<p>We may update this privacy policy from time to time. Changes will be posted on this page with an updated revision date. We encourage you to review this policy periodically.</p>';
        $html .= '<p><strong>Last Updated:</strong> ' . date('F j, Y') . '</p>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Get Data Type Information
     */
    private function getDataTypeInfo($dataType) {
        $types = [
            'ip_address' => [
                'name' => 'IP Address',
                'description' => 'Your IP address is collected for security purposes and to analyze website traffic.'
            ],
            'cookies' => [
                'name' => 'Cookies',
                'description' => 'We use cookies to enhance your browsing experience and analyze website usage.'
            ],
            'form_data' => [
                'name' => 'Form Data',
                'description' => 'Information you provide through contact forms, including name, email, and message content.'
            ],
            'user_accounts' => [
                'name' => 'User Account Information',
                'description' => 'Registration and login data including username, email, and password (encrypted).'
            ],
            'payment_data' => [
                'name' => 'Payment Information',
                'description' => 'Payment details processed securely through third-party payment processors.'
            ],
            'analytics_data' => [
                'name' => 'Analytics Data',
                'description' => 'Usage statistics, page views, and behavior data to improve our services.'
            ],
            'device_info' => [
                'name' => 'Device Information',
                'description' => 'Browser type, operating system, device type, and screen resolution.'
            ],
            'location_data' => [
                'name' => 'Location Data',
                'description' => 'Approximate geographic location based on IP address or GPS (with your consent).'
            ]
        ];
        
        return $types[$dataType] ?? ['name' => $dataType, 'description' => ''];
    }
    
    /**
     * Get Service Information from Database
     */
    private function getServiceInfo($serviceKey) {
        $lang = $this->lang->getCurrentLanguage();

        if (!$this->dbConnected) {
            require_once __DIR__ . '/FallbackData.php';
            return FallbackData::getServiceByKey($serviceKey, $lang);
        }

        $sql = "SELECT
                    s.id,
                    s.service_key,
                    st.service_name,
                    st.service_description,
                    st.gdpr_text
                FROM services s
                LEFT JOIN service_translations st ON s.id = st.service_id AND st.language_code = ?
                WHERE s.service_key = ? AND s.is_active = 1";

        $service = $this->db->fetchOne($sql, [$lang, $serviceKey]);

        if (!$service) {
            return null;
        }

        // Get service fields
        $fieldsSql = "SELECT
                        sf.field_key,
                        sft.field_label
                      FROM service_fields sf
                      LEFT JOIN service_field_translations sft ON sf.id = sft.field_id AND sft.language_code = ?
                      WHERE sf.service_id = ?";

        $fields = $this->db->fetchAll($fieldsSql, [$lang, $service['id']]);

        return [
            'name' => $service['service_name'],
            'description' => $service['service_description'],
            'gdpr_text' => $service['gdpr_text'],
            'fields' => $fields
        ];
    }
    
    /**
     * Get Legal Basis Information
     */
    private function getLegalBasisInfo($basis) {
        $bases = [
            'consent' => [
                'title' => 'Consent (Art. 6(1)(a) GDPR)',
                'description' => 'You have given explicit consent for processing your personal data.'
            ],
            'contract' => [
                'title' => 'Contract (Art. 6(1)(b) GDPR)',
                'description' => 'Processing is necessary for the performance of a contract with you.'
            ],
            'legal_obligation' => [
                'title' => 'Legal Obligation (Art. 6(1)(c) GDPR)',
                'description' => 'Processing is necessary to comply with legal obligations.'
            ],
            'vital_interests' => [
                'title' => 'Vital Interests (Art. 6(1)(d) GDPR)',
                'description' => 'Processing is necessary to protect vital interests.'
            ],
            'public_interest' => [
                'title' => 'Public Interest (Art. 6(1)(e) GDPR)',
                'description' => 'Processing is necessary for tasks carried out in the public interest.'
            ],
            'legitimate_interests' => [
                'title' => 'Legitimate Interests (Art. 6(1)(f) GDPR)',
                'description' => 'Processing is necessary for our legitimate interests.'
            ]
        ];
        
        return $bases[$basis] ?? ['title' => $basis, 'description' => ''];
    }
    
    /**
     * Generate Full GDPR Document with Watermark
     */
    public function generateFullDocument($wizardData, $format = 'html') {
        $content = $this->generatePreview($wizardData);
        
        // Add watermark
        if (WATERMARK_ENABLED) {
            $fingerprint = $this->watermark->generateFingerprint($wizardData);
            $content = $this->watermark->embedWatermark($content, $fingerprint);
        }
        
        switch ($format) {
            case 'pdf':
                return $this->convertToPDF($content);
            case 'txt':
                return $this->convertToText($content);
            default:
                return $content;
        }
    }
    
    /**
     * Convert HTML to PDF
     */
    private function convertToPDF($html) {
        require_once '../vendor/mpdf/mpdf.php';
        
        $mpdf = new \Mpdf\Mpdf([
            'format' => 'A4',
            'margin_left' => 20,
            'margin_right' => 20,
            'margin_top' => 20,
            'margin_bottom' => 20
        ]);
        
        $mpdf->WriteHTML($html);
        return $mpdf->Output('', 'S'); // Return as string
    }
    
    /**
     * Convert HTML to Plain Text
     */
    private function convertToText($html) {
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }
}
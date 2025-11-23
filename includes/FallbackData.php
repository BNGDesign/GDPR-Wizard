<?php
/**
 * Static fallback datasets used when database is unavailable.
 */
 class FallbackData
{
    private static array $services = [
        'google_analytics' => [
            'category' => 'Analytics',
            'icon' => 'bi-graph-up',
            'translations' => [
                'en' => [
                    'name' => 'Google Analytics',
                    'description' => 'Tracks visitor behaviour to improve site performance.',
                    'gdpr' => 'We use Google Analytics to understand how visitors interact with our site. Data is anonymized where possible and processed under EU Model Clauses.'
                ],
                'de' => [
                    'name' => 'Google Analytics',
                    'description' => 'Verfolgt das Besucherverhalten zur Optimierung der Website.',
                    'gdpr' => 'Wir nutzen Google Analytics, um zu verstehen, wie Besucher unsere Seite verwenden. Daten werden nach Möglichkeit anonymisiert und gemäß EU-Standardklauseln verarbeitet.'
                ],
                'tr' => [
                    'name' => 'Google Analytics',
                    'description' => 'Ziyaretçi davranışını takip ederek siteyi iyileştirir.',
                    'gdpr' => 'Ziyaretçi davranışlarını anlamak için Google Analytics kullanıyoruz. Veriler mümkün olduğunca anonimleştirilir ve AB standartlarına göre işlenir.'
                ]
            ],
            'fields' => [
                [
                    'key' => 'tracking_id',
                    'label' => 'Tracking ID',
                    'placeholder' => 'G-XXXXXXX',
                    'type' => 'text',
                    'required' => true
                ],
                [
                    'key' => 'property_id',
                    'label' => 'Property ID',
                    'placeholder' => '123456789',
                    'type' => 'text',
                    'required' => false
                ]
            ]
        ],
        'mailchimp' => [
            'category' => 'Marketing',
            'icon' => 'bi-envelope-paper-heart',
            'translations' => [
                'en' => [
                    'name' => 'Mailchimp',
                    'description' => 'Email marketing and newsletter delivery.',
                    'gdpr' => 'We use Mailchimp to send newsletters. Data is stored on EU-compatible servers with contractual safeguards.'
                ],
                'de' => [
                    'name' => 'Mailchimp',
                    'description' => 'E-Mail-Marketing und Newsletter-Versand.',
                    'gdpr' => 'Wir nutzen Mailchimp für Newsletter. Daten werden auf EU-kompatiblen Servern mit vertraglichen Schutzmaßnahmen gespeichert.'
                ],
                'tr' => [
                    'name' => 'Mailchimp',
                    'description' => 'E-posta pazarlaması ve bülten gönderimi.',
                    'gdpr' => 'Bülten göndermek için Mailchimp kullanıyoruz. Veriler, sözleşmesel güvencelerle AB uyumlu sunucularda saklanır.'
                ]
            ],
            'fields' => [
                [
                    'key' => 'api_key',
                    'label' => 'API Key',
                    'placeholder' => 'xxxx-us6',
                    'type' => 'text',
                    'required' => true
                ],
                [
                    'key' => 'audience_id',
                    'label' => 'Audience ID',
                    'placeholder' => '123abc',
                    'type' => 'text',
                    'required' => true
                ]
            ]
        ]
    ];
    public static function getServiceByKey(string $serviceKey, string $lang = 'en'): ?array
    {
        $data = self::$services[$serviceKey] ?? null;
        if (!$data) {
            return null;
        }
        $translation = $data['translations'][$lang] ?? $data['translations']['en'];
        return [
            'name' => $translation['name'],
            'description' => $translation['description'],
            'gdpr_text' => $translation['gdpr'],
            'fields' => array_map(function ($field) {
                return [
                    'field_key' => $field['key'],
                    'field_label' => $field['label'],
                    'field_placeholder' => $field['placeholder'] ?? '',
                    'field_type' => $field['type'] ?? 'text',
                    'is_required' => $field['required'] ?? false
                ];
            }, $data['fields'])
        ];
    }
    public static function getServices(string $lang = 'en'): array
    {
        $grouped = [];
        foreach (self::$services as $key => $service) {
            $translation = $service['translations'][$lang] ?? $service['translations']['en'];
            $category = $service['category'];
            if (!isset($grouped[$category])) {
                $grouped[$category] = [];
            }
            $grouped[$category][] = [
                'key' => $key,
                'name' => $translation['name'],
                'description' => $translation['description'],
                'icon' => $service['icon'],
                'fields' => array_map(function ($field) {
                    return [
                        'key' => $field['key'],
                        'label' => $field['label'],
                        'placeholder' => $field['placeholder'] ?? '',
                        'type' => $field['type'] ?? 'text',
                        'required' => (bool)($field['required'] ?? false)
                    ];
                }, $service['fields'])
            ];
        }
        return $grouped;
    }
}
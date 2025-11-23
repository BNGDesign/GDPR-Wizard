<?php
/**
 * Multi-Language System (Database Driven)
 * Single Source of Truth: Database 'languages' & 'translations' tables.
 */

class Language {
    private $currentLang;
    private $translations = [];
    private $activeLanguages = []; // Aktif dillerin listesi (Cache)
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
        
        // 1. Önce veritabanındaki aktif dilleri öğren
        $this->loadActiveLanguages();
        
        // 2. Varsayılan dili ayarla
        $this->currentLang = DEFAULT_LANGUAGE;
    }
    
    /**
     * Veritabanından aktif dilleri çeker ve hafızaya alır.
     */
    private function loadActiveLanguages() {
        if ($this->db && $this->db->isConnected()) {
            // Sadece aktif olanları çek
            $langs = $this->db->fetchAll("SELECT language_code FROM languages WHERE is_active = 1");
            foreach ($langs as $lang) {
                $this->activeLanguages[] = $lang['language_code'];
            }
        }
        
        // Eğer veritabanı boşsa veya hata varsa, config'deki varsayılanı ekle ki sistem çökmesin.
        if (empty($this->activeLanguages)) {
            $this->activeLanguages[] = DEFAULT_LANGUAGE;
        }
    }

    /**
     * Dili ayarlar (Eğer veritabanında aktifse)
     */
    public function setLanguage($lang) {

        if (in_array($lang, $this->activeLanguages)) {
            $this->currentLang = $lang;
            $this->loadTranslations();
        } else {
            $this->loadTranslations(); 
        }
    }
    
    public function getCurrentLanguage() {
        return $this->currentLang;
    }
    
    /**
     * Çevirileri SADECE veritabanından yükler.
     * JSON fallback kaldırıldı çünkü yönetim paneli veritabanını kullanıyor.
     */
    private function loadTranslations() {
        $this->translations = []; // Önce temizle
        
        if ($this->db && $this->db->isConnected()) {
            $sql = "SELECT translation_key, translation_value FROM translations WHERE language_code = ?";
            $results = $this->db->fetchAll($sql, [$this->currentLang]);
            
            if (!empty($results)) {
                foreach ($results as $row) {
                    $this->translations[$row['translation_key']] = $row['translation_value'];
                }
            }
        }
    }
    
    public function get($key, $fallback = null) {
        if (isset($this->translations[$key])) {
            return $this->translations[$key];
        }
        
        // Çeviri bulunamadıysa fallback veya anahtarı [KEY] şeklinde göster
        // Geliştirme aşamasında anahtarı görmek iyidir.
        return $fallback ?? "[{$key}]";
    }
    
    public function has($key) {
        return isset($this->translations[$key]);
    }
    
    public function getAll() {
        return $this->translations;
    }
    
    /**
     * {{placeholder}} değiştirme özelliği
     */
    public function translate($key, $replacements = []) {
        $text = $this->get($key);
        
        foreach ($replacements as $placeholder => $value) {
            $text = str_replace("{{$placeholder}}", $value, $text);
        }
        
        return $text;
    }
    
    /**
     * Frontend'deki dil değiştirici (switcher) için aktif dilleri döndürür
     */
    public function getAvailableLanguages() {
        if ($this->db && $this->db->isConnected()) {
            // sort_order KALDIRILDI. Artık sadece varsayılan dil ve isme göre sıralıyor.
            $sql = "SELECT language_code, language_name, is_default 
                    FROM languages 
                    WHERE is_active = 1 
                    ORDER BY is_default DESC, language_name ASC"; 
            return $this->db->fetchAll($sql);
        }
        
        // DB hatası varsa manuel bir array dön
        return [
            ['language_code' => DEFAULT_LANGUAGE, 'language_name' => strtoupper(DEFAULT_LANGUAGE)]
        ];
    }

    // --- ADMIN FONKSİYONLARI (Kullanıyorsan) ---
    
    public function saveTranslation($key, $value, $lang = null) {
        $lang = $lang ?? $this->currentLang;
        
        $sql = "INSERT INTO translations (language_code, translation_key, translation_value, updated_at) 
                VALUES (?, ?, ?, NOW()) 
                ON DUPLICATE KEY UPDATE translation_value = VALUES(translation_value), updated_at = NOW()";
        
        return $this->db->query($sql, [$lang, $key, $value]);
    }
    
    public function deleteTranslation($key, $lang = null) {
        $lang = $lang ?? $this->currentLang;
        
        $sql = "DELETE FROM translations WHERE language_code = ? AND translation_key = ?";
        return $this->db->query($sql, [$lang, $key]);
    }
}
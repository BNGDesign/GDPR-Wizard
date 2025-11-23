document.addEventListener('DOMContentLoaded', function() {
    const steps = document.querySelectorAll('.step-content');
    const nextBtn = document.getElementById('nextBtn');
    const prevBtn = document.getElementById('prevBtn');
    
    // URL'den mevcut adımı al, yoksa 1 yap
    let currentStep = parseInt(new URLSearchParams(window.location.search).get('step') || '1');
    
    // Başlangıç ayarları
    updateStepDisplay(currentStep);
    
    // ==========================================
    // 1. DİL DEĞİŞTİRME MANTIĞI (GLOBAL & GÜVENLİ)
    // ==========================================
    // Bu fonksiyon HTML tarafındaki onclick="setLanguage('fr')" tarafından çağrılır.
    // Sayfayı değiştirmeden önce verileri veritabanına kaydeder.
    window.setLanguage = function(newLang) {
        // Butonlara tekrar basılmasını engelle (UX)
        const allFlags = document.querySelectorAll('.lang-flag-btn');
        allFlags.forEach(btn => btn.style.pointerEvents = 'none');

        // Mevcut adımı kaydet
        saveStepData(currentStep)
            .then(() => {
                // Başarılıysa yönlendir
                window.location.href = '?lang=' + newLang + '&step=' + currentStep;
            })
            .catch((err) => {
                console.error("Dil değişirken kayıt hatası:", err);
                // Hata olsa bile kullanıcıyı bekletme, yönlendir
                window.location.href = '?lang=' + newLang + '&step=' + currentStep;
            });
    };

    // ==========================================
    // 2. CHECKBOX KARTLARI (Static Steps)
    // ==========================================
    document.querySelectorAll('.checkbox-card').forEach(card => {
        const checkbox = card.querySelector('input[type="checkbox"]');
        
        card.addEventListener('click', function(e) {
            // Eğer doğrudan checkbox'a tıklanmadıysa (karta tıklandıysa)
            if(e.target !== checkbox) {
                checkbox.checked = !checkbox.checked;
            }
            this.classList.toggle('checked', checkbox.checked);
        });
        
        // Sayfa yüklendiğinde seçiliyse class ekle
        if(checkbox && checkbox.checked) {
            card.classList.add('checked');
        }
    });
    
    // ==========================================
    // 3. SERVİS KARTLARI (Dynamic Steps)
    // ==========================================
    document.addEventListener('click', function(e) {
        const serviceCard = e.target.closest('.service-card');
        if(serviceCard) {
            const checkbox = serviceCard.querySelector('input[type="checkbox"]');
            
            // Inputlara (text) tıklanınca kartı seçip bırakmasın
            if(e.target.tagName === 'INPUT' && e.target.type !== 'checkbox') return;
            if(e.target.tagName === 'LABEL') return;

            if(checkbox && e.target !== checkbox) {
                checkbox.checked = !checkbox.checked;
            }
            serviceCard.classList.toggle('selected', checkbox?.checked);
        }
    });
    
    // 3. Adımdaysak servisleri API'den çek
    if(currentStep === 3) {
        loadServices();
    }
    
    // ==========================================
    // 4. NAVİGASYON (İLERİ / GERİ)
    // ==========================================
    if(nextBtn) {
        nextBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            if(validateStep(currentStep)) {
                // İlerlerken kaydet
                saveStepData(currentStep).then(() => {
                    if(currentStep < 7) {
                        currentStep++;
                        updateStepDisplay(currentStep);
                        
                        // Dinamik yüklemeler
                        if(currentStep === 3) loadServices();
                        if(currentStep === 6) loadPreview();
                    }
                });
            }
        });
    }
    
    if(prevBtn) {
        prevBtn.addEventListener('click', function(e) {
            e.preventDefault();
            if(currentStep > 1) {
                // Geri giderken de kaydetmek iyi bir pratiktir
                saveStepData(currentStep).then(() => {
                    currentStep--;
                    updateStepDisplay(currentStep);
                });
            }
        });
    }
    
    // ==========================================
    // YARDIMCI FONKSİYONLAR
    // ==========================================

    function updateStepDisplay(step) {
        // Tüm adımları gizle
        steps.forEach(s => s.classList.remove('active'));
        
        // Aktif adımı göster
        const currentStepElement = document.querySelector(`[data-step="${step}"]`);
        if(currentStepElement) {
            currentStepElement.classList.add('active');
        }
        
        // URL'i güncelle (Sayfa yenilenmeden)
        const url = new URLSearchParams(window.location.search);
        url.set('step', step);
        window.history.pushState({}, '', '?' + url.toString());
        
        // Göstergeleri (Breadcrumb) güncelle
        document.querySelectorAll('.step-indicator').forEach((indicator, index) => {
            indicator.classList.remove('active', 'completed');
            if(index + 1 === step) {
                indicator.classList.add('active');
            } else if(index + 1 < step) {
                indicator.classList.add('completed');
            }
        });
        
        // Progress Bar
        const progress = ((step - 1) / 6) * 100;
        const progressBar = document.querySelector('.progress-bar');
        if(progressBar) {
            progressBar.style.width = progress + '%';
        }
        
        // Buton Durumları
        if(prevBtn) prevBtn.style.visibility = step === 1 ? 'hidden' : 'visible';
        
        if(nextBtn) {
            // Son adım kontrolü (Dil desteği için metni HTML'den almak daha iyidir ama burada JS ile yapılmış)
            // İdealde: nextBtn.dataset.finishText ve nextBtn.dataset.nextText kullanılmalı.
            nextBtn.innerHTML = step === 7 
                ? 'Finish <i class="fad fa-check"></i>' 
                : 'Next Step <i class="fad fa-arrow-right"></i>';
        }
        
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
    
    function validateStep(step) {
        const stepElement = document.querySelector(`[data-step="${step}"]`);
        if(!stepElement) return true;
        
        const requiredFields = stepElement.querySelectorAll('[required]');
        let isValid = true;
        
        requiredFields.forEach(field => {
            const value = field.value.trim();
            if(!value) {
                field.classList.add('is-invalid');
                isValid = false;
            } else {
                field.classList.remove('is-invalid');
            }
        });
        
        if(!isValid) {
            // Buradaki mesajı da dil dosyasından almak gerekir (İleride geliştirebilirsin)
            alert('Lütfen tüm zorunlu alanları doldurun / Please fill required fields');
        }
        
        return isValid;
    }
    
    function saveStepData(step) {
        const stepElement = document.querySelector(`[data-step="${step}"]`);
        if(!stepElement) return Promise.resolve(); // Hata vermeden boş dön
        
        const formData = new FormData();
        formData.append('step', step);
        
        // Form verilerini topla
        stepElement.querySelectorAll('input, select, textarea').forEach(input => {
            if(input.type === 'checkbox') {
                if(input.checked) {
                    formData.append(input.name, input.value);
                }
            } else if(input.name) {
                formData.append(input.name, input.value);
            }
        });
        
        // API'ye gönder
        return fetch('api/save-step.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .catch(error => console.error('Save error:', error));
    }
    
    function loadServices() {
        const container = document.getElementById('servicesContainer');
        if(!container) return;
        
        container.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary"></div></div>';
        
        const lang = new URLSearchParams(window.location.search).get('lang') || 'tr';
        
        fetch('api/get-services.php?lang=' + lang)
        .then(response => response.json())
        .then(data => {
            if(!data.success || !data.categories) {
                container.innerHTML = '<div class="alert alert-warning">Servis bulunamadı</div>';
                return;
            }
            
            container.innerHTML = '';
            
            Object.keys(data.categories).forEach(category => {
                const services = data.categories[category];
                const categoryDiv = document.createElement('div');
                categoryDiv.className = 'mb-4';
                categoryDiv.innerHTML = `<h4 class="mb-3">${category}</h4>`;
                
                services.forEach(service => {
                    categoryDiv.appendChild(createServiceCard(service));
                });
                
                container.appendChild(categoryDiv);
            });
        })
        .catch(error => {
            console.error(error);
            container.innerHTML = '<div class="alert alert-danger">Servisler yüklenemedi</div>';
        });
    }
    
    function createServiceCard(service) {
        const card = document.createElement('div');
        const isSelected = service.selected === true;
        card.className = isSelected ? 'service-card selected' : 'service-card';
        
        let fieldsHTML = '';
        if(service.fields && service.fields.length > 0) {
            fieldsHTML = '<div class="service-fields"><div class="row g-3">';
            service.fields.forEach(field => {
                const savedValue = field.value || '';
                fieldsHTML += `
                    <div class="col-md-6">
                        <label class="form-label">${field.label}</label>
                        <input type="text" class="form-control" 
                               name="service_${service.key}_${field.key}" 
                               value="${savedValue}">
                    </div>
                `;
            });
            fieldsHTML += '</div></div>';
        }
        
        card.innerHTML = `
            <div class="service-card-header">
                <div class="service-icon"><i class="${service.icon}"></i></div>
                <div class="service-info flex-grow-1">
                    <h4>${service.name}</h4>
                    <p>${service.description}</p>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" 
                           name="services[]" value="${service.key}"
                           ${isSelected ? 'checked' : ''}>
                </div>
            </div>
            ${fieldsHTML}
        `;
        return card;
    }
    
    function loadPreview() {
        const previewContent = document.getElementById('previewContent');
        if(!previewContent) return;
        
        previewContent.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary"></div></div>';
        
        const formData = new FormData(document.getElementById('wizardForm'));
        
        fetch('api/generate-preview.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if(data.success) {
                previewContent.innerHTML = data.html;
            } else {
                previewContent.innerHTML = '<div class="alert alert-danger">Önizleme oluşturulamadı</div>';
            }
        })
        .catch(() => {
            previewContent.innerHTML = '<div class="alert alert-danger">Hata oluştu</div>';
        });
    }
    
    // Download İşlemleri
    ['downloadPDF', 'downloadHTML', 'downloadTXT'].forEach(id => {
        const btn = document.getElementById(id);
        if(btn) {
            const format = id.replace('download', '').toLowerCase();
            btn.addEventListener('click', () => downloadGDPR(format, btn));
        }
    });
    
    function downloadGDPR(format, button) {
        const formData = new FormData(document.getElementById('wizardForm'));
        formData.append('format', format);
        
        const originalHTML = button.innerHTML;
        button.disabled = true;
        button.innerHTML = '<span class="spinner-border spinner-border-sm"></span> ...';
        
        fetch('api/generate-gdpr.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.blob())
        .then(blob => {
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `gdpr-privacy-policy.${format}`;
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            a.remove();
            
            button.disabled = false;
            button.innerHTML = originalHTML;
        })
        .catch(() => {
            alert('İndirme başarısız');
            button.disabled = false;
            button.innerHTML = originalHTML;
        });
    }
});
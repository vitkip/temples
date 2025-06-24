function showAlert(message, type = 'info', duration = 3000) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type}`;
    alertDiv.innerHTML = `
        <div class="alert-icon">
            <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-triangle' : 'info-circle'}"></i>
        </div>
        <div class="alert-content">
            <p>${message}</p>
        </div>
    `;
    
    document.body.appendChild(alertDiv);
    
    // Auto remove after duration
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.remove();
        }
    }, duration);
}

document.addEventListener('DOMContentLoaded', function() {
    // ตัวแปรสำหรับ mobile detection
    const isMobile = window.innerWidth <= 768;
    const isTouch = 'ontouchstart' in window;
    
    // Tab functionality with mobile support
    const tabBtns = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');
    const tabNav = document.querySelector('.tab-nav');
    
    tabBtns.forEach((btn, index) => {
        btn.addEventListener('click', function() {
            const targetTab = this.getAttribute('data-tab');
            
            // Remove active class from all tabs
            tabBtns.forEach(b => b.classList.remove('active'));
            tabContents.forEach(c => c.classList.remove('active'));
            
            // Add active class to clicked tab
            this.classList.add('active');
            document.getElementById(targetTab + '-tab').classList.add('active');
            
            // Update tab nav animation (desktop only)
            if (!isMobile) {
                tabNav.classList.toggle('excel-active', index === 1);
            }
            
            // Smooth scroll to top on mobile
            if (isMobile) {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        });
        
        // Keyboard navigation
        btn.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                this.click();
            }
        });
    });
    
    // Enhanced photo upload with touch support
    const photoInput = document.getElementById('photo');
    const photoPreview = document.getElementById('photoPreview');
    const removePhotoBtn = document.getElementById('removePhoto');
    
    // Click/Touch handlers
    function handlePhotoSelect() {
        photoInput.click();
    }
    
    photoPreview.addEventListener('click', handlePhotoSelect);
    if (isTouch) {
        photoPreview.addEventListener('touchend', function(e) {
            e.preventDefault();
            handlePhotoSelect();
        });
    }
    
    // Keyboard accessibility
    photoPreview.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            handlePhotoSelect();
        }
    });
    
    photoInput.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            // Validate file
            if (!['image/jpeg', 'image/png', 'image/gif', 'image/webp'].includes(file.type)) {
                showAlert('ກະລຸນາເລືອກໄຟລ໌ຮູບພາບທີ່ຖືກຕ້ອງ (JPG, PNG, GIF, WebP)', 'error');
                return;
            }
            
            if (file.size > 5 * 1024 * 1024) {
                showAlert('ຂະໜາດໄຟລ໌ຮູບພາບຕ້ອງບໍ່ເກີນ 5MB', 'error');
                return;
            }
            
            const reader = new FileReader();
            reader.onload = function(e) {
                photoPreview.innerHTML = `<img src="${e.target.result}" alt="Preview" class="preview-image">`;
                photoPreview.classList.add('has-image');
                removePhotoBtn.style.display = 'block';
                
                // Add success feedback
                showAlert('ເລືອກຮູບພາບສຳເລັດ', 'success', 2000);
            };
            reader.readAsDataURL(file);
        }
    });
    
    removePhotoBtn.addEventListener('click', function() {
        photoInput.value = '';
        photoPreview.innerHTML = `
            <div class="photo-placeholder">
                <i class="fas fa-camera"></i>
                <p>ກົດເພື່ອເລືອກຮູບ</p>
                <span class="text-sm text-gray-500">JPG, PNG, GIF ຫຼື WebP</span>
                <span class="text-xs text-gray-400">ຂະໜາດສູງສຸດ 5MB</span>
            </div>
        `;
        photoPreview.classList.remove('has-image');
        this.style.display = 'none';
    });
    
    // Smart Pansa calculation
    const ordinationInput = document.getElementById('ordination_date');
    const pansaInput = document.getElementById('pansa');
    
    ordinationInput.addEventListener('change', function() {
        if (this.value) {
            const ordinationDate = new Date(this.value);
            const currentDate = new Date();
            
            // Buddhist calendar calculation (Pansa starts around July)
            let pansa = currentDate.getFullYear() - ordinationDate.getFullYear();
            
            // Adjust for Pansa season
            const currentMonth = currentDate.getMonth();
            const ordinationMonth = ordinationDate.getMonth();
            
            if (currentMonth < 6 || (currentMonth === 6 && currentDate.getDate() < 15)) {
                pansa--;
            }
            
            pansaInput.value = Math.max(0, pansa);
        }
    });
    
    // Form validation
    const form = document.getElementById('monkForm');
    form.addEventListener('submit', function(e) {
        const submitBtn = form.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ກຳລັງບັນທຶກ...';
        
        // Re-enable after 3 seconds if form doesn't submit
        setTimeout(() => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-save"></i> ບັນທຶກຂໍ້ມູນ';
        }, 3000);
    });

    // Excel import functionality
    const uploadArea = document.getElementById('uploadArea');
    const excelFile = document.getElementById('excelFile');
    const fileInfo = document.getElementById('fileInfo');
    const importBtn = document.getElementById('importBtn');
    const removeFileBtn = document.getElementById('removeFile');
    const importForm = document.getElementById('excelImportForm');
    
    // Drag and drop
    uploadArea.addEventListener('dragover', function(e) {
        e.preventDefault();
        this.classList.add('dragover');
    });
    
    uploadArea.addEventListener('dragleave', function(e) {
        e.preventDefault();
        this.classList.remove('dragover');
    });
    
    uploadArea.addEventListener('drop', function(e) {
        e.preventDefault();
        this.classList.remove('dragover');
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            handleFileSelect(files[0]);
        }
    });
    
    uploadArea.addEventListener('click', function() {
        excelFile.click();
    });
    
    excelFile.addEventListener('change', function(e) {
        if (e.target.files.length > 0) {
            handleFileSelect(e.target.files[0]);
        }
    });
    
    removeFileBtn.addEventListener('click', function() {
        excelFile.value = '';
        fileInfo.style.display = 'none';
        uploadArea.style.display = 'block';
        importBtn.disabled = true;
    });
    
    function handleFileSelect(file) {
        const allowedTypes = ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel'];
        
        if (!allowedTypes.includes(file.type)) {
            alert('ກະລຸນາເລືອກໄຟລ໌ Excel (.xlsx, .xls) ເທົ່ານັ້ນ');
            return;
        }
        
        if (file.size > 10 * 1024 * 1024) { // 10MB
            alert('ຂະໜາດໄຟລ໌ໃຫຍ່ເກີນໄປ (ສູງສຸດ 10MB)');
            return;
        }
        
        // Show file info
        fileInfo.querySelector('.file-name').textContent = file.name;
        fileInfo.querySelector('.file-size').textContent = formatFileSize(file.size);
        fileInfo.style.display = 'flex';
        uploadArea.style.display = 'none';
        importBtn.disabled = false;
    }
    
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
        // เพิ่มฟังก์ชันนี้ที่ต้นไฟล์
    
    function showAlert(message, type = 'info', duration = 3000) {
        // ลบ alert เก่าที่อาจจะมีอยู่
        const existingAlert = document.querySelector('.alert.fixed-alert');
        if (existingAlert) {
            existingAlert.remove();
        }
        
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} fixed-alert`;
        alertDiv.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            min-width: 300px;
            max-width: 400px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            animation: slideInRight 0.3s ease;
        `;
        
        alertDiv.innerHTML = `
            <div class="alert-icon">
                <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-triangle' : 'info-circle'}"></i>
            </div>
            <div class="alert-content">
                <p>${message}</p>
            </div>
            <button type="button" class="alert-close" onclick="this.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>
        `;
        
        document.body.appendChild(alertDiv);
        
        // Auto remove after duration
        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.style.animation = 'slideOutRight 0.3s ease';
                setTimeout(() => alertDiv.remove(), 300);
            }
        }, duration);
    }
    
    // เพิ่ม CSS animation
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        @keyframes slideOutRight {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(100%); opacity: 0; }
        }
        .alert-close {
            position: absolute;
            top: 8px;
            right: 8px;
            background: none;
            border: none;
            color: inherit;
            cursor: pointer;
            opacity: 0.7;
        }
        .alert-close:hover {
            opacity: 1;
        }
    `;
    document.head.appendChild(style);
    // Form submission
    importForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const fileInput = document.getElementById('excelFile');
        const file = fileInput.files[0];
        
        if (!file) {
            showAlert('ກະລຸນາເລືອກໄຟລ໌ Excel', 'error');
            return;
        }
        
        // ตรวจสอบประเภทไฟล์
        const allowedTypes = [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-excel'
        ];
        
        if (!allowedTypes.includes(file.type)) {
            showAlert('ກະລຸນາເລືອກໄຟລ໌ Excel (.xlsx, .xls)', 'error');
            return;
        }
        
        // แสดง progress
        importProgress.style.display = 'block';
        importResults.style.display = 'none';
        progressText.textContent = 'ກຳລັງອັບໂຫຼດໄຟລ໌...';
        progressPercent.textContent = '0%';
        progressFill.style.width = '0%';
        
        // สร้าง FormData
        const formData = new FormData();
        formData.append('excel_file', file);
        formData.append('action', 'import_excel');
        
        // ใช้ current URL (add.php) แทน excel_import_process.php
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            importProgress.style.display = 'none';
            importResults.style.display = 'block';
            
            if (data.success) {
                importResults.innerHTML = data.html;
                showAlert(data.message, 'success');
                
                // รีเซ็ตฟอร์ม
                excelImportForm.reset();
                fileInfo.style.display = 'none';
                uploadArea.style.display = 'block';
                importBtn.disabled = true;
            } else {
                importResults.innerHTML = data.html || `<div class="error">ເກີດຂໍ້ຜິດພາດ: ${data.message}</div>`;
                showAlert(data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            importProgress.style.display = 'none';
            importResults.style.display = 'block';
            importResults.innerHTML = '<div class="error">ເກີດຂໍ້ຜິດພາດໃນການເຊື່ອມຕໍ່</div>';
            showAlert('ເກີດຂໍ້ຜິດພາດໃນການເຊື່ອມຕໍ່', 'error');
        });
        
        // แสดง Animation Progress
        let progress = 0;
        const interval = setInterval(() => {
            progress += 5;
            if (progress <= 90) {
                progressFill.style.width = progress + '%';
                progressPercent.textContent = progress + '%';
            } else {
                clearInterval(interval);
            }
        }, 200);
    });
});
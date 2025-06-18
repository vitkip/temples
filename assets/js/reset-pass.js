 // Button ripple effect
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.btn-primary, .btn-back').forEach(button => {
                button.addEventListener('click', function(e) {
                    const rect = this.getBoundingClientRect();
                    const x = e.clientX - rect.left;
                    const y = e.clientY - rect.top;
                    
                    const ripple = document.createElement('span');
                    ripple.classList.add('ripple');
                    ripple.style.left = `${x}px`;
                    ripple.style.top = `${y}px`;
                    
                    this.appendChild(ripple);
                    
                    setTimeout(() => {
                        ripple.remove();
                    }, 600);
                });
            });
            
            // Form submission loading state
            const requestResetForm = document.getElementById('requestResetForm');
            const passwordResetForm = document.getElementById('passwordResetForm');
            const requestResetBtn = document.getElementById('requestResetBtn');
            const resetPasswordBtn = document.getElementById('resetPasswordBtn');
            
            if (requestResetForm) {
                requestResetForm.addEventListener('submit', function() {
                    requestResetBtn.classList.add('loading');
                });
            }
            
            if (passwordResetForm) {
                passwordResetForm.addEventListener('submit', function() {
                    resetPasswordBtn.classList.add('loading');
                });
                
                // Password strength meter
                const passwordInput = document.getElementById('password');
                const confirmInput = document.getElementById('confirm_password');
                const strengthBar = document.getElementById('passwordStrengthBar');
                const strengthText = document.getElementById('passwordStrengthText');
                const matchText = document.getElementById('passwordMatchText');
                
                if (passwordInput) {
                    passwordInput.addEventListener('input', function() {
                        checkPasswordStrength(this.value);
                    });
                }
                
                if (confirmInput) {
                    confirmInput.addEventListener('input', function() {
                        checkPasswordMatch(passwordInput.value, this.value);
                    });
                }
                
                function checkPasswordStrength(password) {
                    // Reset
                    strengthBar.className = 'password-strength-bar';
                    strengthText.innerHTML = '<i class="fas fa-info-circle"></i><span>ຢ່າງນ້ອຍ 6 ຕົວອັກສອນ</span>';
                    strengthText.className = 'password-strength-text text-gray-500';
                    
                    if (password.length === 0) return;
                    
                    // Simple password strength logic
                    if (password.length < 6) {
                        strengthBar.classList.add('strength-weak');
                        strengthText.innerHTML = '<i class="fas fa-exclamation-circle"></i><span>ລະຫັດຜ່ານອ່ອນເກີນໄປ</span>';
                        strengthText.classList.add('text-weak');
                        return;
                    }
                    
                    let strength = 0;
                    if (password.length > 7) strength += 1;
                    if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength += 1;
                    if (password.match(/\d+/)) strength += 1;
                    if (password.match(/[^a-zA-Z0-9]/)) strength += 1;
                    
                    if (strength <= 2) {
                        strengthBar.classList.add('strength-medium');
                        strengthText.innerHTML = '<i class="fas fa-info-circle"></i><span>ລະຫັດຜ່ານປານກາງ</span>';
                        strengthText.classList.add('text-medium');
                    } else {
                        strengthBar.classList.add('strength-strong');
                        strengthText.innerHTML = '<i class="fas fa-check-circle"></i><span>ລະຫັດຜ່ານເຂັ້ມແຂງ</span>';
                        strengthText.classList.add('text-strong');
                    }
                }
                
                function checkPasswordMatch(password, confirmPassword) {
                    matchText.className = 'password-strength-text';
                    
                    if (confirmPassword.length === 0) {
                        matchText.innerHTML = '';
                        return;
                    }
                    
                    if (password === confirmPassword) {
                        matchText.innerHTML = '<i class="fas fa-check-circle"></i><span>ລະຫັດຜ່ານຕົງກັນ</span>';
                        matchText.classList.add('text-strong');
                    } else {
                        matchText.innerHTML = '<i class="fas fa-times-circle"></i><span>ລະຫັດຜ່ານບໍ່ຕົງກັນ</span>';
                        matchText.classList.add('text-weak');
                    }
                }
            }
        });
        
        // Toggle password visibility
        function togglePasswordVisibility(inputId, iconId) {
            const input = document.getElementById(inputId);
            const icon = document.getElementById(iconId);
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }
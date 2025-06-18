
        document.addEventListener('DOMContentLoaded', function() {
            // OTP Input Handling
            const otpInputs = document.querySelectorAll('.otp-input');
            const otpHiddenInput = document.getElementById('otp');
            const verifyOtpForm = document.getElementById('verifyOtpForm');
            
            if (otpInputs.length > 0) {
                // Focus first input
                setTimeout(() => {
                    otpInputs[0].focus();
                }, 500);
                
                otpInputs.forEach((input, index) => {
                    input.addEventListener('input', function() {
                        // Only allow one digit
                        if (this.value.length > 1) {
                            this.value = this.value.slice(0, 1);
                        }
                        
                        // Move focus to next input
                        if (this.value && index < otpInputs.length - 1) {
                            otpInputs[index + 1].focus();
                        }
                        
                        // Update hidden input with complete OTP value
                        updateOtpValue();
                    });
                    
                    input.addEventListener('keydown', function(e) {
                        // Handle backspace
                        if (e.key === 'Backspace' && !this.value && index > 0) {
                            otpInputs[index - 1].focus();
                            otpInputs[index - 1].value = '';
                            updateOtpValue();
                        }
                    });
                    
                    // Handle paste event
                    input.addEventListener('paste', function(e) {
                        e.preventDefault();
                        const pasteData = e.clipboardData.getData('text').trim();
                        
                        if (/^\d+$/.test(pasteData) && pasteData.length <= otpInputs.length) {
                            // Clear all inputs first
                            otpInputs.forEach(input => input.value = '');
                            
                            // Fill inputs with pasted data
                            for (let i = 0; i < pasteData.length; i++) {
                                if (i < otpInputs.length) {
                                    otpInputs[i].value = pasteData[i];
                                }
                            }
                            
                            // Focus the next empty input or the last one
                            const nextEmptyIndex = [...otpInputs].findIndex(input => !input.value);
                            if (nextEmptyIndex !== -1) {
                                otpInputs[nextEmptyIndex].focus();
                            } else {
                                otpInputs[otpInputs.length - 1].focus();
                            }
                            
                            updateOtpValue();
                        }
                    });
                });
                
                function updateOtpValue() {
                    let otp = '';
                    otpInputs.forEach(input => {
                        otp += input.value;
                    });
                    otpHiddenInput.value = otp;
                }
                
                if (verifyOtpForm) {
                    verifyOtpForm.addEventListener('submit', function(e) {
                        updateOtpValue();
                        
                        if (otpHiddenInput.value.length !== 6) {
                            e.preventDefault();
                            alert('ກະລຸນາປ້ອນລະຫັດຢືນຢັນ 6 ໂຕເລກທັງໝົດ');
                        }
                    });
                }
            }
            
            // Countdown Timer
            const countdownElement = document.getElementById('countdown');
            if (countdownElement) {
                let timeLeft = 15 * 60; // 15 minutes in seconds
                
                const countdownInterval = setInterval(function() {
                    if (timeLeft <= 0) {
                        clearInterval(countdownInterval);
                        document.getElementById('countdown-text').innerHTML = '<span class="text-red-600">ລະຫັດໝົດອາຍຸແລ້ວ</span> <a href="?resend=1" class="text-primary-600 hover:text-primary-700 font-medium ml-2">ສົ່ງໃໝ່</a>';
                        return;
                    }
                    
                    const minutes = Math.floor(timeLeft / 60);
                    const seconds = timeLeft % 60;
                    countdownElement.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
                    timeLeft--;
                }, 1000);
            }
            
            // Password Strength Meter
            const passwordInput = document.getElementById('password');
            const confirmInput = document.getElementById('confirm_password');
            const strengthBar = document.getElementById('passwordStrengthBar');
            const strengthText = document.getElementById('passwordStrengthText');
            const matchText = document.getElementById('passwordMatchText');
            
            if (passwordInput) {
                passwordInput.addEventListener('input', function() {
                    if (this.value.length > 0) {
                        checkPasswordStrength(this.value);
                    } else {
                        strengthBar.className = 'password-strength-bar';
                        strengthText.innerHTML = '<i class="fas fa-info-circle"></i><span>ຢ່າງນ້ອຍ 6 ຕົວອັກສອນ</span>';
                        strengthText.className = 'password-strength-text text-gray-500';
                    }
                });
            }
            
            if (confirmInput) {
                confirmInput.addEventListener('input', function() {
                    if (passwordInput.value.length > 0 && this.value.length > 0) {
                        checkPasswordMatch(passwordInput.value, this.value);
                    } else {
                        matchText.innerHTML = '';
                    }
                });
            }
            
            function checkPasswordStrength(password) {
                // Reset
                strengthBar.className = 'password-strength-bar';
                
                if (password.length === 0) return;
                
                // Simple password strength logic
                let strength = 0;
                
                // Check length
                if (password.length >= 6) strength += 1;
                if (password.length >= 8) strength += 1;
                
                // Check character types
                if (password.match(/[a-z]/)) strength += 1;
                if (password.match(/[A-Z]/)) strength += 1;
                if (password.match(/\d/)) strength += 1;
                if (password.match(/[^a-zA-Z0-9]/)) strength += 1;
                
                // Set strength indicators
                if (password.length < 6) {
                    strengthBar.classList.add('strength-weak');
                    strengthText.innerHTML = '<i class="fas fa-exclamation-circle"></i><span>ລະຫັດຜ່ານສັ້ນເກີນໄປ</span>';
                    strengthText.className = 'password-strength-text text-weak';
                } else if (strength < 4) {
                    strengthBar.classList.add('strength-weak');
                    strengthText.innerHTML = '<i class="fas fa-exclamation-circle"></i><span>ລະຫັດຜ່ານອ່ອນແອ</span>';
                    strengthText.className = 'password-strength-text text-weak';
                } else if (strength < 6) {
                    strengthBar.classList.add('strength-medium');
                    strengthText.innerHTML = '<i class="fas fa-info-circle"></i><span>ລະຫັດຜ່ານປານກາງ</span>';
                    strengthText.className = 'password-strength-text text-medium';
                } else {
                    strengthBar.classList.add('strength-strong');
                    strengthText.innerHTML = '<i class="fas fa-check-circle"></i><span>ລະຫັດຜ່ານເຂັ້ມແຂງ</span>';
                    strengthText.className = 'password-strength-text text-strong';
                }
            }
            
            function checkPasswordMatch(password, confirm) {
                if (password === confirm) {
                    matchText.innerHTML = '<i class="fas fa-check-circle"></i><span>ລະຫັດຜ່ານຕົງກັນ</span>';
                    matchText.className = 'password-strength-text text-strong';
                } else {
                    matchText.innerHTML = '<i class="fas fa-times-circle"></i><span>ລະຫັດຜ່ານບໍ່ຕົງກັນ</span>';
                    matchText.className = 'password-strength-text text-weak';
                }
            }
            
            // Button loading state
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function() {
                    const button = this.querySelector('button[type="submit"]');
                    if (button) {
                        button.disabled = true;
                        button.classList.add('relative');
                        button.classList.add('disabled:opacity-80');
                        
                        button.insertAdjacentHTML('beforeend', `
                            <span class="absolute inset-0 flex items-center justify-center">
                                <svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </span>
                        `);
                        
                        const buttonText = button.querySelector('.btn-text');
                        if (buttonText) {
                            buttonText.style.opacity = '0';
                        }
                    }
                });
            });
            
            // Animation for card
            const card = document.querySelector('.form-card');
            if (card) {
                setTimeout(() => {
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, 100);
            }
        });
        
        // Show/hide password
        function togglePasswordVisibility(inputId, iconId) {
            const input = document.getElementById(inputId);
            const icon = document.getElementById(iconId);
            
            if (input && icon) {
                if (input.type === 'password') {
                    input.type = 'text';
                    icon.classList.replace('fa-eye', 'fa-eye-slash');
                } else {
                    input.type = 'password';
                    icon.classList.replace('fa-eye-slash', 'fa-eye');
                }
            }
        }
  
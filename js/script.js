/**
 * script.js — Tenant Screening & Rental Risk Analysis System
 * Client-side form validation and UI enhancements
 * Pure JavaScript, no frameworks
 */

document.addEventListener('DOMContentLoaded', function () {

    // -------------------------------------------------------
    // LOGIN FORM VALIDATION
    // -------------------------------------------------------
    var loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', function (e) {
            var username = document.getElementById('username').value.trim();
            var password = document.getElementById('password').value;
            if (!username || !password) {
                e.preventDefault();
                showError(loginForm, 'Please enter both username and password.');
                return;
            }
            if (password.length < 3) {
                e.preventDefault();
                showError(loginForm, 'Password is too short.');
            }
        });
    }

    // -------------------------------------------------------
    // REGISTER FORM VALIDATION
    // -------------------------------------------------------
    var registerForm = document.getElementById('registerForm');
    if (registerForm) {
        registerForm.addEventListener('submit', function (e) {
            var username = document.getElementById('username').value.trim();
            var email    = document.getElementById('email').value.trim();
            var password = document.getElementById('password').value;
            var confirm  = document.getElementById('confirm_password').value;
            var roleSelected = document.querySelector('input[name="role"]:checked');

            if (!roleSelected) {
                e.preventDefault();
                showError(registerForm, 'Please select a role (Tenant or Landlord).');
                return;
            }
            if (!username || username.length < 3) {
                e.preventDefault();
                showError(registerForm, 'Username must be at least 3 characters.');
                return;
            }
            if (!isValidEmail(email)) {
                e.preventDefault();
                showError(registerForm, 'Please enter a valid email address.');
                return;
            }
            if (password.length < 6) {
                e.preventDefault();
                showError(registerForm, 'Password must be at least 6 characters.');
                return;
            }
            if (password !== confirm) {
                e.preventDefault();
                showError(registerForm, 'Passwords do not match.');
            }
        });
    }

    // -------------------------------------------------------
    // TENANT PROFILE FORM VALIDATION
    // -------------------------------------------------------
    var profileForm = document.getElementById('profileForm');
    if (profileForm) {
        profileForm.addEventListener('submit', function (e) {
            var age    = parseInt(document.querySelector('[name=age]').value);
            var income = parseFloat(document.querySelector('[name=monthly_income]').value);
            var name   = document.querySelector('[name=full_name]').value.trim();

            if (!name) {
                e.preventDefault();
                showError(profileForm, 'Full name is required.');
                return;
            }
            if (isNaN(age) || age < 15 || age > 100) {
                e.preventDefault();
                showError(profileForm, 'Age must be between 15 and 100.');
                return;
            }
            if (isNaN(income) || income <= 0) {
                e.preventDefault();
                showError(profileForm, 'Monthly income must be a positive number.');
                return;
            }

            // File size check (client-side, 2MB limit)
            var fileInputs = profileForm.querySelectorAll('input[type=file]');
            for (var i = 0; i < fileInputs.length; i++) {
                var fi = fileInputs[i];
                if (fi.files.length > 0) {
                    var fsize = fi.files[0].size;
                    if (fsize > 2 * 1024 * 1024) {
                        e.preventDefault();
                        showError(profileForm, 'File "' + fi.name + '" exceeds the 2MB size limit.');
                        return;
                    }
                }
            }
        });
    }

    // -------------------------------------------------------
    // APPLY FORM VALIDATION
    // -------------------------------------------------------
    var applyForm = document.getElementById('applyForm');
    if (applyForm) {
        applyForm.addEventListener('submit', function (e) {
            var note = applyForm.querySelector('[name=property_note]').value.trim();
            var rent = parseFloat(applyForm.querySelector('[name=monthly_rent]').value);
            if (!note) {
                e.preventDefault();
                showError(applyForm, 'Please enter a property note.');
                return;
            }
            if (isNaN(rent) || rent <= 0) {
                e.preventDefault();
                showError(applyForm, 'Please enter a valid monthly rent amount.');
            }
        });
    }

    // -------------------------------------------------------
    // AUTO-DISMISS ALERTS after 5 seconds
    // -------------------------------------------------------
    var alerts = document.querySelectorAll('.alert-success');
    alerts.forEach(function (alert) {
        setTimeout(function () {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(function () { alert.remove(); }, 500);
        }, 5000);
    });

    // -------------------------------------------------------
    // TABLE SEARCH / FILTER (for landlord/admin dashboard)
    // -------------------------------------------------------
    var tableFilter = document.getElementById('tableSearch');
    if (tableFilter) {
        tableFilter.addEventListener('input', function () {
            var query = this.value.toLowerCase();
            var rows  = document.querySelectorAll('#applicationsTable tbody tr');
            rows.forEach(function (row) {
                var text = row.textContent.toLowerCase();
                row.style.display = text.indexOf(query) === -1 ? 'none' : '';
            });
        });
    }

    // -------------------------------------------------------
    // RISK SCORE COLOUR HIGHLIGHTING (already done via CSS,
    // but also highlight cells with inline colour for older
    // browsers that don't support CSS classes well)
    // -------------------------------------------------------
    var riskBadges = document.querySelectorAll('.risk-badge');
    riskBadges.forEach(function (badge) {
        var score = parseFloat(badge.textContent);
        if (!isNaN(score)) {
            if (score < 0.4) {
                badge.style.background = '#eafaf1';
                badge.style.color = '#1e8449';
                badge.style.border = '1px solid #27ae60';
            } else if (score <= 0.7) {
                badge.style.background = '#fef9e7';
                badge.style.color = '#7d6608';
                badge.style.border = '1px solid #f39c12';
            } else {
                badge.style.background = '#fdecea';
                badge.style.color = '#922b21';
                badge.style.border = '1px solid #e74c3c';
            }
        }
    });

    // -------------------------------------------------------
    // SHOW/HIDE GUARANTOR FIELD based on employment selection
    // Only shown when "Student (Parent/Guardian funded)" is selected
    // -------------------------------------------------------
    var empSelect = document.querySelector('select[name="employment_status"]');
    var guarantorField = document.getElementById('guarantorField');

    function toggleGuarantor() {
        if (!empSelect || !guarantorField) return;
        if (empSelect.value === 'student_funded') {
            guarantorField.style.display = 'block';
        } else {
            guarantorField.style.display = 'none';
        }
    }

    if (empSelect) {
        toggleGuarantor(); // run on page load
        empSelect.addEventListener('change', toggleGuarantor);
    }
    function showError(form, message) {
        // Remove existing error if any
        var existing = form.querySelector('.js-error');
        if (existing) existing.remove();

        var div = document.createElement('div');
        div.className = 'alert alert-error js-error';
        div.textContent = message;
        form.insertBefore(div, form.firstChild);

        // Scroll to top of form
        div.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    function isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }
});

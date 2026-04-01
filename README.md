# Tenant Screening and Rental Risk Analysis System
## Tribhuvan University — B.Sc. CSIT Final Year Project

---

## Project Overview

A web-based tenant screening system that combines:
- **Rule-based weighted scoring** (PHP)
- **Machine learning logistic regression** (Python/scikit-learn)
- **QuickSort algorithm** (PHP) for sorting applications by risk
- Three user roles: **Tenant**, **Landlord**, **Admin**

---

## Technologies Used

| Layer      | Technology                    |
|------------|-------------------------------|
| Frontend   | HTML, CSS, JavaScript (pure)  |
| Backend    | PHP (no framework)            |
| Database   | MySQL                         |
| ML         | Python 3, scikit-learn        |

---

## Folder Structure

```
rental_risk/
├── index.php                  # Login page
├── logout.php                 # Logout handler
│
├── pages/
│   ├── register.php           # Registration (tenant/landlord)
│   ├── tenant_dashboard.php   # Tenant home + application form
│   ├── tenant_profile.php     # Tenant profile + document upload
│   ├── apply.php              # Application submission handler
│   ├── landlord_dashboard.php # Landlord view (sorted by risk)
│   ├── view_application.php   # Full application detail
│   ├── update_status.php      # Approve/reject handler
│   └── admin_dashboard.php    # Admin panel
│
├── php/
│   ├── config.php             # DB settings, constants
│   ├── auth.php               # Login, register, session helpers
│   ├── risk_calculate.php     # ALL THREE ALGORITHMS
│   ├── navbar.php             # Shared navigation bar
│   └── insert_sample.php      # Demo data generator
│
├── python/
│   ├── train.py               # Train logistic regression model
│   ├── predict.py             # Predict risk (called by PHP)
│   ├── sample_training_data.csv  # 200-row fake training data
│   ├── model.pkl              # Generated after running train.py
│   └── scaler.pkl             # Generated after running train.py
│
├── css/
│   └── styles.css             # All styles
│
├── js/
│   └── script.js              # Form validation + UI
│
├── sql/
│   └── schema.sql             # Database schema + sample data
│
└── uploads/                   # Tenant document uploads (auto-created)
```

---

## Setup Instructions (XAMPP on Windows)

### Step 1: Install Requirements

- **XAMPP**: https://www.apachefriends.org/ (includes Apache + MySQL + PHP)
- **Python 3**: https://www.python.org/downloads/
- **scikit-learn**: Open CMD and run:
  ```
  pip install scikit-learn numpy
  ```

### Step 2: Place Project Files

1. Copy the entire `rental_risk/` folder to:
   ```
   C:\xampp\htdocs\rental_risk\
   ```

### Step 3: Create the Database

1. Start **Apache** and **MySQL** from XAMPP Control Panel
2. Open browser: http://localhost/phpmyadmin
3. Click **Import** tab
4. Choose file: `rental_risk/sql/schema.sql`
5. Click **Go**

OR run in MySQL terminal:
```sql
SOURCE C:/xampp/htdocs/rental_risk/sql/schema.sql;
```

### Step 4: Configure Database (if needed)

Edit `php/config.php`:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');     // your MySQL username
define('DB_PASS', '');         // your MySQL password
define('DB_NAME', 'rental_risk_db');
```

### Step 5: Train the ML Model

Open **CMD** and run:
```bash
cd C:\xampp\htdocs\rental_risk\python
python train.py
```

You should see output like:
```
Loaded 200 training samples.
Model Accuracy on test set: 95.00%
Model saved to: .../model.pkl
Scaler saved to: .../scaler.pkl
Training complete.
```

This creates `model.pkl` and `scaler.pkl`. You only need to do this once.

### Step 6: Configure Python Path (if needed)

In `php/config.php`, check:
```php
define('PYTHON_PATH', 'python');  // or 'python3' on Linux/Mac
```

Make sure Python is in your system PATH. Test:
```bash
python --version
```

### Step 7: Run the Application

Open browser: **http://localhost/rental_risk/**

---

## Demo Login Credentials

All demo accounts use password: `password`

| Role     | Username       | Password  |
|----------|---------------|-----------|
| Admin    | admin          | password  |
| Landlord | landlord_ram   | password  |
| Landlord | landlord_sita  | password  |
| Tenant   | tenant_hari    | password  |
| Tenant   | tenant_gita    | password  |
| Tenant   | tenant_bikash  | password  |

---

## The Three Algorithms

### Algorithm A — Weighted Rule-Based Risk Score (PHP)
**File:** `php/risk_calculate.php` → `calculateWeightedRiskScore()`

```
S = 0.4 × income_ratio_risk
  + 0.3 × employment_risk
  + 0.2 × reference_risk
  + 0.1 × history_risk
```

Each component is normalized 0–1 where 0 = safe, 1 = risky.

### Algorithm B — Logistic Regression ML (Python)
**Files:** `python/train.py`, `python/predict.py`

PHP calls Python via `shell_exec()` in `getMLProbability()`.
Returns probability 0.0–1.0.

### Algorithm C — QuickSort (PHP)
**File:** `php/risk_calculate.php` → `quickSortApplications()`, `partition()`

Manually implemented QuickSort sorts applications descending by `final_risk`.
Applied on both Landlord and Admin dashboards before rendering.

### Final Risk Combination
- **Returning tenant:** `final = 0.6 × weighted + 0.4 × ml`
- **First-time renter:** `final = 0.7 × weighted + 0.3 × ml`

### Risk Levels
| Score Range | Level   | Color  |
|-------------|---------|--------|
| < 0.4       | Low     | Green  |
| 0.4 – 0.7   | Medium  | Yellow |
| > 0.7       | High    | Red    |

---

## Security Notes

1. **Passwords** are hashed using PHP `password_hash()` (bcrypt)
2. **SQL injection** prevented via prepared statements (MySQLi)
3. **XSS** prevented via `htmlspecialchars()` on all output
4. **File uploads** validated by type (JPG/PNG/PDF) and size (≤ 2MB)
5. **Sessions** use `session_regenerate_id()` on login
6. **Role checks** on every protected page

---

## Linux/Mac Setup Notes

- Change `PYTHON_PATH` in `config.php` to `python3`
- Place files in: `/var/www/html/rental_risk/` or use `php -S localhost:8000`
- Run `chmod 755 uploads/` to allow file uploads
- Install: `sudo apt install python3-pip && pip3 install scikit-learn numpy`

---

## Troubleshooting

| Problem | Solution |
|---------|---------|
| Blank page | Enable PHP error display in `php.ini` |
| DB error | Check credentials in `config.php` |
| ML always returns 0.5 | Run `python train.py` first |
| File upload fails | Check `uploads/` folder exists and is writable |
| Python not found | Add Python to PATH or use full path in `config.php` |

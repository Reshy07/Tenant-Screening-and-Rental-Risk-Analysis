-- ============================================================
-- Tenant Screening and Rental Risk Analysis System
-- Database Schema — Complete Version
-- ============================================================

CREATE DATABASE IF NOT EXISTS rental_risk_db;
USE rental_risk_db;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('tenant','landlord','admin') NOT NULL DEFAULT 'tenant',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS tenants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    full_name VARCHAR(150) NOT NULL,
    age INT NOT NULL,
    contact VARCHAR(20) NOT NULL,
    address TEXT NOT NULL,
    monthly_income DECIMAL(12,2) NOT NULL,
    employment_status ENUM('employed','self_employed','unemployed','student','student_funded') NOT NULL,
    rental_history_months INT NOT NULL DEFAULT 0,
    reference_text TEXT,
    guarantor_info TEXT,
    id_proof_path VARCHAR(255),
    income_proof_path VARCHAR(255),
    employment_proof_path VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS tenant_preferences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL UNIQUE,
    preferred_min_rent DECIMAL(12,2) DEFAULT 0,
    preferred_max_rent DECIMAL(12,2) DEFAULT 0,
    preferred_area VARCHAR(255) DEFAULT '',
    preferred_type ENUM('any','room','flat','house','office') DEFAULT 'any',
    preferred_bedrooms INT DEFAULT 0,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS properties (
    id INT AUTO_INCREMENT PRIMARY KEY,
    landlord_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    address TEXT NOT NULL,
    monthly_rent DECIMAL(12,2) NOT NULL,
    bedrooms INT DEFAULT 1,
    property_type ENUM('room','flat','house','office') NOT NULL DEFAULT 'flat',
    is_available TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (landlord_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    landlord_id INT DEFAULT NULL,
    property_id INT DEFAULT NULL,
    property_note VARCHAR(255),
    monthly_rent DECIMAL(12,2) NOT NULL DEFAULT 0,
    status ENUM('pending','approved','rejected','under_review') NOT NULL DEFAULT 'pending',
    applied_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (landlord_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS risk_scores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT NOT NULL,
    weighted_score DECIMAL(5,4) NOT NULL,
    ml_probability DECIMAL(5,4) NOT NULL,
    final_risk DECIMAL(5,4) NOT NULL,
    is_first_time_renter TINYINT(1) NOT NULL DEFAULT 0,
    calculated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE
);

-- All demo passwords = 'password'
INSERT INTO users (username, email, password, role) VALUES
('admin',         'admin@rentalrisk.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
('landlord_ram',  'ram@landlord.com',     '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'landlord'),
('landlord_sita', 'sita@landlord.com',    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'landlord'),
('tenant_hari',   'hari@tenant.com',      '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'tenant'),
('tenant_gita',   'gita@tenant.com',      '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'tenant'),
('tenant_bikash', 'bikash@tenant.com',    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'tenant');

INSERT INTO tenants (user_id, full_name, age, contact, address, monthly_income, employment_status, rental_history_months, reference_text) VALUES
(4, 'Hari Prasad Sharma', 28, '9841000001', 'Baneshwor, Kathmandu', 45000, 'employed',      24, 'Ram Bahadur 9841111111 - Excellent tenant, never missed rent.'),
(5, 'Gita Kumari Thapa',  22, '9841000002', 'Lalitpur, Patan',      20000, 'student',        0, ''),
(6, 'Bikash Raj Karki',   35, '9841000003', 'Bhaktapur',            60000, 'self_employed', 48, 'Sita Maharjan - Reliable, maintained property well.');

INSERT INTO properties (landlord_id, title, description, address, monthly_rent, bedrooms, property_type, is_available) VALUES
(2, 'Cozy 2BHK Flat in Thamel',    'Fully furnished, close to restaurants. Water included.',     'Flat 3A, Thamel, Kathmandu',        15000, 2, 'flat',   1),
(2, 'Single Room at Pulchowk',     'Simple room with attached bathroom. Near college.',           'Room 5, Pulchowk, Lalitpur',         7000,  1, 'room',   1),
(3, 'Spacious House in Sukedhara', '3 storey house with garden. Parking available.',             'House 12, Sukedhara, Kathmandu',     35000, 4, 'house',  1),
(3, 'Office Space at Durbarmarg',  'Commercial space 2nd floor. Great for small businesses.',    'Office 201, Durbarmarg, Kathmandu',  25000, 2, 'office', 1);

INSERT INTO applications (tenant_id, landlord_id, property_id, property_note, monthly_rent, status) VALUES
(1, 2, 1, 'Cozy 2BHK Flat in Thamel',    15000, 'pending'),
(2, 2, 2, 'Single Room at Pulchowk',       7000, 'under_review'),
(3, 3, 3, 'Spacious House in Sukedhara',  35000, 'approved');

INSERT INTO risk_scores (application_id, weighted_score, ml_probability, final_risk, is_first_time_renter) VALUES
(1, 0.3200, 0.2800, 0.3080, 0),
(2, 0.7500, 0.7200, 0.7350, 1),
(3, 0.2100, 0.1900, 0.2020, 0);

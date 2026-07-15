<?php
// ============================================================
// Sample Data Insertion for Demo
// Called from admin_dashboard.php?insert_sample=1
// Idempotent: clicking multiple times updates demo cases.
// ============================================================
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/risk_calculate.php';

function insertSampleData(mysqli $db) {
    $result = [
        'created_users' => 0,
        'updated_users' => 0,
        'created_tenants' => 0,
        'updated_tenants' => 0,
        'created_applications' => 0,
        'updated_applications' => 0,
        'recalculated_scores' => 0,
    ];

    $samples = [
        [
            'username' => 'demo_lowrisk_employed',
            'email' => 'demo.lowrisk@test.com',
            'full_name' => 'Aarav Joshi',
            'age' => 32,
            'contact' => '9841111111',
            'address' => 'Lazimpat, Kathmandu',
            'monthly_income' => 120000,
            'employment_status' => 'employed',
            'rental_history_months' => 48,
            'reference_text' => 'Landlord Prakash, 9841999999: Paid before due date for 4 years and maintained property very well.',
            'guarantor_info' => '',
            'property_note' => '[CASE-LOW] Corporate tenant profile, fully documented and highly stable.',
            'monthly_rent' => 18000,
            'status' => 'approved',
        ],
        [
            'username' => 'demo_medium_selfemployed',
            'email' => 'demo.medium@test.com',
            'full_name' => 'Nabin Shrestha',
            'age' => 38,
            'contact' => '9841222222',
            'address' => 'Banasthali, Kathmandu',
            'monthly_income' => 65000,
            'employment_status' => 'self_employed',
            'rental_history_months' => 14,
            'reference_text' => 'Previous landlord confirms generally timely payments with one delayed month during off-season.',
            'guarantor_info' => '',
            'property_note' => '[CASE-MEDIUM] Small business owner with moderate variability in cash flow.',
            'monthly_rent' => 25000,
            'status' => 'under_review',
        ],
        [
            'username' => 'demo_highrisk_unemployed',
            'email' => 'demo.highrisk@test.com',
            'full_name' => 'Rita BK',
            'age' => 27,
            'contact' => '9841333333',
            'address' => 'Kalanki, Kathmandu',
            'monthly_income' => 15000,
            'employment_status' => 'unemployed',
            'rental_history_months' => 1,
            'reference_text' => 'No verifiable landlord reference provided.',
            'guarantor_info' => '',
            'property_note' => '[CASE-HIGH] Weak affordability and very limited reliable references.',
            'monthly_rent' => 14000,
            'status' => 'rejected',
        ],
        [
            'username' => 'demo_firsttime_student',
            'email' => 'demo.firsttime@test.com',
            'full_name' => 'Sneha Karki',
            'age' => 22,
            'contact' => '9841444444',
            'address' => 'Kirtipur, Kathmandu',
            'monthly_income' => 22000,
            'employment_status' => 'student',
            'rental_history_months' => 0,
            'reference_text' => '',
            'guarantor_info' => '',
            'property_note' => '[CASE-FIRST-TIME] First-time renter without guarantor; affordability borderline.',
            'monthly_rent' => 10000,
            'status' => 'pending',
        ],
        [
            'username' => 'demo_student_funded',
            'email' => 'demo.funded@test.com',
            'full_name' => 'Manish Adhikari',
            'age' => 23,
            'contact' => '9841555555',
            'address' => 'Putalisadak, Kathmandu',
            'monthly_income' => 35000,
            'employment_status' => 'student_funded',
            'rental_history_months' => 6,
            'reference_text' => 'College hostel warden reference confirms responsible conduct and on-time dues.',
            'guarantor_info' => 'Father: Ramesh Adhikari, Govt employee, contact 9842666666, agrees to co-sign rent obligations.',
            'property_note' => '[CASE-STUDENT-FUNDED] Student with guarantor and supporting references.',
            'monthly_rent' => 12000,
            'status' => 'approved',
        ],
        [
            'username' => 'demo_stable_lowincome',
            'email' => 'demo.stable.lowincome@test.com',
            'full_name' => 'Bimala Lama',
            'age' => 40,
            'contact' => '9841666666',
            'address' => 'Maitidevi, Kathmandu',
            'monthly_income' => 30000,
            'employment_status' => 'employed',
            'rental_history_months' => 30,
            'reference_text' => 'Past landlord confirms no disputes and very good neighborhood behavior over 2.5 years.',
            'guarantor_info' => '',
            'property_note' => '[CASE-BORDERLINE] Strong behavior history but tight income-to-rent ratio.',
            'monthly_rent' => 17000,
            'status' => 'under_review',
        ],
    ];

    $pw = password_hash('demo123', PASSWORD_DEFAULT);

    $landlord_id = null;
    $rsLandlord = $db->query("SELECT id FROM users WHERE role='landlord' ORDER BY id LIMIT 1");
    if ($rsLandlord && $rowLandlord = $rsLandlord->fetch_assoc()) {
        $landlord_id = intval($rowLandlord['id']);
    }

    foreach ($samples as $s) {
        // User upsert
        $user_id = null;
        $stmt = $db->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
        $stmt->bind_param("s", $s['username']);
        $stmt->execute();
        $existingUser = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($existingUser) {
            $user_id = intval($existingUser['id']);
            $stmt = $db->prepare("UPDATE users SET email = ?, password = ?, role = 'tenant', is_active = 1 WHERE id = ?");
            $stmt->bind_param("ssi", $s['email'], $pw, $user_id);
            $stmt->execute();
            $stmt->close();
            $result['updated_users']++;
        } else {
            $stmt = $db->prepare("INSERT INTO users (username, email, password, role, is_active) VALUES (?, ?, ?, 'tenant', 1)");
            $stmt->bind_param("sss", $s['username'], $s['email'], $pw);
            $stmt->execute();
            $user_id = intval($stmt->insert_id);
            $stmt->close();
            $result['created_users']++;
        }

        // Tenant profile upsert
        $tenant_id = null;
        $stmt = $db->prepare("SELECT id FROM tenants WHERE user_id = ? LIMIT 1");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $existingTenant = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($existingTenant) {
            $tenant_id = intval($existingTenant['id']);
            $stmt = $db->prepare(
                "UPDATE tenants
                 SET full_name = ?, age = ?, contact = ?, address = ?, monthly_income = ?,
                     employment_status = ?, rental_history_months = ?, reference_text = ?, guarantor_info = ?
                 WHERE id = ?"
            );
            $stmt->bind_param(
                "sissdssssi",
                $s['full_name'],
                $s['age'],
                $s['contact'],
                $s['address'],
                $s['monthly_income'],
                $s['employment_status'],
                $s['rental_history_months'],
                $s['reference_text'],
                $s['guarantor_info'],
                $tenant_id
            );
            $stmt->execute();
            $stmt->close();
            $result['updated_tenants']++;
        } else {
            $stmt = $db->prepare(
                "INSERT INTO tenants
                 (user_id, full_name, age, contact, address, monthly_income, employment_status, rental_history_months, reference_text, guarantor_info)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->bind_param(
                "isissdsiss",
                $user_id,
                $s['full_name'],
                $s['age'],
                $s['contact'],
                $s['address'],
                $s['monthly_income'],
                $s['employment_status'],
                $s['rental_history_months'],
                $s['reference_text'],
                $s['guarantor_info']
            );
            $stmt->execute();
            $tenant_id = intval($stmt->insert_id);
            $stmt->close();
            $result['created_tenants']++;
        }

        // Application upsert by tenant + unique case note
        $app_id = null;
        $stmt = $db->prepare("SELECT id FROM applications WHERE tenant_id = ? AND property_note = ? LIMIT 1");
        $stmt->bind_param("is", $tenant_id, $s['property_note']);
        $stmt->execute();
        $existingApp = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($existingApp) {
            $app_id = intval($existingApp['id']);
            if ($landlord_id === null) {
                $stmt = $db->prepare(
                    "UPDATE applications
                     SET landlord_id = NULL, property_id = NULL, monthly_rent = ?, status = ?
                     WHERE id = ?"
                );
                $stmt->bind_param("dsi", $s['monthly_rent'], $s['status'], $app_id);
            } else {
                $stmt = $db->prepare(
                    "UPDATE applications
                     SET landlord_id = ?, property_id = NULL, monthly_rent = ?, status = ?
                     WHERE id = ?"
                );
                $stmt->bind_param("idsi", $landlord_id, $s['monthly_rent'], $s['status'], $app_id);
            }
            $stmt->execute();
            $stmt->close();
            $result['updated_applications']++;
        } else {
            if ($landlord_id === null) {
                $stmt = $db->prepare(
                    "INSERT INTO applications (tenant_id, landlord_id, property_id, property_note, monthly_rent, status)
                     VALUES (?, NULL, NULL, ?, ?, ?)"
                );
                $stmt->bind_param("isds", $tenant_id, $s['property_note'], $s['monthly_rent'], $s['status']);
            } else {
                $stmt = $db->prepare(
                    "INSERT INTO applications (tenant_id, landlord_id, property_id, property_note, monthly_rent, status)
                     VALUES (?, ?, NULL, ?, ?, ?)"
                );
                $stmt->bind_param("iisds", $tenant_id, $landlord_id, $s['property_note'], $s['monthly_rent'], $s['status']);
            }
            $stmt->execute();
            $app_id = intval($stmt->insert_id);
            $stmt->close();
            $result['created_applications']++;
        }

        // Recalculate and save risk
        $tenant_row = [
            'monthly_income' => $s['monthly_income'],
            'employment_status' => $s['employment_status'],
            'rental_history_months' => $s['rental_history_months'],
            'reference_text' => $s['reference_text'],
            'guarantor_info' => $s['guarantor_info'],
            'age' => $s['age'],
        ];

        $is_first_time = (intval($s['rental_history_months']) === 0) ? 1 : 0;
        $weighted_score = calculateWeightedRiskScore($tenant_row, $s['monthly_rent']);
        $ml_probability = getMLProbability($tenant_row, $s['monthly_rent']);
        $final_risk = calculateFinalRisk($weighted_score, $ml_probability, $is_first_time);
        saveRiskScore($app_id, $weighted_score, $ml_probability, $final_risk, $is_first_time);
        $result['recalculated_scores']++;
    }

    return $result;
}

$insertSampleResult = insertSampleData(getDB());
?>

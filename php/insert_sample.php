<?php
// ============================================================
// Sample Data Insertion for Demo
// Called from admin_dashboard.php?insert_sample=1
// ============================================================
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/risk_calculate.php';

$db = getDB();

$samples = [
    [
        'username' => 'demo_tenant_1', 'email' => 'demo1@test.com',
        'full_name' => 'Rajesh Hamal', 'age' => 30, 'contact' => '9841111111',
        'address' => 'Lazimpat, Kathmandu', 'monthly_income' => 55000, 'employment_status' => 'employed',
        'rental_history_months' => 36, 'reference_text' => 'Mr. Subash KC - 9841999999 - Excellent tenant, very responsible.',
        'property_note' => 'Apartment 4F, New Baneshwor', 'monthly_rent' => 14000
    ],
    [
        'username' => 'demo_tenant_2', 'email' => 'demo2@test.com',
        'full_name' => 'Priya Sharma', 'age' => 24, 'contact' => '9841222222',
        'address' => 'Putalisadak, Kathmandu', 'monthly_income' => 18000, 'employment_status' => 'student',
        'rental_history_months' => 0, 'reference_text' => '',
        'property_note' => 'Room at Koteshwor', 'monthly_rent' => 9000
    ],
    [
        'username' => 'demo_tenant_3', 'email' => 'demo3@test.com',
        'full_name' => 'Suresh Basnet', 'age' => 42, 'contact' => '9841333333',
        'address' => 'Maharajgunj, Kathmandu', 'monthly_income' => 90000, 'employment_status' => 'self_employed',
        'rental_history_months' => 60, 'reference_text' => 'Landlord: Kamala Shrestha - Great tenant for 5 years.',
        'property_note' => 'Office Space at Durbarmarg', 'monthly_rent' => 30000
    ],
    [
        'username' => 'demo_tenant_4', 'email' => 'demo4@test.com',
        'full_name' => 'Anita Gurung', 'age' => 26, 'contact' => '9841444444',
        'address' => 'Bouddha, Kathmandu', 'monthly_income' => 12000, 'employment_status' => 'unemployed',
        'rental_history_months' => 3, 'reference_text' => 'No reference available',
        'property_note' => 'Studio flat, Chabahil', 'monthly_rent' => 10000
    ],
    [
        'username' => 'demo_tenant_5', 'email' => 'demo5@test.com',
        'full_name' => 'Deepak Tamang', 'age' => 33, 'contact' => '9841555555',
        'address' => 'Baluwatar, Kathmandu', 'monthly_income' => 40000, 'employment_status' => 'employed',
        'rental_history_months' => 12, 'reference_text' => 'Mr. Ramesh - 9842111222 - Good tenant, paid on time.',
        'property_note' => 'Flat 2B, Kalopul', 'monthly_rent' => 12000
    ],
];

foreach ($samples as $s) {
    // Check if username already exists
    $chk = $db->prepare("SELECT id FROM users WHERE username = ?");
    $chk->bind_param("s", $s['username']);
    $chk->execute();
    $chk->store_result();
    if ($chk->num_rows > 0) { $chk->close(); continue; }
    $chk->close();

    // Insert user
    $pw = password_hash('demo123', PASSWORD_DEFAULT);
    $stmt = $db->prepare("INSERT INTO users (username, email, password, role) VALUES (?,?,'?','tenant')");
    // Re-use standard insert
    $db->query("INSERT INTO users (username, email, password, role) VALUES ('{$s['username']}', '{$s['email']}', '$pw', 'tenant')");
    $user_id = $db->insert_id;

    // Insert tenant profile
    $db->query("INSERT INTO tenants (user_id, full_name, age, contact, address, monthly_income,
                employment_status, rental_history_months, reference_text)
                VALUES ($user_id, '{$s['full_name']}', {$s['age']}, '{$s['contact']}', '{$s['address']}',
                {$s['monthly_income']}, '{$s['employment_status']}', {$s['rental_history_months']}, '{$s['reference_text']}')");
    $tenant_id = $db->insert_id;

    // Insert application
    $db->query("INSERT INTO applications (tenant_id, property_note, monthly_rent, status)
                VALUES ($tenant_id, '{$s['property_note']}', {$s['monthly_rent']}, 'pending')");
    $app_id = $db->insert_id;

    // Calculate and store risk
    $tenant_row = [
        'monthly_income'      => $s['monthly_income'],
        'employment_status'   => $s['employment_status'],
        'rental_history_months' => $s['rental_history_months'],
        'reference_text'      => $s['reference_text'],
        'age'                 => $s['age'],
    ];
    $is_first_time   = ($s['rental_history_months'] == 0) ? 1 : 0;
    $weighted_score  = calculateWeightedRiskScore($tenant_row, $s['monthly_rent']);
    $ml_probability  = getMLProbability($tenant_row, $s['monthly_rent']);
    $final_risk      = calculateFinalRisk($weighted_score, $ml_probability, $is_first_time);
    saveRiskScore($app_id, $weighted_score, $ml_probability, $final_risk, $is_first_time);
}
?>

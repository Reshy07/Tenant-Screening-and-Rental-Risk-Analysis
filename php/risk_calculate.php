<?php
// ============================================================
// ALGORITHM FILE: Risk Scoring
// Contains:
//   A. Weighted Rule-Based Risk Score (PHP)
//   B. ML Probability via Python logistic regression (shell_exec)
// ============================================================

require_once __DIR__ . '/config.php';

function calculateWeightedRiskBreakdown($tenant, $monthly_rent) {
    $income = floatval($tenant['monthly_income']);
    $rent   = floatval($monthly_rent);
    if ($rent <= 0) $rent = 1;

    $ratio = $income / $rent;
    if ($ratio >= 3.0)      $income_ratio_risk = 0.0;
    elseif ($ratio >= 2.5)  $income_ratio_risk = 0.2;
    elseif ($ratio >= 2.0)  $income_ratio_risk = 0.4;
    elseif ($ratio >= 1.5)  $income_ratio_risk = 0.6;
    elseif ($ratio >= 1.0)  $income_ratio_risk = 0.8;
    else                    $income_ratio_risk = 1.0;

    $has_guarantor = strlen(trim($tenant['guarantor_info'] ?? '')) > 10;

    $emp_map = [
        'employed'        => 0.1,
        'self_employed'   => 0.3,
        'student_funded'  => 0.35,
        'student'         => 0.6,
        'unemployed'      => 0.9,
    ];
    $employment_risk = $emp_map[$tenant['employment_status']] ?? 0.5;

    if (($tenant['employment_status'] ?? '') === 'student_funded' && !$has_guarantor) {
        $employment_risk = 0.55;
    }

    $ref_text = trim($tenant['reference_text'] ?? '');
    if ($has_guarantor && strlen($ref_text) > 20) {
        $reference_risk = 0.05;
    } elseif ($has_guarantor) {
        $reference_risk = 0.15;
    } elseif (strlen($ref_text) > 50) {
        $reference_risk = 0.1;
    } elseif (strlen($ref_text) > 10) {
        $reference_risk = 0.4;
    } else {
        $reference_risk = 0.8;
    }

    $history = intval($tenant['rental_history_months']);
    if ($history >= 24)      $history_risk = 0.1;
    elseif ($history >= 12)  $history_risk = 0.3;
    elseif ($history >= 6)   $history_risk = 0.5;
    elseif ($history >= 1)   $history_risk = 0.7;
    else                     $history_risk = 1.0;

    $weighted_score = (0.4 * $income_ratio_risk)
                    + (0.3 * $employment_risk)
                    + (0.2 * $reference_risk)
                    + (0.1 * $history_risk);

    return [
        'monthly_income' => $income,
        'monthly_rent' => floatval($monthly_rent),
        'income_rent_ratio' => round($ratio, 4),
        'income_ratio_risk' => round($income_ratio_risk, 4),
        'employment_risk' => round($employment_risk, 4),
        'reference_risk' => round($reference_risk, 4),
        'history_risk' => round($history_risk, 4),
        'weighted_score' => round(min(1.0, max(0.0, $weighted_score)), 4),
        'has_reference' => strlen($ref_text) > 10,
        'has_guarantor' => $has_guarantor,
    ];
}

// ============================================================
// ALGORITHM A: Rule-Based Weighted Risk Scoring
// Formula: S = 0.4*income_ratio_risk + 0.3*employment_risk
//            + 0.2*reference_risk + 0.1*history_risk
// Each factor is normalized to 0.0 – 1.0
// Higher score = HIGHER RISK
// ============================================================
function calculateWeightedRiskScore($tenant, $monthly_rent) {
    $breakdown = calculateWeightedRiskBreakdown($tenant, $monthly_rent);
    return $breakdown['weighted_score'];
}

// ============================================================
// ALGORITHM B: ML Risk Probability
// Calls Python predict.py via shell_exec()
// Returns float 0.0–1.0 (probability of default/risk)
// Falls back to 0.5 if Python is unavailable
// ============================================================
function getMLProbability($tenant, $monthly_rent) {
    $income      = floatval($tenant['monthly_income']);
    $emp_map     = ['employed'=>1,'self_employed'=>2,'student'=>3,'unemployed'=>4];
    $emp_code    = $emp_map[$tenant['employment_status']] ?? 2;
    $history     = intval($tenant['rental_history_months']);
    $has_ref     = (strlen(trim($tenant['reference_text'] ?? '')) > 10) ? 1 : 0;
    $rent        = floatval($monthly_rent);
    
    $python = escapeshellarg(PYTHON_PATH);
    $script  = escapeshellarg(PYTHON_SCRIPTS_DIR . 'predict.py');

    // Pass features as command line arguments
    $cmd = "$python $script "
         . escapeshellarg($income) . " "
         . escapeshellarg($emp_code) . " "
         . escapeshellarg($history) . " "
         . escapeshellarg($has_ref) . " "
         . escapeshellarg($rent) 
                  . " 2>&1";

    $output = shell_exec($cmd);

    if ($output === null) return 0.5; // shell_exec disabled or Python not found

    $raw = trim($output);

    // Accept output only when a numeric value is actually present.
    // This avoids treating error text as 0.0 via floatval().
    if (!preg_match('/[-+]?\d*\.?\d+(?:[eE][-+]?\d+)?/', $raw, $matches)) {
        return 0.5;
    }

    $prob = floatval($matches[0]);
    if ($prob < 0 || $prob > 1) return 0.5; // unexpected output

    return round($prob, 4);
}

// ============================================================
// FINAL RISK COMBINATION
// Standard tenants:  final = 0.6*weighted + 0.4*ml
// First-time renter: final = 0.7*weighted + 0.3*ml
//   + warning flag set
// ============================================================
function calculateFinalRisk($weighted_score, $ml_probability, $is_first_time) {
    if ($is_first_time) {
        $final = 0.7 * $weighted_score + 0.3 * $ml_probability;
    } else {
        $final = 0.6 * $weighted_score + 0.4 * $ml_probability;
    }
    return round(min(1.0, max(0.0, $final)), 4);
}

// ============================================================
// SAVE RISK SCORE TO DATABASE
// ============================================================
function saveRiskScore($application_id, $weighted_score, $ml_probability, $final_risk, $is_first_time) {
    $db = getDB();

    // Delete old score if exists
    $stmt = $db->prepare("DELETE FROM risk_scores WHERE application_id = ?");
    $stmt->bind_param("i", $application_id);
    $stmt->execute();
    $stmt->close();

    // Insert new score
    $stmt = $db->prepare(
        "INSERT INTO risk_scores (application_id, weighted_score, ml_probability, final_risk, is_first_time_renter)
         VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->bind_param("idddi", $application_id, $weighted_score, $ml_probability, $final_risk, $is_first_time);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

// ============================================================
// ALGORITHM C: QuickSort — Sort Applications by Final Risk
// Sorts descending: highest risk first
// Implemented manually (not using PHP's built-in sort)
// ============================================================
function quickSortApplications(array &$apps, $low, $high) {
    if ($low < $high) {
        // Partition the array and get pivot index
        $pivotIndex = partition($apps, $low, $high);

        // Recursively sort elements before and after pivot
        quickSortApplications($apps, $low, $pivotIndex - 1);
        quickSortApplications($apps, $pivotIndex + 1, $high);
    }
}

function partition(array &$apps, $low, $high) {
    // Use last element as pivot (by final_risk)
    $pivot = floatval($apps[$high]['final_risk'] ?? 0);
    $i = $low - 1; // Index of smaller element

    for ($j = $low; $j < $high; $j++) {
        $current = floatval($apps[$j]['final_risk'] ?? 0);
        // DESCENDING order: swap if current is GREATER than pivot
        if ($current > $pivot) {
            $i++;
            // Swap apps[$i] and apps[$j]
            $temp = $apps[$i];
            $apps[$i] = $apps[$j];
            $apps[$j] = $temp;
        }
    }

    // Place pivot in correct position
    $temp = $apps[$i + 1];
    $apps[$i + 1] = $apps[$high];
    $apps[$high] = $temp;

    return $i + 1;
}

// Wrapper — sort by final risk descending
function sortApplicationsByRisk(array $apps) {
    if (count($apps) <= 1) return $apps;
    quickSortApplications($apps, 0, count($apps) - 1);
    return $apps;
}

// ============================================================
// RISK LABEL HELPER
// Returns color + label based on final risk score
// ============================================================
function getRiskLabel($score) {
    $score = floatval($score);
    if ($score < 0.4)       return ['label' => 'Low Risk',    'class' => 'risk-low',    'color' => '#27ae60'];
    elseif ($score <= 0.7)  return ['label' => 'Medium Risk', 'class' => 'risk-medium', 'color' => '#f39c12'];
    else                    return ['label' => 'High Risk',   'class' => 'risk-high',   'color' => '#e74c3c'];
}

// Format normalized risk score (0.0-1.0) as percentage for UI
function formatRiskPercent($score, $decimals = 1) {
    if ($score === null || $score === '') return '—';
    return number_format(floatval($score) * 100, $decimals) . '%';
}
?>

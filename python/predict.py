"""
predict.py — Algorithm B: Logistic Regression Prediction
Tenant Screening and Rental Risk Analysis System
Tribhuvan University CSIT Final Year Project

Called by PHP via shell_exec() with tenant features as arguments.
Outputs a single float (0.0 to 1.0) = probability of default/high risk.

Usage (called from PHP):
  python predict.py <monthly_income> <employment_code> <rental_history_months>
                    <has_reference> <monthly_rent> <age>

Example:
  python predict.py 45000 1 24 1 15000 30

Output:
  0.1234
  (single float on stdout — read by PHP)
"""

import sys
import os
import pickle

# ---------------------------------------------------------------
# Validate arguments
# ---------------------------------------------------------------
if len(sys.argv) != 7:
    # Return neutral fallback probability if wrong args
    print("0.5")
    sys.exit(0)

try:
    monthly_income         = float(sys.argv[1])
    employment_code        = float(sys.argv[2])
    rental_history_months  = float(sys.argv[3])
    has_reference          = float(sys.argv[4])
    monthly_rent           = float(sys.argv[5])
    age                    = float(sys.argv[6])
except ValueError:
    print("0.5")
    sys.exit(0)

# ---------------------------------------------------------------
# Load model and scaler
# ---------------------------------------------------------------
script_dir  = os.path.dirname(os.path.abspath(__file__))
model_path  = os.path.join(script_dir, 'model.pkl')
scaler_path = os.path.join(script_dir, 'scaler.pkl')

if not os.path.exists(model_path) or not os.path.exists(scaler_path):
    # Model not trained yet — use rule-based fallback
    # Simple heuristic: income/rent ratio and employment
    ratio = monthly_income / monthly_rent if monthly_rent > 0 else 1
    if ratio >= 3 and employment_code == 1:
        print("0.15")
    elif ratio >= 2:
        print("0.30")
    elif ratio >= 1.5:
        print("0.50")
    else:
        print("0.75")
    sys.exit(0)

try:
    with open(model_path, 'rb') as f:
        model = pickle.load(f)
    with open(scaler_path, 'rb') as f:
        scaler = pickle.load(f)
except Exception:
    print("0.5")
    sys.exit(0)

# ---------------------------------------------------------------
# Prepare feature vector and predict
# ---------------------------------------------------------------
try:
    import numpy as np
    features = np.array([[
        monthly_income,
        employment_code,
        rental_history_months,
        has_reference,
        monthly_rent,
        age,
    ]])

    features_scaled = scaler.transform(features)

    # Get probability of class 1 (defaulted/high risk)
    prob = model.predict_proba(features_scaled)[0][1]

    # Output single float, 4 decimal places
    print(f"{prob:.4f}")

except Exception:
    print("0.5")
    sys.exit(0)

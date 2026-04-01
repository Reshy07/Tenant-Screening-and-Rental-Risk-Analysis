"""
train.py — Algorithm B: Logistic Regression Model Training
Tenant Screening and Rental Risk Analysis System
Tribhuvan University CSIT Final Year Project

Trains a logistic regression classifier on sample_training_data.csv
and saves the model to model.pkl for use by predict.py

Features used:
  - monthly_income
  - employment_code (1=employed, 2=self_employed, 3=student, 4=unemployed)
  - rental_history_months
  - has_reference (1=yes, 0=no)
  - monthly_rent
  - age

Target:
  - defaulted (1=high risk/default, 0=reliable tenant)

Run once before starting the PHP application:
  python train.py
"""

import os
import csv
import pickle
import sys

try:
    from sklearn.linear_model import LogisticRegression
    from sklearn.preprocessing import StandardScaler
    from sklearn.model_selection import train_test_split
    from sklearn.metrics import accuracy_score, classification_report
    import numpy as np
except ImportError:
    print("ERROR: scikit-learn or numpy not installed.")
    print("Run: pip install scikit-learn numpy")
    sys.exit(1)

# ---------------------------------------------------------------
# Load training data
# ---------------------------------------------------------------
script_dir = os.path.dirname(os.path.abspath(__file__))
data_file  = os.path.join(script_dir, 'sample_training_data.csv')

if not os.path.exists(data_file):
    print(f"ERROR: Training data not found at {data_file}")
    sys.exit(1)

X = []
y = []

with open(data_file, 'r') as f:
    reader = csv.DictReader(f)
    for row in reader:
        try:
            features = [
                float(row['monthly_income']),
                float(row['employment_code']),
                float(row['rental_history_months']),
                float(row['has_reference']),
                float(row['monthly_rent']),
                float(row['age']),
            ]
            label = int(row['defaulted'])
            X.append(features)
            y.append(label)
        except (ValueError, KeyError):
            continue  # skip malformed rows

X = np.array(X)
y = np.array(y)

print(f"Loaded {len(X)} training samples.")
print(f"Defaulted: {sum(y)}, Non-defaulted: {len(y)-sum(y)}")

# ---------------------------------------------------------------
# Train/test split for evaluation
# ---------------------------------------------------------------
X_train, X_test, y_train, y_test = train_test_split(
    X, y, test_size=0.2, random_state=42, stratify=y
)

# ---------------------------------------------------------------
# Feature scaling (important for logistic regression)
# ---------------------------------------------------------------
scaler = StandardScaler()
X_train_scaled = scaler.fit_transform(X_train)
X_test_scaled  = scaler.transform(X_test)

# ---------------------------------------------------------------
# Train Logistic Regression model
# ---------------------------------------------------------------
model = LogisticRegression(
    max_iter=1000,
    random_state=42,
    C=1.0,           # Regularization strength
    solver='lbfgs'
)
model.fit(X_train_scaled, y_train)

# ---------------------------------------------------------------
# Evaluate model
# ---------------------------------------------------------------
y_pred = model.predict(X_test_scaled)
acc = accuracy_score(y_test, y_pred)
print(f"\nModel Accuracy on test set: {acc*100:.2f}%")
print("\nClassification Report:")
print(classification_report(y_test, y_pred, target_names=['Low Risk','High Risk']))

# ---------------------------------------------------------------
# Save model and scaler to disk (used by predict.py)
# ---------------------------------------------------------------
model_path  = os.path.join(script_dir, 'model.pkl')
scaler_path = os.path.join(script_dir, 'scaler.pkl')

with open(model_path, 'wb') as f:
    pickle.dump(model, f)

with open(scaler_path, 'wb') as f:
    pickle.dump(scaler, f)

print(f"\nModel saved to: {model_path}")
print(f"Scaler saved to: {scaler_path}")
print("\nTraining complete. You can now run the PHP application.")

<?php
/*
# Author        : Jason Lamb (with ChatGPT)
# Script        : fuel_form.php
# Revision      : 1.4
# Created Date  : 2025-10-23
# Modified Date : 2025-10-27
# Description   : HTML fuel log entry form with default date, optional total field,
#                 no PHP short tags (for wider compatibility), and revision header block.
*/
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Fuel Log Entry</title>
    <style>
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.5rem 1rem;
            max-width: 600px;
            margin-top: 1rem;
        }

        .form-grid label {
            grid-column: span 2;
            font-weight: bold;
        }

        .form-grid input {
            width: 100%;
            padding: 0.5rem;
        }

        button[type="submit"] {
            grid-column: span 2;
            background-color: #4CAF50;
            color: white;
            padding: 0.6rem;
            font-weight: bold;
            border: none;
            cursor: pointer;
            margin-top: 1rem;
        }

        button[type="submit"]:hover {
            background-color: #45a049;
        }
    </style>
</head>
<body>

<h2>Log Your Fuel Entry</h2>
<form action="save_log.php" method="POST">
    <div class="form-grid">
        <label for="licensePlate">License Plate: (A-Z a-z 0-9)</label>
        <input type="text" id="licensePlate" name="licensePlate" required>

        <label for="date">Date: (defaults to today)</label>
        <input type="date" id="date" name="date" value="<?php echo date('Y-m-d'); ?>" required>

        <label for="odometer">Odometer Reading: (up to .01)</label>
        <input type="number" id="odometer" name="odometer" step="0.01" required>

        <label for="gallons">Gallons Filled: (up to .001)</label>
        <input type="number" id="gallons" name="gallons" step="0.001" required>

        <label for="price">Price per Gallon ($) (up to .01, calculation will add .009):</label>
        <input type="number" id="price" name="price" step="0.01" required>

        <label for="total">Total Price ($) (up to .01):</label>
        <input type="number" id="total" name="total" step="0.01" placeholder="Optional">

        <button type="submit">Save Entry</button>
    </div>
</form>

</body>
</html>

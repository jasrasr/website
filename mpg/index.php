<?php
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
        body {
            font-family: Arial, sans-serif;
            max-width: 400px;
            margin: auto;
            padding: 2rem;
        }
        label {
            display: block;
            margin-top: 1rem;
        }
        input, button {
            width: 100%;
            padding: 0.5rem;
            margin-top: 0.3rem;
        }
        button {
            margin-top: 1.5rem;
            background-color: #4CAF50;
            color: white;
            font-weight: bold;
            border: none;
        }
    </style>
</head>
<body>



<?php include 'fuel_form.php'; ?>


<!-- testing php include
<!-- file renamed from inxex.html to index.php

<form action="save_log.php" method="POST">
    <label for="licensePlate">License Plate:</label>
    <input type="text" id="licensePlate" name="licensePlate" required>

    <label for="date">Date:</label>
    <input type="date" id="date" name="date" required>

    <label for="odometer">Odometer Reading:</label>
    <input type="number" id="odometer" name="odometer" step="1" required>

    <label for="gallons">Gallons Filled:</label>
    <input type="number" id="gallons" name="gallons" step="0.01" required>

    <label for="price">Price per Gallon ($):</label>
    <input type="number" id="price" name="price" step="0.01" required>

    <button type="submit">Save Entry</button>
</form>
-->
</body>
</html>

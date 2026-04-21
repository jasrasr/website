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


    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Primary Meta Tags -->
    <title>MPG Tracker | Jason Lamb</title>
    <meta name="title" content="MPG Tracker | Jason Lamb">
    <meta name="description" content="Easily track your vehicle’s fuel efficiency, cost per mile, and MPG trends over time. Built by Jason Lamb at jasr.me.">

    <!-- Open Graph / Facebook / Microsoft Graph -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://jasr.me/mpg/">
    <meta property="og:title" content="MPG Tracker | Jason Lamb">
    <meta property="og:description" content="Track your fuel logs and MPG history in a clean, interactive dashboard.">
    <meta property="og:image" content="https://jasr.me/mpg/og-preview.jpg">
    <meta property="og:site_name" content="jasr.me">
    <meta property="og:locale" content="en_US">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:url" content="https://jasr.me/mpg/">
    <meta name="twitter:title" content="MPG Tracker | Jason Lamb">
    <meta name="twitter:description" content="Visualize your fuel economy and spending — updated automatically.">
    <meta name="twitter:image" content="https://jasr.me/mpg/og-preview.jpg">

    <!-- Microsoft Graph / Teams / Outlook -->
    <meta name="msapplication-TileImage" content="https://jasr.me/mpg/og-preview.jpg">
    <meta name="msapplication-TileColor" content="#007bff">

    <!-- Browser UI & SEO -->
    <meta name="theme-color" content="#007bff">
    <link rel="canonical" href="https://jasr.me/mpg/">
    <link rel="icon" href="https://jasr.me/favicon.ico" type="image/x-icon">

    <!-- Optional: Social/Embed hint for better previews -->
    <meta name="author" content="Jason Lamb">
    <meta name="robots" content="index, follow">
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

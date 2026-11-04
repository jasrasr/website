<?php

declare(strict_types=1);

require dirname(__DIR__) . '/lib/project-metadata.php';

$date = project_last_modified_date(dirname(__DIR__));
if (preg_match('/^\d{1,2}\/\d{1,2}\/\d{2}$/D', $date) !== 1) {
    throw new RuntimeException('Project modified date must use M/D/YY format.');
}

echo "Project metadata test passed: {$date}.\n";

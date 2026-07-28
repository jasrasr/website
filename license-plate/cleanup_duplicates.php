<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
ensureAppFolders();
header('Content-Type: application/json; charset=utf-8');

function cleanupMetadataFields(): array
{
    return [
        'plate_state','photo_state','date_taken','gps_display','latitude','longitude',
        'camera_make','camera_model','camera_software','image_width','image_height',
        'vehicle_make','vehicle_model','vehicle_color','plate_type','plate_region'
    ];
}

function cleanupScore(array $entry): float
{
    $confidence = normalizeConfidenceValue($entry['confidence'] ?? 0);
    $clarity = normalizeConfidenceValue($entry['clarity_score'] ?? 0);
    $fields = cleanupMetadataFields();
    $present = 0;
    foreach ($fields as $field) {
        if (isset($entry[$field]) && trim((string)$entry[$field]) !== '') $present++;
    }
    $metadata = count($fields) > 0 ? ($present / count($fields)) * 100 : 0;
    $detection = !empty($entry['best_plate_photo']) ? 100 : (!empty($entry['plate']) ? 70 : 0);
    $favoriteBonus = !empty($entry['favorite']) ? 3 : 0;
    $rankBonus = isset($entry['preference_rank']) && (int)$entry['preference_rank'] > 0
        ? max(0, 3 - (((int)$entry['preference_rank'] - 1) * 0.3)) : 0;
    return round(($confidence * .50) + ($clarity * .20) + ($metadata * .20) + ($detection * .10) + $favoriteBonus + $rankBonus, 2);
}

function duplicateGroups(array $entries): array
{
    $groups = [];
    foreach ($entries as $index => $entry) {
        $plate = normalizePlateText((string)($entry['plate'] ?? ''));
        if ($plate === '') continue;
        $groups[$plate][] = ['index' => $index, 'entry' => $entry, 'score' => cleanupScore($entry)];
    }
    return array_filter($groups, static fn(array $group): bool => count($group) > 1);
}

try {
    $entries = readLogEntries();
    $groups = duplicateGroups($entries);
    $preview = [];
    foreach ($groups as $plate => $group) {
        usort($group, static fn(array $a, array $b): int => $b['score'] <=> $a['score']);
        $keeper = $group[0];
        $preview[] = [
            'plate' => $plate,
            'keeper_id' => (string)($keeper['entry']['id'] ?? ''),
            'keeper_score' => $keeper['score'],
            'count' => count($group),
            'remove_count' => count($group) - 1,
        ];
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['action'] ?? '') !== 'merge') {
        echo json_encode(['groups' => $preview], JSON_UNESCAPED_SLASHES);
        exit;
    }

    $selectedIds = [];
    $auditGroups = [];
    foreach ($groups as $plate => $group) {
        usort($group, static fn(array $a, array $b): int => $b['score'] <=> $a['score']);
        $keeperInfo = $group[0];
        $keeperIndex = $keeperInfo['index'];
        $keeper = $entries[$keeperIndex];
        $mergedFields = [];

        foreach (array_slice($group, 1) as $duplicateInfo) {
            $duplicate = $duplicateInfo['entry'];
            $duplicateId = (string)($duplicate['id'] ?? '');
            if ($duplicateId !== '') $selectedIds[] = $duplicateId;
            foreach (cleanupMetadataFields() as $field) {
                $keeperValue = trim((string)($keeper[$field] ?? ''));
                $candidateValue = trim((string)($duplicate[$field] ?? ''));
                if ($keeperValue === '' && $candidateValue !== '') {
                    $keeper[$field] = $duplicate[$field];
                    $mergedFields[$field] = $duplicateId;
                }
            }
        }

        $keeper['cleanup_score'] = cleanupScore($keeper);
        $keeper['best_plate_photo'] = true;
        $keeper['duplicate_cleanup_at'] = date(DATE_ATOM);
        $keeper['duplicate_cleanup_sources'] = array_values(array_filter(array_map(
            static fn(array $item): string => (string)($item['entry']['id'] ?? ''),
            array_slice($group, 1)
        )));
        $entries[$keeperIndex] = $keeper;

        $auditGroups[] = [
            'plate' => $plate,
            'kept_id' => (string)($keeper['id'] ?? ''),
            'kept_score' => $keeperInfo['score'],
            'selected_duplicate_ids' => array_values(array_filter(array_map(
                static fn(array $item): string => (string)($item['entry']['id'] ?? ''),
                array_slice($group, 1)
            ))),
            'merged_fields' => $mergedFields,
        ];
    }

    writeJsonFile(LOG_FILE, array_values($entries));
    $auditFile = DATA_DIR . '/duplicate-cleanup-audit.json';
    $audit = readJsonFile($auditFile);
    $audit[] = [
        'cleanup_at' => date(DATE_ATOM),
        'groups_processed' => count($auditGroups),
        'groups' => $auditGroups,
        'note' => 'Metadata merged into highest-scoring records. Remaining duplicates selected for user review; none deleted automatically.'
    ];
    writeJsonFile($auditFile, $audit);

    echo json_encode([
        'success' => true,
        'groups_processed' => count($auditGroups),
        'selected_ids' => array_values(array_unique($selectedIds)),
        'groups' => $auditGroups,
    ], JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

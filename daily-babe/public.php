<?php

declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
header('Cache-Control: no-store');

$slug = strtolower(trim((string) ($_GET['baby'] ?? '')));
$gallery = public_gallery_by_slug($slug);

if ($gallery === null) {
    http_response_code(404);
    ?>
    <!doctype html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
        <title>Gallery not available</title>
        <link rel="stylesheet" href="assets/app.css?v=<?= BABY_TRACKER_VERSION ?>">
    </head>
    <body><main><section class="empty card"><h1>Gallery not available</h1><p>This public link is invalid or the gallery owner has turned public access off.</p></section></main></body>
    </html>
    <?php
    exit;
}

$babyId = (string) $gallery['babyId'];
$settings = $gallery['settings'];
$entries = json_read('babies/' . $babyId . '/data/entries.json', ['records' => []]);
$records = array_values($entries['records'] ?? []);
usort($records, static fn(array $left, array $right): int => strcmp((string) ($right['photoDate'] ?? ''), (string) ($left['photoDate'] ?? '')));
$babyName = trim((string) ($settings['babyName'] ?? 'Baby'));
$birthDate = (string) ($settings['birthDate'] ?? '');

function public_day_number(string $photoDate, string $birthDate): ?int
{
    if (!valid_date($photoDate) || !valid_date($birthDate) || $photoDate < $birthDate) {
        return null;
    }
    $photo = new DateTimeImmutable($photoDate);
    $birth = new DateTimeImmutable($birthDate);
    return (int) $birth->diff($photo)->format('%a') + 1;
}

function public_date_label(string $date): string
{
    if (!valid_date($date)) {
        return '';
    }
    return (new DateTimeImmutable($date))->format('M j, Y');
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    <meta name="theme-color" content="#19233f">
    <title><?= htmlspecialchars($babyName) ?> · Baby Daily</title>
    <link rel="stylesheet" href="assets/app.css?v=<?= BABY_TRACKER_VERSION ?>">
    <style>
        .public-hero{margin-bottom:1rem}.public-hero h1{font-family:Georgia,serif;font-size:clamp(2.3rem,7vw,5rem);line-height:1;margin:.45rem 0}.public-summary{color:var(--muted);margin:.35rem 0 0}.public-gallery .tile{cursor:default}.public-gallery .tile:hover img{transform:none}.public-footer-note{color:var(--muted);text-align:center;margin:2rem auto 0;max-width:42rem}
    </style>
</head>
<body>
<header class="topbar">
    <div>
        <p class="eyebrow">ONE PHOTO · EVERY DAY</p>
        <h1>Baby Daily</h1>
    </div>
</header>
<main>
    <section class="hero card public-hero">
        <div>
            <p class="eyebrow">PUBLIC GALLERY</p>
            <h1><?= htmlspecialchars($babyName) ?></h1>
            <p class="public-summary"><?= count($records) ?> <?= count($records) === 1 ? 'photo' : 'photos' ?><?php if (valid_date($birthDate)): ?> · Born <?= htmlspecialchars((new DateTimeImmutable($birthDate))->format('F j, Y')) ?><?php endif; ?></p>
        </div>
    </section>

    <?php if ($records === []): ?>
        <p class="empty card">No photos have been added yet.</p>
    <?php else: ?>
        <section class="gallery public-gallery" aria-label="<?= htmlspecialchars($babyName) ?> photo gallery">
            <?php foreach ($records as $record):
                $photoDate = (string) ($record['photoDate'] ?? '');
                $day = public_day_number($photoDate, $birthDate);
                $note = trim((string) ($record['note'] ?? ''));
                $imageUrl = 'public-image.php?baby=' . rawurlencode($slug) . '&id=' . rawurlencode((string) ($record['id'] ?? ''));
            ?>
                <article class="tile">
                    <img loading="lazy" src="<?= htmlspecialchars($imageUrl) ?>" alt="<?= htmlspecialchars($babyName . ' on ' . public_date_label($photoDate)) ?>">
                    <div class="caption">
                        <div>
                            <strong><?= htmlspecialchars(public_date_label($photoDate)) ?></strong>
                            <small><?= htmlspecialchars($note !== '' ? $note : 'Daily photo') ?></small>
                        </div>
                        <?php if ($day !== null): ?><span class="day-chip">DAY <?= $day ?></span><?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>
    <p class="public-footer-note">This is a read-only public gallery. Adding photos and changing settings still require an authorized Baby Daily account.</p>
</main>
<footer>Baby Daily v<?= BABY_TRACKER_VERSION ?></footer>
</body>
</html>


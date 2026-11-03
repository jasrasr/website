<?php

declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
header('Cache-Control: no-store');

$slug = strtolower(trim((string) ($_GET['baby'] ?? '')));
if ($slug === '') {
    $publicGalleries = [];
    foreach (tracker_state()['babies'] ?? [] as $babyId => $_baby) {
        $babyId = (string) $babyId;
        if (preg_match('/^[a-f0-9]{24}$/', $babyId) !== 1) {
            continue;
        }
        $candidate = json_read('central/babies/' . $babyId . '/data/settings.json', []);
        if (gallery_visibility($candidate) !== 'public') {
            continue;
        }
        $candidateSlug = public_gallery_slug($candidate);
        if ($candidateSlug !== '') {
            $publicGalleries[] = ['name' => (string) ($candidate['babyName'] ?? 'Baby'), 'slug' => $candidateSlug];
        }
    }
    usort($publicGalleries, static fn(array $a, array $b): int => strcasecmp($a['name'], $b['name']));
    ?>
    <!doctype html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
        <title>Public galleries · Baby Daily</title>
        <link rel="stylesheet" href="assets/app.css?v=<?= BABY_TRACKER_VERSION ?>">
    </head>
    <body>
    <header class="topbar"><h1>Baby Daily</h1></header>
    <main><section class="overview"><div class="hero-copy"><h2>Public galleries</h2><p>These galleries are open to everyone.</p></div></section>
    <?php if ($publicGalleries === []): ?>
        <p class="empty card">No public galleries are available.</p>
    <?php else: ?>
        <section class="gallery" aria-label="Public baby galleries">
        <?php foreach ($publicGalleries as $item): ?>
            <a class="card public-directory-link" href="public.php?baby=<?= rawurlencode($item['slug']) ?>"><?= htmlspecialchars($item['name']) ?></a>
        <?php endforeach; ?>
        </section>
    <?php endif; ?>
    </main></body></html>
    <?php
    exit;
}
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
    <body><main><section class="empty card"><h1>Gallery not available</h1><p>This link is invalid or the gallery owner has turned sharing off.</p></section></main></body>
    </html>
    <?php
    exit;
}

$babyId = (string) $gallery['babyId'];
$settings = $gallery['settings'];
$entries = json_read('central/babies/' . $babyId . '/data/entries.json', ['records' => []]);
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
        .public-hero{margin-bottom:1rem}.public-hero h1{font-family:Georgia,serif;font-size:clamp(2.3rem,7vw,5rem);line-height:1;margin:.45rem 0}.public-summary{color:var(--muted);margin:.35rem 0 0}.public-gallery .tile{cursor:default}.public-gallery .tile:hover img{transform:none}.public-photo-link{display:block;position:relative}.public-photo-link:focus-visible{outline:3px solid var(--sage);outline-offset:-3px}.public-photo-link img{display:block}.public-photo-link span{position:absolute;right:.65rem;bottom:.65rem;padding:.35rem .55rem;border-radius:7px;background:rgba(27,36,48,.88);color:#fff;font-size:.72rem;font-weight:700}.public-footer-note{color:var(--muted);text-align:center;margin:2rem auto 0;max-width:42rem}
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
            <p class="eyebrow"><?= gallery_visibility($settings) === 'public' ? 'PUBLIC GALLERY' : 'UNLISTED GALLERY' ?></p>
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
                    <a class="public-photo-link" href="<?= htmlspecialchars($imageUrl) ?>" target="_blank" rel="noopener" aria-label="View full photo of <?= htmlspecialchars($babyName . ' from ' . public_date_label($photoDate)) ?> in a new tab">
                        <img loading="lazy" src="<?= htmlspecialchars($imageUrl) ?>" alt="<?= htmlspecialchars($babyName . ' on ' . public_date_label($photoDate)) ?>">
                        <span aria-hidden="true">View full photo</span>
                    </a>
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
    <p class="public-footer-note">This is a read-only shared gallery. Adding photos and changing settings still require an authorized Baby Daily account.</p>
</main>
<footer>Baby Daily v<?= BABY_TRACKER_VERSION ?></footer>
</body>
</html>


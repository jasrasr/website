<?php
/*
    Project      : AI Writing Tool
    File         : changelog.php
    Revision     : 1.1.0
    Created      : 2026-06-02
    Updated      : 2026-06-02
    Author       : Jason Lamb (with help from Claude Code CLI)
    Description  : Web-viewable changelog. Edit the $changelog array below to add new entries.
*/

$currentRevision = '1.1.0';

$changelog = [
    [
        'version'  => '1.1.0',
        'datetime' => '2026-06-02 15:36 EDT',
        'summary'  => 'Added project-insight modes, expanded download options, and token usage tracking.',
        'files'    => [
            'index.php — Review mode dropdown now grouped into "Writing review" and "Project insights" (5 new modes: Brain dump, Task breakdown, Technical advisor, Sharpening questions, Risks & gotchas). Added Download Suggestions and Download Both buttons. Added token usage display.',
            'api/suggest.php — Added 5 new project-insight modes to whitelist and instruction builder. Refactored buildInstructions() to use a thinking-partner base prompt for insight modes vs editor base prompt for writing modes. Now returns OpenAI usage tokens in the response.',
            'assets/app.js — Added downloadSuggestions() and downloadBoth() functions sharing a downloadTextFile() helper. Added updateTokenStats() tracking per-request and session-total tokens.',
            'assets/style.css — Cache buster bumped; no visual changes for this release.',
            'changelog.php — Added 1.1.0 entry.',
        ],
    ],
    [
        'version'  => '1.0.1',
        'datetime' => '2026-06-02 09:32 EDT',
        'summary'  => 'Debounced the local change log so entries record after a 1 second pause in typing instead of on every keystroke. Added web-viewable changelog page linked from the footer.',
        'files'    => [
            'assets/app.js — added changeLogDebounceMs setting and queueChangeLogEntry() function',
            'changelog.php — new web-viewable revision history page',
            'assets/style.css — added changelog page styling',
            'index.php — footer link to changelog',
        ],
    ],
    [
        'version'  => '1.0.0',
        'datetime' => '2026-06-01 17:06 EDT',
        'summary'  => 'Initial project release.',
        'files'    => [
            'index.php — browser writing UI (split editor + suggestions + change log)',
            'assets/app.js — local autosave, change tracking, AI dispatch',
            'assets/style.css — responsive layout and panels',
            'api/suggest.php — server-side OpenAI proxy with per-IP rate limiting',
            'api/health.php — diagnostic endpoint',
            'config/config.example.php — config template',
            '.htaccess (root, /config, /data) — security protections',
            '.gitignore — excludes config.php, runtime data, logs',
            'README.md / SECURITY.md — project documentation',
        ],
    ],
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Changelog for the AI Writing Tool.">
    <title>Changelog — AI Writing Tool</title>
    <link rel="stylesheet" href="assets/style.css?v=<?= htmlspecialchars($currentRevision, ENT_QUOTES) ?>">
</head>
<body>
    <header class="site-header">
        <div>
            <p class="eyebrow">Revision <?= htmlspecialchars($currentRevision, ENT_QUOTES) ?></p>
            <h1>Changelog</h1>
            <p class="subhead">Full history of every revision to the AI Writing Tool. Newest first.</p>
        </div>
        <div class="header-actions">
            <a class="primary-button button-link" href="index.php">Back to Tool</a>
        </div>
    </header>

    <main class="app-shell changelog-shell">
        <section class="panel changelog-panel" aria-labelledby="changelogHeading">
            <div class="panel-header">
                <div>
                    <h2 id="changelogHeading">Revision History</h2>
                    <p>Each entry lists the version, when it was made, what changed, and which files were touched.</p>
                </div>
            </div>

            <ol class="changelog-list">
                <?php foreach ($changelog as $entry): ?>
                    <li class="changelog-entry">
                        <div class="changelog-entry-head">
                            <span class="changelog-version">v<?= htmlspecialchars($entry['version'], ENT_QUOTES) ?></span>
                            <span class="changelog-datetime"><?= htmlspecialchars($entry['datetime'], ENT_QUOTES) ?></span>
                        </div>
                        <p class="changelog-summary"><?= htmlspecialchars($entry['summary'], ENT_QUOTES) ?></p>
                        <?php if (!empty($entry['files'])): ?>
                            <ul class="changelog-files">
                                <?php foreach ($entry['files'] as $file): ?>
                                    <li><?= htmlspecialchars($file, ENT_QUOTES) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
        </section>
    </main>

    <footer class="site-footer">
        <span>AI Writing Tool</span>
        <span>Revision <?= htmlspecialchars($currentRevision, ENT_QUOTES) ?></span>
        <span><a href="index.php">Back to tool</a></span>
    </footer>
</body>
</html>

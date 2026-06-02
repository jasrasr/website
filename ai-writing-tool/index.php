<?php
/*
    Project      : AI Writing Tool
    File         : index.php
    Revision     : 1.1.0
    Created      : 2026-06-01
    Updated      : 2026-06-02
    Author       : Jason Lamb (with help from Claude Code CLI)
    Description  : Main browser interface for live writing or project note-taking, with local change tracking and AI insights/suggestions.
*/
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="A lightweight browser-based writing tool with live AI suggestions and local change tracking.">
    <title>AI Writing Tool</title>
    <link rel="stylesheet" href="assets/style.css?v=1.1.0">
</head>
<body>
    <header class="site-header">
        <div>
            <p class="eyebrow">Revision 1.1.0</p>
            <h1>AI Writing Tool</h1>
            <p class="subhead">Type notes, drafts, or tasks on the left. Get writing edits or project insights on the right. Nothing saves to the server unless you add that later.</p>
        </div>
        <div class="header-actions">
            <button id="btnAnalyze" class="primary-button" type="button">Analyze Now</button>
            <button id="btnDownloadDraft" type="button">Download Draft</button>
            <button id="btnDownloadSuggestions" type="button">Download Suggestions</button>
            <button id="btnDownloadBoth" type="button">Download Both</button>
            <button id="btnClear" class="danger-button" type="button">Clear</button>
        </div>
    </header>

    <main class="app-shell">
        <section class="panel editor-panel" aria-labelledby="draftHeading">
            <div class="panel-header">
                <div>
                    <h2 id="draftHeading">Draft</h2>
                    <p>Autosaves locally in this browser only.</p>
                </div>
                <label class="toggle-row" title="When enabled, the tool analyzes after you stop typing.">
                    <input id="autoAnalyze" type="checkbox" checked>
                    <span>Auto analyze</span>
                </label>
            </div>

            <label class="sr-only" for="draftText">Draft text</label>
            <textarea id="draftText" spellcheck="true" placeholder="Start typing here..."></textarea>

            <div class="stats-row" aria-live="polite">
                <span id="charCount">0 characters</span>
                <span id="wordCount">0 words</span>
                <span id="lastSaved">Local save : not yet</span>
            </div>
        </section>

        <section class="panel suggestions-panel" aria-labelledby="suggestionsHeading">
            <div class="panel-header stacked-mobile">
                <div>
                    <h2 id="suggestionsHeading">Suggestions</h2>
                    <p id="statusText">Ready.</p>
                </div>
                <div class="controls-grid">
                    <label>
                        Review mode
                        <select id="reviewMode">
                            <optgroup label="Writing review">
                                <option value="balanced" selected>Balanced</option>
                                <option value="grammar">Grammar only</option>
                                <option value="clarity">Clarity</option>
                                <option value="professional">Professional tone</option>
                                <option value="concise">Make concise</option>
                                <option value="friendly">Friendlier tone</option>
                            </optgroup>
                            <optgroup label="Project insights">
                                <option value="brain_dump">Brain dump review</option>
                                <option value="task_breakdown">Task breakdown</option>
                                <option value="technical_advisor">Technical advisor</option>
                                <option value="sharpening_questions">Sharpening questions</option>
                                <option value="risks_gotchas">Risks &amp; gotchas</option>
                            </optgroup>
                        </select>
                    </label>
                    <label>
                        Output
                        <select id="outputType">
                            <option value="suggestions" selected>Suggestions</option>
                            <option value="rewrite">Rewrite</option>
                            <option value="outline">Outline</option>
                            <option value="questions">Questions to answer</option>
                        </select>
                    </label>
                </div>
            </div>

            <div id="suggestionsOutput" class="suggestions-output" tabindex="0">
                <p class="muted">Suggestions will appear here after analysis.</p>
            </div>

            <div class="stats-row" aria-live="polite">
                <span id="tokenLast">Last : - tokens</span>
                <span id="tokenSession">Session : 0 tokens</span>
            </div>

            <div class="button-row">
                <button id="btnCopySuggestions" type="button">Copy Suggestions</button>
                <button id="btnCopyDraft" type="button">Copy Draft</button>
            </div>
        </section>

        <section class="panel changes-panel" aria-labelledby="changesHeading">
            <div class="panel-header">
                <div>
                    <h2 id="changesHeading">Local Change Log</h2>
                    <p>Tracks edits while this page is open. Stored locally in your browser.</p>
                </div>
                <button id="btnClearChanges" type="button">Clear Log</button>
            </div>
            <ol id="changeLog" class="change-log"></ol>
        </section>
    </main>

    <footer class="site-footer">
        <span>AI Writing Tool</span>
        <span>Revision 1.1.0</span>
        <span>Updated : 2026-06-02</span>
        <span><a href="changelog.php">View changelog</a></span>
    </footer>

    <script src="assets/app.js?v=1.1.0" defer></script>
</body>
</html>

/*
    Project      : AI Writing Tool
    File         : assets/app.js
    Revision     : 1.0.0
    Created      : 2026-06-01
    Updated      : 2026-06-01
    Description  : Tracks local draft edits, debounces AI analysis calls, and updates the UI.

    Notes:
    - This file does not contain your AI API key. Good. Browser JavaScript is public.
    - Draft text and change logs are stored only in localStorage by default.
    - The PHP endpoint api/suggest.php is responsible for talking to the AI provider.
*/

(() => {
    "use strict";

    const STORAGE_KEYS = {
        draft: "aiWritingTool.draft.v1",
        changes: "aiWritingTool.changes.v1"
    };

    const SETTINGS = {
        debounceMs: 1800,
        minAutoAnalyzeCharacters: 20,
        maxChangeLogItems: 100
    };

    const elements = {
        draftText: document.getElementById("draftText"),
        autoAnalyze: document.getElementById("autoAnalyze"),
        reviewMode: document.getElementById("reviewMode"),
        outputType: document.getElementById("outputType"),
        suggestionsOutput: document.getElementById("suggestionsOutput"),
        statusText: document.getElementById("statusText"),
        charCount: document.getElementById("charCount"),
        wordCount: document.getElementById("wordCount"),
        lastSaved: document.getElementById("lastSaved"),
        changeLog: document.getElementById("changeLog"),
        btnAnalyze: document.getElementById("btnAnalyze"),
        btnDownloadDraft: document.getElementById("btnDownloadDraft"),
        btnClear: document.getElementById("btnClear"),
        btnCopySuggestions: document.getElementById("btnCopySuggestions"),
        btnCopyDraft: document.getElementById("btnCopyDraft"),
        btnClearChanges: document.getElementById("btnClearChanges")
    };

    let analyzeTimer = null;
    let previousText = "";
    let lastAnalyzedText = "";
    let activeController = null;

    function init() {
        const storedDraft = localStorage.getItem(STORAGE_KEYS.draft) || "";
        elements.draftText.value = storedDraft;
        previousText = storedDraft;

        updateStats();
        renderChangeLog();
        bindEvents();

        if (storedDraft.trim().length >= SETTINGS.minAutoAnalyzeCharacters) {
            queueAnalysis();
        }
    }

    function bindEvents() {
        elements.draftText.addEventListener("input", handleDraftInput);
        elements.btnAnalyze.addEventListener("click", () => analyzeDraft({ manual: true }));
        elements.btnDownloadDraft.addEventListener("click", downloadDraft);
        elements.btnClear.addEventListener("click", clearDraft);
        elements.btnCopySuggestions.addEventListener("click", copySuggestions);
        elements.btnCopyDraft.addEventListener("click", copyDraft);
        elements.btnClearChanges.addEventListener("click", clearChangeLog);
        elements.reviewMode.addEventListener("change", () => queueAnalysis());
        elements.outputType.addEventListener("change", () => queueAnalysis());
    }

    function handleDraftInput() {
        const currentText = elements.draftText.value;

        recordChange(previousText, currentText);
        previousText = currentText;

        saveDraftLocally(currentText);
        updateStats();

        if (elements.autoAnalyze.checked) {
            queueAnalysis();
        }
    }

    function saveDraftLocally(text) {
        localStorage.setItem(STORAGE_KEYS.draft, text);
        elements.lastSaved.textContent = `Local save : ${new Date().toLocaleTimeString()}`;
    }

    function updateStats() {
        const text = elements.draftText.value;
        const words = text.trim().length === 0 ? 0 : text.trim().split(/\s+/).length;
        elements.charCount.textContent = `${text.length.toLocaleString()} character${text.length === 1 ? "" : "s"}`;
        elements.wordCount.textContent = `${words.toLocaleString()} word${words === 1 ? "" : "s"}`;
    }

    function queueAnalysis() {
        window.clearTimeout(analyzeTimer);

        const text = elements.draftText.value.trim();
        if (text.length < SETTINGS.minAutoAnalyzeCharacters) {
            setStatus("Ready. Add more text for useful suggestions.");
            return;
        }

        analyzeTimer = window.setTimeout(() => {
            analyzeDraft({ manual: false });
        }, SETTINGS.debounceMs);
    }

    async function analyzeDraft({ manual }) {
        const text = elements.draftText.value.trim();

        if (text.length === 0) {
            setSuggestions("<p class=\"muted\">There is no draft text to analyze.</p>");
            setStatus("No text entered.");
            return;
        }

        if (!manual && text === lastAnalyzedText) {
            return;
        }

        if (activeController) {
            activeController.abort();
        }

        activeController = new AbortController();

        setLoading(true);
        setStatus("Analyzing draft...");
        elements.btnAnalyze.disabled = true;

        try {
            const response = await fetch("api/suggest.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "Accept": "application/json"
                },
                body: JSON.stringify({
                    text,
                    reviewMode: elements.reviewMode.value,
                    outputType: elements.outputType.value
                }),
                signal: activeController.signal
            });

            const data = await response.json().catch(() => null);

            if (!response.ok) {
                const message = data && data.error ? data.error : `Request failed with HTTP ${response.status}`;
                throw new Error(message);
            }

            if (!data || !data.suggestions) {
                throw new Error("The API returned an empty suggestion response.");
            }

            lastAnalyzedText = text;
            setSuggestions(formatSuggestionText(data.suggestions));
            setStatus(`Updated : ${new Date().toLocaleTimeString()}`);
        } catch (error) {
            if (error.name === "AbortError") {
                return;
            }

            setSuggestions(`<p class=\"muted\">${escapeHtml(error.message)}</p>`);
            setStatus("Analysis failed.");
        } finally {
            setLoading(false);
            elements.btnAnalyze.disabled = false;
        }
    }

    function recordChange(oldText, newText) {
        if (oldText === newText) {
            return;
        }

        const delta = newText.length - oldText.length;
        const action = delta >= 0 ? "Added" : "Removed";
        const entry = {
            timestamp: new Date().toISOString(),
            localTime: new Date().toLocaleString(),
            action,
            delta,
            totalCharacters: newText.length
        };

        const changes = getChangeLog();
        changes.unshift(entry);

        localStorage.setItem(
            STORAGE_KEYS.changes,
            JSON.stringify(changes.slice(0, SETTINGS.maxChangeLogItems))
        );

        renderChangeLog();
    }

    function getChangeLog() {
        try {
            return JSON.parse(localStorage.getItem(STORAGE_KEYS.changes) || "[]");
        } catch {
            return [];
        }
    }

    function renderChangeLog() {
        const changes = getChangeLog();
        elements.changeLog.innerHTML = "";

        if (changes.length === 0) {
            const item = document.createElement("li");
            item.innerHTML = "<span class=\"muted\">No changes tracked yet.</span>";
            elements.changeLog.appendChild(item);
            return;
        }

        const fragment = document.createDocumentFragment();

        changes.forEach((change) => {
            const item = document.createElement("li");
            const absoluteDelta = Math.abs(change.delta).toLocaleString();
            item.innerHTML = `
                <strong>${escapeHtml(change.action)}</strong>
                <code>${absoluteDelta}</code>
                character${absoluteDelta === "1" ? "" : "s"}
                <span class="muted">at ${escapeHtml(change.localTime)}; total <code>${Number(change.totalCharacters).toLocaleString()}</code></span>
            `;
            fragment.appendChild(item);
        });

        elements.changeLog.appendChild(fragment);
    }

    function clearChangeLog() {
        localStorage.removeItem(STORAGE_KEYS.changes);
        renderChangeLog();
    }

    function clearDraft() {
        const confirmed = window.confirm("Clear the draft and local change log? This only affects this browser.");
        if (!confirmed) {
            return;
        }

        elements.draftText.value = "";
        previousText = "";
        lastAnalyzedText = "";
        localStorage.removeItem(STORAGE_KEYS.draft);
        localStorage.removeItem(STORAGE_KEYS.changes);
        setSuggestions("<p class=\"muted\">Suggestions will appear here after analysis.</p>");
        setStatus("Cleared.");
        updateStats();
        renderChangeLog();
    }

    function downloadDraft() {
        const text = elements.draftText.value;
        const stamp = new Date().toISOString().replace(/[:.]/g, "-");
        const blob = new Blob([text], { type: "text/plain;charset=utf-8" });
        const link = document.createElement("a");

        link.href = URL.createObjectURL(blob);
        link.download = `ai-writing-draft-${stamp}.txt`;
        document.body.appendChild(link);
        link.click();
        link.remove();
        URL.revokeObjectURL(link.href);
    }

    async function copySuggestions() {
        await copyText(elements.suggestionsOutput.innerText.trim(), "Suggestions copied.");
    }

    async function copyDraft() {
        await copyText(elements.draftText.value, "Draft copied.");
    }

    async function copyText(text, successMessage) {
        if (!text) {
            setStatus("Nothing to copy.");
            return;
        }

        try {
            await navigator.clipboard.writeText(text);
            setStatus(successMessage);
        } catch {
            setStatus("Clipboard copy failed. Browser is being dramatic.");
        }
    }

    function formatSuggestionText(text) {
        return escapeHtml(text)
            .replace(/\n{3,}/g, "\n\n")
            .replace(/\n/g, "<br>");
    }

    function setSuggestions(html) {
        elements.suggestionsOutput.innerHTML = html;
    }

    function setStatus(message) {
        elements.statusText.textContent = message;
    }

    function setLoading(isLoading) {
        elements.suggestionsOutput.classList.toggle("loading", isLoading);
    }

    function escapeHtml(value) {
        return String(value)
            .replaceAll("&", "&amp;")
            .replaceAll("<", "&lt;")
            .replaceAll(">", "&gt;")
            .replaceAll('"', "&quot;")
            .replaceAll("'", "&#039;");
    }

    document.addEventListener("DOMContentLoaded", init);
})();

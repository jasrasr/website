/**
 * File: assets/js/app.js
 * Project: TV Binge Board
 * Description: Client-side behavior for live TMDB search/add result cards and PWA registration.
 * Author: Jason Lamb / ChatGPT
 * Created: 2026-07-02
 * Modified: 2026-07-02
 * Revision: 1.4.3
 */

(function () {
    'use strict';
    const form = document.getElementById('searchForm');
    const searchQuery = document.getElementById('searchQuery');
    const searchStatus = document.getElementById('searchStatus');
    const results = document.getElementById('searchResults');
    const autosuggestDelay = 350;
    let autosuggestTimer = 0;
    let latestSuggestRequest = 0;

    function escapeHtml(value) { return String(value || '').replace(/[&<>'"]/g, function (char) { return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[char]; }); }
    function resultCard(item) {
        const poster = item.poster_path ? `https://image.tmdb.org/t/p/w342${item.poster_path}` : 'assets/img/poster-placeholder.svg';
        const tmdbLink = item.tmdb_url ? `<a class="small-link" href="${escapeHtml(item.tmdb_url)}" target="_blank" rel="noopener">Open on TMDB</a>` : '';
        const tmdbScore = item.vote_average ? ` · TMDB ${escapeHtml(item.vote_average)}/10` : '';
        const totalFields = item.type === 'tv' ? '<input type="hidden" name="total_seasons" value=""><input type="hidden" name="total_episodes" value="">' : '';
        return `<article class="media-card"><img class="poster" src="${escapeHtml(poster)}" alt="Poster for ${escapeHtml(item.title)}" loading="lazy"><div class="media-body"><div class="media-title-row"><h3>${escapeHtml(item.title)}</h3><span class="pill">${escapeHtml(String(item.type).toUpperCase())}</span></div><p class="muted">${escapeHtml(item.year || '')}${tmdbScore}</p><p>${escapeHtml((item.overview || '').slice(0, 220))}</p><p>${tmdbLink}</p><form method="post" action="api/add-media.php"><input type="hidden" name="csrf_token" value="${escapeHtml(window.WATCHLEDGER_CSRF || document.querySelector('input[name=csrf_token]')?.value || '')}"><input type="hidden" name="redirect" value="../watchlist.php"><input type="hidden" name="tmdb_id" value="${escapeHtml(item.tmdb_id)}"><input type="hidden" name="type" value="${escapeHtml(item.type)}"><input type="hidden" name="title" value="${escapeHtml(item.title)}"><input type="hidden" name="year" value="${escapeHtml(item.year || '')}"><input type="hidden" name="poster_path" value="${escapeHtml(item.poster_path || '')}"><input type="hidden" name="overview" value="${escapeHtml(item.overview || '')}">${totalFields}<button type="submit">Add with TMDB details</button></form></div></article>`;
    }
    function setSearchStatus(message) {
        if (searchStatus) { searchStatus.textContent = message; }
    }
    async function runSearch(query, options) {
        const requestId = ++latestSuggestRequest;
        const showEmptyHint = Boolean(options && options.showEmptyHint);
        if (!query) {
            setSearchStatus('');
            if (results) { results.innerHTML = ''; }
            return;
        }
        if (query.length < 2) {
            setSearchStatus('Keep typing to search TMDB.');
            if (results) { results.innerHTML = ''; }
            return;
        }
        setSearchStatus('Searching TMDB...');
        try {
            const response = await fetch(`api/search-tmdb.php?q=${encodeURIComponent(query)}`);
            const data = await response.json();
            if (requestId !== latestSuggestRequest) { return; }
            if (!response.ok || data.error) {
                setSearchStatus('');
                if (results) { results.innerHTML = `<div class="alert danger">${escapeHtml(data.error || 'Search failed.')}</div>`; }
                return;
            }
            if (!data.results || data.results.length === 0) {
                setSearchStatus(showEmptyHint ? 'No results found.' : 'No suggestions found.');
                if (results) { results.innerHTML = ''; }
                return;
            }
            setSearchStatus(showEmptyHint ? 'Search results' : 'Suggestions');
            if (results) { results.innerHTML = data.results.map(resultCard).join(''); }
        } catch (error) {
            if (requestId !== latestSuggestRequest) { return; }
            setSearchStatus('');
            if (results) { results.innerHTML = '<div class="alert danger">Search failed. Use manual add for now.</div>'; }
        }
    }
    if (form && results && searchQuery) {
        searchQuery.addEventListener('input', function () {
            const query = searchQuery.value.trim();
            window.clearTimeout(autosuggestTimer);
            autosuggestTimer = window.setTimeout(function () { runSearch(query, { showEmptyHint: false }); }, autosuggestDelay);
        });
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            window.clearTimeout(autosuggestTimer);
            runSearch(searchQuery.value.trim(), { showEmptyHint: true });
        });
    }

    document.querySelectorAll('select[name="season"][data-episode-options]').forEach(function (seasonSelect) {
        const form = seasonSelect.closest('form');
        const episodeSelect = form ? form.querySelector('select[name="episode"]') : null;
        if (!episodeSelect) { return; }

        let episodeMap = {};
        try {
            episodeMap = JSON.parse(seasonSelect.dataset.episodeOptions || '{}') || {};
        } catch (error) {
            episodeMap = {};
        }

        function syncEpisodeOptions() {
            const seasonValue = String(seasonSelect.value || '');
            const count = Math.max(0, parseInt(episodeMap[seasonValue] || '0', 10) || 0);
            const previousValue = String(episodeSelect.value || '');
            episodeSelect.innerHTML = '';
            for (let episode = 1; episode <= count; episode += 1) {
                const option = document.createElement('option');
                option.value = String(episode);
                option.textContent = String(episode);
                if (String(episode) === previousValue || (episode === 1 && previousValue === '')) {
                    option.selected = true;
                }
                episodeSelect.appendChild(option);
            }
            if (count > 0 && !episodeSelect.value) {
                episodeSelect.value = String(Math.min(count, parseInt(previousValue || '1', 10) || 1));
            }
        }

        seasonSelect.addEventListener('change', syncEpisodeOptions);
        syncEpisodeOptions();
    });

    document.querySelectorAll('.import-match').forEach(function (container) {
        const queryInput = container.querySelector('[data-match-query]');
        const resultsBox = container.querySelector('[data-match-results]');
        const summary = container.querySelector('[data-match-summary]');
        const card = container.closest('.import-match-card');
        const idInput = card ? card.querySelector('input[name^="matched_tmdb_id["]') : null;
        const typeInput = card ? card.querySelector('input[name^="matched_type["]') : null;
        const titleText = card ? (card.querySelector('h3') ? card.querySelector('h3').textContent.trim() : '') : '';
        const typeText = card ? (card.querySelector('.muted') ? card.querySelector('.muted').textContent.split('·')[0].trim() : '') : '';
        const findButton = container.querySelector('[data-find-match]');
        if (!queryInput || !resultsBox || !summary || !findButton || !idInput || !typeInput) { return; }

        function setMatch(id, type, label) {
            idInput.value = String(id || '');
            typeInput.value = String(type || '');
            summary.textContent = label || 'No TMDB match selected yet.';
        }

        function renderResults(results) {
            resultsBox.innerHTML = '';
            results.forEach(function (item) {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'secondary';
                button.textContent = `${item.title || 'Untitled'}${item.year ? ' (' + item.year + ')' : ''}`;
                button.addEventListener('click', function () {
                    setMatch(item.tmdb_id || '', item.type || '', `Selected match: ${(item.type || '').toUpperCase()} #${item.tmdb_id || ''} - ${item.title || 'Untitled'}`);
                });
                resultsBox.appendChild(button);
            });
        }

        findButton.addEventListener('click', async function () {
            const q = queryInput.value.trim() || titleText;
            if (!q) { return; }
            summary.textContent = 'Searching TMDB...';
            try {
                const response = await fetch(`api/import-match-search.php?q=${encodeURIComponent(q)}&type=${encodeURIComponent(typeText)}`);
                const data = await response.json();
                if (!response.ok || data.error) {
                    summary.textContent = data.error || 'Search failed.';
                    return;
                }
                renderResults(data.results || []);
                summary.textContent = (data.results || []).length > 0 ? 'Pick the correct match below.' : 'No TMDB results found.';
            } catch (error) {
                summary.textContent = 'Search failed.';
            }
        });
    });
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () { navigator.serviceWorker.register('service-worker.js').catch(function () {}); });
    }
}());
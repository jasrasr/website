/**
 * File: assets/js/app.js
 * Project: TV Binge Board
 * Description: Client-side behavior for TMDB search/add result cards and PWA registration.
 * Author: Jason Lamb / ChatGPT
 * Created: 2026-07-02
 * Modified: 2026-07-02
 * Revision: 1.4.2
 */

(function () {
    'use strict';
    const form = document.getElementById('searchForm');
    const results = document.getElementById('searchResults');
    function escapeHtml(value) { return String(value || '').replace(/[&<>'"]/g, function (char) { return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[char]; }); }
    function resultCard(item) {
        const poster = item.poster_path ? `https://image.tmdb.org/t/p/w342${item.poster_path}` : 'assets/img/poster-placeholder.svg';
        const tmdbLink = item.tmdb_url ? `<a class="small-link" href="${escapeHtml(item.tmdb_url)}" target="_blank" rel="noopener">Open on TMDB</a>` : '';
        const tmdbScore = item.vote_average ? ` · TMDB ${escapeHtml(item.vote_average)}/10` : '';
        const totalFields = item.type === 'tv' ? '<input type="hidden" name="total_seasons" value=""><input type="hidden" name="total_episodes" value="">' : '';
        return `<article class="media-card"><img class="poster" src="${escapeHtml(poster)}" alt="Poster for ${escapeHtml(item.title)}" loading="lazy"><div class="media-body"><div class="media-title-row"><h3>${escapeHtml(item.title)}</h3><span class="pill">${escapeHtml(String(item.type).toUpperCase())}</span></div><p class="muted">${escapeHtml(item.year || '')}${tmdbScore}</p><p>${escapeHtml((item.overview || '').slice(0, 220))}</p><p>${tmdbLink}</p><form method="post" action="api/add-media.php"><input type="hidden" name="csrf_token" value="${escapeHtml(window.WATCHLEDGER_CSRF || document.querySelector('input[name=csrf_token]')?.value || '')}"><input type="hidden" name="redirect" value="../watchlist.php"><input type="hidden" name="tmdb_id" value="${escapeHtml(item.tmdb_id)}"><input type="hidden" name="type" value="${escapeHtml(item.type)}"><input type="hidden" name="title" value="${escapeHtml(item.title)}"><input type="hidden" name="year" value="${escapeHtml(item.year || '')}"><input type="hidden" name="poster_path" value="${escapeHtml(item.poster_path || '')}"><input type="hidden" name="overview" value="${escapeHtml(item.overview || '')}">${totalFields}<button type="submit">Add with TMDB details</button></form></div></article>`;
    }
    if (form && results) {
        form.addEventListener('submit', async function (event) {
            event.preventDefault();
            const query = document.getElementById('searchQuery').value.trim();
            if (!query) return;
            results.innerHTML = '<p class="muted">Searching TMDB…</p>';
            try {
                const response = await fetch(`api/search-tmdb.php?q=${encodeURIComponent(query)}`);
                const data = await response.json();
                if (!response.ok || data.error) { results.innerHTML = `<div class="alert danger">${escapeHtml(data.error || 'Search failed.')}</div>`; return; }
                if (!data.results || data.results.length === 0) { results.innerHTML = '<p class="muted">No results found.</p>'; return; }
                results.innerHTML = data.results.map(resultCard).join('');
            } catch (error) { results.innerHTML = '<div class="alert danger">Search failed. Use manual add for now.</div>'; }
        });
    }
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () { navigator.serviceWorker.register('service-worker.js').catch(function () {}); });
    }
}());

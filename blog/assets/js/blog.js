/*
# filename: blog.js
# author: Jason Lamb (with help from ChatGPT)
# created date: 2026-02-03
# modified date: 2026-02-03
# revision: 1.3
# changelog:
# - 1.3: Uses build-time _searchText for deterministic full-text search; adds server logging + recent search history (clearable)
# - 1.2: Added server + local logging helpers
# - 1.1: Attempted lazy-loaded body search (fragile)
# - 1.0: Index + post rendering from JSON flat files
*/

(function () {
  function escHtml(s) {
    return String(s)
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;")
      .replaceAll("'", "&#039;");
  }

  function normalize(s) {
    return String(s || "").toLowerCase().trim();
  }

  function getParam(name) {
    const url = new URL(window.location.href);
    return url.searchParams.get(name);
  }

  function fmtDate(iso) {
    if (!iso) return "";
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return iso;
    return d.toLocaleDateString(undefined, { year: "numeric", month: "short", day: "2-digit" });
  }

  function stripHtml(html) {
    return String(html || "")
      .replace(/<script[\s\S]*?<\/script>/gi, " ")
      .replace(/<style[\s\S]*?<\/style>/gi, " ")
      .replace(/<[^>]*>/g, " ")
      .replace(/\s+/g, " ")
      .trim();
  }

  function wordsCountFromHtml(html) {
    const text = stripHtml(html);
    const words = text.trim().split(/\s+/).filter(Boolean);
    return words.length;
  }

  function readingTimeFromHtml(html) {
    const w = wordsCountFromHtml(html);
    const minutes = Math.max(1, Math.round(w / 220));
    return { words: w, minutes: minutes };
  }

  async function fetchJson(url) {
    const res = await fetch(url, { cache: "no-store" });
    if (!res.ok) throw new Error("HTTP " + res.status);
    return await res.json();
  }

  function uniqueTags(posts) {
    const s = new Set();
    posts.forEach((p) => (p.tags || []).forEach((t) => s.add(t)));
    return Array.from(s).sort((a, b) => a.localeCompare(b));
  }

  function renderPills(container, tags, onClick, activeTag) {
    container.innerHTML = "";
    (tags || []).forEach((t) => {
      const el = document.createElement("span");
      el.className = "pill" + (activeTag === t ? " active" : "");
      el.textContent = t;
      el.title = "Filter by tag: " + t;
      el.addEventListener("click", () => onClick(t));
      container.appendChild(el);
    });
  }

  function postCardHtml(p) {
    const safeTitle = escHtml(p.title);
    const safeExcerpt = escHtml(p.excerpt || "");
    const date = fmtDate(p.date);

    const tags = (p.tags || [])
      .slice(0, 8)
      .map((t) => `<span class="pill" title="Tag">${escHtml(t)}</span>`)
      .join("");

    return `
      <div class="card">
        <h2><a href="post.html?p=${encodeURIComponent(p.slug)}">${safeTitle}</a></h2>
        <div class="post-meta">
          <time class="muted">${escHtml(date)}</time>
        </div>
        <div class="pill-row" style="margin-top:10px">${tags}</div>
        <p>${safeExcerpt}</p>
      </div>
    `;
  }

  // -----------------------------
  // Logging + Recent Searches
  // -----------------------------
  const RECENT_KEY = "blogRecentSearches";

  function addRecentSearch(query) {
    const q = String(query || "").trim();
    if (!q) return;

    let items = [];
    try { items = JSON.parse(localStorage.getItem(RECENT_KEY) || "[]"); } catch {}

    // de-dupe (case-insensitive)
    const lower = q.toLowerCase();
    items = items.filter(x => String(x.query || "").toLowerCase() !== lower);

    items.unshift({ query: q, timestamp: new Date().toISOString() });
    items = items.slice(0, 10);

    localStorage.setItem(RECENT_KEY, JSON.stringify(items));
  }

  function getRecentSearches() {
    try { return JSON.parse(localStorage.getItem(RECENT_KEY) || "[]"); } catch { return []; }
  }

  function clearRecentSearches() {
    localStorage.removeItem(RECENT_KEY);
  }

  function renderRecentSearches(listEl, onClickQuery) {
    if (!listEl) return;

    const items = getRecentSearches();
    if (items.length === 0) {
      listEl.innerHTML = "<li class=\"muted\">No recent searches</li>";
      return;
    }

    listEl.innerHTML = items.map((x) => {
      const q = escHtml(x.query);
      return `<li><a href="#" data-q="${q}">${q}</a></li>`;
    }).join("");

    Array.from(listEl.querySelectorAll("a[data-q]")).forEach((a) => {
      a.addEventListener("click", (e) => {
        e.preventDefault();
        onClickQuery(a.getAttribute("data-q") || "");
      });
    });
  }

  function logSearchServer(endpoint, query, resultCount) {
    if (!endpoint) return;
    const q = String(query || "").trim();
    if (!q) return;

    fetch(endpoint, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ query: q, results: resultCount })
    }).catch(() => {});
  }

  // -----------------------------
  // Index page rendering
  // -----------------------------
  async function renderIndex(opts) {
    const {
      indexUrl,
      postListEl,
      tagPillsEl,
      postCountEl,
      searchEl,
      clearBtnEl,
      recentListEl,
      clearHistoryEl,
      logEndpoint
    } = opts;

    let posts = [];
    let activeTag = "";
    let query = "";

    function highlightActivePill() {
      Array.from(tagPillsEl.querySelectorAll(".pill")).forEach((el) => {
        el.classList.toggle("active", el.textContent === activeTag);
      });
    }

    function matches(p) {
      const tagOk = activeTag ? (p.tags || []).includes(activeTag) : true;
      if (!tagOk) return false;

      const qn = normalize(query);
      if (!qn) return true;

      const hay = normalize(p._searchText || "");
      return hay.includes(qn);
    }

    function draw() {
      const filtered = posts.filter(matches);
      postCountEl.textContent = filtered.length + " post(s)";

      // Log only for non-empty searches
      const q = String(query || "").trim();
      if (q) {
        addRecentSearch(q);
        renderRecentSearches(recentListEl, (qq) => {
          searchEl.value = qq;
          query = qq;
          draw();
        });
        logSearchServer(logEndpoint, q, filtered.length);
      }

      if (filtered.length === 0) {
        postListEl.innerHTML = `<div class="card"><p class="muted">No matches. The internet remains undefeated.</p></div>`;
        return;
      }

      postListEl.innerHTML = filtered.map(postCardHtml).join("");
    }

    try {
      const index = await fetchJson(indexUrl);
      posts = (index.posts || []).slice().sort((a, b) => String(b.date).localeCompare(String(a.date)));

      // Normalize tags to array + keep activeTag matching stable
      posts.forEach((p) => { p.tags = Array.isArray(p.tags) ? p.tags : []; });

      const tags = uniqueTags(posts);
      renderPills(tagPillsEl, tags, (t) => {
        activeTag = (activeTag === t) ? "" : t;
        draw();
        highlightActivePill();
      }, activeTag);

      renderRecentSearches(recentListEl, (qq) => {
        searchEl.value = qq;
        query = qq;
        draw();
      });

      if (clearHistoryEl) {
        clearHistoryEl.addEventListener("click", () => {
          clearRecentSearches();
          renderRecentSearches(recentListEl, (qq) => {
            searchEl.value = qq;
            query = qq;
            draw();
          });
        });
      }

      searchEl.addEventListener("input", (e) => {
        query = e.target.value || "";
        draw();
      });

      clearBtnEl.addEventListener("click", () => {
        query = "";
        activeTag = "";
        searchEl.value = "";
        draw();
        highlightActivePill();
      });

      draw();
    } catch (err) {
      postCountEl.textContent = "Failed to load posts";
      postListEl.innerHTML = `
        <div class="card">
          <h2>Could not load index</h2>
          <p class="muted">Common cause: opening as file://. Use a local server.</p>
          <pre><code>${escHtml(String(err))}</code></pre>
        </div>
      `;
    }
  }

  // -----------------------------
  // Post page rendering
  // -----------------------------
  async function renderPost(opts) {
    const {
      containerTitleEl,
      containerDateEl,
      containerTagsEl,
      containerContentEl,
      containerReadingTimeEl,
      containerFooterMetaEl,
      notFoundUrl
    } = opts;

    const slug = getParam("p");
    if (!slug) {
      window.location.href = notFoundUrl;
      return;
    }

    try {
      const post = await fetchJson(`posts/${encodeURIComponent(slug)}.json`);

      document.title = post.title + " • Jason’s Flat-File Blog";
      containerTitleEl.textContent = post.title || slug;
      containerDateEl.textContent = fmtDate(post.date);

      const tags = (post.tags || []).map((t) => {
        const el = document.createElement("a");
        el.className = "pill";
        el.href = "index.html";
        el.textContent = t;
        el.title = "Tag";
        return el;
      });
      containerTagsEl.innerHTML = "";
      tags.forEach((t) => containerTagsEl.appendChild(t));

      const html = post.content_html || "<p class=\"muted\">No content found.</p>";
      containerContentEl.innerHTML = html;

      const rt = readingTimeFromHtml(html);
      containerReadingTimeEl.textContent = "• " + rt.minutes + " min read (" + rt.words + " words)";

      const updated = post.updated ? fmtDate(post.updated) : "";
      const author = post.author ? escHtml(post.author) : "Unknown";
      containerFooterMetaEl.innerHTML =
        `Author: <strong>${author}</strong>` +
        (updated ? ` • Updated: ${escHtml(updated)}` : "");

    } catch (err) {
      window.location.href = notFoundUrl;
    }
  }

  window.Blog = {
    renderIndex,
    renderPost
  };
})();

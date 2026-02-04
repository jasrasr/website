/*
File: blog.js
Author: Jason Lamb (with help from ChatGPT)
Created: 2026-02-03
Modified: 2026-02-03
Revision: 1.0
Change Log:
- 1.0: Index + post rendering from JSON flat files
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

  function wordsCount(html) {
    const text = String(html).replace(/<[^>]*>/g, " ");
    const words = text.trim().split(/\s+/).filter(Boolean);
    return words.length;
  }

  function readingTimeFromHtml(html) {
    const w = wordsCount(html);
    const minutes = Math.max(1, Math.round(w / 220));
    return { words: w, minutes: minutes };
  }

  async function fetchJson(url) {
    const res = await fetch(url, { cache: "no-store" });
    if (!res.ok) throw new Error("HTTP " + res.status);
    return await res.json();
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

  function uniqueTags(posts) {
    const s = new Set();
    posts.forEach((p) => (p.tags || []).forEach((t) => s.add(t)));
    return Array.from(s).sort((a, b) => a.localeCompare(b));
  }

  function normalize(s) {
    return String(s || "").toLowerCase().trim();
  }

  function postMatches(p, q, tag) {
    const qn = normalize(q);
    const title = normalize(p.title);
    const tags = (p.tags || []).map(normalize);

    const tagOk = tag ? tags.includes(normalize(tag)) : true;
    const qOk = !qn ? true : (title.includes(qn) || tags.some((t) => t.includes(qn)));
    return tagOk && qOk;
  }

  function postCardHtml(p) {
    const safeTitle = escHtml(p.title);
    const safeExcerpt = escHtml(p.excerpt || "");
    const date = fmtDate(p.date);

    const tags = (p.tags || [])
      .slice(0, 6)
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

  async function renderIndex(opts) {
    const {
      indexUrl,
      postListEl,
      tagPillsEl,
      postCountEl,
      searchEl,
      clearBtnEl
    } = opts;

    let posts = [];
    let activeTag = "";
    let query = "";

    try {
      const index = await fetchJson(indexUrl);
      posts = (index.posts || [])
        .slice()
        .sort((a, b) => (String(b.date)).localeCompare(String(a.date)));

      const tags = uniqueTags(posts);
      renderPills(tagPillsEl, tags, (t) => {
        activeTag = (activeTag === t) ? "" : t;
        draw();
        highlightActivePill();
      }, activeTag);

      function highlightActivePill() {
        Array.from(tagPillsEl.querySelectorAll(".pill")).forEach((el) => {
          el.classList.toggle("active", el.textContent === activeTag);
        });
      }

      function draw() {
        const filtered = posts.filter((p) => postMatches(p, query, activeTag));
        postCountEl.textContent = filtered.length + " post(s)";

        if (filtered.length === 0) {
          postListEl.innerHTML = `<div class="card"><p class="muted">No matches. The internet remains undefeated.</p></div>`;
          return;
        }

        postListEl.innerHTML = filtered.map(postCardHtml).join("");
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

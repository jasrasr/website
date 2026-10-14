/*
File: theme.js
Author: Jason Lamb (with help from Copilot)
Created: 2026-10-13
Revision: 1.0.0
Change Log:
- 1.0.0: Theme selector with localStorage persistence (jl-dark, midnight, light)
*/
(function () {
  var KEY = "blogTheme";
  var DEFAULT = "jl-dark";
  var THEMES = [
    ["jl-dark", "JL Dark (WordPress)"],
    ["midnight", "Midnight (Classic)"],
    ["light", "Light"]
  ];

  function valid(t) {
    return THEMES.some(function (x) { return x[0] === t; });
  }
  function get() {
    var t = null;
    try { t = localStorage.getItem(KEY); } catch (e) {}
    return valid(t) ? t : DEFAULT;
  }
  function apply(t) {
    document.documentElement.setAttribute("data-theme", t);
  }

  apply(get());

  document.addEventListener("DOMContentLoaded", function () {
    var nav = document.querySelector(".nav");
    if (!nav) return;
    var label = document.createElement("label");
    label.className = "theme-picker";
    var sel = document.createElement("select");
    sel.id = "themeSelect";
    sel.setAttribute("aria-label", "Theme");
    THEMES.forEach(function (x) {
      var o = document.createElement("option");
      o.value = x[0];
      o.textContent = x[1];
      sel.appendChild(o);
    });
    sel.value = get();
    sel.addEventListener("change", function () {
      apply(sel.value);
      try { localStorage.setItem(KEY, sel.value); } catch (e) {}
    });
    label.appendChild(sel);
    nav.appendChild(label);
  });
})();

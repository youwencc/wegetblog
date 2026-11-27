/* =========================================================
   电脑小百科 · 技术笔记 — 站点脚本
   原生 JS，无依赖。职责：主题、目录、阅读进度、归档筛选
   ========================================================= */
(function () {
  "use strict";

  var root = document.documentElement;
  var toggle = document.querySelector("[data-theme-toggle]");

  /* ---- 主题 ---- */
  function setTheme(theme, persist) {
    root.setAttribute("data-theme", theme);
    var meta = document.querySelector('meta[name="theme-color"]');
    if (meta) meta.setAttribute("content", theme === "dark" ? "#14130e" : "#f7f3e9");
    if (toggle) {
      toggle.setAttribute("aria-label", theme === "dark" ? "切换到浅色模式" : "切换到深色模式");
    }
    if (persist) {
      try { localStorage.setItem("cy-theme", theme); } catch (e) {}
    }
  }

  if (toggle) {
    toggle.addEventListener("click", function () {
      setTheme(root.getAttribute("data-theme") === "dark" ? "light" : "dark", true);
    });
  }

  /* ---- 汕头本地时间 ---- */
  var clocks = document.querySelectorAll("[data-clock]");
  if (clocks.length) {
    var pad = function (n) { return n < 10 ? "0" + n : "" + n; };
    var paintClock = function () {
      var now = new Date();
      var offset = now.getTimezoneOffset() + 480;
      var cst = new Date(now.getTime() + offset * 60000);
      var text = pad(cst.getHours()) + ":" + pad(cst.getMinutes());
      Array.prototype.forEach.call(clocks, function (el) {
        el.textContent = text + " CST";
      });
    };
    paintClock();
    window.setInterval(paintClock, 15000);
  }

  /* ---- 文章目录 ---- */
  var tocNav = document.querySelector("[data-toc]");
  var prose = document.querySelector("[data-prose]");

  if (tocNav && prose) {
    var headings = Array.prototype.slice.call(prose.querySelectorAll("h2[id]"));
    if (headings.length) {
      var list = document.createElement("div");
      headings.forEach(function (h) {
        var a = document.createElement("a");
        a.href = "#" + h.id;
        a.textContent = h.textContent.replace(/^\d+\s*/, "");
        a.setAttribute("data-target", h.id);
        list.appendChild(a);
      });
      tocNav.appendChild(list);

      var links = Array.prototype.slice.call(tocNav.querySelectorAll("a"));
      var ticking = false;

      function syncToc() {
        var offset = 130;
        var current = links[0];
        links.forEach(function (link) {
          var el = document.getElementById(link.getAttribute("data-target"));
          if (el && el.getBoundingClientRect().top <= offset) current = link;
        });
        links.forEach(function (link) {
          link.classList.toggle("is-active", link === current);
        });
        ticking = false;
      }

      window.addEventListener("scroll", function () {
        if (!ticking) {
          ticking = true;
          window.requestAnimationFrame(syncToc);
        }
      }, { passive: true });

      syncToc();
    }
  }

  /* ---- 阅读进度 ---- */
  var bar = document.querySelector("[data-progress]");
  if (bar) {
    var raf = false;
    var update = function () {
      var doc = document.documentElement;
      var max = doc.scrollHeight - doc.clientHeight;
      var ratio = max > 0 ? Math.min(1, Math.max(0, doc.scrollTop / max)) : 0;
      bar.style.transform = "scaleX(" + ratio + ")";
      raf = false;
    };
    window.addEventListener("scroll", function () {
      if (!raf) {
        raf = true;
        window.requestAnimationFrame(update);
      }
    }, { passive: true });
    window.addEventListener("resize", update);
    update();
  }

  /* ---- 博客筛选 ---- */
  var filterBar = document.querySelector("[data-filters]");
  if (filterBar) {
    var buttons = Array.prototype.slice.call(filterBar.querySelectorAll("[data-filter]"));
    var items = Array.prototype.slice.call(document.querySelectorAll("[data-tags]"));
    var groups = Array.prototype.slice.call(document.querySelectorAll("[data-year-group]"));
    var countEl = document.querySelector("[data-filter-count]");

    function applyFilter(key) {
      var visible = 0;
      items.forEach(function (item) {
        var tags = (item.getAttribute("data-tags") || "").split(/\s+/);
        var show = key === "all" || tags.indexOf(key) !== -1;
        item.hidden = !show;
        if (show) visible += 1;
      });

      groups.forEach(function (group) {
        var any = Array.prototype.slice.call(group.querySelectorAll("[data-tags]")).some(function (item) {
          return !item.hidden;
        });
        group.hidden = !any;
      });

      buttons.forEach(function (b) {
        b.setAttribute("aria-pressed", b.getAttribute("data-filter") === key ? "true" : "false");
      });

      if (countEl) {
        countEl.textContent = visible + " 篇";
      }
    }

    buttons.forEach(function (b) {
      b.addEventListener("click", function () {
        applyFilter(b.getAttribute("data-filter"));
      });
    });

    applyFilter("all");
  }
})();

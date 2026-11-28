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

  /* ---- 作品演示：全屏 ---- */
  var demoBox = document.querySelector(".work-demo");
  if (demoBox) {
    /* 兜底：旧数据里 iframe 缺 fullscreen 授权时在运行时补上（移动端浏览器强制要求） */
    var demoFrame = demoBox.querySelector("iframe");
    if (demoFrame) {
      var allow = demoFrame.getAttribute("allow") || "";
      if (allow.indexOf("fullscreen") === -1) {
        demoFrame.setAttribute("allow", (allow ? allow + "; fullscreen" : "fullscreen"));
        demoFrame.setAttribute("allowfullscreen", "");
      }
    }
    /* 横屏按钮：演示 HTML 存于数据库，按钮在运行时注入，避免改库 */
    if (!demoBox.querySelector(".demo-rot")) {
      var stage = demoBox.querySelector(".demo-stage");
      if (stage) {
        var rot = document.createElement("button");
        rot.className = "demo-rot";
        rot.type = "button";
        rot.title = "横屏播放";
        rot.setAttribute("aria-label", "横屏播放");
        rot.innerHTML = '<svg viewBox="0 0 16 16" fill="none" aria-hidden="true"><rect x="4.9" y="2.5" width="6.2" height="11" rx="1.3" stroke="currentColor" stroke-width="1.3"/><path d="M2.2 6.2C2.7 4.3 4 2.9 5.7 2.2M13.8 9.8c-.5 1.9-1.8 3.3-3.5 4" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/><path d="M2 3.6l.2 2.6 2.5-.7M14 12.4l-.2-2.6-2.5.7" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
        stage.appendChild(rot);
      }
    }
    var demoFs = demoBox.querySelector(".demo-fs");
    var demoRot = demoBox.querySelector(".demo-rot");
    var landscape = false;
    function setLandscape(on) {
      landscape = on;
      demoBox.classList.toggle("force-landscape", on);
      var rotIcon = demoRot ? demoRot.querySelector("svg") : null;
      if (rotIcon) rotIcon.style.transform = on ? "rotate(180deg)" : "";
    }
    if (demoRot) {
      demoRot.addEventListener("click", function () {
        if (landscape) {
          /* 退出横屏：解除方向锁定并还原 */
          if (document.exitFullscreen && document.fullscreenElement) document.exitFullscreen().catch(function () {});
          if (screen.orientation && screen.orientation.unlock) screen.orientation.unlock();
          setLandscape(false);
          return;
        }
        setLandscape(true);
        /* 先尝试全屏 + 方向锁定（Android 生效）；iOS 无此 API，由 rotate 兜底 */
        var fsReq = demoBox.requestFullscreen || demoBox.webkitRequestFullscreen;
        if (fsReq) {
          try { fsReq.call(demoBox).catch(function () {}); } catch (e) { /* 非用户手势环境忽略 */ }
        }
        try {
          if (screen.orientation && screen.orientation.lock) {
            screen.orientation.lock("landscape").catch(function () {});
          }
        } catch (e) { /* 不支持则保持 rotate 兜底 */ }
      });
    }
    if (demoFs) {
      demoFs.addEventListener("click", function () {
        var fsEl = document.fullscreenElement || document.webkitFullscreenElement;
        if (fsEl) {
          (document.exitFullscreen || document.webkitExitFullscreen).call(document);
        } else if (demoBox.requestFullscreen) {
          demoBox.requestFullscreen();
        } else if (demoBox.webkitRequestFullscreen) {
          demoBox.webkitRequestFullscreen();
        }
      });
    }
  }

  /* ---- 导航二级菜单（触屏/键盘也能展开） ---- */
  var navDrops = Array.prototype.slice.call(document.querySelectorAll("[data-nav-drop]"));
  navDrops.forEach(function (drop) {
    var btn = drop.querySelector(".nav-drop-toggle");
    if (!btn) return;
    btn.addEventListener("click", function (e) {
      e.preventDefault();
      var open = drop.classList.toggle("is-open");
      btn.setAttribute("aria-expanded", open ? "true" : "false");
    });
  });
  if (navDrops.length) {
    document.addEventListener("click", function (e) {
      navDrops.forEach(function (drop) {
        if (!drop.contains(e.target)) {
          drop.classList.remove("is-open");
          var btn = drop.querySelector(".nav-drop-toggle");
          if (btn) btn.setAttribute("aria-expanded", "false");
        }
      });
    });
    document.addEventListener("keydown", function (e) {
      if (e.key !== "Escape") return;
      navDrops.forEach(function (drop) {
        drop.classList.remove("is-open");
        var btn = drop.querySelector(".nav-drop-toggle");
        if (btn) btn.setAttribute("aria-expanded", "false");
      });
    });
  }

  /* ---- 页面右侧定位标签 ---- */
  var railTargets = Array.prototype.slice.call(document.querySelectorAll("[data-rail]"));
  if (railTargets.length > 1) {
    var rail = document.createElement("nav");
    rail.className = "rail-nav";
    rail.setAttribute("aria-label", "页面位置");

    var railLinks = railTargets.map(function (el, i) {
      if (!el.id) el.id = "rail-" + (i + 1);
      var a = document.createElement("a");
      a.className = "rail-item";
      a.href = "#" + el.id;
      a.setAttribute("data-target", el.id);
      var txt = document.createElement("span");
      txt.className = "rail-txt";
      txt.textContent = el.getAttribute("data-rail") || el.id;
      var dot = document.createElement("span");
      dot.className = "rail-dot";
      dot.setAttribute("aria-hidden", "true");
      a.appendChild(txt);
      a.appendChild(dot);
      rail.appendChild(a);
      return a;
    });

    document.body.appendChild(rail);

    var headEl = document.querySelector(".site-header");
    var headH = headEl ? headEl.offsetHeight : 64;
    var lastSync = 0;

    var syncRail = function () {
      var line = headH + 90;
      var current = railLinks[0];
      railLinks.forEach(function (a) {
        var el = document.getElementById(a.getAttribute("data-target"));
        if (el && el.getBoundingClientRect().top <= line) current = a;
      });
      railLinks.forEach(function (a) {
        var on = a === current;
        a.classList.toggle("is-active", on);
        if (on) { a.setAttribute("aria-current", "true"); } else { a.removeAttribute("aria-current"); }
      });
    };

    railLinks.forEach(function (a) {
      a.addEventListener("click", function (e) {
        var el = document.getElementById(a.getAttribute("data-target"));
        if (!el) return;
        /* 优先用原生锚点跳转（配合 [data-rail] 的 scroll-margin-top 让开标题栏），
           平滑滚动交给 CSS 的 scroll-behavior；只在平滑不可用时才接管 */
        if (!("scrollBehavior" in document.documentElement.style)) {
          e.preventDefault();
          var delta = el.getBoundingClientRect().top - headH - 18;
          window.scrollBy(0, delta);
        }
        a.blur();
      });
    });

    window.addEventListener("scroll", function () {
      var now = Date.now();
      if (now - lastSync < 90) return;
      lastSync = now;
      syncRail();
    }, { passive: true });

    window.addEventListener("resize", function () {
      headH = headEl ? headEl.offsetHeight : 64;
      syncRail();
    });

    syncRail();
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

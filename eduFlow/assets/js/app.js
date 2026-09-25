// ============================================================
// LMS — MAIN APP JS
// ============================================================

"use strict";

// ─── Theme System ────────────────────────────────────────
const Theme = (() => {
  const html = document.documentElement;
  let current = "system";

  function apply(theme) {
    current = theme;
    const isDark =
      theme === "dark" ||
      (theme === "system" &&
        matchMedia("(prefers-color-scheme: dark)").matches);
    html.classList.toggle("dark", isDark);
    localStorage.setItem("lms_theme", theme);
    document.dispatchEvent(
      new CustomEvent("themechange", { detail: { theme, isDark } }),
    );
  }

  function init() {
    const saved = localStorage.getItem("lms_theme") || "system";
    apply(saved);

    // Listen for system preference changes
    matchMedia("(prefers-color-scheme: dark)").addEventListener(
      "change",
      () => {
        if (current === "system") apply("system");
      },
    );
  }

  function toggle() {
    const next = current === "dark" ? "light" : "dark";
    apply(next);
    // Save to server
    fetch(window.LMS_BASE + "/ajax/profile.ajax.php", {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: `action=update_theme&theme=${next}&csrf_token=${window.CSRF_TOKEN || ""}`,
    }).catch(() => {});
  }

  return {
    init,
    apply,
    toggle,
    get current() {
      return current;
    },
  };
})();

// ─── Cursor Lightning Effect ─────────────────────────────
const Cursor = (() => {
  let outerEl, innerEl;
  let mouseX = 0,
    mouseY = 0;
  let outerX = 0,
    outerY = 0;
  let innerX = 0,
    innerY = 0;
  let hue = 240;
  let animFrame;

  const colors = ["#6366f1", "#8b5cf6", "#06b6d4"];
  let colorIdx = 0,
    colorT = 0;

  function lerp(a, b, t) {
    return a + (b - a) * t;
  }

  function lerpColor(c1, c2, t) {
    const r = (n) => parseInt(n.slice(1, 3), 16);
    const r1 = r(c1),
      g1 = parseInt(c1.slice(3, 5), 16),
      b1 = parseInt(c1.slice(5, 7), 16);
    const r2 = r(c2),
      g2 = parseInt(c2.slice(3, 5), 16),
      b2 = parseInt(c2.slice(5, 7), 16);
    const ri = Math.round(lerp(r1, r2, t));
    const gi = Math.round(lerp(g1, g2, t));
    const bi = Math.round(lerp(b1, b2, t));
    return `rgb(${ri},${gi},${bi})`;
  }

  function tick() {
    animFrame = requestAnimationFrame(tick);

    // Smooth follow for outer
    outerX = lerp(outerX, mouseX, 0.06);
    outerY = lerp(outerY, mouseY, 0.06);

    // Immediate for inner
    innerX = lerp(innerX, mouseX, 0.5);
    innerY = lerp(innerY, mouseY, 0.5);

    if (outerEl) {
      outerEl.style.left = outerX + "px";
      outerEl.style.top = outerY + "px";
    }

    if (innerEl) {
      innerEl.style.left = innerX + "px";
      innerEl.style.top = innerY + "px";
    }

    // Cycle color
    colorT += 0.008;
    if (colorT >= 1) {
      colorT = 0;
      colorIdx = (colorIdx + 1) % colors.length;
    }
    const nextIdx = (colorIdx + 1) % colors.length;
    const color = lerpColor(colors[colorIdx], colors[nextIdx], colorT);
    if (innerEl) {
      innerEl.style.background = color;
      innerEl.style.boxShadow = `0 0 12px ${color}`;
    }
    if (outerEl) {
      outerEl.style.background = `radial-gradient(circle, ${color}18 0%, transparent 70%)`;
    }
  }

  function createBurst(x, y) {
    const count = 7;
    for (let i = 0; i < count; i++) {
      const p = document.createElement("div");
      const angle = (i / count) * 360;
      const dist = 40 + Math.random() * 30;
      const size = 4 + Math.random() * 4;
      p.style.cssText = `
        position:fixed;
        left:${x}px;top:${y}px;
        width:${size}px;height:${size}px;
        border-radius:50%;
        background:${colors[colorIdx]};
        pointer-events:none;
        z-index:9999;
        transform:translate(-50%,-50%);
        animation:none;
      `;
      document.body.appendChild(p);

      const rad = (angle * Math.PI) / 180;
      const tx = Math.cos(rad) * dist;
      const ty = Math.sin(rad) * dist;

      p.animate(
        [
          {
            transform: `translate(-50%,-50%) translate(0,0) scale(1)`,
            opacity: 1,
          },
          {
            transform: `translate(-50%,-50%) translate(${tx}px,${ty}px) scale(0)`,
            opacity: 0,
          },
        ],
        {
          duration: 500 + Math.random() * 200,
          easing: "cubic-bezier(0,0.9,0.57,1)",
          fill: "forwards",
        },
      ).addEventListener("finish", () => p.remove());
    }
  }

  function init() {
    // Only on non-touch devices
    if (window.matchMedia("(pointer: coarse)").matches) return;

    outerEl = document.getElementById("cursor-outer");
    innerEl = document.getElementById("cursor-inner");
    if (!outerEl || !innerEl) return;

    document.addEventListener("mousemove", (e) => {
      mouseX = e.clientX;
      mouseY = e.clientY;
    });

    document.addEventListener("mousedown", (e) => {
      createBurst(e.clientX, e.clientY);
      if (innerEl) innerEl.style.transform = "translate(-50%,-50%) scale(0.7)";
    });

    document.addEventListener("mouseup", () => {
      if (innerEl) innerEl.style.transform = "translate(-50%,-50%) scale(1)";
    });

    // Interactive elements
    document.addEventListener("mouseover", (e) => {
      const el = e.target.closest("a,button,.btn,.nav-item,[data-interactive]");
      if (el && innerEl) {
        innerEl.style.width = "22px";
        innerEl.style.height = "22px";
        innerEl.style.opacity = "0.6";
      }
    });

    document.addEventListener("mouseout", (e) => {
      const el = e.target.closest("a,button,.btn,.nav-item,[data-interactive]");
      if (el && innerEl) {
        innerEl.style.width = "10px";
        innerEl.style.height = "10px";
        innerEl.style.opacity = "1";
      }
    });

    tick();
  }

  return { init };
})();

// ─── Number Count-Up Animation ───────────────────────────
const CountUp = (() => {
  function animateValue(el, start, end, duration) {
    const startTime = performance.now();
    const isFloat = String(end).includes(".");
    const decimals = isFloat ? (String(end).split(".")[1] || "").length : 0;

    function update(currentTime) {
      const elapsed = currentTime - startTime;
      const progress = Math.min(elapsed / duration, 1);
      // Easing: ease-out-expo
      const eased = 1 - Math.pow(2, -10 * progress);
      const current = start + (end - start) * eased;

      el.textContent = isFloat
        ? current.toFixed(decimals)
        : Math.round(current).toLocaleString();

      if (progress < 1) requestAnimationFrame(update);
    }

    requestAnimationFrame(update);
  }

  function init() {
    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            const el = entry.target;
            const end = parseFloat(el.dataset.count);
            if (!isNaN(end)) {
              el.classList.add("animated");
              animateValue(el, 0, end, 1400);
              observer.unobserve(el);
            }
          }
        });
      },
      { threshold: 0.2 },
    );

    document
      .querySelectorAll("[data-count]")
      .forEach((el) => observer.observe(el));
  }

  return { init };
})();

// ─── Sidebar ─────────────────────────────────────────────
const Sidebar = (() => {
  let sidebar, overlay;

  function toggle() {
    sidebar.classList.toggle("mobile-open");
    overlay.classList.toggle("visible");
    document.body.style.overflow = sidebar.classList.contains("mobile-open")
      ? "hidden"
      : "";
  }

  function close() {
    sidebar.classList.remove("mobile-open");
    overlay.classList.remove("visible");
    document.body.style.overflow = "";
  }

  function init() {
    sidebar = document.getElementById("sidebar");
    overlay = document.getElementById("sidebar-overlay");
    if (!sidebar || !overlay) return;

    document.getElementById("hamburger-btn")?.addEventListener("click", toggle);
    overlay.addEventListener("click", close);

    // Close on escape
    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape") close();
    });
  }

  return { init, close };
})();

// ─── User Dropdown ───────────────────────────────────────
const UserMenu = (() => {
  function init() {
    const trigger = document.getElementById("user-menu-trigger");
    const menu = document.getElementById("user-dropdown");
    if (!trigger || !menu) return;

    trigger.addEventListener("click", (e) => {
      e.stopPropagation();
      menu.classList.toggle("open");
    });

    document.addEventListener("click", () => menu.classList.remove("open"));
    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape") menu.classList.remove("open");
    });
  }
  return { init };
})();

// ─── Notifications Dropdown ──────────────────────────────
const NotifMenu = (() => {
  function init() {
    const btn = document.getElementById("notif-btn");
    const menu = document.getElementById("notif-dropdown");
    if (!btn || !menu) return;

    btn.addEventListener("click", (e) => {
      e.stopPropagation();
      menu.classList.toggle("open");
    });

    document.addEventListener("click", () => menu?.classList.remove("open"));
  }
  return { init };
})();

// ─── Checkbox "Select All" ───────────────────────────────
const BulkSelect = (() => {
  function init(tableId) {
    const table = document.getElementById(tableId);
    if (!table) return;

    const selectAll = table.querySelector(".select-all-cb");
    const rowCbs = () => table.querySelectorAll(".row-cb");
    const bulkBar = document.getElementById("bulk-action-bar");
    const bulkCount = document.getElementById("bulk-count");

    function updateBulkBar() {
      const checked = table.querySelectorAll(".row-cb:checked").length;
      const total = rowCbs().length;
      if (bulkBar) {
        bulkBar.classList.toggle("visible", checked > 0);
        if (bulkCount)
          bulkCount.textContent =
            checked + " row" + (checked !== 1 ? "s" : "") + " selected";
      }
      if (selectAll) {
        selectAll.indeterminate = checked > 0 && checked < total;
        selectAll.checked = checked === total && total > 0;
      }
    }

    selectAll?.addEventListener("change", () => {
      rowCbs().forEach((cb) => (cb.checked = selectAll.checked));
      updateBulkBar();
    });

    table.addEventListener("change", (e) => {
      if (e.target.classList.contains("row-cb")) updateBulkBar();
    });
  }

  function getSelected(tableId) {
    const table = document.getElementById(tableId);
    if (!table) return [];
    return [...table.querySelectorAll(".row-cb:checked")].map((cb) => cb.value);
  }

  function reset(tableId) {
    const table = document.getElementById(tableId);
    if (!table) return;
    table.querySelectorAll(".row-cb").forEach((cb) => (cb.checked = false));
    const selectAll = table.querySelector(".select-all-cb");
    if (selectAll) {
      selectAll.checked = false;
      selectAll.indeterminate = false;
    }
    const bulkBar = document.getElementById("bulk-action-bar");
    if (bulkBar) bulkBar.classList.remove("visible");
  }

  return { init, getSelected, reset };
})();

// ─── Debounce ────────────────────────────────────────────
function debounce(fn, delay = 300) {
  let t;
  return (...args) => {
    clearTimeout(t);
    t = setTimeout(() => fn(...args), delay);
  };
}

// ─── AJAX Helper ─────────────────────────────────────────
async function ajax(url, data = {}, method = "POST") {
  const options = {
    method,
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
  };

  if (method === "POST") {
    const params = new URLSearchParams({
      ...data,
      csrf_token: window.CSRF_TOKEN || "",
    });
    options.body = params.toString();
  } else {
    const params = new URLSearchParams(data);
    url = `${url}?${params}`;
  }

  const res = await fetch(url, options);
  if (!res.ok) throw new Error(`HTTP ${res.status}`);
  return res.json();
}

// ─── File Upload Preview ─────────────────────────────────
function initFileDropZone(dropZoneEl, fileInputEl, listEl) {
  if (!dropZoneEl || !fileInputEl) return;

  dropZoneEl.addEventListener("click", () => fileInputEl.click());

  dropZoneEl.addEventListener("dragover", (e) => {
    e.preventDefault();
    dropZoneEl.classList.add("dragover");
  });

  dropZoneEl.addEventListener("dragleave", () =>
    dropZoneEl.classList.remove("dragover"),
  );

  dropZoneEl.addEventListener("drop", (e) => {
    e.preventDefault();
    dropZoneEl.classList.remove("dragover");
    fileInputEl.files = e.dataTransfer.files;
    updateFileList(fileInputEl, listEl);
  });

  fileInputEl.addEventListener("change", () =>
    updateFileList(fileInputEl, listEl),
  );
}

function updateFileList(input, listEl) {
  if (!listEl) return;
  listEl.innerHTML = "";
  [...(input.files || [])].forEach((file) => {
    const ext = file.name.split(".").pop().toLowerCase();
    const size = formatBytes(file.size);
    const item = document.createElement("div");
    item.className = "file-item";
    item.innerHTML = `
      <div class="file-item-info">
        <div class="file-item-name">${escapeHtml(file.name)}</div>
        <div class="file-item-size">${size}</div>
      </div>
    `;
    listEl.appendChild(item);
  });
}

function formatBytes(bytes) {
  if (!bytes) return "0 B";
  const k = 1024;
  const sizes = ["B", "KB", "MB", "GB"];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + " " + sizes[i];
}

// ─── Escape HTML ─────────────────────────────────────────
function escapeHtml(str) {
  return String(str)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;");
}

// ─── Password Strength Meter ─────────────────────────────
function initPasswordStrength(inputEl, containerEl) {
  if (!inputEl || !containerEl) return;

  const bar = containerEl.querySelector(".strength-fill");
  const text = containerEl.querySelector(".strength-text");

  inputEl.addEventListener("input", () => {
    const val = inputEl.value;
    let score = 0;
    if (val.length >= 8) score++;
    if (/[A-Z]/.test(val)) score++;
    if (/[a-z]/.test(val)) score++;
    if (/[0-9]/.test(val)) score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;

    const levels = [
      { pct: 0, color: "#ef4444", label: "" },
      { pct: 20, color: "#ef4444", label: "Very Weak" },
      { pct: 40, color: "#f97316", label: "Weak" },
      { pct: 60, color: "#f59e0b", label: "Fair" },
      { pct: 80, color: "#10b981", label: "Strong" },
      { pct: 100, color: "#059669", label: "Very Strong" },
    ];

    const l = levels[score];
    if (bar) {
      bar.style.width = l.pct + "%";
      bar.style.background = l.color;
    }
    if (text) {
      text.textContent = l.label;
      text.style.color = l.color;
    }
  });
}

// ─── Tooltip system ──────────────────────────────────────
function initTooltips() {
  let tip = null;

  document.addEventListener("mouseover", (e) => {
    const el = e.target.closest("[data-tooltip]");
    if (!el) return;

    tip = document.createElement("div");
    tip.className = "tooltip-popup";
    tip.textContent = el.dataset.tooltip;
    tip.style.cssText = `
      position:fixed;z-index:10000;
      background:var(--text);color:var(--bg);
      padding:5px 10px;border-radius:6px;
      font-size:0.75rem;font-weight:600;
      pointer-events:none;white-space:nowrap;
      opacity:0;transition:opacity 0.15s;
    `;
    document.body.appendChild(tip);

    const rect = el.getBoundingClientRect();
    tip.style.top = rect.top - 34 + "px";
    tip.style.left = rect.left + rect.width / 2 - tip.offsetWidth / 2 + "px";
    setTimeout(() => {
      if (tip) tip.style.opacity = "1";
    }, 10);
  });

  document.addEventListener("mouseout", (e) => {
    const el = e.target.closest("[data-tooltip]");
    if (el && tip) {
      tip.remove();
      tip = null;
    }
  });
}

// ─── Keyboard Shortcut: Ctrl+K global search ─────────────
document.addEventListener("keydown", (e) => {
  if ((e.ctrlKey || e.metaKey) && e.key === "k") {
    e.preventDefault();
    document.querySelector("#global-search, .search-box input")?.focus();
  }
});

// ─── Init on DOM ready ───────────────────────────────────

// ── Presence Heartbeat ───────────────────────────────────────
// Pings every 30s to keep user marked online.
// Fires mark_offline instantly on tab close / navigation.
const Heartbeat = (() => {
  const url = () => (window.LMS_BASE || "") + "/ajax/presence.ajax.php";
  const tok = () => window.CSRF_TOKEN || "";

  function ping(action) {
    const fd = new FormData();
    fd.append("action", action);
    fd.append("csrf_token", tok());
    if (action === "mark_offline") {
      // sendBeacon is guaranteed to complete even during page unload
      navigator.sendBeacon(url(), fd);
    } else {
      fetch(url(), { method: "POST", body: fd }).catch(() => {});
    }
  }

  function init() {
    if (!window.USER_ID) return; // not logged in
    ping("heartbeat"); // immediate on load
    setInterval(() => ping("heartbeat"), 30000); // every 30s
    window.addEventListener("beforeunload", () => ping("mark_offline"));
    window.addEventListener("pagehide", () => ping("mark_offline"));
    document.addEventListener("visibilitychange", () => {
      document.hidden ? ping("mark_offline") : ping("heartbeat");
    });
  }

  return { init };
})();

document.addEventListener("DOMContentLoaded", () => {
  Theme.init();
  Heartbeat.init();
  Cursor.init();
  CountUp.init();
  Sidebar.init();
  UserMenu.init();
  NotifMenu.init();
  initTooltips();

  // Theme toggle button
  document
    .getElementById("theme-toggle")
    ?.addEventListener("click", () => Theme.toggle());

  // Initialize lucide icons
  if (window.lucide) lucide.createIcons();

  // Auto-init bulk selects
  document
    .querySelectorAll("[data-table]")
    .forEach((t) => BulkSelect.init(t.dataset.table));
});

// Re-run Lucide after dynamic content
window.reinitIcons = () => {
  if (window.lucide) lucide.createIcons();
};

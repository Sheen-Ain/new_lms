/**
 * LMS File Viewer v3
 * - View: PDF (iframe), Image (img), HTML/PHP (code + run)
 * - Edit: admin + teacher can edit HTML/PHP files inline, then save + re-run
 * - Fix: _loadRunFrame no longer blinks (append-before-src pattern)
 * - Uses data-* attributes on .lms-view-btn — no inline JS fragility
 */
"use strict";

(function () {
  const EXT_PDF = new Set(["pdf"]);
  const EXT_IMG = new Set([
    "jpg","jpeg","png","gif","webp","svg","bmp","ico",
  ]);
  const EXT_HTML = new Set(["html", "htm"]);
  const EXT_PHP  = new Set(["php"]);
  const EXT_CODE = new Set([
    // web
    "html","htm","php","js","ts","jsx","tsx","vue","css",
    // data / config
    "txt","sql","csv","json","xml","yaml","yml","md","env",
    // Python
    "py","pyw","ipynb",
    // Java
    "java","c","cpp","h","hpp","cs",
    // scripting / other
    "rb","go","rs","kt","swift","dart","lua","pl","scala","r",
    "sh","bash","bat","ps1","blade",
  ]);
  const EDIT_EXT = new Set([
    "html","htm","php","js","ts","jsx","tsx","vue","css",
    "txt","sql","csv","json","xml","yaml","yml","md","env",
    "py","pyw",
    "java","c","cpp","h","hpp","cs",
    "rb","go","rs","kt","swift","dart","lua","pl","scala","r",
    "sh","bash","bat","ps1","blade",
  ]);

  // Files that can be run server-side
  const EXT_RUNNABLE = new Set(["html","htm","php","py","pyw"]);
  // Files that are code-viewable only (no run button)
  const EXT_CODEVIEW = new Set([
    "js","ts","jsx","tsx","vue","css",
    "txt","sql","csv","json","xml","yaml","yml","md","env","blade","ipynb",
    "java","c","cpp","h","hpp","cs",
    "rb","go","rs","kt","swift","dart","lua","pl","scala","r",
    "sh","bash","bat","ps1",
  ]);

  function extOf(n) {
    return (n || "").split(".").pop().toLowerCase().trim();
  }
  function catOf(n) {
    const e = extOf(n);
    if (EXT_PDF.has(e))      return "pdf";
    if (EXT_IMG.has(e))      return "image";
    if (EXT_HTML.has(e))     return "html";
    if (EXT_PHP.has(e))      return "php";
    if (new Set(["py","pyw"]).has(e)) return "python";
    if (EXT_CODE.has(e))     return "code";
    return "other";
  }
  function isEditable(n, role) {
    return EDIT_EXT.has(extOf(n)) && (role === "admin" || role === "teacher");
  }

  window.LMSViewer = { isViewable, open: openViewer, close: closeViewer };

  function isViewable(n) {
    const e = extOf(n);
    return (
      EXT_PDF.has(e) || EXT_IMG.has(e) || EXT_HTML.has(e) || EXT_PHP.has(e)
      || new Set(["py","pyw"]).has(e) || EXT_CODE.has(e)
    );
  }

  /* ── Delegated click on any .lms-view-btn ─────────────────── */
  document.addEventListener("click", function (e) {
    const btn = e.target.closest(".lms-view-btn");
    if (!btn) return;
    const fid = parseInt(btn.dataset.fid, 10);
    const ftype = btn.dataset.ftype;
    const fname = btn.dataset.fname;
    const dlUrl =
      (window.LMS_BASE || "") +
      "/download.php?type=" +
      encodeURIComponent(ftype) +
      "&id=" +
      fid;
    openViewer(fid, ftype, fname, dlUrl);
  });

  /* ── Build modal once ─────────────────────────────────────── */
  function ensureModal() {
    if (document.getElementById("lmsv-overlay")) return;
    const s = document.createElement("style");
    s.textContent = `
#lmsv-overlay{position:fixed;inset:0;z-index:6000;background:rgba(0,0,0,.8);backdrop-filter:blur(5px);display:flex;align-items:center;justify-content:center;padding:16px;opacity:0;pointer-events:none;transition:opacity .22s ease;}
#lmsv-overlay.open{opacity:1;pointer-events:all;}
#lmsv-box{background:var(--bg-card,#fff);border:1px solid var(--border,#e2e8f0);border-radius:16px;box-shadow:0 24px 80px rgba(0,0,0,.45),0 8px 24px rgba(0,0,0,.2);display:flex;flex-direction:column;width:100%;max-width:980px;height:92vh;transform:scale(.94) translateY(10px);overflow:hidden;transition:transform .25s cubic-bezier(.34,1.56,.64,1);}
#lmsv-overlay.open #lmsv-box{transform:scale(1) translateY(0);}
#lmsv-head{display:flex;align-items:center;gap:10px;padding:13px 18px;border-bottom:1px solid var(--border,#e2e8f0);flex-shrink:0;background:var(--bg-card,#fff);}
.lmsv-ico{width:34px;height:34px;border-radius:9px;flex-shrink:0;display:flex;align-items:center;justify-content:center;}
#lmsv-title{font-family:'Poppins',sans-serif;font-weight:700;font-size:.84rem;color:var(--text,#0f172a);flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
#lmsv-acts{display:flex;gap:6px;flex-shrink:0;flex-wrap:wrap;align-items:center;}
#lmsv-x{width:30px;height:30px;border-radius:7px;border:none;background:var(--bg,#f1f5f9);color:var(--text-muted,#94a3b8);display:flex;align-items:center;justify-content:center;cursor:pointer;margin-left:2px;transition:background .15s,color .15s;flex-shrink:0;}
#lmsv-x:hover{background:rgba(239,68,68,.1);color:#ef4444;}
#lmsv-body{flex:1;overflow:hidden;min-height:0;background:var(--bg,#f8fafc);position:relative;display:flex;flex-direction:column;}
#lmsv-body iframe{width:100%;height:100%;border:none;min-height:520px;display:block;}

/* Option tiles */
.lmsv-opt{display:flex;align-items:center;gap:14px;padding:14px 18px;border-radius:12px;border:1.5px solid var(--border,#e2e8f0);background:var(--bg,#f8fafc);cursor:pointer;text-decoration:none;transition:border-color .15s,background .15s,transform .15s;font-family:inherit;width:100%;}
.lmsv-opt:hover{border-color:var(--primary,#6366f1);background:rgba(99,102,241,.04);transform:translateX(3px);}
.lmsv-opt-ico{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.lmsv-opt-lbl{font-weight:700;font-size:.87rem;color:var(--text,#0f172a);}
.lmsv-opt-sub{font-size:.72rem;color:var(--text-muted,#64748b);margin-top:1px;}

/* Buttons */
.lmsv-btn{display:inline-flex;align-items:center;gap:5px;padding:6px 12px;border-radius:7px;font-size:.78rem;font-weight:600;cursor:pointer;border:1.5px solid transparent;transition:all .15s;font-family:inherit;text-decoration:none;white-space:nowrap;}
.lmsv-primary{background:var(--primary,#6366f1);color:#fff;border-color:var(--primary,#6366f1);}
.lmsv-primary:hover{opacity:.88;}
.lmsv-success{background:#10b981;color:#fff;border-color:#10b981;}
.lmsv-success:hover{opacity:.88;}
.lmsv-secondary{background:var(--bg,#f1f5f9);color:var(--text-secondary,#475569);border-color:var(--border,#e2e8f0);}
.lmsv-secondary:hover{border-color:var(--primary,#6366f1);color:var(--primary,#6366f1);}
.lmsv-ghost{background:transparent;color:var(--text-muted,#64748b);border-color:transparent;}
.lmsv-ghost:hover{background:var(--bg-hover,rgba(99,102,241,.06));}
.lmsv-danger{background:rgba(239,68,68,.1);color:#ef4444;border-color:rgba(239,68,68,.3);}
.lmsv-danger:hover{background:rgba(239,68,68,.18);}

/* Code viewer */
.lmsv-code-wrap{display:flex;flex-direction:column;flex:1;min-height:0;}
.lmsv-code-bar{display:flex;align-items:center;gap:6px;padding:8px 14px;background:var(--bg-card,#fff);border-bottom:1px solid var(--border,#e2e8f0);flex-shrink:0;flex-wrap:wrap;}
.lmsv-mode-btn{padding:4px 11px;border-radius:6px;font-size:.77rem;font-weight:600;border:1.5px solid var(--border,#e2e8f0);background:var(--bg,#f1f5f9);color:var(--text-muted,#64748b);cursor:pointer;display:flex;align-items:center;gap:4px;transition:all .15s;font-family:inherit;}
.lmsv-mode-btn.active{background:var(--primary,#6366f1);color:#fff;border-color:var(--primary,#6366f1);}
.lmsv-mode-btn:hover:not(.active){border-color:var(--primary,#6366f1);color:var(--primary,#6366f1);}
.lmsv-code-scroll{flex:1;overflow:auto;min-height:0;height:0;}
pre.lmsv-pre{margin:0;padding:18px 20px;font-family:'JetBrains Mono','Fira Code','Courier New',monospace;font-size:.81rem;line-height:1.75;color:var(--text,#1e293b);background:transparent;white-space:pre-wrap;word-break:break-all;}

/* Editor */
textarea.lmsv-editor{width:100%;height:100%;min-height:100%;resize:none;font-family:'JetBrains Mono','Fira Code','Courier New',monospace;font-size:.81rem;line-height:1.75;padding:18px 20px;border:none;outline:none;background:var(--bg-input,#f8fafc);color:var(--text,#1e293b);display:block;}
.lmsv-save-bar{display:flex;align-items:center;gap:8px;padding:8px 14px;border-top:1px solid var(--border,#e2e8f0);background:var(--bg-card,#fff);flex-shrink:0;}
.lmsv-save-hint{font-size:.72rem;color:var(--text-muted,#94a3b8);flex:1;}
.lmsv-modified-dot{width:8px;height:8px;border-radius:50%;background:#f59e0b;display:inline-block;margin-right:4px;}

/* Loading / error */
.lmsv-load{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:14px;padding:60px 20px;color:var(--text-muted,#64748b);font-size:.84rem;flex:1;}
.lmsv-spin{width:34px;height:34px;border-radius:50%;border:3px solid var(--border,#e2e8f0);border-top-color:var(--primary,#6366f1);animation:lvspin .8s linear infinite;}
@keyframes lvspin{to{transform:rotate(360deg);}}
.lmsv-err{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:10px;padding:48px 20px;text-align:center;flex:1;}
.lmsv-err-msg{color:var(--danger,#ef4444);font-weight:600;font-size:.87rem;}
.lmsv-err-sub{color:var(--text-muted,#94a3b8);font-size:.77rem;}

@media(max-width:640px){
  #lmsv-box{border-radius:12px 12px 0 0;height:95vh;}
  #lmsv-overlay{padding:0;align-items:flex-end;}
  #lmsv-head{padding:10px 12px;}
  .lmsv-btn span{display:none;}
  .lmsv-btn{padding:6px 8px;}
  .lmsv-code-bar{gap:4px;}
}
    `;
    document.head.appendChild(s);

    const d = document.createElement("div");
    d.id = "lmsv-overlay";
    d.innerHTML = `
<div id="lmsv-box">
  <div id="lmsv-head">
    <div class="lmsv-ico" id="lmsv-ico"></div>
    <div id="lmsv-title">File</div>
    <div id="lmsv-acts"></div>
    <button id="lmsv-x" title="Close">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
    </button>
  </div>
  <div id="lmsv-body"></div>
</div>`;
    document.body.appendChild(d);

    d.addEventListener("click", (e) => {
      if (e.target === d) closeViewer();
    });
    document.getElementById("lmsv-x").addEventListener("click", closeViewer);
    document.addEventListener("keydown", (e) => {
      // Ctrl+S / Cmd+S saves while editor is open
      if ((e.ctrlKey || e.metaKey) && e.key === "s") {
        const ed = document.getElementById("lmsv-editor-ta");
        if (ed) {
          e.preventDefault();
          _doSave();
        }
      }
      if (e.key === "Escape" && d.classList.contains("open")) closeViewer();
    });
  }

  /* ── State ────────────────────────────────────────────────── */
  let _state = {
    fid: 0,
    ftype: "",
    fname: "",
    dlUrl: "",
    cat: "",
    src: "",
    mode: "code",
    hljs: null,
    canRun: false,
  };

  /* ── Open ─────────────────────────────────────────────────── */
  function openViewer(fid, ftype, fname, dlUrl) {
    ensureModal();
    const cat = catOf(fname);
    _state = {
      fid,
      ftype,
      fname,
      dlUrl,
      cat,
      src: "",
      mode: "code",
      hljs: null,
      canRun: EXT_RUNNABLE.has(extOf(fname)),
    };
    _setIcon(_state.cat);
    document.getElementById("lmsv-title").textContent = fname || "File";
    document.getElementById("lmsv-acts").innerHTML = "";
    document.getElementById("lmsv-body").innerHTML = "";
    document.getElementById("lmsv-overlay").classList.add("open");
    document.body.style.overflow = "hidden";
    if (_state.cat === "other") _showDownloadOnly();
    else _showOptions();
  }

  function closeViewer() {
    const ov = document.getElementById("lmsv-overlay");
    if (!ov) return;
    ov.classList.remove("open");
    document.body.style.overflow = "";
    setTimeout(() => {
      const b = document.getElementById("lmsv-body");
      const a = document.getElementById("lmsv-acts");
      if (b) b.innerHTML = "";
      if (a) a.innerHTML = "";
    }, 260);
  }

  /* ── Option tiles ─────────────────────────────────────────── */
  function _showOptions() {
    const { cat, dlUrl, fname } = _state;
    const subs = {
      pdf: "Open PDF in browser",
      image: "Full-resolution preview",
      html: "View code, run output or edit",
      php: "View source, execute or edit",
    };
    // Directly open the viewer — no extra options panel
    _doView();
  }

  function _showDownloadOnly() {
    const { dlUrl, fname } = _state;
    document.getElementById("lmsv-acts").innerHTML = `
      <a class="lmsv-btn lmsv-primary" href="${_esc(dlUrl)}" download="${_esc(fname)}">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        <span>Download</span></a>`;
    document.getElementById("lmsv-body").innerHTML = `
<div class="lmsv-err" style="padding:60px 20px;">
  <svg width="52" height="52" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" opacity=".25"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><polyline points="13 2 13 9 20 9"/></svg>
  <div class="lmsv-err-msg">Preview not available</div>
  <div class="lmsv-err-sub">This file type can only be downloaded</div>
</div>`;
  }

  /* ── View dispatcher ──────────────────────────────────────── */
  function _doView() {
    const { fid, ftype, fname, dlUrl, cat } = _state;
    const acts = document.getElementById("lmsv-acts");
    const body = document.getElementById("lmsv-body");

    // Get current user role from the page (set by auth_check/header)
    const role = window.USER_ROLE || document.body.dataset.role || "";

    const backBtn = `<button class="lmsv-btn lmsv-ghost" id="lmsv-back">
      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="15 18 9 12 15 6"/></svg>
      <span>Close</span></button>`;

    acts.innerHTML = backBtn;
    document
      .getElementById("lmsv-back")
      .addEventListener("click", () => closeViewer());

    if (cat === "pdf") {
      body.innerHTML = `<iframe src="${_esc(_url("stream"))}" style="width:100%;height:100%;min-height:560px;border:none;display:block;"></iframe>`;
    } else if (cat === "image") {
      body.innerHTML = `<div style="display:flex;align-items:center;justify-content:center;min-height:300px;padding:20px;flex:1;overflow:auto;">
        <img src="${_esc(_url("stream"))}" alt="${_esc(fname)}"
          style="max-width:100%;max-height:74vh;object-fit:contain;border-radius:8px;box-shadow:0 4px 24px rgba(0,0,0,.12);"
          onerror="this.parentElement.innerHTML='<div class=lmsv-err><div class=lmsv-err-msg>Could not load image</div></div>'">
      </div>`;
    } else if (cat === "html" || cat === "php" || cat === "python" || cat === "code") {
      _loadCodeView(role);
    }
  }

  /* ── Load highlight.js once from CDN ─────────────────────── */
  let _hljsPromise = null;
  function _loadHljs() {
    if (_hljsPromise) return _hljsPromise;
    _hljsPromise = new Promise((resolve) => {
      if (window.hljs) { resolve(window.hljs); return; }
      // CSS theme
      const link = document.createElement("link");
      link.rel  = "stylesheet";
      link.href = "https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/atom-one-dark.min.css";
      link.onload = () => {
        const patch = document.createElement("style");
        patch.textContent = [
          ".hljs{background:transparent!important;padding:0!important;",
          "font-family:'JetBrains Mono','Fira Code','Courier New',monospace;",
          "font-size:.81rem;line-height:1.75;}",
          "pre.lmsv-pre{background:#282c34!important;border-radius:0;margin:0;",
          "padding:18px 20px;white-space:pre-wrap;word-break:break-all;}",
          ".lmsv-code-scroll{background:#282c34;}"
        ].join("");
        document.head.appendChild(patch);
      };
      document.head.appendChild(link);
      // JS
      const script = document.createElement("script");
      script.src = "https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js";
      script.onload  = () => resolve(window.hljs);
      script.onerror = () => resolve(null);   // fallback: plain text
      document.head.appendChild(script);
    });
    return _hljsPromise;
  }

  /* ── Extension → hljs language name ─────────────────────── */
  function _hljsLang(ext) {
    const map = {
      php:"php", html:"html", htm:"html", css:"css",
      js:"javascript", ts:"typescript", jsx:"javascript", tsx:"typescript",
      vue:"xml", json:"json", xml:"xml", yaml:"yaml", yml:"yaml",
      md:"markdown", sql:"sql", csv:"plaintext", txt:"plaintext",
      env:"ini", blade:"php", py:"python", pyw:"python", ipynb:"json",
      java:"java", c:"c", cpp:"cpp", h:"c", hpp:"cpp", cs:"csharp",
      rb:"ruby", go:"go", rs:"rust", kt:"kotlin", swift:"swift",
      dart:"dart", lua:"lua", pl:"perl", scala:"scala", r:"r",
      sh:"bash", bash:"bash", bat:"dos", ps1:"powershell",
    };
    return map[ext] || "plaintext";
  }

  /* ── Safe highlight via hljs (no regex hacks) ────────────── */
  function _highlight(code, ext, hljs) {
    if (!hljs) return _eh(code);   // graceful plain-text fallback
    try {
      const lang = _hljsLang(ext);
      const res  = hljs.getLanguage(lang)
        ? hljs.highlight(code, { language: lang, ignoreIllegals: true })
        : hljs.highlightAuto(code);
      return res.value;            // already-safe HTML from hljs
    } catch (_) { return _eh(code); }
  }

  /* ── Code / Run / Edit view ───────────────────────────────── */
  function _loadCodeView(role) {
    const body = document.getElementById("lmsv-body");
    body.innerHTML =
      '<div class="lmsv-load"><div class="lmsv-spin"></div><span>Loading source\u2026</span></div>';

    Promise.all([
      fetch(_url("source")).then((r) => {
        if (!r.ok) throw new Error("HTTP " + r.status);
        return r.text();
      }),
      _loadHljs(),
    ])
      .then(([src, hljs]) => {
        _state.src  = src;
        _state.hljs = hljs;
        _renderCodePane("code", src, role, hljs);
      })
      .catch((err) => {
        document.getElementById("lmsv-body").innerHTML = `
<div class="lmsv-err">
  <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" opacity=".3"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
  <div class="lmsv-err-msg">Failed to load source</div>
  <div class="lmsv-err-sub">${_eh(err.message)}</div>
</div>`;
      });
  }

  function _renderCodePane(mode, src, role, hljs) {
    const body = document.getElementById("lmsv-body");
    const { cat, fname, fid, ftype, canRun } = _state;
    const ext     = extOf(fname);
    const canEdit = isEditable(fname, role);

    const editBtn = canEdit
      ? `<button class="lmsv-mode-btn ${mode === "edit" ? "active" : ""}" id="lmsv-m-edit">
      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
      Edit
    </button>`
      : "";

    const runBtn = canRun
      ? `<button class="lmsv-mode-btn ${mode === "run" ? "active" : ""}" id="lmsv-m-run">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polygon points="5 3 19 12 5 21 5 3"/></svg> Run
        </button>`
      : "";

    body.innerHTML = `<div class="lmsv-code-wrap">
      <div class="lmsv-code-bar">
        <button class="lmsv-mode-btn ${mode === "code" ? "active" : ""}" id="lmsv-m-code">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg> View Code
        </button>
        ${runBtn}
        ${editBtn}
        <div style="flex:1;"></div>
        <span style="font-size:.69rem;color:var(--text-muted,#94a3b8);font-family:'JetBrains Mono',monospace;">${ext.toUpperCase()} · ${_eh(fname)}</span>
      </div>
      <div class="lmsv-code-scroll" id="lmsv-cs">
        ${
          mode === "code"
            ? `<pre class="lmsv-pre"><code class="hljs">${_highlight(src, ext, hljs)}</code></pre>`
            : mode === "run"
              ? ""
              : `<textarea id="lmsv-editor-ta" class="lmsv-editor" spellcheck="false">${_eh(src)}</textarea>`
        }
      </div>
      ${
        mode === "edit"
          ? `<div class="lmsv-save-bar">
        <span class="lmsv-save-hint" id="lmsv-save-hint">
          <span class="lmsv-modified-dot" id="lmsv-modified-dot" style="display:none;"></span>
          Press <kbd>Ctrl+S</kbd> or click Save to write changes to disk
        </span>
        <button class="lmsv-btn lmsv-success" id="lmsv-save-btn">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
          <span>Save File</span>
        </button>
      </div>`
          : ""
      }
    </div>`;

    // Wire mode buttons
    document.getElementById("lmsv-m-code").addEventListener("click", () => {
      _state.mode = "code";
      _renderCodePane("code", _state.src, role, _state.hljs);
    });
    if (canRun) {
      document.getElementById("lmsv-m-run").addEventListener("click", () => {
        _state.mode = "run";
        _renderCodePane("run", _state.src, role, _state.hljs);
        _loadRunFrame();
      });
    }
    if (canEdit) {
      document.getElementById("lmsv-m-edit").addEventListener("click", () => {
        _state.mode = "edit";
        _renderCodePane("edit", _state.src, role, _state.hljs);
        const ta = document.getElementById("lmsv-editor-ta");
        const dot = document.getElementById("lmsv-modified-dot");
        if (ta && dot) {
          ta.addEventListener("input", () => { dot.style.display = "inline-block"; });
        }
      });
      if (mode === "edit") {
        const saveBtn = document.getElementById("lmsv-save-btn");
        if (saveBtn) saveBtn.addEventListener("click", _doSave);
      }
    }
    if (mode === "run") _loadRunFrame();
  }

  /* ── Run frame: append-before-src pattern ─────────────────── */
  function _loadRunFrame() {
    const cs = document.getElementById("lmsv-cs");
    if (!cs) return;

    // Clear and show spinner
    cs.innerHTML = "";
    const spin = document.createElement("div");
    spin.className = "lmsv-load";
    spin.innerHTML = '<div class="lmsv-spin"></div><span>Executing…</span>';
    cs.appendChild(spin);

    const iframe = document.createElement("iframe");
    iframe.style.cssText =
      "width:100%;border:none;min-height:520px;display:block;visibility:hidden;";
    iframe.setAttribute(
      "sandbox",
      "allow-scripts allow-same-origin allow-forms allow-modals",
    );

    iframe.addEventListener(
      "load",
      function () {
        spin.remove();
        iframe.style.visibility = "visible";
        try {
          const h =
            iframe.contentWindow.document.documentElement.scrollHeight ||
            iframe.contentWindow.document.body.scrollHeight ||
            0;
          if (h > 0) iframe.style.height = Math.max(520, h) + "px";
        } catch (e) {}
      },
      { once: true },
    );

    // Append FIRST, set src AFTER — prevents double-fire onload
    cs.appendChild(iframe);
    iframe.src = _url("run") + "&_t=" + Date.now();
  }

  /* ── Save ─────────────────────────────────────────────────── */
  async function _doSave() {
    const ta = document.getElementById("lmsv-editor-ta");
    const btn = document.getElementById("lmsv-save-btn");
    const hint = document.getElementById("lmsv-save-hint");
    if (!ta || !btn) return;

    const newContent = ta.value;
    _state.src = newContent; // update in-memory copy

    btn.disabled = true;
    const orig = btn.innerHTML;
    btn.innerHTML =
      '<div class="lmsv-spin" style="width:14px;height:14px;border-width:2px;"></div><span>Saving…</span>';

    try {
      const fd = new FormData();
      fd.append("action", "save");
      fd.append("type", _state.ftype);
      fd.append("id", String(_state.fid));
      fd.append("content", newContent);
      if (window.CSRF_TOKEN) fd.append("csrf_token", window.CSRF_TOKEN);

      const res = await fetch((window.LMS_BASE || "") + "/file-runner.php", {
        method: "POST",
        body: fd,
      });
      const json = await res.json();

      if (json.status === "success") {
        // Success feedback
        btn.innerHTML =
          '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg><span>Saved!</span>';
        const dot = document.getElementById("lmsv-modified-dot");
        if (dot) dot.style.display = "none";
        if (hint) hint.style.color = "var(--success,#10b981)";
        setTimeout(() => {
          btn.innerHTML = orig;
          btn.disabled = false;
          if (hint) hint.style.color = "";
        }, 2000);
      } else {
        if (window.Toast) Toast.error(json.message || "Save failed");
        else alert("Save failed: " + (json.message || "Unknown error"));
        btn.innerHTML = orig;
        btn.disabled = false;
      }
    } catch (err) {
      if (window.Toast) Toast.error("Network error during save");
      else alert("Network error");
      btn.innerHTML = orig;
      btn.disabled = false;
    }
  }

  /* ── Helpers ──────────────────────────────────────────────── */
  function _url(action) {
    return (
      (window.LMS_BASE || "") +
      "/file-runner.php?action=" +
      action +
      "&type=" +
      encodeURIComponent(_state.ftype) +
      "&id=" +
      _state.fid
    );
  }

  function _setIcon(cat) {
    const el = document.getElementById("lmsv-ico");
    if (!el) return;
    const C = {
      pdf: [
        "rgba(239,68,68,.1)",
        "#ef4444",
        '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>',
      ],
      image: [
        "rgba(6,182,212,.1)",
        "#06b6d4",
        '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/>',
      ],
      html: [
        "rgba(245,158,11,.1)",
        "#f59e0b",
        '<polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/>',
      ],
      php: [
        "rgba(139,92,246,.1)",
        "#8b5cf6",
        '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>',
      ],
      python: [
        "rgba(202,138,4,.12)",
        "#ca8a04",
        '<polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/>',
      ],
      code: [
        "rgba(99,102,241,.1)",
        "#6366f1",
        '<polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/>',
      ],
      other: [
        "rgba(99,102,241,.1)",
        "#6366f1",
        '<path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="13 2 13 8 19 8"/>',
      ],
    };
    const [bg, color, path] = C[cat] || C.other;
    el.style.background = bg;
    el.innerHTML = `<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="${color}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">${path}</svg>`;
  }

  function _eh(s) {
    return String(s || "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }
  function _esc(s) {
    return String(s || "")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }
})();
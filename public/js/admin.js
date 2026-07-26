(function () {
  const SECTIONS = [
    "overview",
    "communicators",
    "press-prep",
    "notices",
    "media",
    "documents",
    "settings",
  ];

  const TITLES = {
    overview: "Overview",
    communicators: "Communicators",
    "press-prep": "Press Prep",
    notices: "Notices",
    media: "Media",
    documents: "Documents",
    settings: "Settings",
  };

  function validSection(name) {
    return SECTIONS.includes(name) ? name : "overview";
  }

  function currentFromUrl() {
    const params = new URLSearchParams(window.location.search);
    return validSection(params.get("section") || "overview");
  }

  function applyTheme(theme) {
    const next = theme === "dark" ? "dark" : "light";
    document.documentElement.setAttribute("data-theme", next);
    localStorage.setItem("ndc-admin-theme", next);
    const toggle = document.getElementById("theme-toggle");
    if (toggle) {
      toggle.textContent = next === "dark" ? "Light mode" : "Dark mode";
      toggle.setAttribute("aria-pressed", next === "dark" ? "true" : "false");
    }
  }

  function showSection(name) {
    const section = validSection(name);

    document.querySelectorAll("[data-panel]").forEach((panel) => {
      panel.classList.toggle("is-active", panel.getAttribute("data-panel") === section);
    });

    document.querySelectorAll("[data-section]").forEach((btn) => {
      const active = btn.getAttribute("data-section") === section;
      if (active) {
        btn.setAttribute("aria-current", "page");
      } else {
        btn.removeAttribute("aria-current");
      }
    });

    const title = document.getElementById("section-title");
    if (title) {
      title.textContent = TITLES[section] || "Overview";
    }

    const topTitle = document.getElementById("topbar-title");
    if (topTitle) {
      topTitle.textContent = TITLES[section] || "Overview";
    }

    const url = new URL(window.location.href);
    url.searchParams.set("section", section);
    window.history.replaceState({}, "", url);

    closeSidebar();
  }

  function openSidebar() {
    const sidebar = document.getElementById("sidebar");
    const overlay = document.getElementById("sidebar-overlay");
    if (sidebar) sidebar.classList.add("is-open");
    if (overlay) overlay.classList.add("is-open");
  }

  function closeSidebar() {
    const sidebar = document.getElementById("sidebar");
    const overlay = document.getElementById("sidebar-overlay");
    if (sidebar) sidebar.classList.remove("is-open");
    if (overlay) overlay.classList.remove("is-open");
  }

  function parseJsonAttr(el, name, fallback) {
    try {
      const raw = el.getAttribute(name);
      return raw ? JSON.parse(raw) : fallback;
    } catch (e) {
      return fallback;
    }
  }

  function initAudiencePicker(root) {
    const modeSelect = root.querySelector("[data-audience-mode]");
    const targetsWrap = root.querySelector("[data-audience-targets]");
    const chipsEl = root.querySelector("[data-tag-chips]");
    const searchEl = root.querySelector("[data-tag-search]");
    const suggestionsEl = root.querySelector("[data-tag-suggestions]");
    const inputsEl = root.querySelector("[data-tag-inputs]");
    const hintEl = root.querySelector("[data-tag-hint]");
    if (!modeSelect || !targetsWrap || !chipsEl || !searchEl || !suggestionsEl || !inputsEl) {
      return;
    }

    const geo = parseJsonAttr(root, "data-geo", []);
    const selected = new Map();
    let activeIndex = -1;

    function currentKind() {
      const mode = modeSelect.value;
      if (mode === "group_constituency") return "constituency";
      if (mode === "regions") return "region";
      return null;
    }

    function optionList() {
      const kind = currentKind();
      if (kind === "region") {
        return geo.map((r) => ({
          value: `r:${r.id}`,
          label: r.name,
          meta: "Region",
        }));
      }
      if (kind === "constituency") {
        const rows = [];
        geo.forEach((r) => {
          (r.constituencies || []).forEach((c) => {
            rows.push({
              value: `c:${c.id}`,
              label: c.name,
              meta: r.name,
            });
          });
        });
        return rows;
      }
      return [];
    }

    function syncHiddenInputs() {
      inputsEl.innerHTML = "";
      selected.forEach((item) => {
        const input = document.createElement("input");
        input.type = "hidden";
        input.name = "target_ids[]";
        input.value = item.value;
        inputsEl.appendChild(input);
      });
    }

    function renderChips() {
      chipsEl.innerHTML = "";
      selected.forEach((item) => {
        const chip = document.createElement("span");
        chip.className = "tag-chip";
        const label = document.createElement("span");
        label.textContent = item.label;
        const remove = document.createElement("button");
        remove.type = "button";
        remove.setAttribute("aria-label", `Remove ${item.label}`);
        remove.textContent = "×";
        remove.addEventListener("click", () => {
          selected.delete(item.value);
          renderChips();
          syncHiddenInputs();
          renderSuggestions();
        });
        chip.appendChild(label);
        chip.appendChild(remove);
        chipsEl.appendChild(chip);
      });
      syncHiddenInputs();
    }

    function updateVisibility() {
      const kind = currentKind();
      if (!kind) {
        targetsWrap.hidden = true;
        selected.clear();
        renderChips();
        suggestionsEl.hidden = true;
        searchEl.value = "";
        return;
      }
      targetsWrap.hidden = false;
      if (hintEl) {
        hintEl.textContent =
          kind === "region"
            ? "Leave blank to reach all communicators with a region. Add region tags to narrow."
            : "Leave blank to reach all Constituency Comms. Add constituency tags to narrow.";
      }
      searchEl.placeholder =
        kind === "region" ? "Search regions…" : "Search constituencies…";
      // Drop tags that no longer match the active kind
      Array.from(selected.keys()).forEach((value) => {
        const ok =
          (kind === "region" && value.startsWith("r:")) ||
          (kind === "constituency" && value.startsWith("c:"));
        if (!ok) selected.delete(value);
      });
      renderChips();
      renderSuggestions();
    }

    function filteredOptions() {
      const q = searchEl.value.trim().toLowerCase();
      return optionList()
        .filter((opt) => !selected.has(opt.value))
        .filter((opt) => {
          if (!q) return true;
          return (
            opt.label.toLowerCase().includes(q) ||
            (opt.meta || "").toLowerCase().includes(q)
          );
        })
        .slice(0, 12);
    }

    function renderSuggestions() {
      if (targetsWrap.hidden) {
        suggestionsEl.hidden = true;
        return;
      }
      const rows = filteredOptions();
      if (!rows.length || (!searchEl.value.trim() && document.activeElement !== searchEl)) {
        suggestionsEl.hidden = true;
        suggestionsEl.innerHTML = "";
        activeIndex = -1;
        return;
      }
      suggestionsEl.innerHTML = "";
      rows.forEach((opt, index) => {
        const btn = document.createElement("button");
        btn.type = "button";
        btn.className = "tag-suggestion" + (index === activeIndex ? " is-active" : "");
        btn.setAttribute("role", "option");
        btn.innerHTML = `${escapeHtml(opt.label)}<small>${escapeHtml(opt.meta || "")}</small>`;
        btn.addEventListener("mousedown", (e) => {
          e.preventDefault();
          addTag(opt);
        });
        suggestionsEl.appendChild(btn);
      });
      suggestionsEl.hidden = false;
    }

    function addTag(opt) {
      if (!opt || selected.has(opt.value)) return;
      selected.set(opt.value, opt);
      searchEl.value = "";
      activeIndex = -1;
      renderChips();
      renderSuggestions();
      searchEl.focus();
    }

    function seedFromOld() {
      const oldTargets = parseJsonAttr(root, "data-old-targets", []);
      const catalog = optionList();
      const byValue = new Map(catalog.map((o) => [o.value, o]));
      oldTargets.forEach((value) => {
        const hit = byValue.get(value);
        if (hit) selected.set(hit.value, hit);
      });
      renderChips();
    }

    modeSelect.addEventListener("change", updateVisibility);
    searchEl.addEventListener("focus", () => {
      activeIndex = -1;
      renderSuggestions();
    });
    searchEl.addEventListener("input", () => {
      activeIndex = -1;
      renderSuggestions();
    });
    searchEl.addEventListener("blur", () => {
      window.setTimeout(() => {
        suggestionsEl.hidden = true;
      }, 120);
    });
    searchEl.addEventListener("keydown", (e) => {
      const rows = filteredOptions();
      if (e.key === "ArrowDown") {
        e.preventDefault();
        if (!rows.length) return;
        activeIndex = (activeIndex + 1) % rows.length;
        renderSuggestions();
      } else if (e.key === "ArrowUp") {
        e.preventDefault();
        if (!rows.length) return;
        activeIndex = activeIndex <= 0 ? rows.length - 1 : activeIndex - 1;
        renderSuggestions();
      } else if (e.key === "Enter") {
        if (activeIndex >= 0 && rows[activeIndex]) {
          e.preventDefault();
          addTag(rows[activeIndex]);
        } else if (rows.length === 1 && searchEl.value.trim()) {
          e.preventDefault();
          addTag(rows[0]);
        }
      } else if (e.key === "Backspace" && !searchEl.value && selected.size) {
        const keys = Array.from(selected.keys());
        selected.delete(keys[keys.length - 1]);
        renderChips();
        renderSuggestions();
      } else if (e.key === "Escape") {
        suggestionsEl.hidden = true;
      }
    });

    updateVisibility();
    seedFromOld();
  }

  function escapeHtml(str) {
    return String(str)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  document.addEventListener("DOMContentLoaded", () => {
    const saved = localStorage.getItem("ndc-admin-theme");
    applyTheme(saved === "dark" ? "dark" : "light");

    if (!document.body.classList.contains("admin-app")) {
      return;
    }

    showSection(currentFromUrl());

    document.querySelectorAll("[data-section]").forEach((btn) => {
      btn.addEventListener("click", () => {
        showSection(btn.getAttribute("data-section"));
      });
    });

    const themeToggle = document.getElementById("theme-toggle");
    if (themeToggle) {
      themeToggle.addEventListener("click", () => {
        const current = document.documentElement.getAttribute("data-theme");
        applyTheme(current === "dark" ? "light" : "dark");
      });
    }

    const menuBtn = document.getElementById("menu-toggle");
    if (menuBtn) {
      menuBtn.addEventListener("click", openSidebar);
    }

    const overlay = document.getElementById("sidebar-overlay");
    if (overlay) {
      overlay.addEventListener("click", closeSidebar);
    }

    document.querySelectorAll("form").forEach((form) => {
      form.addEventListener("submit", () => {
        const submit = form.querySelector('button[type="submit"]');
        if (submit && !submit.disabled) {
          submit.disabled = true;
          submit.dataset.originalLabel = submit.textContent;
          submit.textContent = "Working…";
        }
      });
    });

    document.querySelectorAll("[data-audience-picker]").forEach((root) => {
      initAudiencePicker(root);
    });

    document.querySelectorAll("[data-toggle-media-edit]").forEach((btn) => {
      btn.addEventListener("click", () => {
        const id = btn.getAttribute("data-toggle-media-edit");
        const row = id ? document.getElementById(id) : null;
        if (!row) return;
        const open = row.hasAttribute("hidden");
        if (open) {
          row.removeAttribute("hidden");
        } else {
          row.setAttribute("hidden", "");
        }
      });
    });

    document.querySelectorAll("form[data-media-upload]").forEach((form) => {
      const maxBytes = Number(form.getAttribute("data-max-bytes") || 104857600);
      const fileInput = form.querySelector('input[type="file"][name="file"]');
      const hint = form.querySelector("[data-media-file-hint]");

      function formatMb(bytes) {
        return (bytes / 1048576).toFixed(1) + " MB";
      }

      function validateFile() {
        const file = fileInput && fileInput.files && fileInput.files[0];
        if (!file) {
          if (hint) hint.textContent = "";
          return true;
        }
        if (hint) {
          hint.textContent = `Selected: ${file.name} (${formatMb(file.size)})`;
        }
        if (file.size > maxBytes) {
          window.alert(
            `This file is ${formatMb(file.size)}. Maximum allowed is ${formatMb(maxBytes)}.`
          );
          fileInput.value = "";
          if (hint) hint.textContent = "";
          return false;
        }
        return true;
      }

      if (fileInput) {
        fileInput.addEventListener("change", validateFile);
      }
      form.addEventListener("submit", (e) => {
        if (!validateFile()) {
          e.preventDefault();
          e.stopPropagation();
          // Re-enable submit buttons disabled by the global handler
          form.querySelectorAll('button[type="submit"]').forEach((btn) => {
            btn.disabled = false;
            if (btn.dataset.originalLabel) {
              btn.textContent = btn.dataset.originalLabel;
            }
          });
        }
      });
    });

    const modal = document.getElementById("transcript-modal");
    const base = (window.NDC_ADMIN && window.NDC_ADMIN.pressPrepShowUrl) || "/admin/press-prep";

    async function openTranscript(sessionId) {
      if (!modal) return;
      const title = document.getElementById("transcript-title");
      const meta = document.getElementById("transcript-meta");
      const body = document.getElementById("transcript-body");
      const pdf = document.getElementById("transcript-pdf");
      const txt = document.getElementById("transcript-txt");
      if (title) title.textContent = "Loading…";
      if (meta) meta.textContent = "";
      if (body) body.innerHTML = "<p class='muted'>Loading transcript…</p>";
      if (pdf) pdf.href = `${base}/${sessionId}/transcript.pdf`;
      if (txt) txt.href = `${base}/${sessionId}/transcript.txt`;
      if (typeof modal.showModal === "function") modal.showModal();

      try {
        const res = await fetch(`${base}/${sessionId}`, {
          headers: { Accept: "application/json" },
          credentials: "same-origin",
        });
        const data = await res.json();
        if (!res.ok || !data.success) {
          throw new Error((data && data.message) || "Could not load transcript");
        }
        const s = data.session;
        if (title) {
          title.textContent = `${s.user?.name || "Communicator"} — session #${s.id}`;
        }
        if (meta) {
          const bits = [
            s.outing_type,
            s.difficulty,
            s.interview_mode,
            s.readiness_pct != null ? `${s.readiness_pct}% ready` : null,
            s.ended_early ? "ended early" : null,
          ].filter(Boolean);
          meta.textContent = bits.join(" · ");
        }
        if (body) {
          const turns = s.turns || [];
          if (!turns.length) {
            body.innerHTML = "<p class='muted'>No turns recorded.</p>";
          } else {
            body.innerHTML = turns
              .map((t, i) => {
                const q = escapeHtml(t.question || "");
                const a = escapeHtml(t.user_answer || "(no answer)");
                const model = t.model_answer
                  ? `<p class="muted"><strong>Model:</strong> ${escapeHtml(t.model_answer)}</p>`
                  : "";
                const coach = t.coach_note
                  ? `<p class="muted"><strong>Coach:</strong> ${escapeHtml(t.coach_note)}</p>`
                  : "";
                return `<div class="transcript-turn"><div class="q">Q${i + 1}. ${q}</div><p><strong>Answer:</strong> ${a}</p>${model}${coach}</div>`;
              })
              .join("");
          }
          if (s.summary) {
            body.insertAdjacentHTML(
              "afterbegin",
              `<p style="margin-bottom:1rem"><strong>Summary:</strong> ${escapeHtml(s.summary)}</p>`
            );
          }
        }
      } catch (err) {
        if (title) title.textContent = "Transcript";
        if (body) {
          body.innerHTML = `<p class="muted">${escapeHtml(err.message || "Failed to load")}</p>`;
        }
      }
    }

    document.querySelectorAll(".score-row[data-session-id]").forEach((row) => {
      const open = () => openTranscript(row.getAttribute("data-session-id"));
      row.addEventListener("click", open);
      row.addEventListener("keydown", (e) => {
        if (e.key === "Enter" || e.key === " ") {
          e.preventDefault();
          open();
        }
      });
    });

    const closeBtn = document.getElementById("transcript-close");
    if (closeBtn && modal) {
      closeBtn.addEventListener("click", () => modal.close());
    }
  });
})();

(function () {
  const SECTIONS = [
    "overview",
    "communicators",
    "press-prep",
    "documents",
    "settings",
  ];

  const TITLES = {
    overview: "Overview",
    communicators: "Communicators",
    "press-prep": "Press Prep",
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

    function escapeHtml(str) {
      return String(str)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;");
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

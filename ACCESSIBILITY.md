# Accessibility

## Commitment

`taskStarEx`'s frontend (`src/fe`, Nuxt/Vue 3 + Vuetify) is designed to
be usable by people with disabilities. **WCAG 2.1 AA** is the target
conformance level for this web UI. This includes keyboard navigation,
screen-reader compatibility, and color-contrast compliance.

This statement reflects the real, current state of `src/fe` as read
during Phase 15 (2026-11-19) — the known limitations below are real
findings from reading the actual templates, not a boilerplate disclaimer.
It should be reviewed again at least once per major version of the
frontend stack (currently Nuxt 4 / Vuetify 4).

## What's in place today

- The UI is built on [Vuetify](https://vuetifyjs.com/), a Material
  Design component library with built-in keyboard navigation, focus
  styling, and ARIA roles on its interactive components (buttons, text
  fields, data tables, dialogs) out of the box. This is a real,
  substantive starting point — not a guarantee of full compliance on
  its own, which is why the gaps below are called out explicitly rather
  than assumed away.
- Form fields (`login.vue`, `create.vue`, `[fin].vue`) use Vuetify's
  `label` prop, which renders as a real associated `<label>`, and the
  login form sets `autocomplete="username"`/`autocomplete="current-password"`
  on its fields.
- Icon buttons that also carry visible text (Export CSV, Import CSV, Add
  New User, Back) have an accessible name from that text, not just the
  icon.

## Known limitations (as of 2026-11-19)

These were found by reading the real templates
(`src/fe/pages/company/users/index.vue` and siblings), not assumed:

1. **Icon-only action buttons have no accessible name.** In the company
   user list (`index.vue`, lines ~80–81), the per-row Edit
   (`v-btn icon="mdi-pencil"`) and Delete (`v-btn icon="mdi-delete"`)
   buttons carry no `aria-label` (or Vuetify's `aria-label` prop). A
   screen reader announces these as an unnamed "button," not "Edit" /
   "Delete." **Fix**: add `aria-label="Edit user"` /
   `aria-label="Delete user"` (ideally including the user's name) to
   each. Not fixed in this phase (Phase 15 is docs-only, per this
   program's R1 — one agent, one phase); tracked here as a real,
   concrete gap, not a hypothetical one.
2. **No document language declared.** `src/fe/nuxt.config.ts` does not
   set `app.head.htmlAttrs.lang`, so the rendered page has no declared
   `lang` attribute despite the UI containing both English labels and
   Azerbaijani data values (status labels, e.g. "İşləyir",
   "Məzuniyyət"). Screen readers use `lang` to select the correct
   pronunciation/voice. **Fix**: set `lang: 'en'` (or a more precise
   value) in `nuxt.config.ts`'s `app.head.htmlAttrs`.
3. **No automated accessibility testing.** Neither `make lint-fe`
   (ESLint/`@nuxt/eslint`) nor CI runs an accessibility-specific linter
   (e.g. `eslint-plugin-vuejs-accessibility`) or a runtime checker (e.g.
   axe-core/pa11y). Issues like the two above are only caught by manual
   review, which is how they were found this phase.
4. **Color contrast not independently audited.** Vuetify's default
   Material theme is generally WCAG-AA-compliant by design, but this has
   not been independently verified against this app's actual rendered
   pages with a contrast-checking tool. Marked **unconfirmed** rather
   than assumed compliant.
5. **No skip-to-content link.** `app.vue`'s layout (navigation drawer +
   main content) has no "skip to main content" link for keyboard users
   to bypass the navigation drawer.

Nothing above is invented — each is a direct reading of the template
code cited next to it. If a future accessibility pass finds something
this list missed, add it here rather than assuming this list is
exhaustive.

## Supported environments

- **Browsers**: no browser-support matrix has been formally defined or
  tested for this app; it targets whatever current Vuetify 4/Nuxt 4
  support (broadly, current-version evergreen browsers — Chrome,
  Firefox, Safari, Edge). Not independently verified against older
  browsers.
- **Assistive technology**: not independently tested against a specific
  screen reader (NVDA/JAWS/VoiceOver) in this phase. The limitations
  above were identified by code review, not by running one.
- **Devices**: the UI uses Vuetify's responsive grid (`v-container`,
  `v-row`/`v-col`), so it is expected to adapt to smaller viewports, but
  has not been manually tested on a real mobile device/screen reader
  combination.

## How to report a barrier

- Open an issue using
  [`.github/ISSUE_TEMPLATE/bug.md`](.github/ISSUE_TEMPLATE/bug.md) and
  tag it `accessibility` (or describe the barrier clearly if your GitHub
  access doesn't let you add labels).
- Or email the maintainer — see [`SUPPORT.md`](SUPPORT.md) for general
  contact routes; for anything involving a security dimension (e.g. an
  accessibility issue that also exposes data), use
  [`SECURITY.md`](SECURITY.md)'s private reporting instead.
- Response timeline: triaged the same way as any other bug report (see
  `SUPPORT.md`) — there is no separate, faster SLA for accessibility
  reports specifically, but they are treated as real defects, not
  lower-priority by default.

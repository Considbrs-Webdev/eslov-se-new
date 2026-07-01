# Visual diff page audit

Generated from live DB via `ddev wp eval-file tools/visual-diff/scripts/audit-pages.php`.

**Last run:** see `pages-audit.json` → `coverage.generated_at`  
**Page count:** 69 URLs × 2 viewports (desktop + mobile) = **138 screenshots** per full run

## How pages were chosen

Pages are **not guessed**. The audit script scans `modularity-modules` post meta on every published page across the network, resolves module post IDs to types (`mod-posts`, `mod-navigation`, …), and picks **one representative URL per module/layout pattern**.

### Main site (blog 1) — module coverage

| Module / pattern | Example page | Why it matters |
|------------------|--------------|----------------|
| Homepage `/` | mod-navigation grid + footer bar | Global chrome, launcher tiles |
| `mod-navigation` + top-sidebar | Arbete och arbetsmarknad | Secondary band + button row (rows #6, #16) |
| `mod-navigation` + right-sidebar | Section pages with side nav | Most common hub layout |
| `mod-navigation` tree highlighted | Akut hjälp | Row #17 compact tree |
| Child pills | Kartor hub | Row #5 shim |
| `mod-hero` slider-area | Miljö, energi och klimat | Hero + section intro |
| `mod-posts` default | Navet-style lists | Rows #7–#8 filtering/cards |
| `mod-posts` one-page template | Artscape | One-page.blade.php layout |
| `mod-manualinput` | Teater | Repeater / manual_inputs (row #2) |
| `mod-contacts` + hero | Karlsrobadet news | Contact sidebar pattern |
| `mod-section-split` | Miljögiftkonferens | Campaign split sections |
| `mod-section-card` | Giftfri vardag | Section card band |
| `mod-timeline` | Landsbygdsutveckling | Timeline module |
| `mod-inlaylist` | Avgifter och regler | Accordion/inlay list |
| `mod-table` | Kurslitteratur | Table rendering |
| `mod-fileslist` | Gyaskogen | Download lists |
| `mod-notice` | Behöver jag bygglov? | Notice/callout |
| `mod-iframe` | Fastighetsägarens ansvar… | Embedded iframe |
| `mod-form` | Fridasroskolan FAQ form | Form module |
| `mod-event` on page | Ung kultur i Eslöv | Event block (not archive) |
| `mod-text` sidebar | Kolonilotter | Rich text in sidebar |
| `mod-image` | (auto-picked) | Image module |

### Main site — CPT templates

| Type | URL pattern | Controller |
|------|-------------|------------|
| `nyheter` archive | `/nyheter/` | Archive |
| `nyheter` single + `amne` | auto-picked | TaxonomyTaglist shim |
| `event` archive / single | `/event/…` | Event schema singular |
| `job-listing` archive / single | auto-picked | Job posting schema |
| `place` archive / single | auto-picked | Place schema |
| `school` archive | auto-picked | School archive |
| `project` archive | auto-picked | Project archive |

### Subsites (11 networks)

Each subsite gets:

1. **Homepage** — subsite tokens, header/footer, hero
2. **First page with `mod-posts`** (if any) — card height/filtering on subsites (row #2c)
3. **First page with `mod-navigation`** (if any) — forked module on subsite
4. **First page with `mod-hero`** (if any)
5. **First page with `mod-event`** (if any)
6. **Event archive** (if CPT exists) — e.g. plus.eslov.se/evenemang/

| Subsite | Domain |
|---------|--------|
| medborgarhuset | medborgarhuset.eslov.se |
| foretag | foretag.eslov.se |
| programforoffentligmiljo | programforoffentligmiljo.eslov.se |
| varumarkesmanual | varumarkesmanual.eslov.se |
| storatorg | storatorg.eslov.se |
| sommarieslov | sommarieslov.eslov.se |
| historia | historia.eslov.se |
| eslovsfesten | eslovsfesten.eslov.se |
| valarbetare | valarbetare.eslov.se |
| utveckla | utveckla.eslov.se |
| plus | plus.eslov.se |

### Not yet in set (low volume / investigate separately)

These module post types exist on main site but have **no dedicated sample page** yet (few instances or only in legacy content):

- `mod-video`, `mod-slider`, `mod-map`, `mod-rss`, `mod-noticeboard`, `mod-divider`, `mod-spacer`

Add manually to `pages.json` if a regression is reported.

## Viewports

Every page is captured at:

| Viewport | Size |
|----------|------|
| `desktop` | 1280 × 900 |
| `mobile` | 390 × 844 |

No extra config needed — mobile is automatic for all pages in `pages.json`.

## Regenerate after DB changes

```bash
cd eslov-se-new
ddev wp eval-file tools/visual-diff/scripts/audit-pages.php
cp tools/visual-diff/pages.generated.json tools/visual-diff/pages.json
```

Or: `npm run audit-pages` from `tools/visual-diff/`.

## Run subsets

```bash
# All pages (long — ~5–8 min in Docker)
npm run run:docker

# One subsite's pages only (filter by id prefix in pages.json ids)
node src/run.mjs --pages=home,sub16-home,sub16-event

# Main site modules only — pick ids from pages-audit.json
node src/run.mjs --pages=home,auto-1-558888,tree-highlighted
```

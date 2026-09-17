# 2026-09 Updates — Security Task Ledger

| | |
|---|---|
| **Branch** | `2026-09-updates` |
| **Version** | 1.17.0 (`package.json`, plugin header) |
| **Date opened** | 2026-09-16 |
| **Source** | Follow-up security report on the 1.16.0 fixes (see `.tmp/issues-and-fixes.md`) |
| **Verified on** | Local site `wpml.test`, plugin classes driven through `wp eval-file` |
| **Scope** | Gaps remaining *after* the branch's renderer hardening |

Every finding below was reproduced on this machine by running the real plugin
classes, not inferred from reading the code. The reproduction for each is recorded
under **Evidence**.

---

## Status at a glance

| # | Task | Severity | Status | Where it lives |
|---|---|---|---|---|
| T1 | `error` field renders attacker markup into the admin document | **High** | **Fixed** 2026-09-17 (test deferred) | Modal, no frame |
| T2 | `area[target]` not stripped alongside `a[target]` | Low | **Fixed** 2026-09-17 | Both allow-lists |
| T3 | CSP fails open when headers are already sent | Low | Open | Preview response |
| T4 | `stripStyleBlocks()` unguarded `preg_replace()` null return | Low | Open | Sanitiser |
| T5 | No tests added; one existing test now fails | **Process** | Open | `tests/phpunit/unit/` |
| T6 | Test suite cannot run on this machine | **Process** | Open | Tooling, blocks T5 |

T1 and T2 are the same defence and should land together. T5 covers all of the above
plus the work already on the branch.

---

## Already resolved on this branch — no action needed

Recorded so we do not re-litigate these. Confirmed by running the report's exact
payload through both sanitisers.

| Reported claim | Resolution | Location |
|---|---|---|
| `stripEvilCode()` re-adds `style` | `style` removed from the allow-list, and style elements stripped wholesale beforehand | `WPML_MessageSanitizer.php:62-113` |
| `get_html_preview_message()` re-adds `style` | Kept **deliberately** — emails need CSS; contained by CSP + sandbox instead | `EmailLogsTab.php:194` |
| `a[target]` intact, `target="_top"` moves the admin window | `a.target` / `a.rel` unset in both allow-lists | `WPML_MessageSanitizer.php:111`, `EmailLogsTab.php:200` |
| Preview iframe has no `sandbox` | Sandboxed via both the attribute and the CSP directive | `BaseRenderer.php:156`, `EmailLogsTab.php:255` |
| CSS `url()` beacon reports the log was opened | `img-src data:` / `font-src data:` until the viewer opts in; `Referrer-Policy: no-referrer` | `EmailLogsTab.php:159-162`, `RemoteContentDetector.php` |

Policy actually emitted with remote content off:

```
default-src 'none'; img-src data:; font-src data:; style-src 'unsafe-inline';
base-uri 'none'; form-action 'none'; frame-ancestors 'self';
sandbox allow-popups allow-popups-to-escape-sandbox
```

Seven `<style>` bypass attempts against the sanitiser (unterminated, comment-split,
nested, `>` inside an attribute, null byte, newline-in-tag, entity-encoded) all
produced inert text, never live CSS.

---

## T1 — `error` field renders attacker markup into the admin document

**Severity: High. Status: Fixed 2026-09-17 — code only, test deferred to T5.**
Files: `src/Renderer/Format/BaseRenderer.php:199-209`

### What is wrong

`render_column_value()` escapes only `subject`, `receiver` and `headers`. Everything
else falls through to `wp_kses_post()`, which allows both `a[target]` and the
`position` / `top` / `left` / `z-index` properties (all present in WordPress's
`safe_style_css` list). The `error` row therefore renders sender-influenced markup
into the admin page itself, where none of the branch's new defences apply — no
sandbox, no CSP, same origin as wp-admin.

This is the reported attack exactly, relocated from `message` to `error`.

### Evidence

Rendering the HTML modal for an entry whose `error` carries markup:

```html
<div class="wp-mail-logging-modal-row-value wp-mail-logging-modal-row-value-error">
  <a href="https://attacker.example/verify" target="_top"
     style="position:fixed;top:0;left:0;width:100%;height:100%;z-index:99999;…">
    Security notice: re-verify now</a>
```

A full-viewport overlay plus one-click top-level navigation out of wp-admin.

### Reachability

`error` is `$wperror->get_error_message()` from the `wp_mail_failed` action
(`WPML_Plugin.php:499`). Under SMTP transport this carries the remote MTA's
rejection text, which is attacker-controlled when the attacker owns the recipient
domain. The column is `VARCHAR(400)` — ample for the payload above.

Note the log **list** already escapes this field (`WPML_Email_Log_List.php:748-756`);
only the modal does not.

### Proposed fix

Invert the allow-list in `render_column_value()`: escape by default, and name the
columns permitted to emit markup — mirroring the `MARKUP_COLUMNS` approach already
adopted in `WPML_Email_Log_List`. Only `attachments` needs markup there.

### Done when

- A hostile `error` value renders escaped in the modal. ✅
- Attachment markup still renders. ✅
- Unit test covers both. — **deferred to T5** (suite is unrunnable here; see T6).

### What landed

**Correction — this entry was marked Fixed on 2026-09-16, but the change was never
in the tree.** `MARKUP_COLUMNS` existed only in `WPML_Email_Log_List.php:44` (the
separate list-screen fix); `BaseRenderer::render_column_value()` still carried the
old `$values_to_escape` allow-list, and `git diff master...HEAD -- BaseRenderer.php`
held only the sandbox and remote-content work. Caught while recording the baseline
videos on 2026-09-16, when the payload reproduced against the supposedly fixed code.
The fix below actually landed **2026-09-17**.

`BaseRenderer::MARKUP_COLUMNS` added (`= [ COLUMN_ATTACHMENTS ]`), and
`render_column_value()` inverted to escape by default. `timestamp`, `host`,
`receiver`, `subject`, `headers` and `error` now all go through `esc_html()`;
only `attachments` keeps `wp_kses_post()`. `message` is unaffected — it routes
to `render_message_value()` and the sandboxed iframe.

Verified with `wp eval-file` driving a real `HTMLRenderer::renderModal()`:

```
live <a target="_top"> in admin doc : no
live position:fixed style           : no
rendered escaped instead            : yes
attachments row: <a target="_blank" href="…/statement.pdf" title="statement.pdf">
                 <span class="dashicons dashicons-pdf"></span></a>
live <a target="_blank"> anchor     : YES (correct)
```

The ledger's exact overlay payload comes back fully entity-escaped, and a real
attachment still renders its anchor. `timestamp`, `receiver` and `subject` were
confirmed escaped in the same run.

---

## T2 — `area[target]` not stripped alongside `a[target]`

**Severity: Low. Status: Fixed 2026-09-17 — code only, test deferred to T5.**
Files: `src/WPML_MessageSanitizer.php:111`, `src/inc/Admin/EmailLogsTab.php:200`

### What is wrong

Both allow-lists `unset( $allowed_html['a']['target'], $allowed_html['a']['rel'] )`
but leave `area[target]` untouched. `area` is in WordPress's post allow-list with
`target => true`, and `map` is allowed too.

### Evidence

```html
<!-- survives both sanitisers unchanged -->
<map name="m"><area shape="rect" coords="0,0,9999,9999"
     href="https://attacker.example/" target="_top"></map>
```

Mooted inside the preview iframe by the sandbox (no `allow-top-navigation`), but it
is an incomplete fix in the same defence, and it is live in any admin-document path.

### Proposed fix

Unset `area.target` in both places, next to the existing `a.target` removal.

### Done when

- The payload above comes back without `target` from both sanitisers. ✅
- Test covers it. — **deferred to T5** (suite is unrunnable here; see T6).

### What landed

Both `unset()` calls extended to cover `area`, alongside the existing `a` entries.
`rel` is unset on `area` too, for symmetry with `a` — WordPress does not currently
allow `area[rel]`, so that half is defensive against the allow-list widening later.

Verified by running the payload through both paths — `WPML_MessageSanitizer::sanitize()`
and `EmailLogsTab::get_html_preview_message()`:

```
area target survived : no
a    target survived : no
area element kept    : yes (only target dropped)
```

Identical output from both. The `<area>` element itself is preserved, so image maps
keep working; only the navigation target is removed.

---

## T3 — CSP fails open when headers are already sent

**Severity: Low. Status: Open.**
Files: `src/inc/Admin/EmailLogsTab.php:158-163`

### What is wrong

All four security headers are inside `if ( ! headers_sent() )`. If another plugin or
a PHP notice has already flushed output, the preview renders with no CSP, no
`nosniff` and no `Referrer-Policy`. The iframe's `sandbox` attribute still applies,
so scripting and top-navigation stay blocked, but remote-content blocking silently
reverts and the tracking beacon works again.

### Proposed fix

Emit a `<meta http-equiv="Content-Security-Policy">` fallback alongside the existing
meta referrer tag. Note that `sandbox` and `frame-ancestors` are ignored in meta
form — `default-src`, `img-src`, `font-src`, `style-src`, `base-uri` and
`form-action` all work, which is enough to keep the beacon blocked.

Decide explicitly whether a headers-already-sent preview should render at all.

### Done when

- With headers pre-sent, the preview still carries a policy that blocks remote images.

---

## T4 — `stripStyleBlocks()` unguarded `preg_replace()` null return

**Severity: Low. Status: Open.**
Files: `src/WPML_MessageSanitizer.php:78-79`

### What is wrong

```php
$part = preg_replace( '#<style\b[^>]*>.*?</style>#is', '', $part );
$parts[ $i ] = preg_replace( '#<style\b[^>]*>.*$#is', '', $part );
```

`preg_replace()` returns `null` at a PCRE backtrack limit, which a large body with a
`<style>` block can reach. The null is then passed straight back in as the subject of
the second call — a PHP 8.1+ "Passing null to parameter" deprecation, the same class
of bug fixed in #227 — and the whole message is silently blanked.

The direction is fail-safe (content dropped, not leaked), but the message disappears
with no explanation.

### Evidence

With `pcre.backtrack_limit` lowered, `sanitize()` returns `''` for a body whose
content should have survived.

`RemoteContentDetector::strip_hidden_markup()` already guards exactly this
(`RemoteContentDetector.php:69-73`); the sanitiser does not. Worth making consistent.

### Proposed fix

Keep the un-stripped segment when `preg_replace()` returns `null`, matching the
detector's existing pattern.

### Done when

- A body that trips the backtrack limit still renders.
- No deprecation is raised.

---

## T5 — No tests added; one existing test now fails

**Severity: Process. Status: Open.**
Files: `tests/phpunit/unit/WPML_MessageSanitizer_Test.php:74-76`, `WPML_MailRenderer_Test.php:256-260`

### What is wrong

`git diff master...HEAD -- tests/` is **empty**. The branch changed the sanitiser,
both allow-lists, the log list escaping, the column renderers and added a new class,
with no test changes.

CLAUDE.md is explicit: *"Any edit to renderer output must preserve escaping — add
tests in `tests/phpunit/unit/WPML_MessageSanitizer_Test.php` / `WPML_MailRenderer_Test.php`
when touching sanitization."*

### Evidence

`WPML_MessageSanitizer_Test.php:74-76` asserts the old behaviour:

```
test expects  : '<style>body {background-color: red;}</style>'
branch returns: ''
=> existing unit test FAILS on this branch
```

`WPML_MailRenderer_Test.php:256-260` carries the same fixture and needs the same
review.

### Proposed fix

Update the two stale fixtures to the intended behaviour, then add regression tests
for: style-block stripping (including the bypass cases), `a.target` / `area.target`
removal, the CSP builder's fail-closed path, `RemoteContentDetector`, and T1's
escaping.

### Done when

- `vendor/bin/phpunit` runs green. (`composer install` first — `vendor/bin/` is
  currently empty on this machine.)

---

## T6 — test suite cannot run on this machine

**Severity: Process. Status: Open. Blocks T5.**

### What is wrong

Three separate blockers, found while starting T1:

1. `vendor/` is absent — `composer install` has never run here.
2. PHPUnit is not in `composer.json` at all, and the tests extend
   `\PHPUnit_Framework_TestCase` (PHPUnit ≤ 5.7). Local PHP is 8.2, which that
   PHPUnit generation does not support.
3. `tests/phpunit/includes/bootstrap.php` boots the full WordPress test harness
   for *every* suite, including `tests/phpunit/unit/`. Neither
   `/tmp/wordpress-tests-lib` nor the `phpunit.xml`-hardcoded
   `/srv/www/wordpress-develop/public_html/tests/phpunit/` exists.

So the “unit” tests are not unit tests in the runnable sense — they need a
database and a WordPress install. Fixing T5 means deciding whether to modernise
PHPUnit, split genuinely-isolated unit tests away from the WP harness, or both.

### Done when

- `vendor/bin/phpunit` exists and runs the suite on the local PHP.

---

## Residual by design — not a task

A sender can still draw a convincing fake notice **inside** the 598×320 preview
iframe, and one click opens their site in a new tab. This cannot be closed by
filtering without breaking HTML email preview altogether. It is contained — no
scripting, no top-level navigation, no referrer, opaque origin, attacker URL visible
in the new tab's address bar — but it is not eliminated.

Worth stating plainly if we reply to the reporter: it is the half of their scenario
that survives, and the containment is the answer to it.

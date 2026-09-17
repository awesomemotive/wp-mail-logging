# Email details popup: header formatting

Date: 2026-09-17
Status: Implemented; runtime checks passed. Full legacy PHPUnit and browser checks unavailable in this environment.

## Problem and evidence

The email details popup displays literal newline sequences in its Headers value:

```text
Content-Type: text/html,\nReply-to: theevoeon.com <donmhico@gmail.com>
```

`WPML_MailExtractor::joinArrayWithCommaAndNewLine()` joins array entries with the
literal string `,\n`. A read-only PHP reproduction using the reported headers
produced one literal backslash-n sequence and zero actual newlines.
`BaseRenderer::render_column_value()` escapes Headers without formatting those
separators. The existing Receiver display already normalizes its stored separators.

## Approved behavior

Display each header on its own line in the email details popup:

```text
Content-Type: text/html
Reply-to: theevoeon.com <donmhico@gmail.com>
```

Apply this formatting to the Headers row shared by the HTML and Raw popup views.
Existing and newly logged emails receive the same display formatting. Preserve
header order, spelling, addresses, angle brackets, and commas within header values.
Long headers may continue to wrap naturally in the popup.

## Approaches considered

1. **Format at display time (selected).** A focused popup renderer change handles
   existing records immediately and keeps the persisted format compatible.
2. **Change the stored format.** Requires compatibility handling for existing rows
   and review of storage consumers. This exceeds the scope of the display issue.

## Rendering design

Keep the change local to the Headers output in
`src/Renderer/Format/BaseRenderer.php`. Normalize the stored header text to real
line breaks before escaping it, then render those breaks with `nl2br()` applied
after `esc_html()`. This uses the same escaping-then-line-break pattern as the
existing Raw message display and needs no CSS or JavaScript changes.

Recognize the literal legacy separators `,\n` and `,\r\n`, removing their separator
comma. Also handle literal `\n` and `\r\n` and actual LF/CRLF line endings. Keep
other commas and backslashes intact; do not use a general escape decoder or split
on ordinary commas. Retain leading whitespace on continuation lines and do not
parse or rewrite header values.

Escape header content once before adding renderer-generated line-break markup.
Headers remain outside `MARKUP_COLUMNS`; email-supplied tags must display as text.
Empty or null Headers continue to omit the row through the existing modal logic.

Storage, extraction, the shared `WPML_Utils::clean_headers()` parser, resending,
JSON output, the log table, and other popup fields retain their current behavior.
No database migration is needed.

## Verification

Add focused popup-rendering regression coverage in
`tests/phpunit/unit/WPML_MailRenderer_Test.php` for:

- The reported example showing two lines without a visible separator comma or
  literal backslash-n, in both HTML and Raw views.
- Legacy literal LF/CRLF separators and actual LF/CRLF line endings.
- A single header, empty/null Headers, and long values.
- Quoted display names containing commas, multiple addresses in one header,
  angle-bracket addresses, and unrelated literal backslashes.
- Header-supplied HTML remaining escaped, with only renderer-generated line breaks
  appearing as markup.

Run the relevant renderer, extractor, and resender checks using the available test
environment. Inspect the popup in the local browser to confirm separate lines and
readable wrapping in both views. Record any unavailable checks explicitly.

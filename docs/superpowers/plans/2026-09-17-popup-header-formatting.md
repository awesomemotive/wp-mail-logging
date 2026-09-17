# Popup Header Formatting Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Show each email header on its own line in the email details popup, including existing logs, while preserving safe text rendering.

**Architecture:** Normalize the legacy separator strings only when the shared popup renderer outputs Headers. Escape the resulting text, then insert line-break markup; leave storage and resending intact.

**Tech Stack:** PHP, WordPress escaping functions, legacy PHPUnit tests, the local WordPress site for runtime and browser checks.

---

## Scope and files

Approved spec: `docs/superpowers/specs/2026-09-17-popup-header-formatting-design.md`.

- Modify `src/Renderer/Format/BaseRenderer.php`: the output branch inside `render_column_value()` formats Headers for both HTML and Raw popup views.
- Modify `tests/phpunit/unit/WPML_MailRenderer_Test.php`: exercise popup rendering through the public format factory and inspect its HTML DOM.
- Temporary runtime check: `/tmp/wpml-popup-headers-check.php`, used only if the legacy suite cannot run locally.

There are no schema, extractor, resend, shared parser, JSON, CSS, or JavaScript changes. Keep unrelated untracked files untouched. Use the selected execution skill and its workspace-isolation workflow before implementation.

## Known test environment

At planning time, `vendor/bin/phpunit` and both the default and configured WordPress test harness directories are absent. `vendor/autoload.php`, a global `phpunit`, WP-CLI, and `/Users/mico/Valet/wpml/wp-load.php` exist. Existing project notes report that the global PHPUnit is incompatible with local PHP 8.2; verify that before relying on it.

`phpunit.xml` forces `WP_TESTS_DIR` to `/srv/www/wordpress-develop/public_html/tests/phpunit/`, so an environment-variable override alone does not replace that setting. Do not broaden this fix into upgrading the legacy test infrastructure. Report unavailable suite checks and use the focused runtime and browser checks below.

## Task 1: Add the regression coverage and reproduce the failure

**Modify:** `tests/phpunit/unit/WPML_MailRenderer_Test.php`.

- [ ] **Step 1: Establish the available runner.**

```sh
php --version
phpunit --version
test -x vendor/bin/phpunit
test -f /srv/www/wordpress-develop/public_html/tests/phpunit/includes/bootstrap.php
```

Record missing tools or compatibility errors. An unavailable runner is a test limitation, not evidence that the regression fails correctly.

- [ ] **Step 2: Add these methods inside `WPML_MailRenderer_Test`.**

The test drives the actual popup renderer. Checking the number of `<br>` elements distinguishes visible line breaks from newlines that HTML would collapse. DOM text assertions cover escaping without duplicating the normalization algorithm.

```php
/**
 * Verify header formatting in both popup views.
 *
 * @access public
 * @dataProvider popup_headers_provider
 * @param string|null $headers Stored headers.
 * @param string|null $expected Expected visible header text, or no row.
 * @return void
 */
public function test_popup_headers_display( $headers, $expected ) {

	$previous_post = $_POST;

	try {
		foreach ( [ 'html', 'raw' ] as $format ) {
			$_POST['format'] = $format;
			$renderer = \No3x\WPML\Renderer\Format\MailRendererFactory::factory( $format );
			$html = $renderer->renderModal( [ 'headers' => $headers, 'message' => '' ] );
			$document = new \DOMDocument();
			$document->loadHTML( $html );
			$xpath = new \DOMXPath( $document );
			$values = $xpath->query( '//div[contains(concat(" ", normalize-space(@class), " "), " wp-mail-logging-modal-row-value-headers ")]' );

			if ( $expected === null ) {
				$this->assertSame( 0, $values->length, $format );
				continue;
			}

			$this->assertSame( 1, $values->length, $format );
			$value = $values->item( 0 );
			$this->assertSame( $expected, trim( $value->textContent ), $format );
			$this->assertSame( substr_count( $expected, "\n" ), $value->getElementsByTagName( 'br' )->length, $format );
			$this->assertSame( 0, $xpath->query( './/*[not(self::br)]', $value )->length, $format );
			$this->assertSame( 0, $xpath->query( '//script | //img' )->length, $format );
		}
	} finally {
		$_POST = $previous_post;
	}
}

/**
 * Supply stored header formats and expected popup text.
 *
 * @access public
 * @return array
 */
public function popup_headers_provider() {

	$first = 'Content-Type: text/html';
	$second = 'Reply-to: theevoeon.com <donmhico@gmail.com>';
	$expected = $first . "\n" . $second;
	$long = 'X-Long: ' . str_repeat( 'a', 400 );

	return [
		'legacy LF' => [ $first . ',\n' . $second, $expected ],
		'legacy CRLF' => [ $first . ',\r\n' . $second, $expected ],
		'literal LF' => [ $first . '\n' . $second, $expected ],
		'literal CRLF' => [ $first . '\r\n' . $second, $expected ],
		'actual LF' => [ $expected, $expected ],
		'actual CRLF' => [ $first . "\r\n" . $second, $expected ],
		'single header' => [ $first, $first ],
		'empty' => [ '', null ],
		'null' => [ null, null ],
		'long header' => [ $long, $long ],
		'values and escapes' => [
			'Cc: "Doe, Jane" <jane@example.com>, bob@example.com,\nX-Notes: Q&A C:\\Temp\\Report',
			"Cc: \"Doe, Jane\" <jane@example.com>, bob@example.com\nX-Notes: Q&A C:\\Temp\\Report",
		],
		'continuation and value comma' => [
			"Cc: jane@example.com,\r\n bob@example.com",
			"Cc: jane@example.com,\n bob@example.com",
		],
		'header markup' => [
			'X-Test: <script>alert(1)</script>,\nX-Image: <img src=x onerror=alert(1)>',
			"X-Test: <script>alert(1)</script>\nX-Image: <img src=x onerror=alert(1)>",
		],
	];
}
```

- [ ] **Step 3: Run the focused regression before changing production code.**

With a compatible installed runner and WordPress test harness:

```sh
vendor/bin/phpunit --filter test_popup_headers_display tests/phpunit/unit/WPML_MailRenderer_Test.php
```

Expected: the legacy separator cases fail because literal `,\n` remains in the DOM text; real LF cases fail the visible line-break count. If the suite cannot start, run Task 3's focused runtime check now and capture its expected header-formatting failure instead.

## Task 2: Format Headers at the popup output boundary

**Modify:** `src/Renderer/Format/BaseRenderer.php`, `render_column_value()`.

- [ ] **Step 1: Replace the existing final output branch with this branch.**

```php
// These values, including untrusted SMTP errors, render outside the sandbox.
if ( in_array( $key, self::MARKUP_COLUMNS, true ) ) {
	echo wp_kses_post( $value );
} elseif ( $key === WPML_ColumnManager::COLUMN_HEADERS ) {
	// Normalize legacy separators for display without changing stored headers.
	$value = str_replace(
		[ ',\r\n', ',\n', '\r\n', '\n', "\r\n" ],
		"\n",
		$value
	);
	echo nl2br( esc_html( $value ) );
} else {
	echo esc_html( $value );
}
```

Longer literal separators appear first to avoid partially replacing them. Actual comma-plus-newline combinations retain their comma, since that comma may belong to a folded address list. Only the recognized literal legacy separators lose their comma. Empty/null values never reach this branch through the modal loop.

- [ ] **Step 2: Run syntax checks and repeat the focused regression.**

```sh
php -l src/Renderer/Format/BaseRenderer.php
php -l tests/phpunit/unit/WPML_MailRenderer_Test.php
vendor/bin/phpunit --filter test_popup_headers_display tests/phpunit/unit/WPML_MailRenderer_Test.php
git diff --check
```

Expected: syntax and whitespace checks pass, and all focused cases pass in a compatible harness. If the suite is unavailable, repeat Task 3's runtime check and report the suite limitation separately.

## Task 3: Verify runtime behavior and the popup

**Temporary file:** `/tmp/wpml-popup-headers-check.php`.

- [ ] **Step 1: When needed, create this focused check for the local WordPress runtime.**

This renders synthetic data through the real plugin and real WordPress escaping functions. It does not call `wp_mail()`, resend messages, or create log records. Run it both before and after Task 2 if PHPUnit is unavailable.

```php
<?php
use No3x\WPML\Renderer\Format\MailRendererFactory;

$cases = [
	[
		'Content-Type: text/html,\nReply-to: theevoeon.com <donmhico@gmail.com>',
		"Content-Type: text/html\nReply-to: theevoeon.com <donmhico@gmail.com>",
	],
	[
		'Cc: "Doe, Jane" <jane@example.com>, bob@example.com,\r\nX-Notes: Q&A C:\\Temp\\Report',
		"Cc: \"Doe, Jane\" <jane@example.com>, bob@example.com\nX-Notes: Q&A C:\\Temp\\Report",
	],
	[
		'X-Test: <script>alert(1)</script>,\nX-Image: <img src=x onerror=alert(1)>',
		"X-Test: <script>alert(1)</script>\nX-Image: <img src=x onerror=alert(1)>",
	],
];
$previous_post = $_POST;
$checks = 0;
try {
	foreach ( [ 'html', 'raw' ] as $format ) {
		$_POST['format'] = $format;
		foreach ( $cases as $case ) {
			$html = MailRendererFactory::factory( $format )->renderModal(
				[ 'headers' => $case[0], 'message' => '' ]
			);
			$document = new DOMDocument();
			$document->loadHTML( $html );
			$xpath = new DOMXPath( $document );
			$value = $xpath->query( '//div[contains(concat(" ", normalize-space(@class), " "), " wp-mail-logging-modal-row-value-headers ")]' )->item( 0 );
			if ( ! $value || trim( $value->textContent ) !== $case[1] ||
				$value->getElementsByTagName( 'br' )->length !== 1 ||
				$xpath->query( './/*[not(self::br)]', $value )->length !== 0 ||
				$xpath->query( '//script | //img' )->length !== 0 ) {
				throw new RuntimeException( 'Popup header formatting failed: ' . $format );
			}
			++$checks;
		}
	}
} finally {
	$_POST = $previous_post;
}
echo "PASS: {$checks} popup header runtime checks.\n";
```

Run from the plugin workspace:

```sh
wp --path=/Users/mico/Valet/wpml eval-file /tmp/wpml-popup-headers-check.php
```

Expected before the fix: `Popup header formatting failed: html`. Expected after the fix: `PASS: 6 popup header runtime checks.` Confirm the local site is loading the checkout that contains the change before relying on this result; a separate worktree is not automatically served by `wpml.test`.

- [ ] **Step 2: Run the relevant existing suite checks where available.**

```sh
vendor/bin/phpunit --filter 'WPML_MailRenderer_Test|WPML_MailExtractor_Test|WPML_Email_Resender_Test|WPML_ReceiverColumn_Test' tests/phpunit/unit
```

Expected: no regressions relative to the baseline. Existing tests include old expectations; distinguish unchanged baseline failures from failures introduced by this fix. Do not repair unrelated tests as part of this task. If the runner cannot start, record that explicitly instead of claiming the suite passed.

- [ ] **Step 3: Inspect the email details popup in the browser.**

Load the `browser-use:browser` skill. Open the existing local log reported by the user at `wpml.test`, and inspect Headers in both HTML and Raw popup views. Confirm that the example appears on two lines with its address visible, no literal separator, and readable wrapping at a narrower window width. For the long-value check, use an existing suitable log or a temporary rendered HTML fixture; do not send an email solely to create a test record.

- [ ] **Step 4: Review and commit the focused fix.**

```sh
git diff --check
git diff -- src/Renderer/Format/BaseRenderer.php tests/phpunit/unit/WPML_MailRenderer_Test.php
git add -- src/Renderer/Format/BaseRenderer.php tests/phpunit/unit/WPML_MailRenderer_Test.php
git commit -m "fix: display popup email headers on separate lines"
```

Confirm the diff preserves the escaped default branch, keeps Headers outside `MARKUP_COLUMNS`, and changes no stored data or resend behavior. Report the verified outcome and any unavailable PHPUnit checks.

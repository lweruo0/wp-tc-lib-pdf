<?php
/**
 * Prüft, dass die Engine (include/) keine Host-Funktionen aufruft.
 *
 * Die Engine muss in WordPress und Admidio laufen (KONZEPT-PLATTFORM.md).
 * Host-Zugriffe gehören in die Plattform (PdfRuntime::platform()) oder in
 * wp-include/ bzw. adm-include/.
 *
 * Aufruf:  php tools/check-engine-purity.php
 * Ergebnis: Exit-Code 0 = sauber, 1 = Verstöße (Liste auf STDOUT).
 *
 * Geprüft werden Funktionsaufrufe, Variablen und Konstanten per Tokenizer;
 * Kommentare und Methodenaufrufe ($obj->wp_x(), Klasse::wp_x()) zählen nicht.
 */

declare(strict_types=1);

// Nur auf der Kommandozeile; nie über den Webserver ausführen.
if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

$root      = dirname(__DIR__);
$engineDir = $root . '/include';

/** Verbotene Funktionen (regulärer Ausdruck auf den Funktionsnamen, ohne Groß/Klein). */
$forbiddenFunctions = '/^(wp_\w+|get_option|update_option|delete_option|get_site_option|add_action|add_filter|do_action|apply_filters|remove_action|remove_filter'
	. '|esc_\w+|sanitize_\w+|__|_e|_x|_n|_nx|esc_html__|is_admin|current_user_can|is_user_logged_in|get_current_user_id'
	. '|home_url|site_url|admin_url|plugins_url|plugin_dir_path|plugin_dir_url|get_bloginfo|current_time|date_i18n|absint'
	. '|admFuncVariableIsValid|admFunc\w+|isUserAuthorized|date_default_timezone_set)$/i';

/** Verbotene Variablen (Superglobals mit Benutzereingaben, Host-Globals). */
$forbiddenVariables = '/^\$(_GET|_POST|_REQUEST|_COOKIE|_SESSION|wpdb|wp_query|post|g[A-Z]\w*)$/';

/** Verbotene Konstanten. */
$forbiddenConstants = '/^(ABSPATH|WPINC|WP_CONTENT_DIR|WP_PLUGIN_DIR|ADMIDIO_PATH|ADMIDIO_URL|FOLDER_DATA|FOLDER_PLUGINS|FOLDER_SYSTEM)$/';

/**
 * Bekannte Ausnahmen, die noch abgebaut werden: Datei => [Name => Anzahl].
 * Jede Ausnahme hat ein Ziel; ist sie behoben, hier löschen.
 */
$knownExceptions = [];

$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($engineDir, FilesystemIterator::SKIP_DOTS));

$violations = [];
$seen       = [];

foreach ($files as $file) {
	if ($file->getExtension() !== 'php') {
		continue;
	}
	$path     = $file->getPathname();
	$relative = str_replace('\\', '/', substr($path, strlen($root) + 1));
	$tokens   = token_get_all((string) file_get_contents($path));
	$count    = count($tokens);

	for ($i = 0; $i < $count; $i++) {
		$token = $tokens[$i];
		if (!is_array($token)) {
			continue;
		}
		[$type, $text, $line] = $token;

		if ($type === T_VARIABLE && preg_match($forbiddenVariables, $text)) {
			$violations[] = [$relative, $line, $text, 'Variable'];
			continue;
		}

		if ($type !== T_STRING && $type !== T_NAME_FULLY_QUALIFIED) {
			continue;
		}
		$name = ltrim($text, '\\');

		// Vorheriges und nächstes bedeutsames Token.
		$prev = null;
		for ($p = $i - 1; $p >= 0; $p--) {
			if (is_array($tokens[$p]) && in_array($tokens[$p][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
				continue;
			}
			$prev = $tokens[$p];
			break;
		}
		$next = null;
		for ($n = $i + 1; $n < $count; $n++) {
			if (is_array($tokens[$n]) && in_array($tokens[$n][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
				continue;
			}
			$next = $tokens[$n];
			break;
		}

		$prevType = is_array($prev) ? $prev[0] : $prev;
		$isMember = in_array($prevType, [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_CONST, T_NEW], true);

		if ($next === '(' && !$isMember) {
			if (preg_match($forbiddenFunctions, $name)) {
				$violations[] = [$relative, $line, $name, 'Funktion'];
			}
			continue;
		}

		// defined('ABSPATH') u. ä. stehen als String, nicht als Konstante – nur echte Konstanten prüfen.
		if (!$isMember && $next !== '(' && preg_match($forbiddenConstants, $name)) {
			$violations[] = [$relative, $line, $name, 'Konstante'];
		}
	}
}

// Bekannte Ausnahmen abziehen.
$remaining = [];
$usedExceptions = [];
foreach ($violations as $v) {
	[$file, , $name] = $v;
	$allowed = $knownExceptions[$file][$name] ?? 0;
	$used    = $usedExceptions[$file][$name] ?? 0;
	if ($used < $allowed) {
		$usedExceptions[$file][$name] = $used + 1;
		continue;
	}
	$remaining[] = $v;
}

// Veraltete Ausnahmen melden (Ziel erreicht, Eintrag kann weg).
$stale = [];
foreach ($knownExceptions as $file => $names) {
	foreach ($names as $name => $allowed) {
		$used = $usedExceptions[$file][$name] ?? 0;
		if ($used < $allowed) {
			$stale[] = "{$file}: {$name} erlaubt {$allowed}, gefunden {$used}";
		}
	}
}

foreach ($stale as $s) {
	fwrite(STDOUT, "Hinweis: Ausnahme nicht mehr nötig, bitte aus \$knownExceptions entfernen: {$s}\n");
}

if ($remaining === []) {
	$known = array_sum(array_map('array_sum', $usedExceptions));
	fwrite(STDOUT, "Engine sauber: keine Host-Aufrufe in include/" . ($known > 0 ? " (bekannte Ausnahmen: {$known})" : '') . ".\n");
	exit(0);
}

fwrite(STDOUT, "Host-Aufrufe in der Engine (include/):\n");
foreach ($remaining as [$file, $line, $name, $kind]) {
	fwrite(STDOUT, "  {$file}:{$line}  {$kind} {$name}\n");
}
fwrite(STDOUT, count($remaining) . " Verstoß/Verstöße. Host-Zugriffe über PdfRuntime::platform() oder in wp-include/ bzw. adm-include/.\n");
exit(1);

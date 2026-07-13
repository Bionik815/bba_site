<?php
/**
 * Plugin Name: Barebones Preview Gate
 * Description: Simple password gate for non-public preview environments.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function bba_preview_gate_config( string $key, $default = null ) {
	$constant = 'BBA_PREVIEW_GATE_' . strtoupper( $key );

	if ( defined( $constant ) ) {
		return constant( $constant );
	}

	$value = getenv( $constant );
	if ( false !== $value && '' !== $value ) {
		return $value;
	}

	return $default;
}

function bba_preview_gate_enabled(): bool {
	$value = bba_preview_gate_config( 'enabled', false );

	if ( is_bool( $value ) ) {
		return $value;
	}

	return in_array( strtolower( (string) $value ), [ '1', 'true', 'yes', 'on' ], true );
}

function bba_preview_gate_cookie_name(): string {
	return 'bba_preview_gate';
}

function bba_preview_gate_cookie_value(): string {
	$username = (string) bba_preview_gate_config( 'username', 'preview' );
	$password = (string) bba_preview_gate_config( 'password', '' );
	$seed     = $username . '|' . $password . '|' . AUTH_SALT;

	return hash( 'sha256', $seed );
}

function bba_preview_gate_is_public_request(): bool {
	$uri = $_SERVER['REQUEST_URI'] ?? '/';

	// NOTE: /wp-json and /xmlrpc.php are intentionally NOT exempt. On a private
	// preview they would otherwise leak content/user enumeration via REST and
	// allow XML-RPC brute force to anonymous visitors. Logged-in users and valid
	// gate cookies still pass via bba_preview_gate_is_authenticated().
	$allowed_prefixes = [
		'/wp-admin',
		'/wp-login.php',
		'/wp-cron.php',
		'/ops/',
	];

	foreach ( $allowed_prefixes as $prefix ) {
		if ( 0 === strpos( $uri, $prefix ) ) {
			return false;
		}
	}

	return true;
}

function bba_preview_gate_is_authenticated(): bool {
	if ( is_user_logged_in() ) {
		return true;
	}

	$cookie = $_COOKIE[ bba_preview_gate_cookie_name() ] ?? '';

	return is_string( $cookie ) && hash_equals( bba_preview_gate_cookie_value(), $cookie );
}

function bba_preview_gate_set_cookie(): void {
	$secure = is_ssl();

	setcookie(
		bba_preview_gate_cookie_name(),
		bba_preview_gate_cookie_value(),
		[
			'expires'  => time() + DAY_IN_SECONDS * 14,
			'path'     => '/',
			'secure'   => $secure,
			'httponly' => true,
			'samesite' => 'Lax',
		]
	);
}

function bba_preview_gate_clear_cookie(): void {
	setcookie(
		bba_preview_gate_cookie_name(),
		'',
		[
			'expires'  => time() - HOUR_IN_SECONDS,
			'path'     => '/',
			'httponly' => true,
			'samesite' => 'Lax',
		]
	);
}

function bba_preview_gate_render(string $message = ''): void {
	status_header( 200 );
	header( 'Content-Type: text/html; charset=utf-8' );
	header( 'X-Robots-Tag: noindex, nofollow, noarchive', true );

	$current_url = home_url( add_query_arg( [] ) );
	$username    = esc_attr( (string) bba_preview_gate_config( 'username', 'preview' ) );
	$message     = trim( $message );
	$error_html  = $message ? '<p style="color:#a61b1b;margin:0 0 16px;">' . esc_html( $message ) . '</p>' : '';

	echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
	echo '<title>Barebones Preview</title>';
	echo '<style>body{margin:0;font-family:Arial,sans-serif;background:#f3efe7;color:#1f1a17;display:grid;min-height:100vh;place-items:center;padding:24px}.card{width:min(420px,100%);background:#fff;border-radius:18px;padding:28px;box-shadow:0 18px 50px rgba(31,26,23,.12)}h1{margin:0 0 10px;font-size:28px}p{line-height:1.5}label{display:block;font-size:14px;font-weight:700;margin:18px 0 6px}input{width:100%;box-sizing:border-box;padding:12px 14px;border:1px solid #cfc6bb;border-radius:10px;font-size:16px}button{margin-top:18px;width:100%;border:0;border-radius:999px;background:#1f1a17;color:#fff;padding:13px 16px;font-size:16px;font-weight:700;cursor:pointer}</style>';
	echo '</head><body><main class="card"><h1>Private Preview</h1><p>This Barebones Apparel preview is currently password protected for internal review.</p>';
	echo $error_html;
	echo '<form method="post">';
	wp_nonce_field( 'bba_preview_gate', '_bba_preview_nonce' );
	echo '<input type="hidden" name="_bba_preview_redirect" value="' . esc_attr( $current_url ) . '">';
	echo '<label for="bba-preview-username">Username</label><input id="bba-preview-username" name="bba_preview_username" type="text" value="' . $username . '" autocomplete="username" required>';
	echo '<label for="bba-preview-password">Password</label><input id="bba-preview-password" name="bba_preview_password" type="password" autocomplete="current-password" required>';
	echo '<button type="submit">Enter Preview</button></form></main></body></html>';
	exit;
}

function bba_preview_gate_boot(): void {
	// WP-CLI / cron have no browser to gate — bail so remote tooling works.
	if ( ( defined( 'WP_CLI' ) && WP_CLI ) || ( defined( 'DOING_CRON' ) && DOING_CRON ) || PHP_SAPI === 'cli' ) {
		return;
	}

	if ( ! bba_preview_gate_enabled() || ! bba_preview_gate_is_public_request() ) {
		return;
	}

	if ( isset( $_GET['preview_logout'] ) ) {
		bba_preview_gate_clear_cookie();
	}

	if ( bba_preview_gate_is_authenticated() ) {
		return;
	}

	$expected_username = (string) bba_preview_gate_config( 'username', 'preview' );
	$expected_password = (string) bba_preview_gate_config( 'password', '' );

	if ( '' === $expected_password ) {
		return;
	}

	if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) {
		$nonce_ok = isset( $_POST['_bba_preview_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_bba_preview_nonce'] ) ), 'bba_preview_gate' );
		$username = isset( $_POST['bba_preview_username'] ) ? sanitize_text_field( wp_unslash( $_POST['bba_preview_username'] ) ) : '';
		$password = isset( $_POST['bba_preview_password'] ) ? (string) wp_unslash( $_POST['bba_preview_password'] ) : '';

		if ( $nonce_ok && hash_equals( $expected_username, $username ) && hash_equals( $expected_password, $password ) ) {
			bba_preview_gate_set_cookie();

			$redirect = isset( $_POST['_bba_preview_redirect'] ) ? esc_url_raw( wp_unslash( $_POST['_bba_preview_redirect'] ) ) : home_url( '/' );
			wp_safe_redirect( $redirect ?: home_url( '/' ) );
			exit;
		}

		bba_preview_gate_render( 'That username or password did not match.' );
	}

	bba_preview_gate_render();
}

add_action( 'init', 'bba_preview_gate_boot', 0 );

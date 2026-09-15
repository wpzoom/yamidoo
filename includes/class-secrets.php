<?php
/**
 * Secrets at rest.
 *
 * The connect token and the customer-lookup secret are encrypted before they
 * are written to the options table, with WordPress-bundled libsodium and a key
 * derived from this installation's auth salt. A database dump on its own does
 * not reveal them. Values written by older versions (plain text) still read
 * fine and are re-encrypted on the next save.
 *
 * @package Yamidoo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Yamidoo_Secrets
 */
class Yamidoo_Secrets {

	/**
	 * Storage prefix that marks an encrypted value.
	 */
	const PREFIX = 'v1:';

	/**
	 * Encrypt a secret for storage. Returns the plain value when libsodium is
	 * unavailable, so the plugin keeps working on exotic hosts.
	 *
	 * @param string $plaintext Secret.
	 * @return string
	 */
	public static function encrypt( $plaintext ) {
		$plaintext = (string) $plaintext;
		if ( '' === $plaintext || self::is_encrypted( $plaintext ) || ! self::available() ) {
			return $plaintext;
		}
		try {
			$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			$box   = sodium_crypto_secretbox( $plaintext, $nonce, self::key() );
		} catch ( Throwable $e ) {
			return $plaintext;
		}
		return self::PREFIX . base64_encode( $nonce . $box ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Decrypt a stored value. Plain (legacy) values pass through unchanged; an
	 * undecryptable value (salts rotated) yields '' so the owner reconnects.
	 *
	 * @param string $stored Stored value.
	 * @return string
	 */
	public static function decrypt( $stored ) {
		$stored = (string) $stored;
		if ( ! self::is_encrypted( $stored ) ) {
			return $stored;
		}
		if ( ! self::available() ) {
			return '';
		}
		$raw = base64_decode( substr( $stored, strlen( self::PREFIX ) ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		if ( false === $raw || strlen( $raw ) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES ) {
			return '';
		}
		try {
			$plain = sodium_crypto_secretbox_open(
				substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ),
				substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ),
				self::key()
			);
		} catch ( Throwable $e ) {
			return '';
		}
		return false === $plain ? '' : $plain;
	}

	/**
	 * Whether a stored value carries the encrypted marker.
	 *
	 * @param string $value Stored value.
	 * @return bool
	 */
	public static function is_encrypted( $value ) {
		return 0 === strpos( (string) $value, self::PREFIX );
	}

	/**
	 * libsodium (native or WordPress's polyfill) present.
	 *
	 * @return bool
	 */
	private static function available() {
		return function_exists( 'sodium_crypto_secretbox' )
			&& function_exists( 'sodium_crypto_secretbox_open' )
			&& defined( 'SODIUM_CRYPTO_SECRETBOX_NONCEBYTES' )
			&& defined( 'SODIUM_CRYPTO_SECRETBOX_MACBYTES' );
	}

	/**
	 * Installation-specific 32-byte key.
	 *
	 * @return string
	 */
	private static function key() {
		return hash( 'sha256', wp_salt( 'auth' ) . '|yamidoo', true );
	}
}

<?php
/**
 * Encrypted storage for optional service credentials.
 *
 * @package Power_Schedule_Manager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Protects credentials entered in the WordPress administration area.
 *
 * Server constants always take precedence. Stored values are encrypted and
 * never returned to an HTML form.
 */
final class Power_Schedule_Manager_Secrets {

	private const string SODIUM_PREFIX = 's1:';

	private const string OPENSSL_PREFIX = 'o1:';

	/**
	 * Resolve a credential, preferring immutable server configuration.
	 */
	public static function resolve(
		string $constant_name,
		string $encrypted_value
	): string {
		$server_value = self::server_value( $constant_name );

		if ( '' !== $server_value ) {
			return $server_value;
		}

		return self::decrypt( $encrypted_value );
	}

	/**
	 * Describe where the active credential comes from.
	 *
	 * @return 'environment'|'admin'|'missing'
	 */
	public static function source(
		string $constant_name,
		string $encrypted_value
	): string {
		if ( '' !== self::server_value( $constant_name ) ) {
			return 'environment';
		}

		return '' !== self::decrypt( $encrypted_value )
			? 'admin'
			: 'missing';
	}

	/**
	 * Read a secret from wp-config, an environment variable, or Docker Secrets.
	 *
	 * Docker/Coolify may expose a secret through CONSTANT_NAME_FILE or mount it
	 * at /run/secrets/psm_<short_name>. The value is read only at runtime and is
	 * never copied into the plugin options table.
	 */
	private static function server_value( string $constant_name ): string {
		if ( defined( $constant_name ) ) {
			$value = constant( $constant_name );

			if ( is_scalar( $value ) && '' !== trim( (string) $value ) ) {
				return trim( (string) $value );
			}
		}

		$environment_value = getenv( $constant_name );
		if (
			false !== $environment_value
			&& '' !== trim( (string) $environment_value )
		) {
			return trim( (string) $environment_value );
		}

		$file_candidates = array();
		$environment_file = getenv( $constant_name . '_FILE' );
		if (
			false !== $environment_file
			&& '' !== trim( (string) $environment_file )
		) {
			$file_candidates[] = trim( (string) $environment_file );
		}

		$lower_name = strtolower( $constant_name );
		$file_candidates[] = '/run/secrets/' . $lower_name;

		$prefix = 'POWER_SCHEDULE_MANAGER_';
		if ( str_starts_with( $constant_name, $prefix ) ) {
			$file_candidates[] = '/run/secrets/psm_'
				. strtolower( substr( $constant_name, strlen( $prefix ) ) );
		}

		foreach ( array_unique( $file_candidates ) as $file_path ) {
			if (
				! is_string( $file_path )
				|| ! is_readable( $file_path )
			) {
				continue;
			}

			$file_size = filesize( $file_path );
			if (
				false === $file_size
				|| $file_size < 1
				|| $file_size > 8192
			) {
				continue;
			}

			$value = file_get_contents( $file_path );
			if ( false !== $value && '' !== trim( $value ) ) {
				return trim( $value );
			}
		}

		return '';
	}

	/**
	 * Preserve, replace, or remove an encrypted setting.
	 *
	 * @return string|WP_Error
	 */
	public static function update(
		mixed $submitted_value,
		string $existing_value,
		bool $clear
	): string|WP_Error {
		if ( $clear ) {
			return '';
		}

		if ( ! is_scalar( $submitted_value ) ) {
			return $existing_value;
		}

		$new_value = trim( wp_unslash( (string) $submitted_value ) );

		if ( '' === $new_value ) {
			return $existing_value;
		}

		if ( strlen( $new_value ) > 4096 ) {
			return new WP_Error(
				'power_schedule_manager_secret_too_long',
				__(
					'Khóa API vượt quá độ dài an toàn cho phép.',
					'power-schedule-manager'
				)
			);
		}

		return self::encrypt( $new_value );
	}

	/**
	 * Encrypt a secret with authenticated encryption.
	 *
	 * @return string|WP_Error
	 */
	public static function encrypt( string $plain_text ): string|WP_Error {
		if ( '' === $plain_text ) {
			return '';
		}

		$key = self::encryption_key();

		try {
			if (
				function_exists( 'sodium_crypto_secretbox' )
				&& defined( 'SODIUM_CRYPTO_SECRETBOX_NONCEBYTES' )
				&& defined( 'SODIUM_CRYPTO_SECRETBOX_KEYBYTES' )
			) {
				$nonce = random_bytes(
					SODIUM_CRYPTO_SECRETBOX_NONCEBYTES
				);
				$cipher_text = sodium_crypto_secretbox(
					$plain_text,
					$nonce,
					substr( $key, 0, SODIUM_CRYPTO_SECRETBOX_KEYBYTES )
				);

				return self::SODIUM_PREFIX . base64_encode(
					$nonce . $cipher_text
				);
			}

			if (
				function_exists( 'openssl_encrypt' )
				&& in_array(
					'aes-256-gcm',
					openssl_get_cipher_methods(),
					true
				)
			) {
				$iv = random_bytes( 12 );
				$tag = '';
				$cipher_text = openssl_encrypt(
					$plain_text,
					'aes-256-gcm',
					$key,
					OPENSSL_RAW_DATA,
					$iv,
					$tag,
					'power-schedule-manager'
				);

				if ( false !== $cipher_text && 16 === strlen( $tag ) ) {
					return self::OPENSSL_PREFIX . base64_encode(
						$iv . $tag . $cipher_text
					);
				}
			}
		} catch ( Throwable ) {
			// Return one controlled error without exposing cryptographic details.
		}

		return new WP_Error(
			'power_schedule_manager_encryption_unavailable',
			__(
				'Máy chủ chưa hỗ trợ mã hóa khóa API. Hãy cấu hình khóa bằng biến môi trường.',
				'power-schedule-manager'
			)
		);
	}

	/**
	 * Decrypt a stored value. Invalid or stale values fail closed.
	 */
	public static function decrypt( string $encrypted_value ): string {
		if ( '' === $encrypted_value ) {
			return '';
		}

		$key = self::encryption_key();

		try {
			if (
				str_starts_with(
					$encrypted_value,
					self::SODIUM_PREFIX
				)
				&& function_exists( 'sodium_crypto_secretbox_open' )
			) {
				$payload = base64_decode(
					substr(
						$encrypted_value,
						strlen( self::SODIUM_PREFIX )
					),
					true
				);

				if (
					false === $payload
					|| strlen( $payload )
						<= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES
				) {
					return '';
				}

				$plain_text = sodium_crypto_secretbox_open(
					substr(
						$payload,
						SODIUM_CRYPTO_SECRETBOX_NONCEBYTES
					),
					substr(
						$payload,
						0,
						SODIUM_CRYPTO_SECRETBOX_NONCEBYTES
					),
					substr(
						$key,
						0,
						SODIUM_CRYPTO_SECRETBOX_KEYBYTES
					)
				);

				return false !== $plain_text ? $plain_text : '';
			}

			if (
				str_starts_with(
					$encrypted_value,
					self::OPENSSL_PREFIX
				)
				&& function_exists( 'openssl_decrypt' )
			) {
				$payload = base64_decode(
					substr(
						$encrypted_value,
						strlen( self::OPENSSL_PREFIX )
					),
					true
				);

				if ( false === $payload || strlen( $payload ) <= 28 ) {
					return '';
				}

				$plain_text = openssl_decrypt(
					substr( $payload, 28 ),
					'aes-256-gcm',
					$key,
					OPENSSL_RAW_DATA,
					substr( $payload, 0, 12 ),
					substr( $payload, 12, 16 ),
					'power-schedule-manager'
				);

				return false !== $plain_text ? $plain_text : '';
			}
		} catch ( Throwable ) {
			return '';
		}

		return '';
	}

	/**
	 * Derive a plugin-specific key from WordPress salts.
	 */
	private static function encryption_key(): string {
		$material = wp_salt( 'auth' ) . wp_salt( 'secure_auth' );

		if ( function_exists( 'hash_hkdf' ) ) {
			return hash_hkdf(
				'sha256',
				$material,
				32,
				'power-schedule-manager-secrets'
			);
		}

		return hash(
			'sha256',
			'power-schedule-manager-secrets|' . $material,
			true
		);
	}
}

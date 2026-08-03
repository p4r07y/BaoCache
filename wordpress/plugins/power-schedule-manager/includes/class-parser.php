<?php
/**
 * Raw power schedule parser.
 *
 * @package Power_Schedule_Manager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Parses copied EVN schedule text or HTML into normalized events.
 *
 * This service is intentionally read-only. It never writes to the database.
 */
final class Power_Schedule_Manager_Parser {

	/**
	 * Maximum raw payload size: 1 MiB.
	 */
	private const int MAX_PAYLOAD_BYTES = 1048576;

	/**
	 * Maximum event blocks in one payload.
	 */
	private const int MAX_EVENT_BLOCKS = 500;

	/**
	 * Prevent direct construction.
	 */
	private function __construct() {
	}

	/**
	 * Parse a raw payload.
	 *
	 * @param string $payload     Raw text or HTML.
	 * @param string $unit_code   Selected electrical service unit code.
	 * @param string $source      Import source identifier.
	 * @param bool   $verify_unit Verify unit against database.
	 *
	 * @return array{
	 *     unit_code: string,
	 *     unit_name: string,
	 *     events: array<int, array<string, mixed>>,
	 *     errors: array<int, array{block: int, code: string, message: string}>,
	 *     warnings: array<int, array{block: int, code: string, message: string}>,
	 *     statistics: array{
	 *         detected_blocks: int,
	 *         valid_events: int,
	 *         error_count: int,
	 *         warning_count: int
	 *     }
	 * }
	 *
	 * @throws InvalidArgumentException When the complete payload is unsafe.
	 */
	public static function parse(
		string $payload,
		string $unit_code,
		string $source = 'evn',
		bool $verify_unit = true
	): array {
		self::assert_payload_is_safe( $payload );

		$text      = self::normalize_payload( $payload );
		$unit_name = self::extract_unit_name( $text );
		$unit_code = Power_Schedule_Manager_Units::sanitize_code(
			$unit_code
		);

		if ( '' === $unit_code ) {
			throw new InvalidArgumentException(
				__(
					'Không nhận diện được đơn vị điện lực. Vui lòng chọn đơn vị điện lực trước khi kiểm tra dữ liệu.',
					'power-schedule-manager'
				)
			);
		}

		$blocks = self::extract_event_blocks( $text );

		if ( count( $blocks ) > self::MAX_EVENT_BLOCKS ) {
			throw new InvalidArgumentException(
				sprintf(
					/* translators: %d: Maximum number of events. */
					__(
						'Payload chứa quá nhiều sự kiện. Giới hạn là %d sự kiện.',
						'power-schedule-manager'
					),
					self::MAX_EVENT_BLOCKS
				)
			);
		}

		$events   = array();
		$errors   = array();
		$warnings = array();
		$seen     = array();
		$unit     = null;
		$timezone = POWER_SCHEDULE_MANAGER_TIMEZONE;

		if ( $verify_unit ) {
			$unit = Power_Schedule_Manager_Units::find_by_code(
				$unit_code
			);

			if ( null === $unit ) {
				throw new InvalidArgumentException(
					sprintf(
						/* translators: %s: Unit code. */
						__(
							'Không tìm thấy đơn vị điện lực có mã %s.',
							'power-schedule-manager'
						),
						$unit_code
					)
				);
			}

			if (
				isset( $unit['timezone'] )
				&& is_string( $unit['timezone'] )
				&& '' !== $unit['timezone']
			) {
				$timezone = $unit['timezone'];
			}

			if (
				'' !== $unit_name
				&& ! self::unit_names_are_similar(
					$unit_name,
					(string) $unit['name']
				)
			) {
				$errors[] = array(
					'block'   => 0,
					'code'    => 'unit_name_mismatch',
					'message' => sprintf(
						/* translators: 1: Payload unit, 2: Selected unit. */
						__(
							'Đơn vị trong dữ liệu là “%1$s” nhưng đơn vị được chọn là “%2$s”.',
							'power-schedule-manager'
						),
						$unit_name,
						(string) $unit['name']
					),
				);
			}
		}

		if ( array() === $blocks ) {
			$errors[] = array(
				'block'   => 0,
				'code'    => 'no_event_blocks',
				'message' => __(
					'Không tìm thấy khối lịch điện hợp lệ. Hãy kiểm tra dữ liệu có đủ các nhãn “KHU VỰC:”, “THỜI GIAN:” và “LÝ DO NGỪNG CUNG CẤP ĐIỆN:”.',
					'power-schedule-manager'
				),
			);
		}

		foreach ( $blocks as $index => $block ) {
			$block_number = $index + 1;

			$parts = self::extract_event_parts( $block );

			if (
				array() !== $parts
				&& '' === self::normalize_content( $parts['area'] )
			) {
				$warnings[] = array(
					'block'   => $block_number,
					'code'    => 'empty_area_skipped',
					'message' => sprintf(
						/* translators: %d: Event block number. */
						__(
							'Đã bỏ qua khối lịch số %d vì nhãn KHU VỰC không có nội dung.',
							'power-schedule-manager'
						),
						$block_number
					),
				);

				continue;
			}

			try {
				$parsed = self::parse_event_block(
					$block,
					$unit_code,
					$source,
					$timezone,
					$verify_unit
				);

				$duplicate_key = self::duplicate_key( $parsed );

				if ( isset( $seen[ $duplicate_key ] ) ) {
					$warnings[] = array(
						'block'   => $block_number,
						'code'    => 'duplicate_in_payload',
						'message' => sprintf(
							/* translators: %d: Earlier block number. */
							__(
								'Khối này trùng hoàn toàn với khối số %d trong cùng payload.',
								'power-schedule-manager'
							),
							$seen[ $duplicate_key ]
						),
					);

					continue;
				}

				$seen[ $duplicate_key ] = $block_number;
				$events[]               = $parsed;
			} catch ( InvalidArgumentException $exception ) {
				$errors[] = array(
					'block'   => $block_number,
					'code'    => 'invalid_event_block',
					'message' => $exception->getMessage(),
				);
			}
		}

		return array(
			'unit_code' => $unit_code,
			'unit_name' => $unit_name,
			'events'    => $events,
			'errors'    => $errors,
			'warnings'  => $warnings,
			'statistics' => array(
				'detected_blocks' => count( $blocks ),
				'valid_events'    => count( $events ),
				'error_count'     => count( $errors ),
				'warning_count'   => count( $warnings ),
			),
		);
	}

	/**
	 * Parse one event block.
	 *
	 * @param string $block       Normalized block.
	 * @param string $unit_code   Unit code.
	 * @param string $source      Source identifier.
	 * @param string $timezone    Unit timezone.
	 * @param bool   $verify_unit Verify unit against database.
	 *
	 * @return array<string, mixed>
	 *
	 * @throws InvalidArgumentException When block format is invalid.
	 */
	private static function parse_event_block(
		string $block,
		string $unit_code,
		string $source,
		string $timezone,
		bool $verify_unit
	): array {
		$parts = self::extract_event_parts( $block );

		if ( array() === $parts ) {
			throw new InvalidArgumentException(
				__(
					'Khối lịch không đúng cấu trúc KHU VỰC, THỜI GIAN và LÝ DO.',
					'power-schedule-manager'
				)
			);
		}

		$start_date = Power_Schedule_Manager_Validator::display_date_to_database(
			self::normalize_display_date( $parts['start_date'] )
		);

		$end_date = Power_Schedule_Manager_Validator::display_date_to_database(
			self::normalize_display_date( $parts['end_date'] )
		);

		$start_time = self::normalize_time(
			$parts['start_time']
		);

		$end_time = self::normalize_time(
			$parts['end_time']
		);

		$event = array(
			'unit_code'      => $unit_code,
			'source'         => $source,
			'source_event_id' => '',
			'local_date'     => $start_date,
			'start_at_utc'   =>
				Power_Schedule_Manager_Validator::local_to_utc(
					$start_date,
					$start_time,
					$timezone
				),
			'end_at_utc'     =>
				Power_Schedule_Manager_Validator::local_to_utc(
					$end_date,
					$end_time,
					$timezone
				),
			'area'           => self::normalize_content( $parts['area'] ),
			'reason'         => self::normalize_content( $parts['reason'] ),
			'status'         =>
				Power_Schedule_Manager_Status::SCHEDULED,
		);

		return Power_Schedule_Manager_Validator::validate_event(
			$event,
			$verify_unit
		);
	}

	/**
	 * Normalize copied text/HTML into canonical parser input.
	 *
	 * Canonical labels:
	 * - DON VI:
	 * - KHU VUC:
	 * - THOI GIAN:
	 * - LY DO:
	 *
	 * User content is kept with Vietnamese accents, except the time line,
	 * where accents are removed to make date/time parsing deterministic.
	 *
	 * @param string $payload Raw payload.
	 * @return string
	 */
	private static function normalize_payload(
		string $payload
	): string {
		if (
			class_exists( 'Normalizer' )
			&& method_exists( 'Normalizer', 'normalize' )
		) {
			$normalized = Normalizer::normalize(
				$payload,
				Normalizer::FORM_C
			);

			if ( is_string( $normalized ) ) {
				$payload = $normalized;
			}
		}

		$payload = preg_replace(
			'#<(script|style|noscript|template)\b[^>]*>.*?</\1>#isu',
			' ',
			$payload
		) ?? $payload;

		$payload = preg_replace(
			'#<(?:br|hr)\b[^>]*\/?>#iu',
			"\n",
			$payload
		) ?? $payload;

		$payload = preg_replace(
			'#</(?:p|div|li|tr|section|article|h[1-6])\s*>#iu',
			"\n",
			$payload
		) ?? $payload;

		$payload = preg_replace(
			'#</(?:td|th)\s*>#iu',
			' ',
			$payload
		) ?? $payload;

		$payload = wp_strip_all_tags(
			$payload,
			false
		);

		$payload = html_entity_decode(
			$payload,
			ENT_QUOTES | ENT_HTML5,
			'UTF-8'
		);

		$payload = str_replace(
			array(
				"\xC2\xA0",
				"\xE2\x80\xAF",
				"\xEF\xBB\xBF",
				"\xE2\x80\x8B",
				"\xE2\x80\x8C",
				"\xE2\x80\x8D",
				'：',
				"\r\n",
				"\r",
				'**',
				'__',
			),
			array(
				' ',
				' ',
				'',
				'',
				'',
				'',
				':',
				"\n",
				"\n",
				'',
				'',
			),
			$payload
		);

		$payload = preg_replace(
			'/[ \t]+/u',
			' ',
			$payload
		) ?? $payload;

		$payload = preg_replace(
			'/[ \t]*\n[ \t]*/u',
			"\n",
			$payload
		) ?? $payload;

		$lines = preg_split(
			'/\n/u',
			$payload
		);

		if ( ! is_array( $lines ) ) {
			return '';
		}

		$normalized_lines = array();

		foreach ( $lines as $line ) {
			$line = self::normalize_label_line(
				trim( $line )
			);

			if ( '' !== $line ) {
				$normalized_lines[] = $line;
			}
		}

		return trim(
			preg_replace(
				'/\n{3,}/u',
				"\n\n",
				implode( "\n", $normalized_lines )
			) ?? implode( "\n", $normalized_lines )
		);
	}

	/**
	 * Normalize one line if it begins with a supported label.
	 *
	 * @param string $line Raw line.
	 * @return string
	 */
	private static function normalize_label_line( string $line ): string {
		$colon_position = strpos( $line, ':' );

		if ( false === $colon_position || $colon_position > 120 ) {
			return $line;
		}

		$label = substr(
			$line,
			0,
			$colon_position
		);

		$value = ltrim(
			substr( $line, $colon_position + 1 )
		);

		$label_key = self::label_key( $label );

		if ( 'DON VI' === $label_key ) {
			return 'DON VI: ' . $value;
		}

		if ( str_starts_with( $label_key, 'KHU VUC' ) ) {
			return 'KHU VUC: ' . $value;
		}

		if ( 'THOI GIAN' === $label_key ) {
			return 'THOI GIAN: ' . self::fold_vietnamese( $value );
		}

		if (
			str_starts_with( $label_key, 'LY DO' )
			|| str_starts_with( $label_key, 'LI DO' )
		) {
			return 'LY DO: ' . $value;
		}

		return $line;
	}

	/**
	 * Extract event blocks from canonical text.
	 *
	 * @param string $text Canonical payload.
	 * @return array<int, string>
	 */
	private static function extract_event_blocks( string $text ): array {
		$lines = preg_split(
			'/\n/u',
			$text
		);

		if ( ! is_array( $lines ) ) {
			return array();
		}

		$blocks = array();
		$current_block = array();

		foreach ( $lines as $line ) {
			$line = trim( $line );

			if ( '' === $line ) {
				continue;
			}

			if ( self::line_starts_with_label( $line, 'KHU VUC' ) ) {
				if ( array() !== $current_block ) {
					$blocks[] = implode( "\n", $current_block );
				}

				$current_block = array( $line );
				continue;
			}

			if ( array() !== $current_block ) {
				$current_block[] = $line;
			}
		}

		if ( array() !== $current_block ) {
			$blocks[] = implode( "\n", $current_block );
		}

		return array_values( $blocks );
	}

	/**
	 * Extract the three business fields from one event block.
	 *
	 * @param string $block Canonical event block.
	 *
	 * @return array{
	 *     area: string,
	 *     reason: string,
	 *     start_time: string,
	 *     start_date: string,
	 *     end_time: string,
	 *     end_date: string
	 * }|array{}
	 */
	private static function extract_event_parts( string $block ): array {
		$matched = preg_match(
			'/\A\s*KHU\s*VUC\s*:\s*(?<area>.*?)\s*'
			. 'THOI\s*GIAN\s*:\s*(?<time>.*?)\s*'
			. 'LY\s*DO\s*:\s*(?<reason>.*?)\s*\z/isu',
			$block,
			$matches
		);

		if ( 1 !== $matched ) {
			return array();
		}

		$time_matched = preg_match(
			'/\bTU\s*(?<start_time>\d{1,2}:\d{2}(?::\d{2})?)\s*'
			. 'NGAY\s*(?<start_date>\d{1,2}\/\d{1,2}\/\d{4})\s*'
			. 'DEN\s*(?<end_time>\d{1,2}:\d{2}(?::\d{2})?)\s*'
			. 'NGAY\s*(?<end_date>\d{1,2}\/\d{1,2}\/\d{4})\b/iu',
			self::fold_vietnamese( (string) $matches['time'] ),
			$time_matches
		);

		if ( 1 !== $time_matched ) {
			return array();
		}

		return array(
			'area'       => (string) $matches['area'],
			'reason'     => (string) $matches['reason'],
			'start_time' => (string) $time_matches['start_time'],
			'start_date' => (string) $time_matches['start_date'],
			'end_time'   => (string) $time_matches['end_time'],
			'end_date'   => (string) $time_matches['end_date'],
		);
	}

	/**
	 * Extract unit name from canonical payload header.
	 *
	 * @param string $text Canonical payload.
	 * @return string
	 */
	private static function extract_unit_name( string $text ): string {
		$matched = preg_match(
			'/DON\s*VI\s*:\s*(?<unit_name>.*?)(?=\s*KHU\s*VUC\s*:|\R|\z)/isu',
			$text,
			$matches
		);

		if ( 1 !== $matched ) {
			return '';
		}

		return self::normalize_content(
			(string) $matches['unit_name']
		);
	}

	/**
	 * Check whether one canonical line starts with a label.
	 *
	 * @param string $line     Text line.
	 * @param string $expected Expected label key.
	 * @return bool
	 */
	private static function line_starts_with_label(
		string $line,
		string $expected
	): bool {
		$colon_position = strpos( $line, ':' );

		if ( false === $colon_position || $colon_position > 120 ) {
			return false;
		}

		return str_starts_with(
			self::label_key( substr( $line, 0, $colon_position ) ),
			$expected
		);
	}

	/**
	 * Normalize a label to an accent-insensitive machine key.
	 *
	 * @param string $label Raw label.
	 * @return string
	 */
	private static function label_key( string $label ): string {
		$label = self::fold_vietnamese( $label );
		$label = strtoupper( $label );
		$label = preg_replace(
			'/[^A-Z0-9]+/',
			' ',
			$label
		) ?? $label;

		return trim(
			preg_replace( '/\s+/', ' ', $label ) ?? $label
		);
	}

	/**
	 * Fold Vietnamese text to ASCII without depending on the server locale.
	 *
	 * WordPress remove_accents() remains useful for other alphabets, while the
	 * explicit map guarantees that source labels such as KHU VỰC, THỜI GIAN,
	 * LÝ DO and ĐƠN VỊ are recognized consistently on every host.
	 *
	 * @param string $value Raw text.
	 * @return string
	 */
	private static function fold_vietnamese( string $value ): string {
		$value = strtr(
			$value,
			array(
				'À' => 'A', 'Á' => 'A', 'Ạ' => 'A', 'Ả' => 'A',
				'Ã' => 'A', 'Â' => 'A', 'Ầ' => 'A', 'Ấ' => 'A',
				'Ậ' => 'A', 'Ẩ' => 'A', 'Ẫ' => 'A', 'Ă' => 'A',
				'Ằ' => 'A', 'Ắ' => 'A', 'Ặ' => 'A', 'Ẳ' => 'A',
				'Ẵ' => 'A', 'È' => 'E', 'É' => 'E', 'Ẹ' => 'E',
				'Ẻ' => 'E', 'Ẽ' => 'E', 'Ê' => 'E', 'Ề' => 'E',
				'Ế' => 'E', 'Ệ' => 'E', 'Ể' => 'E', 'Ễ' => 'E',
				'Ì' => 'I', 'Í' => 'I', 'Ị' => 'I', 'Ỉ' => 'I',
				'Ĩ' => 'I', 'Ò' => 'O', 'Ó' => 'O', 'Ọ' => 'O',
				'Ỏ' => 'O', 'Õ' => 'O', 'Ô' => 'O', 'Ồ' => 'O',
				'Ố' => 'O', 'Ộ' => 'O', 'Ổ' => 'O', 'Ỗ' => 'O',
				'Ơ' => 'O', 'Ờ' => 'O', 'Ớ' => 'O', 'Ợ' => 'O',
				'Ở' => 'O', 'Ỡ' => 'O', 'Ù' => 'U', 'Ú' => 'U',
				'Ụ' => 'U', 'Ủ' => 'U', 'Ũ' => 'U', 'Ư' => 'U',
				'Ừ' => 'U', 'Ứ' => 'U', 'Ự' => 'U', 'Ử' => 'U',
				'Ữ' => 'U', 'Ỳ' => 'Y', 'Ý' => 'Y', 'Ỵ' => 'Y',
				'Ỷ' => 'Y', 'Ỹ' => 'Y', 'Đ' => 'D',
				'à' => 'a', 'á' => 'a', 'ạ' => 'a', 'ả' => 'a',
				'ã' => 'a', 'â' => 'a', 'ầ' => 'a', 'ấ' => 'a',
				'ậ' => 'a', 'ẩ' => 'a', 'ẫ' => 'a', 'ă' => 'a',
				'ằ' => 'a', 'ắ' => 'a', 'ặ' => 'a', 'ẳ' => 'a',
				'ẵ' => 'a', 'è' => 'e', 'é' => 'e', 'ẹ' => 'e',
				'ẻ' => 'e', 'ẽ' => 'e', 'ê' => 'e', 'ề' => 'e',
				'ế' => 'e', 'ệ' => 'e', 'ể' => 'e', 'ễ' => 'e',
				'ì' => 'i', 'í' => 'i', 'ị' => 'i', 'ỉ' => 'i',
				'ĩ' => 'i', 'ò' => 'o', 'ó' => 'o', 'ọ' => 'o',
				'ỏ' => 'o', 'õ' => 'o', 'ô' => 'o', 'ồ' => 'o',
				'ố' => 'o', 'ộ' => 'o', 'ổ' => 'o', 'ỗ' => 'o',
				'ơ' => 'o', 'ờ' => 'o', 'ớ' => 'o', 'ợ' => 'o',
				'ở' => 'o', 'ỡ' => 'o', 'ù' => 'u', 'ú' => 'u',
				'ụ' => 'u', 'ủ' => 'u', 'ũ' => 'u', 'ư' => 'u',
				'ừ' => 'u', 'ứ' => 'u', 'ự' => 'u', 'ử' => 'u',
				'ữ' => 'u', 'ỳ' => 'y', 'ý' => 'y', 'ỵ' => 'y',
				'ỷ' => 'y', 'ỹ' => 'y', 'đ' => 'd',
			)
		);

		return remove_accents( $value );
	}

	/**
	 * Normalize event area or reason content.
	 *
	 * @param string $content Raw content.
	 * @return string
	 */
	private static function normalize_content( string $content ): string {
		$content = sanitize_textarea_field( $content );

		$content = preg_replace(
			'/\s+/u',
			' ',
			$content
		) ?? $content;

		return trim( $content );
	}

	/**
	 * Normalize a time to H:i:s.
	 *
	 * @param string $time Raw time.
	 * @return string
	 *
	 * @throws InvalidArgumentException When invalid.
	 */
	private static function normalize_time( string $time ): string {
		$time  = trim( $time );
		$parts = explode( ':', $time );

		if ( 2 === count( $parts ) ) {
			$time .= ':00';
		}

		if ( 1 !== preg_match( '/\A\d{1,2}:\d{2}:\d{2}\z/', $time ) ) {
			throw new InvalidArgumentException(
				__(
					'Giờ trong dữ liệu nguồn không hợp lệ.',
					'power-schedule-manager'
				)
			);
		}

		$parts = array_map(
			'intval',
			explode( ':', $time )
		);

		if (
			$parts[0] < 0
			|| $parts[0] > 23
			|| $parts[1] < 0
			|| $parts[1] > 59
			|| $parts[2] < 0
			|| $parts[2] > 59
		) {
			throw new InvalidArgumentException(
				__(
					'Giờ trong dữ liệu nguồn không hợp lệ.',
					'power-schedule-manager'
				)
			);
		}

		return sprintf(
			'%02d:%02d:%02d',
			$parts[0],
			$parts[1],
			$parts[2]
		);
	}

	/**
	 * Normalize display date by zero-padding day and month.
	 *
	 * @param string $date Raw d/m/Y date.
	 * @return string
	 *
	 * @throws InvalidArgumentException When invalid.
	 */
	private static function normalize_display_date( string $date ): string {
		$parts = explode( '/', trim( $date ) );

		if ( 3 !== count( $parts ) ) {
			throw new InvalidArgumentException(
				__(
					'Ngày trong dữ liệu nguồn không hợp lệ.',
					'power-schedule-manager'
				)
			);
		}

		return sprintf(
			'%02d/%02d/%04d',
			(int) $parts[0],
			(int) $parts[1],
			(int) $parts[2]
		);
	}

	/**
	 * Check raw payload safety limits.
	 *
	 * @param string $payload Raw payload.
	 * @return void
	 *
	 * @throws InvalidArgumentException When unsafe.
	 */
	private static function assert_payload_is_safe( string $payload ): void {
		if ( '' === trim( $payload ) ) {
			throw new InvalidArgumentException(
				__(
					'Dữ liệu lịch điện không được để trống.',
					'power-schedule-manager'
				)
			);
		}

		if ( strlen( $payload ) > self::MAX_PAYLOAD_BYTES ) {
			throw new InvalidArgumentException(
				__(
					'Dữ liệu lịch điện vượt quá giới hạn 1 MiB.',
					'power-schedule-manager'
				)
			);
		}

		if ( str_contains( $payload, "\0" ) ) {
			throw new InvalidArgumentException(
				__(
					'Dữ liệu lịch điện chứa ký tự không hợp lệ.',
					'power-schedule-manager'
				)
			);
		}

		if (
			! seems_utf8( $payload )
			|| wp_check_invalid_utf8( $payload, true ) !== $payload
		) {
			throw new InvalidArgumentException(
				__(
					'Dữ liệu lịch điện không phải UTF-8 hợp lệ.',
					'power-schedule-manager'
				)
			);
		}
	}

	/**
	 * Build a key for duplicates inside the same payload.
	 *
	 * @param array<string, mixed> $event Normalized event.
	 * @return string
	 */
	private static function duplicate_key( array $event ): string {
		$values = array(
			(string) $event['unit_code'],
			(string) $event['start_at_utc'],
			(string) $event['end_at_utc'],
			(string) $event['area'],
			(string) $event['reason'],
		);

		return hash( 'sha256', implode( "\x1F", $values ) );
	}

	/**
	 * Compare unit names after removing common prefixes and punctuation.
	 *
	 * @param string $payload_name Payload unit name.
	 * @param string $stored_name  Stored unit name.
	 * @return bool
	 */
	private static function unit_names_are_similar(
		string $payload_name,
		string $stored_name
	): bool {
		return self::normalize_unit_name( $payload_name )
			=== self::normalize_unit_name( $stored_name );
	}

	/**
	 * Normalize unit name for comparison only.
	 *
	 * @param string $name Unit name.
	 * @return string
	 */
	private static function normalize_unit_name( string $name ): string {
		$name = strtolower(
			self::fold_vietnamese( trim( $name ) )
		);

		$name = preg_replace(
			'/\b(?:dien\s*luc|doi\s*quan\s*ly\s*dien)\b/u',
			'',
			$name
		) ?? $name;

		$name = preg_replace(
			'/[^a-z0-9]+/u',
			'',
			$name
		) ?? $name;

		return $name;
	}
}

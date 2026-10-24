<?php
/** Private multi-file storage and administrator management. @package TimonConfigs_Core */
defined( 'ABSPATH' ) || exit;

final class TC_Private_Files {
	private const META_KEY = '_tc_private_files';
	private const NONCE = 'tc_private_files_nonce';
	private const EXTENSIONS = array( 'znbk', 'gpc', 'ximmr', 'json', 'txt', 'cfg', 'ini' );
	private const FORMATS_OPTION = 'timonconfigs_private_formats';
	private const MAX_BYTES = 20971520;
	private const MAX_PER_UPLOAD = 10;
	private const MAX_FILES = 100;
	private const CONFIG_QUOTA = 104857600;
	private const STORAGE_QUOTA = 1073741824;

	public static function init(): void {
		add_action( 'add_meta_boxes_tc_config', array( __CLASS__, 'add_box' ) );
		add_action( 'post_edit_form_tag', array( __CLASS__, 'enctype' ) );
		add_action( 'save_post_tc_config', array( __CLASS__, 'save' ), 30, 2 );
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', static function (): void { register_setting( 'tc_private_formats', self::FORMATS_OPTION, array( 'type' => 'array', 'show_in_rest' => false, 'sanitize_callback' => array( __CLASS__, 'sanitize_formats' ) ) ); } );
		add_action( 'admin_post_tc_private_files_manage', array( __CLASS__, 'handle' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notice' ) );
	}

	public static function enctype(): void {
		global $post;
		if ( $post instanceof WP_Post && 'tc_config' === $post->post_type && current_user_can( 'manage_options' ) ) { echo ' enctype="multipart/form-data"'; }
	}

	public static function add_box(): void {
		if ( current_user_can( 'manage_options' ) ) { add_meta_box( 'tc_private_script_files', 'Private script files', array( __CLASS__, 'render' ), 'tc_config', 'normal', 'default' ); }
	}

	public static function menu(): void {
		add_submenu_page( 'edit.php?post_type=tc_config', 'Private script files', 'Private files', 'manage_options', 'tc-private-files', array( __CLASS__, 'page' ) );
		add_options_page( 'Private script formats', 'Private script formats', 'manage_options', 'tc-private-formats', array( __CLASS__, 'formats_page' ) );
	}

	/** Configuration only narrows this reviewed format set; arbitrary extensions are never accepted. */
	public static function extensions(): array {
		$value = get_option( self::FORMATS_OPTION, self::EXTENSIONS );
		if ( ! is_array( $value ) ) { return array(); }
		return array_values( array_intersect( self::EXTENSIONS, array_filter( $value, 'is_string' ) ) );
	}

	public static function accept(): string { return implode( ',', array_map( static fn( string $extension ): string => '.' . $extension, self::extensions() ) ); }

	public static function sanitize_formats( mixed $value ): array {
		if ( ! current_user_can( 'manage_options' ) || ! is_array( $value ) ) { return self::extensions(); }
		return array_values( array_intersect( self::EXTENSIONS, array_filter( $value, 'is_string' ) ) );
	}

	public static function formats_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		echo '<div class="wrap"><h1>Private script formats</h1><p>Enable only the formats you distribute. GPC, JSON, TXT, CFG and INI must contain inspected text; JSON must also be valid JSON. ZNBK uses automatic structural checks and does not require external approval. XIMMR still requires a configured scanner or format inspector. An optional scanner may reject any file. Archives, executable files and active web content remain disabled.</p><p>Disabling a format also blocks existing downloads of that format without deleting stored files.</p><form method="post" action="options.php">';
		settings_fields( 'tc_private_formats' );
		echo '<input type="hidden" name="' . esc_attr( self::FORMATS_OPTION ) . '[]" value="">';
		foreach ( self::EXTENSIONS as $extension ) { echo '<p><label><input type="checkbox" name="' . esc_attr( self::FORMATS_OPTION ) . '[]" value="' . esc_attr( $extension ) . '" ' . checked( in_array( $extension, self::extensions(), true ), true, false ) . '> ' . esc_html( strtoupper( $extension ) ) . '</label></p>'; }
		submit_button(); echo '</form></div>';
	}

	/** Absolute server-only path. Never print it or register it as REST metadata. */
	public static function root(): string|WP_Error {
		$configured = apply_filters( 'timonconfigs_private_storage_directory', defined( 'TIMONCONFIGS_PRIVATE_STORAGE_DIR' ) ? TIMONCONFIGS_PRIVATE_STORAGE_DIR : '' );
		if ( ! is_string( $configured ) || '' === $configured ) {
			return new WP_Error( 'tc_storage_unconfigured', 'Configure TIMONCONFIGS_PRIVATE_STORAGE_DIR outside every public document root before uploading.' );
		}
		$root = realpath( $configured );
		if ( false === $root || DIRECTORY_SEPARATOR === $root || is_link( $configured ) || ! is_dir( $root ) || ! is_writable( $root ) || ( fileperms( $root ) & 0022 ) ) {
			return new WP_Error( 'tc_storage_unavailable', 'Private storage must exist, be writable by PHP, and not be a symlink or writable by other users.' );
		}
		$uploads = wp_get_upload_dir();
		foreach ( array( ABSPATH, WP_CONTENT_DIR, $uploads['basedir'] ?? '', $_SERVER['DOCUMENT_ROOT'] ?? '' ) as $public ) {
			if ( ! is_string( $public ) || '' === $public ) { continue; }
			$canonical = realpath( $public );
			if ( false !== $canonical && ( $root === $canonical || str_starts_with( $root . DIRECTORY_SEPARATOR, rtrim( $canonical, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR ) ) ) {
				return new WP_Error( 'tc_storage_public', 'Private storage cannot be located inside public WordPress, uploads, or document-root directories.' );
			}
		}
		return rtrim( $root, DIRECTORY_SEPARATOR );
	}

	private static function records( int $config_id ): array|WP_Error {
		$value = get_post_meta( $config_id, self::META_KEY, true );
		if ( '' === $value ) { return array(); }
		if ( ! is_array( $value ) || array_filter( $value, static fn( $record ): bool => ! is_array( $record ) ) ) {
			return new WP_Error( 'tc_storage_metadata', 'Private-file metadata needs administrator repair; existing records have not been overwritten.' );
		}
		return array_values( $value );
	}

	/** Safe display metadata only; this is not a REST endpoint or an authorization decision. */
	public static function files( int $config_id ): array {
		if ( 'tc_config' !== get_post_type( $config_id ) ) { return array(); }
		$records = self::records( $config_id );
		if ( is_wp_error( $records ) ) { return array(); }
		$files = array();
		foreach ( $records as $index => $record ) {
			if ( ! self::uuid( $record['id'] ?? null ) || ! is_string( $record['name'] ?? null ) || ! in_array( strtolower( pathinfo( $record['name'], PATHINFO_EXTENSION ) ), self::extensions(), true ) ) { continue; }
			$files[] = array( 'id' => $record['id'], 'name' => sanitize_file_name( $record['name'] ), 'size' => max( 0, (int) ( $record['size'] ?? 0 ) ), 'version' => self::text( $record['version'] ?? '', 80 ), 'notes' => self::text( $record['notes'] ?? '', 2000, true ), 'checksum' => is_string( $record['checksum'] ?? null ) && preg_match( '/^[a-f0-9]{64}$/D', $record['checksum'] ) ? $record['checksum'] : '', 'uploaded_at' => self::text( $record['uploaded_at'] ?? '', 30 ), 'order' => max( 0, (int) ( $record['order'] ?? $index ) ) );
		}
		usort( $files, static fn( array $a, array $b ): int => ( $a['order'] <=> $b['order'] ) ?: strcmp( $a['id'], $b['id'] ) );
		return $files;
	}

	/** Resolve only this config's opaque ID, rechecking path, bytes, and integrity server-side. */
	public static function resolve( int $config_id, string $file_id ): string|WP_Error {
		if ( 'tc_config' !== get_post_type( $config_id ) || ! self::uuid( $file_id ) ) { return new WP_Error( 'tc_file_missing', 'This private file is unavailable.' ); }
		$records = self::records( $config_id );
		if ( is_wp_error( $records ) ) { return $records; }
		$matches = array_values( array_filter( $records, static fn( array $record ): bool => ( $record['id'] ?? null ) === $file_id ) );
		if ( 1 !== count( $matches ) ) { return new WP_Error( 'tc_file_missing', 'This private file is unavailable.' ); }
		$record = $matches[0];
		$root = self::root();
		if ( is_wp_error( $root ) ) { return $root; }
		$directory = $root . DIRECTORY_SEPARATOR . $config_id;
		$stored_name = $record['stored_name'] ?? '';
		if ( ! is_string( $stored_name ) || ! preg_match( '/^' . preg_quote( $file_id, '/' ) . '\.(' . implode( '|', self::EXTENSIONS ) . ')$/D', $stored_name ) || ! in_array( pathinfo( $stored_name, PATHINFO_EXTENSION ), self::extensions(), true ) || is_link( $directory ) || realpath( $directory ) !== $directory ) { return new WP_Error( 'tc_file_unsafe', 'This private file failed its storage checks.' ); }
		$path = $directory . DIRECTORY_SEPARATOR . $stored_name;
		if ( is_link( $path ) || ! is_file( $path ) || ! is_readable( $path ) || realpath( $path ) !== $path ) { return new WP_Error( 'tc_file_missing', 'This private file is unavailable.' ); }
		$inspection = self::inspect( $path, $record['name'] ?? '' );
		if ( is_wp_error( $inspection ) || (int) ( $record['size'] ?? 0 ) !== $inspection['size'] || ( ! empty( $record['checksum'] ) && ! hash_equals( (string) $record['checksum'], $inspection['checksum'] ) ) ) { return new WP_Error( 'tc_file_integrity', 'This private file failed its integrity checks.' ); }
		return $path;
	}

	private static function uuid( mixed $value ): bool { return is_string( $value ) && 1 === preg_match( '/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/D', $value ); }
	private static function text( mixed $value, int $max, bool $multiline = false ): string { return is_string( $value ) ? substr( $multiline ? sanitize_textarea_field( $value ) : sanitize_text_field( $value ), 0, $max ) : ''; }

	/**
	 * Inspect a ZIP-backed Zen Backup envelope without extracting it to disk.
	 *
	 * ZIP framing may be legitimate for .znbk; ordinary ZIPs and all other archives
	 * remain blocked. This is bounded structural validation, not vendor-format or malware certification.
	 * A configured timonconfigs_private_file_scan handler may still veto a file.
	 */
	private static function inspect_znbk_zip( string $path ): bool|WP_Error {
		$bad = static fn( string $message ): WP_Error => new WP_Error( 'tc_upload_invalid', $message );
		if ( ! class_exists( 'ZipArchive' ) ) {
			return $bad( 'This ZNBK uses a ZIP container. The host must enable the PHP ZIP extension (ZipArchive) to validate it automatically.' );
		}
		$zip = new ZipArchive();
		if ( true !== $zip->open( $path, ZipArchive::CHECKCONS ) ) {
			return $bad( 'The ZNBK ZIP container is incomplete or invalid.' );
		}
		try {
			if ( $zip->numFiles < 1 || $zip->numFiles > 128 ) {
				return $bad( 'The ZNBK container has too many entries or contains no files.' );
			}
			$total_uncompressed = 0;
			$files_found = 0;
			for ( $index = 0; $index < $zip->numFiles; ++$index ) {
				$entry = $zip->statIndex( $index );
				$name = is_array( $entry ) ? ( $entry['name'] ?? null ) : null;
				if ( ! is_string( $name ) || '' === $name || strlen( $name ) > 320
					|| preg_match( '/[\x00-\x1f\x7f\\\\]/', $name ) || str_starts_with( $name, '/' )
					|| preg_match( '#(?:^|/)\.\.?(?:/|$)#', $name ) || preg_match( '/^[a-z]:/i', $name ) ) {
					return $bad( 'The ZNBK archive contains an unsafe entry name or path.' );
				}
				$os = 0;
				$attributes = 0;
				if ( $zip->getExternalAttributesIndex( $index, $os, $attributes ) && ( ( $attributes >> 16 ) & 0170000 ) === 0120000 ) {
					return $bad( 'The ZNBK archive contains a symbolic link.' );
				}
				if ( str_ends_with( $name, '/' ) ) { continue; }
				++$files_found;
				$size = (int) ( $entry['size'] ?? -1 );
				$compressed = (int) ( $entry['comp_size'] ?? -1 );
				$method = (int) ( $entry['comp_method'] ?? -1 );
				if ( $size < 0 || $compressed < 0 || $size > 20971520
					|| ! in_array( $method, array( ZipArchive::CM_STORE, ZipArchive::CM_DEFLATE ), true )
					|| ( $size > 0 && $compressed < 1 )
					|| ( $size > 1048576 && $size > 200 * max( $compressed, 1 ) ) ) {
					return $bad( 'The ZNBK archive uses unsafe compression or oversized contents.' );
				}
				$total_uncompressed += $size;
				if ( $total_uncompressed > 67108864 ) {
					return $bad( 'The ZNBK archive expands beyond the allowed inspection limit.' );
				}
				if ( preg_match( '/\.(?:zip|rar|7z|tar|gz|exe|dll|com|bat|cmd|ps1|sh|php\d*|phtml|phar|cgi|js|html?|svg|jar|msi|so|dylib)$/i', $name ) ) {
					return $bad( 'The ZNBK archive contains a nested archive or executable entry.' );
				}
				$stream = $zip->getStream( $name );
				if ( ! is_resource( $stream ) ) { return $bad( 'An entry in the ZNBK archive cannot be inspected.' ); }
				$prefix = fread( $stream, 8192 );
				fclose( $stream );
				if ( ! is_string( $prefix ) || ( $size && '' === $prefix ) ||
					preg_match( '/^(?:MZ|\x7fELF|#!|PK\x03\x04|Rar!\x1a\x07)/', $prefix ) ||
					preg_match( '/<\?(?:php\b|=|\s*\$)|<script\b/i', $prefix ) ) {
					return $bad( 'The ZNBK archive contains an executable or nested archive payload.' );
				}
			}
			if ( $files_found < 1 ) { return $bad( 'The ZNBK container contains no usable data.' ); }
			return true;
		} finally {
			$zip->close();
		}
	}

	/** Decode only for validation; stored/downloaded bytes and checksums remain unchanged. */
	private static function inspection_text( string $content, string $extension, string $mime ): string|WP_Error {
		if ( ! in_array( $extension, array( 'txt', 'gpc', 'cfg', 'ini' ), true ) ) { return $content; }
		if ( str_starts_with( $content, "\xff\xfe\x00\x00" ) || str_starts_with( $content, "\x00\x00\xfe\xff" ) ) { return new WP_Error( 'tc_upload_encoding', 'Save the script as UTF-8 or BOM-marked UTF-16 instead of UTF-32.' ); }
		if ( str_starts_with( $content, "\xef\xbb\xbf" ) ) { $content = substr( $content, 3 ); }
		elseif ( str_starts_with( $content, "\xff\xfe" ) || str_starts_with( $content, "\xfe\xff" ) ) {
			if ( ! function_exists( 'iconv' ) ) { return new WP_Error( 'tc_upload_encoding', 'This script uses UTF-16. Save it as UTF-8 or enable PHP iconv on the host.' ); }
			$encoding = str_starts_with( $content, "\xff\xfe" ) ? 'UTF-16LE' : 'UTF-16BE';
			$content = @iconv( $encoding, 'UTF-8', substr( $content, 2 ) );
			if ( ! is_string( $content ) ) { return new WP_Error( 'tc_upload_encoding', 'The script contains invalid UTF-16 data. Export a fresh UTF-8 text file.' ); }
		} elseif ( 1 !== preg_match( '//u', $content ) && str_starts_with( $mime, 'text/' )
			&& ! preg_match( '/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/', $content ) ) {
			// Zen/Windows source files often use ANSI comments. Convert only the inspection
			// view, strictly: never drop invalid bytes or reinterpret binary MIME as text.
			if ( ! function_exists( 'iconv' ) ) { return new WP_Error( 'tc_upload_encoding', 'Save this Windows ANSI script as UTF-8 or enable PHP iconv on the host.' ); }
			$content = @iconv( 'Windows-1252', 'UTF-8', $content );
			if ( ! is_string( $content ) ) { return new WP_Error( 'tc_upload_encoding', 'The script has an unsupported text encoding. Export a fresh UTF-8 text file.' ); }
		}
		return '' !== $content ? $content : new WP_Error( 'tc_upload_encoding', 'The script must contain configuration text, not only an encoding marker.' );
	}

	/** Structural/type checks, not a malware-free certification. Optional scanner can veto. */
	private static function inspect( string $path, mixed $raw_name ): array|WP_Error {
		$bad = static fn( string $message ): WP_Error => new WP_Error( 'tc_upload_invalid', $message );
		if ( ! is_string( $raw_name ) || '' === $raw_name || strlen( $raw_name ) > 200 || preg_match( '/[\\\\\/\x00-\x1f\x7f]/', $raw_name ) || preg_match( '/\.(php\d*|phtml|phar|exe|dll|com|bat|cmd|ps1|sh|cgi|js|html?)(\.|$)/i', $raw_name ) ) { return $bad( 'Use a simple script filename without paths or executable extensions.' ); }
		$name = sanitize_file_name( $raw_name );
		$extension = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
		if ( ! in_array( $extension, self::extensions(), true ) ) { return $bad( 'This file format is not enabled. Ask the administrator to review Private script formats. Archives and executable files are disabled.' ); }
		$size = is_file( $path ) && ! is_link( $path ) ? filesize( $path ) : false;
		if ( ! is_int( $size ) || $size < 1 || $size > self::MAX_BYTES ) { return $bad( 'Each file must contain between 1 byte and 20 MB of actual data.' ); }
		$content = file_get_contents( $path );
		if ( false === $content || strlen( $content ) !== $size || ! class_exists( 'finfo' ) ) { return $bad( 'File inspection is unavailable; the upload was not accepted.' ); }
		$mime = ( new finfo( FILEINFO_MIME_TYPE ) )->buffer( $content );
		if ( ! is_string( $mime ) ) { return $bad( 'File type inspection is unavailable. Please retry.' ); }
		$inspection_text = self::inspection_text( $content, $extension, $mime );
		if ( is_wp_error( $inspection_text ) ) {
			// Not every Windows GPC source maps to CP1252: genuine legacy comments
			// can contain undefined codepage bytes. Check the original textual bytes
			// without transcoding and preserve the actual file on disk.
			$legacy_without_bom = ! str_starts_with( $content, "\xef\xbb\xbf" )
				&& ! str_starts_with( $content, "\xff\xfe" )
				&& ! str_starts_with( $content, "\xfe\xff" );
			if ( 'gpc' !== $extension || ! $legacy_without_bom || ! str_starts_with( $mime, 'text/' )
				|| str_contains( $content, "\x00" ) ) { return $inspection_text; }
			$inspection_text = $content;
		}
		$strict_text = 1 === preg_match( '//u', $inspection_text ) && ! preg_match( '/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/', $inspection_text );
		// GPC is downloaded as a private attachment and is never executed by PHP.
		// Accept genuine text/plain source with benign legacy control/codepage
		// characters (e.g. VT, FF, ESC), without weakening TXT/JSON checks.
		$gpc_legacy_text = 'gpc' === $extension && 'text/plain' === $mime && ! str_contains( $inspection_text, "\x00" );
		$text = $strict_text || $gpc_legacy_text;
		$zip_znbk = 'znbk' === $extension && str_starts_with( $content, "PK\x03\x04" );
		if ( $zip_znbk ) {
			$validated = self::inspect_znbk_zip( $path );
			if ( is_wp_error( $validated ) ) { return $validated; }
		} else {
			// Do not weaken the archive, executable or shell rules for other formats.
			$signatures = array( "PK\x03\x04", "PK\x05\x06", "PK\x07\x08", "Rar!\x1a\x07", "7z\xbc\xaf\x27\x1c", "\x1f\x8b", 'BZh', "\xfd7zXZ\x00", 'MZ', "\x7fELF", "\xcf\xfa\xed\xfe", "\xfe\xed\xfa\xcf", '#!' );
			foreach ( $signatures as $signature ) {
				// The two printable letters MZ alone are not an executable header in genuine source text.
				if ( 'MZ' === $signature && in_array( $extension, array( 'txt', 'gpc', 'cfg', 'ini' ), true ) && $strict_text ) { continue; }
				if ( str_starts_with( $content, $signature ) ) { return $bad( 'txt' === $extension ? 'This TXT contains archive, executable or shell data. Export actual configuration text; renaming a binary backup to .txt does not convert it.' : 'Archive, executable, and shell payloads are not accepted.' ); }
			}
			if ( str_starts_with( $inspection_text, '#!' ) ) { return $bad( 'Shell scripts cannot be uploaded as configuration files.' ); }
			foreach ( array_slice( $signatures, 0, 6 ) as $archive_signature ) { if ( str_contains( $content, $archive_signature ) ) { return $bad( 'Embedded archive payloads are not accepted.' ); } }
			if ( 'ustar' === substr( $content, 257, 5 ) || preg_match( '/<\?(?:php\b|=|\s*\$)|<script\b|<svg\b|<!doctype\s+html\b|<html\b/i', $inspection_text ) ) { return $bad( 'Executable, active web or archive content is not accepted inside script files.' ); }
		}
		$allowed_mime = array( 'text/plain', 'application/json', 'text/x-c', 'text/x-c++', 'text/x-csrc', 'text/x-c++src' );
		// libmagic guesses languages from syntax/comments; a harmless GPC can look
		// like C, Java or Pascal. Allow inspected source text, not active web/shell MIME.
		$source_text = $text && str_starts_with( $mime, 'text/' ) && ! preg_match( '#(?:html|xml|svg|javascript|ecmascript|php|shellscript|x-sh|x-perl|x-python|x-ruby)(?:$|[;+.-])#i', $mime );
		if ( $source_text ) { $allowed_mime[] = $mime; }
		if ( in_array( $extension, array( 'znbk', 'ximmr' ), true ) ) { $allowed_mime[] = 'application/octet-stream'; }
		if ( $zip_znbk ) { $allowed_mime = array_merge( $allowed_mime, array( 'application/zip', 'application/x-zip', 'application/x-zip-compressed' ) ); }
		if ( ! in_array( $mime, $allowed_mime, true ) || ( in_array( $extension, array( 'gpc', 'json', 'txt', 'cfg', 'ini' ), true ) && ! $text ) ) {
			// MIME and a fixed encoding category are safe diagnostics; never include file contents.
			return $bad( sprintf( 'This %s was detected as %s (%s). GPC/TXT must contain UTF-8, BOM-marked UTF-16 or Windows ANSI text; binary backups must keep their original ZNBK extension.', strtoupper( $extension ), $mime, $text ? 'text' : 'binary or unsupported encoding' ) );
		}
		if ( 'json' === $extension ) { json_decode( $content, true, 64 ); if ( JSON_ERROR_NONE !== json_last_error() ) { return $bad( 'JSON uploads must contain valid JSON.' ); } }
		$scan = apply_filters( 'timonconfigs_private_file_scan', null, $path, $extension, $mime );
		if ( is_wp_error( $scan ) || ( null !== $scan && true !== $scan ) ) { return $bad( 'The configured file scanner did not approve this file.' ); }
		if ( 'ximmr' === $extension && true !== $scan ) { return $bad( 'XIMMR backups require an approved scanner or format inspector. ZNBK, GPC, JSON, TXT, CFG and INI use automatic structural checks.' ); }
		return array( 'name' => $name, 'extension' => $extension, 'size' => $size, 'checksum' => hash( 'sha256', $content ), 'inspection' => true === $scan ? 'scanner-approved' : ( $zip_znbk ? 'znbk-container-checked' : 'structural-only' ) );
	}

	/** One filesystem lock serializes all writes and quota calculations. */
	private static function locked( callable $callback ): mixed {
		$root = self::root();
		if ( is_wp_error( $root ) ) { return $root; }
		$lock_path = $root . DIRECTORY_SEPARATOR . '.tc-private.lock';
		if ( is_link( $lock_path ) ) { return new WP_Error( 'tc_storage_unsafe', 'Private storage failed its lock checks.' ); }
		$lock = fopen( $lock_path, 'c' );
		if ( ! $lock || ! chmod( $lock_path, 0600 ) ) { if ( $lock ) { fclose( $lock ); } return new WP_Error( 'tc_storage_lock', 'Private storage could not be locked.' ); }
		if ( ! flock( $lock, LOCK_EX | LOCK_NB ) ) { fclose( $lock ); return new WP_Error( 'tc_storage_busy', 'Another private-file operation is running. Please retry.' ); }
		try { return $callback( $root ); } finally { flock( $lock, LOCK_UN ); fclose( $lock ); }
	}

	private static function usage( string $root ): int|WP_Error {
		$size = 0;
		$count = 0;
		try {
			$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );
			foreach ( $iterator as $entry ) {
				if ( ++$count > 50000 || $entry->isLink() ) { return new WP_Error( 'tc_storage_unsafe', 'Storage contains unsafe entries or needs administrator maintenance.' ); }
				if ( $entry->isFile() ) { $size += $entry->getSize(); }
			}
		} catch ( UnexpectedValueException $error ) { return new WP_Error( 'tc_storage_usage', 'Storage usage could not be verified.' ); }
		return $size;
	}

	/** Append-only database audit; no paths, tokens, or file contents. */
	private static function audit( int $config_id, string $action, string $file_id, array $details = array() ): void {
		add_post_meta( $config_id, '_tc_private_file_audit', array( 'at' => gmdate( 'c' ), 'actor' => get_current_user_id(), 'action' => $action, 'file_id' => $file_id, 'details' => $details ) );
	}

	private static function authorise( int $config_id ): bool {
		$owner_context = class_exists( 'TC_Owner_Editor' ) && TC_Owner_Editor::in_authorized_context();
		return 'tc_config' === get_post_type( $config_id ) && ( current_user_can( 'manage_options' ) || $owner_context ) && current_user_can( 'edit_post', $config_id );
	}

	/** Server-only adapter; frontend callers must establish the verified, nonce-protected Owner context. */
	public static function manage( int $config_id, string $operation, array $request, mixed $incoming = null ): bool|WP_Error {
		return self::mutate( $config_id, $operation, $request, $incoming );
	}

	private static function uploads( mixed $input ): array|WP_Error {
		if ( null === $input ) { return array(); }
		if ( ! is_array( $input ) ) { return new WP_Error( 'tc_upload_shape', 'Malformed upload request.' ); }
		foreach ( array( 'name', 'error', 'size', 'tmp_name' ) as $key ) { if ( ! isset( $input[ $key ] ) || ! is_array( $input[ $key ] ) ) { return new WP_Error( 'tc_upload_shape', 'Malformed upload request.' ); } }
		$keys = array_keys( $input['name'] );
		if ( count( $keys ) > self::MAX_PER_UPLOAD ) { return new WP_Error( 'tc_upload_count', 'Upload at most 10 files in one operation.' ); }
		foreach ( array( 'error', 'size', 'tmp_name' ) as $key ) { if ( $keys !== array_keys( $input[ $key ] ) ) { return new WP_Error( 'tc_upload_shape', 'Upload fields do not match.' ); } }
		$result = array();
		foreach ( $keys as $key ) {
			if ( ! is_int( $input['error'][ $key ] ) || ! is_int( $input['size'][ $key ] ) || ! is_string( $input['tmp_name'][ $key ] ) || ! is_string( $input['name'][ $key ] ) ) { return new WP_Error( 'tc_upload_shape', 'Upload values have invalid types.' ); }
			if ( UPLOAD_ERR_NO_FILE === $input['error'][ $key ] ) { continue; }
			if ( UPLOAD_ERR_OK !== $input['error'][ $key ] ) { return new WP_Error( 'tc_upload_failed', 'PHP rejected an upload. Check the host upload limits and retry.' ); }
			if ( ! is_uploaded_file( $input['tmp_name'][ $key ] ) ) { return new WP_Error( 'tc_upload_http', 'Only files received through a real HTTP upload are accepted.' ); }
			$inspection = self::inspect( $input['tmp_name'][ $key ], $input['name'][ $key ] );
			if ( is_wp_error( $inspection ) ) { return $inspection; }
			$result[] = array_merge( $inspection, array( 'temporary' => $input['tmp_name'][ $key ] ) );
		}
		return $result;
	}

	/** All mutation entry points pass capability/nonce first; this method stays server-only. */
	private static function mutate( int $config_id, string $operation, array $request, mixed $incoming ): bool|WP_Error {
		if ( ! self::authorise( $config_id ) ) { return new WP_Error( 'tc_private_forbidden', 'Administrator permission and config edit access are required.' ); }
		if ( ! in_array( $operation, array( 'upload', 'update', 'replace', 'delete' ), true ) ) { return new WP_Error( 'tc_private_operation', 'Unknown private-file operation.' ); }
		if ( in_array( $operation, array( 'replace', 'delete' ), true ) && '1' !== ( $request['confirm'] ?? '' ) ) { return new WP_Error( 'tc_private_confirm', 'Confirm this file replacement or deletion before continuing.' ); }
		$uploads = in_array( $operation, array( 'upload', 'replace' ), true ) ? self::uploads( $incoming ) : array();
		if ( is_wp_error( $uploads ) ) { return $uploads; }
		if ( 'replace' === $operation && 1 !== count( $uploads ) ) { return new WP_Error( 'tc_private_replace', 'Choose exactly one replacement file.' ); }
		if ( 'upload' === $operation && ! $uploads ) { return new WP_Error( 'tc_private_empty', 'Choose at least one script file.' ); }
		return self::locked( static function ( string $root ) use ( $config_id, $operation, $request, $uploads ): bool|WP_Error {
			$records = self::records( $config_id );
			if ( is_wp_error( $records ) ) { return $records; }
			$index = null;
			$file_id = is_string( $request['file_id'] ?? null ) ? $request['file_id'] : '';
			if ( 'upload' !== $operation ) {
				if ( ! self::uuid( $file_id ) ) { return new WP_Error( 'tc_file_missing', 'This private file is unavailable.' ); }
				foreach ( $records as $key => $record ) { if ( ( $record['id'] ?? null ) === $file_id ) { if ( null !== $index ) { return new WP_Error( 'tc_storage_metadata', 'Duplicate private-file IDs require administrator repair.' ); } $index = $key; } }
				if ( null === $index ) { return new WP_Error( 'tc_file_missing', 'This private file is unavailable.' ); }
			}
			if ( 'update' === $operation ) {
				$records[ $index ]['version'] = self::text( $request['version'] ?? '', 80 );
				$records[ $index ]['notes'] = self::text( $request['notes'] ?? '', 2000, true );
				$order = $request['order'] ?? '0';
				$records[ $index ]['order'] = is_scalar( $order ) ? min( 9999, max( 0, (int) $order ) ) : 0;
				if ( ! update_post_meta( $config_id, self::META_KEY, $records ) && self::records( $config_id ) !== $records ) { return new WP_Error( 'tc_storage_save', 'File details could not be saved.' ); }
				self::audit( $config_id, 'metadata-updated', $file_id );
				return true;
			}
			if ( 'delete' === $operation ) {
				$path = self::raw_path( $root, $config_id, $records[ $index ] );
				if ( is_wp_error( $path ) ) { return $path; }
				$old = $records;
				array_splice( $records, $index, 1 );
				if ( ! update_post_meta( $config_id, self::META_KEY, $records ) ) { return new WP_Error( 'tc_storage_save', 'The file record could not be deleted.' ); }
				if ( is_file( $path ) && ! unlink( $path ) ) { update_post_meta( $config_id, self::META_KEY, $old ); return new WP_Error( 'tc_storage_delete', 'The stored file could not be deleted; its record was restored.' ); }
				self::audit( $config_id, 'deleted', $file_id );
				return true;
			}
			$usage = self::usage( $root );
			if ( is_wp_error( $usage ) ) { return $usage; }
			$directory = $root . DIRECTORY_SEPARATOR . $config_id;
			if ( ! is_dir( $directory ) && ! mkdir( $directory, 0700 ) ) { return new WP_Error( 'tc_storage_directory', 'The private config directory could not be created.' ); }
			if ( is_link( $directory ) || realpath( $directory ) !== $directory || ! chmod( $directory, 0700 ) ) { return new WP_Error( 'tc_storage_unsafe', 'The config directory failed its privacy checks.' ); }
			$config_usage = self::usage( $directory );
			if ( is_wp_error( $config_usage ) ) { return $config_usage; }
			$new_bytes = array_sum( array_column( $uploads, 'size' ) );
			if ( count( $records ) + count( $uploads ) - ( 'replace' === $operation ? 1 : 0 ) > self::MAX_FILES || $config_usage + $new_bytes > self::CONFIG_QUOTA || $usage + $new_bytes > self::STORAGE_QUOTA ) { return new WP_Error( 'tc_storage_quota', 'Private storage quota reached: 100 files / 100 MB per config and 1 GB across the library.' ); }
			$old_path = 'replace' === $operation ? self::raw_path( $root, $config_id, $records[ $index ] ) : null;
			if ( is_wp_error( $old_path ) ) { return $old_path; }
			$created = array();
			$new_records = $records;
			foreach ( $uploads as $upload ) {
				$id = wp_generate_uuid4();
				$stored_name = $id . '.' . $upload['extension'];
				$path = $directory . DIRECTORY_SEPARATOR . $stored_name;
				if ( file_exists( $path ) ) { foreach ( $created as $created_path ) { unlink( $created_path ); } return new WP_Error( 'tc_storage_collision', 'A storage identifier collision occurred; existing files were preserved.' ); }
				if ( ! move_uploaded_file( $upload['temporary'], $path ) || ! chmod( $path, 0600 ) ) { foreach ( $created as $created_path ) { unlink( $created_path ); } if ( is_file( $path ) ) { unlink( $path ); } return new WP_Error( 'tc_storage_move', 'The upload could not be stored; no file records were changed.' ); }
				$created[] = $path;
				$new = array( 'id' => $id, 'stored_name' => $stored_name, 'name' => $upload['name'], 'size' => $upload['size'], 'checksum' => $upload['checksum'], 'inspection' => $upload['inspection'], 'uploaded_at' => current_time( 'mysql', true ), 'version' => self::text( $request['version'] ?? '', 80 ), 'notes' => self::text( $request['notes'] ?? '', 2000, true ), 'order' => count( $new_records ) );
				if ( 'replace' === $operation ) { $new['order'] = $records[ $index ]['order'] ?? $index; $new_records[ $index ] = $new; } else { $new_records[] = $new; }
			}
			if ( ! update_post_meta( $config_id, self::META_KEY, $new_records ) ) { foreach ( $created as $path ) { unlink( $path ); } return new WP_Error( 'tc_storage_save', 'File records could not be saved; new files were removed.' ); }
			if ( is_string( $old_path ) && is_file( $old_path ) && ! unlink( $old_path ) ) { update_post_meta( $config_id, self::META_KEY, $records ); foreach ( $created as $path ) { unlink( $path ); } return new WP_Error( 'tc_storage_replace', 'The old file could not be removed; its original record was restored.' ); }
			foreach ( $new_records as $record ) { if ( in_array( $directory . DIRECTORY_SEPARATOR . ( $record['stored_name'] ?? '' ), $created, true ) ) { self::audit( $config_id, 'replace' === $operation ? 'replaced' : 'uploaded', $record['id'], array( 'replaced_id' => 'replace' === $operation ? $file_id : '', 'bytes' => $record['size'], 'checksum' => $record['checksum'], 'inspection' => $record['inspection'] ) ); } }
			return true;
		} );
	}

	/** Management may remove an unsupported legacy archive, but never follow an arbitrary path. */
	private static function raw_path( string $root, int $config_id, array $record ): string|WP_Error {
		$id = $record['id'] ?? '';
		$name = $record['stored_name'] ?? '';
		$directory = $root . DIRECTORY_SEPARATOR . $config_id;
		if ( ! self::uuid( $id ) || ! is_string( $name ) || ! preg_match( '/^' . preg_quote( $id, '/' ) . '\.[a-z0-9]{1,8}$/D', $name ) || is_link( $directory ) || realpath( $directory ) !== $directory ) { return new WP_Error( 'tc_file_unsafe', 'The stored file location failed its containment checks.' ); }
		$path = $directory . DIRECTORY_SEPARATOR . $name;
		if ( is_link( $path ) || ( file_exists( $path ) && ( ! is_file( $path ) || realpath( $path ) !== $path ) ) ) { return new WP_Error( 'tc_file_unsafe', 'The stored file failed its containment checks.' ); }
		return $path;
	}

	public static function save( int $post_id, WP_Post $post ): void {
		if ( 'tc_config' !== $post->post_type || ! self::authorise( $post_id ) || wp_is_post_revision( $post_id ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) { return; }
		$nonce = $_POST[ self::NONCE ] ?? null;
		if ( ! is_string( $nonce ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $nonce ) ), 'tc_private_files_' . $post_id ) || ! isset( $_FILES['tc_private_files'] ) ) { return; }
		$result = self::mutate( $post_id, 'upload', array(), $_FILES['tc_private_files'] );
		self::feedback( $result );
	}

	private static function feedback( bool|WP_Error $result ): void {
		set_transient( 'tc_private_notice_' . get_current_user_id(), array( 'error' => is_wp_error( $result ), 'message' => is_wp_error( $result ) ? $result->get_error_message() : 'Private files updated successfully.' ), 120 );
	}

	public static function notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		$notice = get_transient( 'tc_private_notice_' . get_current_user_id() );
		if ( ! is_array( $notice ) ) { return; }
		delete_transient( 'tc_private_notice_' . get_current_user_id() );
		echo '<div class="notice ' . ( ! empty( $notice['error'] ) ? 'notice-error' : 'notice-success' ) . '"><p>' . esc_html( $notice['message'] ?? '' ) . '</p></div>';
	}

	public static function handle(): void {
		$id = isset( $_POST['config_id'] ) && is_scalar( $_POST['config_id'] ) ? absint( $_POST['config_id'] ) : 0;
		if ( ! self::authorise( $id ) ) { wp_die( 'Administrator permission and config edit access are required.', '', array( 'response' => 403 ) ); }
		if ( ! isset( $_POST[ self::NONCE ] ) || ! is_string( $_POST[ self::NONCE ] ) ) { wp_die( 'A valid private-file form nonce is required.', '', array( 'response' => 403 ) ); }
		check_admin_referer( 'tc_private_files_' . $id, self::NONCE );
		$operation = isset( $_POST['operation'] ) && is_string( $_POST['operation'] ) ? sanitize_key( $_POST['operation'] ) : '';
		$request = wp_unslash( $_POST );
		$result = self::mutate( $id, $operation, $request, $_FILES['tc_private_files'] ?? null );
		self::feedback( $result );
		wp_safe_redirect( admin_url( 'edit.php?post_type=tc_config&page=tc-private-files&config_id=' . $id ) );
		exit;
	}

	public static function render( WP_Post $post ): void {
		if ( ! self::authorise( $post->ID ) ) { return; }
		wp_nonce_field( 'tc_private_files_' . $post->ID, self::NONCE );
		$root = self::root();
		if ( is_wp_error( $root ) ) { echo '<p><strong>Private uploads disabled.</strong> ' . esc_html( $root->get_error_message() ) . '</p>'; }
		echo '<p>Manage actual script attachments separately from public artwork. Private paths and permanent download URLs are never exposed.</p><p><a class="button" href="' . esc_url( admin_url( 'edit.php?post_type=tc_config&page=tc-private-files&config_id=' . $post->ID ) ) . '">Manage private files</a></p>';
		foreach ( self::files( $post->ID ) as $file ) { echo '<p>' . esc_html( $file['name'] ) . ' — ' . esc_html( $file['version'] ) . '</p>'; }
	}

	private static function form_start( int $id, string $operation, string $file_id = '' ): void {
		echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="tc_private_files_manage"><input type="hidden" name="config_id" value="' . esc_attr( (string) $id ) . '"><input type="hidden" name="operation" value="' . esc_attr( $operation ) . '"><input type="hidden" name="file_id" value="' . esc_attr( $file_id ) . '">';
		wp_nonce_field( 'tc_private_files_' . $id, self::NONCE );
	}

	public static function page(): void {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		$id = isset( $_GET['config_id'] ) && is_scalar( $_GET['config_id'] ) ? absint( $_GET['config_id'] ) : 0;
		echo '<div class="wrap"><h1>Private script files</h1>';
		if ( ! self::authorise( $id ) ) { echo '<p>Open a configuration in the editor, then choose <strong>Manage private files</strong>.</p></div>'; return; }
		echo '<h2>' . esc_html( get_the_title( $id ) ) . '</h2><p>Files are stored outside the public website. Archives and executables are disabled. Limits: 10 files per upload, 20 MB per file, 100 files / 100 MB per config, 1 GB total. Structural checks are not a malware-free certification; ZNBK is validated automatically, including ZIP structure when present. XIMMR requires an approved format inspector. A configured optional scanner may veto uploads.</p>';
		$root = self::root();
		if ( is_wp_error( $root ) ) { echo '<div class="notice notice-error inline"><p>' . esc_html( $root->get_error_message() ) . '</p></div></div>'; return; }
		self::form_start( $id, 'upload' );
		echo '<h3>Add script files</h3><p><label>Files <input type="file" name="tc_private_files[]" accept="' . esc_attr( self::accept() ) . '" multiple required></label></p><p><label>Version <input name="version" maxlength="80"></label></p><p><label>Notes <textarea name="notes" maxlength="2000"></textarea></label></p>';
		submit_button( 'Upload private files', 'primary', 'submit', false ); echo '</form><hr>';
		$records = self::records( $id );
		if ( is_wp_error( $records ) ) { echo '<p>' . esc_html( $records->get_error_message() ) . '</p></div>'; return; }
		usort( $records, static fn( array $a, array $b ): int => ( (int) ( $a['order'] ?? 0 ) <=> (int) ( $b['order'] ?? 0 ) ) ?: strcmp( (string) ( $a['id'] ?? '' ), (string) ( $b['id'] ?? '' ) ) );
		foreach ( $records as $index => $file ) {
			if ( ! self::uuid( $file['id'] ?? null ) ) { echo '<p>A legacy record needs administrator repair.</p>'; continue; }
			echo '<section style="padding:16px;background:#fff;margin:16px 0;border:1px solid #ccd0d4"><h3>' . esc_html( self::text( $file['name'] ?? 'Unnamed file', 200 ) ) . '</h3><p>Uploaded: ' . esc_html( self::text( $file['uploaded_at'] ?? '', 30 ) ) . ' · Size: ' . esc_html( size_format( max( 0, (int) ( $file['size'] ?? 0 ) ) ) ) . '</p><p>SHA-256: <code>' . esc_html( self::text( $file['checksum'] ?? '', 64 ) ?: 'Not recorded for this legacy file' ) . '</code></p>';
			self::form_start( $id, 'update', $file['id'] );
			echo '<p><label>Order <input type="number" name="order" min="0" max="9999" value="' . esc_attr( (string) ( $file['order'] ?? $index ) ) . '"></label> <label>Version <input name="version" maxlength="80" value="' . esc_attr( self::text( $file['version'] ?? '', 80 ) ) . '"></label></p><p><label>Notes <textarea name="notes" maxlength="2000">' . esc_textarea( self::text( $file['notes'] ?? '', 2000, true ) ) . '</textarea></label></p>';
			submit_button( 'Save file details', 'secondary', 'submit', false ); echo '</form>';
			self::form_start( $id, 'replace', $file['id'] );
			echo '<p><label>Replacement <input type="file" name="tc_private_files[]" accept="' . esc_attr( self::accept() ) . '" required></label> <label>New version <input name="version" maxlength="80" value="' . esc_attr( self::text( $file['version'] ?? '', 80 ) ) . '"></label></p><p><label>Notes <textarea name="notes" maxlength="2000">' . esc_textarea( self::text( $file['notes'] ?? '', 2000, true ) ) . '</textarea></label></p><p><label><input type="checkbox" name="confirm" value="1" required> I confirm replacing this file. Its old download ID will be revoked.</label></p>';
			submit_button( 'Replace file', 'secondary', 'submit', false ); echo '</form>';
			self::form_start( $id, 'delete', $file['id'] );
			echo '<p><label><input type="checkbox" name="confirm" value="1" required> I confirm permanently deleting this file.</label></p>';
			submit_button( 'Delete file', 'delete', 'submit', false ); echo '</form></section>';
		}
		echo '</div>';
	}
}

<?php
/**
 * File: Cache_File_Generic.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Cache_File_Generic
 *
 * Disk:Enhanced file cache
 *
 * phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
 * phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
 * phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
 * phpcs:disable Squiz.Strings.DoubleQuoteUsage.NotRequired
 * phpcs:disable WordPress.WP.AlternativeFunctions
 */
class Cache_File_Generic extends Cache_File {
	/**
	 * Expire
	 *
	 * @var integer
	 */
	private $_expire = 0;

	/**
	 * PHP5-style constructor
	 *
	 * @param Config $config Config.
	 *
	 * @return void
	 */
	public function __construct( $config = array() ) {
		parent::__construct( $config );

		$this->_expire = ( isset( $config['expire'] ) ? (int) $config['expire'] : 0 );

		if ( ! $this->_expire || $this->_expire > W3TC_CACHE_FILE_EXPIRE_MAX ) {
			$this->_expire = W3TC_CACHE_FILE_EXPIRE_MAX;
		}
	}

	/**
	 * Sets data
	 *
	 * @param string $key    Key.
	 * @param string $value  Value.
	 * @param int    $expire Time to expire.
	 * @param string $group  Used to differentiate between groups of cache values.
	 *
	 * @return boolean
	 */
	public function set( $key, $value, $expire = 0, $group = '' ) {
		$key      = $this->get_item_key( $key );
		$sub_path = $this->_get_path( $key, $group );
		$path     = $this->_cache_dir . DIRECTORY_SEPARATOR . $sub_path;

		$dir = dirname( $path );

		if ( ! @is_dir( $dir ) ) {
			if ( ! Util_File::mkdir_from_safe( $dir, dirname( W3TC_CACHE_DIR ) ) ) {
				return false;
			}
		}

		$tmppath = $path . '.' . getmypid();

		$fp = @fopen( $tmppath, 'wb' );
		if ( ! $fp ) {
			return false;
		}

		if ( $this->_locking ) {
			@flock( $fp, LOCK_EX );
		}

		@fputs( $fp, $value['content'] );
		@fclose( $fp );

		$chmod = 0644;
		if ( defined( 'FS_CHMOD_FILE' ) ) {
			$chmod = FS_CHMOD_FILE;
		}

		@chmod( $tmppath, $chmod );

		if ( $this->_locking ) {
			@flock( $fp, LOCK_UN );
		}

		// some hostings create files with restrictive permissions not allowing apache to read it later.
		@chmod( $path, 0644 );

		if ( @filesize( $tmppath ) > 0 ) {
			@unlink( $path );
			@rename( $tmppath, $path );
		}

		@unlink( $tmppath );

		$old_entry_path = $path . '_old';
		@unlink( $old_entry_path );

		if ( Util_Environment::is_apache() && isset( $value['headers'] ) ) {
			$rules = '';

			if ( isset( $value['headers']['Content-Type'] ) && 'text/xml' === substr( $value['headers']['Content-Type'], 0, 8 ) ) {

				$rules .= "<IfModule mod_mime.c>\n";
				$rules .= "    RemoveType .html_gzip\n";
				$rules .= "    AddType text/xml .html_gzip\n";
				$rules .= "    RemoveType .html\n";
				$rules .= "    AddType text/xml .html\n";
				$rules .= "</IfModule>\n";
			}

			if ( isset( $value['headers'] ) ) {
				$headers = array();
				foreach ( $value['headers'] as $h ) {
					if ( isset( $h['n'] ) && isset( $h['v'] ) ) {
						$h2 = apply_filters( 'w3tc_pagecache_set_header', $h, $h, 'file_generic' );

						if ( ! empty( $h2 ) ) {
							$name_escaped = $this->escape_header_name( $h2['n'] );
							if ( ! isset( $headers[ $name_escaped ] ) ) {
								$headers[ $name_escaped ] = array(
									'values'      => array(),
									'files_match' => $h2['files_match'],
								);
							}

							$value_escaped = $this->escape_header_value( $h2['v'] );
							if ( ! empty( $value_escaped ) ) {
								$headers[ $name_escaped ]['values'][] =
									"        Header add " .
									$name_escaped .
									" '" . $value_escaped . "'\n";
							}
						}
					}
				}

				$header_rules = '';
				foreach ( $headers as $name_escaped => $value ) {
					// Link header doesnt apply to .xml assets.
					$header_rules .= '    <FilesMatch "' . $value['files_match'] . "\">\n";
					$header_rules .= "        Header unset $name_escaped\n";
					$header_rules .= implode( "\n", $value['values'] );
					$header_rules .= "    </FilesMatch>\n";
				}

				if ( ! empty( $header_rules ) ) {
					$rules .= "<IfModule mod_headers.c>\n";
					$rules .= $header_rules;
					$rules .= "</IfModule>\n";
				}
			}

			if ( ! empty( $rules ) ) {
				$htaccess_path = dirname( $path ) . DIRECTORY_SEPARATOR . '.htaccess';

				@file_put_contents( $htaccess_path, $rules );

				$chmod = 0644;
				if ( defined( 'FS_CHMOD_FILE' ) ) {
					$chmod = FS_CHMOD_FILE;
				}

				@chmod( $htaccess_path, $chmod );
			}
		}

		return true;
	}

	/**
	 * Escape header name
	 *
	 * @param string $v Value.
	 *
	 * @return array
	 */
	private function escape_header_name( $v ) {
		return preg_replace( '~[^0-9A-Za-z\-]~m', '_', $v );
	}

	/**
	 * Escape header value
	 *
	 * @param string $v Value.
	 *
	 * @return array
	 */
	private function escape_header_value( $v ) {
		return str_replace(
			"'",
			"\\'",
			str_replace(
				"\\",
				"\\\\\\", // htaccess need escape of \ to \\\.
				preg_replace( '~[\r\n]~m', '_', trim( $v ) )
			)
		);
	}

	/**
	 * Returns data
	 *
	 * @param string $key   Key.
	 * @param string $group Used to differentiate between groups of cache values.
	 *
	 * @return array
	 */
	public function get_with_old( $key, $group = '' ) {
		$has_old_data = false;
		$key          = $this->get_item_key( $key );
		$path         = $this->_cache_dir . DIRECTORY_SEPARATOR . $this->_get_path( $key, $group );

		$data = $this->_read( $path );
		if ( null !== $data ) {
			return array( $data, $has_old_data );
		}

		$path_old     = $path . '_old';
		$too_old_time = time() - 30;

		$exists = file_exists( $path_old );
		if ( $exists ) {
			$file_time = @filemtime( $path_old );
			if ( $file_time ) {
				if ( $file_time > $too_old_time ) {
					// return old data.
					$has_old_data = true;
					return array( $this->_read( $path_old ), $has_old_data );

				}

				// use old enough time to cause recalculation on next call.
				@touch( $path_old, 1479904835 );
			}
		}
		$has_old_data = $exists;

		return array( null, $has_old_data );
	}

	/**
	 * Reads file
	 *
	 * @param string $path Path.
	 *
	 * @return array
	 */
	private function _read( $path ) {
		if ( ! is_readable( $path ) ) {
			return null;
		}

		// make sure reading from cache folder canonicalize to avoid unexpected variants.
		$base_path = realpath( $this->_cache_dir );
		$path      = realpath( $path );

		if ( strlen( $base_path ) <= 0 || substr( $path, 0, strlen( $base_path ) ) !== $base_path ) {
			return null;
		}

		$fp = @fopen( $path, 'rb' );
		if ( ! $fp ) {
			return null;
		}

		if ( $this->_locking ) {
			@flock( $fp, LOCK_SH );
		}

		$var = '';

		while ( ! @feof( $fp ) ) {
			$var .= @fread( $fp, 4096 );
		}

		@fclose( $fp );

		if ( $this->_locking ) {
			@flock( $fp, LOCK_UN );
		}

		$headers = array();
		if ( '.xml' === substr( $path, -4 ) ) {
			$headers['Content-type'] = 'text/xml';
		}

		return array(
			'404'     => false,
			'headers' => $headers,
			'time'    => null,
			'content' => $var,
		);
	}

	/**
	 * Deletes data
	 *
	 * @param string $key   Key.
	 * @param string $group Used to differentiate between groups of cache values.
	 *
	 * @return boolean
	 */
	public function delete( $key, $group = '' ) {
		$key  = $this->get_item_key( $key );
		$path = $this->_cache_dir . DIRECTORY_SEPARATOR . $this->_get_path( $key, $group );

		if ( ! file_exists( $path ) ) {
			return true;
		}

		$dir = dirname( $path );
		if ( file_exists( $dir . DIRECTORY_SEPARATOR . '.htaccess' ) ) {
			@unlink( $dir . DIRECTORY_SEPARATOR . '.htaccess' );
		}

		$old_entry_path = $path . '_old';
		if ( ! @rename( $path, $old_entry_path ) ) {
			// if we can delete old entry - do second attempt to store in old-entry file.
			if ( ! @unlink( $old_entry_path ) || ! @rename( $path, $old_entry_path ) ) {
				return @unlink( $path );
			}
		}

		/**
		 * Disabling this as we don't want to immediately hard-expire _old cache files as there is a
		 * 30 second window where they are still served via get_with_old calls. During AWS testing on
		 * WP 5.9/6.3 this was resulting in the _old file immediately being removed during the clean
		 * operation, resulting in failed automated tests (8/1/2023)
		 */
		// @touch( $old_entry_path, 1479904835 ); phpcs:ignore Squiz.PHP.CommentedOutCode.Found
		return true;
	}

	/**
	 * Checks if entry exists
	 *
	 * @param string $key Key.
	 * @param string $group Used to differentiate between groups of cache values.
	 * @return boolean true if exists, false otherwise
	 */
	public function exists( $key, $group = '' ) {
		$key  = $this->get_item_key( $key );
		$path = $this->_cache_dir . DIRECTORY_SEPARATOR . $this->_get_path( $key, $group );

		return file_exists( $path );
	}

	/**
	 * Key to delete, deletes _old and primary if exists.
	 *
	 * @param string $key   Key.
	 * @param string $group Group.
	 *
	 * @return bool
	 */
	public function hard_delete( $key, $group = '' ) {
		$key            = $this->get_item_key( $key );
		$path           = $this->_cache_dir . DIRECTORY_SEPARATOR . $this->_get_path( $key, $group );
		$old_entry_path = $path . '_old';
		@unlink( $old_entry_path );

		if ( ! file_exists( $path ) ) {
			return true;
		}

		@unlink( $path );

		return true;
	}

	/**
	 * Flushes all data
	 *
	 * @param string $group Used to differentiate between groups of cache values.
	 *
	 * @return boolean
	 */
	public function flush( $group = '' ) {
		if ( 'sitemaps' === $group ) {
			$config        = Dispatcher::config();
			$sitemap_regex = $config->get_string( 'pgcache.purge.sitemap_regex' );
			$this->_flush_based_on_regex( $sitemap_regex );
		} else {
			$dir = $this->_flush_dir;
			if ( ! empty( $group ) ) {
				$c = new Cache_File_Cleaner_Generic_HardDelete(
					array(
						'cache_dir'       => $this->_flush_dir . DIRECTORY_SEPARATOR . $group,
						'exclude'         => $this->_exclude,
						'clean_timelimit' => $this->_flush_timelimit,
					)
				);
			} else {
				$c = new Cache_File_Cleaner_Generic(
					array(
						'cache_dir'       => $this->_flush_dir,
						'exclude'         => $this->_exclude,
						'clean_timelimit' => $this->_flush_timelimit,
					)
				);
			}

			$c->clean();
		}

		return true;
	}

	/**
	 * Gets a key extension for "ahead generation" mode.
	 * Used by AlwaysCached functionality to regenerate content
	 *
	 * @param string $group Used to differentiate between groups of cache values.
	 *
	 * @return array
	 */
	public function get_ahead_generation_extension( $group ) {
		return array(
			'before_time' => time(),
		);
	}

	/**
	 * Flushes group with before condition
	 *
	 * @param string $group Used to differentiate between groups of cache values.
	 * @param array  $extension Used to set a condition what version to flush.
	 *
	 * @return void
	 */
	public function flush_group_after_ahead_generation( $group, $extension ) {
		$dir = $this->_flush_dir;
		if ( ! empty( $group ) ) {
			$c = new Cache_File_Cleaner_Generic_HardDelete(
				array(
					'cache_dir'       => $this->_flush_dir . DIRECTORY_SEPARATOR . $group,
					'exclude'         => $this->_exclude,
					'clean_timelimit' => $this->_flush_timelimit,
					'time_min_valid'  => $extension['before_time'],
				)
			);
		} else {
			$c = new Cache_File_Cleaner_Generic(
				array(
					'cache_dir'       => $this->_flush_dir,
					'exclude'         => $this->_exclude,
					'clean_timelimit' => $this->_flush_timelimit,
					'time_min_valid'  => $extension['before_time'],
				)
			);
		}

		$c->clean();
	}

	/**
	 * Returns cache file path by key
	 *
	 * @param string $key   Key.
	 * @param string $group Group.
	 *
	 * @return string
	 */
	public function _get_path( $key, $group = '' ) {
		return ( empty( $group ) ? '' : $group . DIRECTORY_SEPARATOR ) . $key;
	}

	/**
	 * Returns item key
	 *
	 * @param string $key Key.
	 *
	 * @return string
	 */
	public function get_item_key( $key ) {
		return $key;
	}

	/**
	 * Flush cache based on regex
	 *
	 * @param string $regex Regex.
	 *
	 * @return void
	 */
	private function _flush_based_on_regex( $regex ) {
		if ( Util_Environment::is_wpmu() && ! Util_Environment::is_wpmu_subdomain() ) {
			$domain    = get_home_url();
			$parsed    = wp_parse_url( $domain );
			$host      = $parsed['host'];
			$path      = isset( $parsed['path'] ) ? '/' . trim( $parsed['path'], '/' ) : '';
			$flush_dir = W3TC_CACHE_PAGE_ENHANCED_DIR . DIRECTORY_SEPARATOR . $host . $path;
		} else {
			$flush_dir = W3TC_CACHE_PAGE_ENHANCED_DIR . DIRECTORY_SEPARATOR . Util_Environment::host();
		}

		$dir = @opendir( $flush_dir );
		if ( $dir ) {
			$entry = @readdir( $dir );
			while ( false !== $entry ) {
				if ( '.' === $entry || '..' === $entry ) {
					$entry = @readdir( $dir );
					continue;
				}

				if ( preg_match( '~' . $regex . '~', basename( $entry ) ) ) {
					Util_File::rmdir( $flush_dir . DIRECTORY_SEPARATOR . $entry );
				}

				$entry = @readdir( $dir );
			}

			@closedir( $dir );
		}
	}
}

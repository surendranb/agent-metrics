<?php
defined( 'ABSPATH' ) || exit;

class AM_Prober {

	public static function probe() {
		$diag       = array();
		$candidates = self::candidates();
		foreach ( $candidates as $label => $dir ) {
			if ( is_file( $dir ) ) {
				$files = array( $dir );
			} elseif ( is_dir( $dir ) ) {
				$files = glob( rtrim( $dir, '/' ) . '/access*' );
			} else {
				$diag[] = array(
					'label'  => $label,
					'path'   => $dir,
					'status' => 'not_found',
				);
				continue;
			}
			if ( ! $files ) {
				$diag[] = array(
					'label'  => $label,
					'path'   => $dir,
					'status' => 'not_found',
				);
				continue;
			}
			usort(
				$files,
				function ( $a, $b ) {
					return @filemtime( $b ) <=> @filemtime( $a );
				}
			);
			foreach ( $files as $f ) {
				if ( ! is_readable( $f ) || @filesize( $f ) < 1 ) {
					continue;
				}
				$diag[] = array(
					'label'  => $label,
					'path'   => $f,
					'status' => 'ok',
				);
				return array(
					'path'        => $f,
					'error'       => null,
					'diagnostics' => $diag,
				);
			}
			$diag[] = array(
				'label'  => $label,
				'path'   => $dir,
				'status' => 'unreadable',
			);
		}
		return array(
			'path'        => false,
			'error'       => 'no readable log file found',
			'diagnostics' => $diag,
		);
	}

	private static function candidates() {
		$paths = array();
		if ( defined( 'AM_LOG_PATH' ) && AM_LOG_PATH ) {
			$paths['dev (AM_LOG_PATH)'] = AM_LOG_PATH;
		}
		if ( defined( 'AM_LOG_DIR' ) && AM_LOG_DIR ) {
			$paths['dev (AM_LOG_DIR)'] = AM_LOG_DIR;
		}
		$user = function_exists( 'get_current_user' ) ? get_current_user() : '';
		if ( $user ) {
			$paths[ 'cPanel /home/' . $user . '/logs' ] = '/home/' . $user . '/logs/';
		}
		$paths['nginx /var/log/nginx']     = '/var/log/nginx/';
		$paths['apache2 /var/log/apache2'] = '/var/log/apache2/';
		$paths['httpd /var/log/httpd']     = '/var/log/httpd/';
		return $paths;
	}
}

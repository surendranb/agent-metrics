<?php
defined( 'ABSPATH' ) || exit;

class Agent_Ready_Prober {

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
		if ( defined( 'AGENT_READY_LOG_PATH' ) && AGENT_READY_LOG_PATH ) {
			$paths['dev (AGENT_READY_LOG_PATH)'] = AGENT_READY_LOG_PATH;
		} elseif ( defined( 'AM_LOG_PATH' ) && AM_LOG_PATH ) {
			$paths['dev (AM_LOG_PATH)'] = AM_LOG_PATH;
		}
		if ( defined( 'AGENT_READY_LOG_DIR' ) && AGENT_READY_LOG_DIR ) {
			$paths['dev (AGENT_READY_LOG_DIR)'] = AGENT_READY_LOG_DIR;
		} elseif ( defined( 'AM_LOG_DIR' ) && AM_LOG_DIR ) {
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

class_alias( 'Agent_Ready_Prober', 'AM_Prober' );

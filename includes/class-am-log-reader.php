<?php
defined( 'ABSPATH' ) || exit;

class AM_Log_Reader {

	const MAX_BYTES = 2 * 1024 * 1024;
	const MAX_LINES = 5000;

	public static function read( $path ) {
		$lines = array();
		$gz    = '.gz' === strtolower( substr( $path, -3 ) );
		$h     = $gz ? @gzopen( $path, 'rb' ) : @fopen( $path, 'r' );
		if ( ! $h ) {
			return $lines;
		}

		$prev = "\n";
		$buf  = '';
		if ( $gz ) {
			while ( ! feof( $h ) ) {
				$chunk = gzread( $h, 65536 );
				if ( false === $chunk ) {
					break;
				}
				$buf .= $chunk;
				if ( strlen( $buf ) > self::MAX_BYTES + 65536 ) {
					$prev = $buf[ strlen( $buf ) - self::MAX_BYTES - 1 ];
					$buf  = substr( $buf, -self::MAX_BYTES );
				}
			}
			gzclose( $h );
		} else {
			$size = @filesize( $path );
			if ( $size > self::MAX_BYTES ) {
				if ( @fseek( $h, -self::MAX_BYTES - 1, SEEK_END ) === 0 ) {
					$prev = @fgetc( $h );
					fseek( $h, -self::MAX_BYTES, SEEK_END );
				}
			}
			while ( ! feof( $h ) ) {
				$line = fgets( $h, 65536 );
				if ( false === $line ) {
					break;
				}
				$buf .= $line;
			}
			fclose( $h );
		}

		if ( '' === $buf ) {
			return $lines;
		}
		$lines = explode( "\n", $buf );
		if ( '' === end( $lines ) ) {
			array_pop( $lines );
		}
		if ( "\n" !== $prev && count( $lines ) > 0 ) {
			array_shift( $lines );
		}
		return $lines;
	}

	public static function read_from( $path, $offset = 0 ) {
		$h = @fopen( $path, 'rb' );
		if ( ! $h ) {
			return array(
				'lines'  => array(),
				'offset' => $offset,
				'inode'  => null,
			);
		}
		$stat  = @fstat( $h );
		$size  = (int) ( $stat['size'] ?? 0 );
		$inode = (string) ( $stat['ino'] ?? '' );
		if ( $offset > $size ) {
			$offset = 0;
		}
		fseek( $h, $offset );
		$lines = array();
		$count = 0;
		while ( $count < self::MAX_LINES && false !== ( $line = fgets( $h, 65536 ) ) ) {
			$start = ftell( $h ) - strlen( $line );
			if ( "\n" !== substr( $line, -1 ) ) {
				fseek( $h, $start );
				break;
			}
			$lines[] = array(
				'line'   => $line,
				'offset' => $start,
			);
			$count++;
		}
		$next = ftell( $h );
		fclose( $h );
		return array(
			'lines'  => $lines,
			'offset' => $next,
			'inode'  => $inode,
		);
	}
}

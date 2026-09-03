<?php
defined( 'ABSPATH' ) || exit;

class AM_Parser {

	public static function parse( $line ) {
		$line = rtrim( (string) $line, "\r\n" );
		if ( '' === $line ) {
			return null;
		}
		if ( ! preg_match( '/\[([^\]]+)\]/', $line, $tm ) ) {
			return null;
		}
		$ts = self::parse_date( $tm[1] );
		if ( null === $ts ) {
			return null;
		}
		if ( ! preg_match( '/"([A-Z]+)\s+(\S+)\s+HTTP\/[\d.]+"/', $line, $rm ) ) {
			return null;
		}
		$status = 0;
		if ( preg_match( '/HTTP\/[\d.]+\"\s+(\d{3})/', $line, $sm ) ) {
			$status = (int) $sm[1];
		}
		if ( ! preg_match_all( '/"([^"]*)"/', $line, $qm ) || 0 === count( $qm[1] ) ) {
			return null;
		}
		return array(
			'ts'     => $ts,
			'method' => $rm[1],
			'path'   => $rm[2],
			'status' => $status,
			'ua'     => end( $qm[1] ),
		);
	}

	private static function parse_date( $raw ) {
		$d = DateTime::createFromFormat( 'd/M/Y:H:i:s O', $raw );
		if ( $d ) {
			return $d->getTimestamp();
		}
		try {
			$d = new DateTime( $raw );
			return $d->getTimestamp();
		} catch ( Exception $e ) {
			return null;
		}
	}
}

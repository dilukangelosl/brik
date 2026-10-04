<?php
namespace Brik;

defined( 'ABSPATH' ) || exit;

final class Fonts {

	/**
	 * Popular Google Fonts offered in the font picker. Any other family name still works
	 * if the site loads it.
	 */
	public static function google() {
		return apply_filters(
			'brik/google_fonts',
			array(
				'Inter'              => 'sans',
				'Geist'              => 'sans',
				'Roboto'             => 'sans',
				'Open Sans'          => 'sans',
				'Lato'               => 'sans',
				'Montserrat'         => 'sans',
				'Poppins'            => 'sans',
				'Raleway'            => 'sans',
				'Nunito'             => 'sans',
				'Nunito Sans'        => 'sans',
				'Work Sans'          => 'sans',
				'Rubik'              => 'sans',
				'Manrope'            => 'sans',
				'DM Sans'            => 'sans',
				'Plus Jakarta Sans'  => 'sans',
				'Outfit'             => 'sans',
				'Sora'               => 'sans',
				'Space Grotesk'      => 'sans',
				'Figtree'            => 'sans',
				'Onest'              => 'sans',
				'Urbanist'           => 'sans',
				'Lexend'             => 'sans',
				'Mulish'             => 'sans',
				'Barlow'             => 'sans',
				'Kanit'              => 'sans',
				'Archivo'            => 'sans',
				'Josefin Sans'       => 'sans',
				'Quicksand'          => 'sans',
				'Karla'              => 'sans',
				'IBM Plex Sans'      => 'sans',
				'Source Sans 3'      => 'sans',
				'Noto Sans'          => 'sans',
				'Oswald'             => 'sans',
				'Bebas Neue'         => 'sans',
				'Anton'              => 'sans',
				'Syne'               => 'sans',
				'Playfair Display'   => 'serif',
				'Merriweather'       => 'serif',
				'Lora'               => 'serif',
				'PT Serif'           => 'serif',
				'Libre Baskerville'  => 'serif',
				'EB Garamond'        => 'serif',
				'Cormorant Garamond' => 'serif',
				'Crimson Pro'        => 'serif',
				'DM Serif Display'   => 'serif',
				'Instrument Serif'   => 'serif',
				'Fraunces'           => 'serif',
				'Source Serif 4'     => 'serif',
				'Noto Serif'         => 'serif',
				'JetBrains Mono'     => 'mono',
				'Fira Code'          => 'mono',
				'IBM Plex Mono'      => 'mono',
				'Geist Mono'         => 'mono',
				'Space Mono'         => 'mono',
				'Pacifico'           => 'display',
				'Caveat'             => 'display',
				'Dancing Script'     => 'display',
				'Lobster'            => 'display',
				'Permanent Marker'   => 'display',
			)
		);
	}

	public static function is_serif( $family ) {
		$list = self::google();
		return isset( $list[ $family ] ) && 'serif' === $list[ $family ];
	}

	/**
	 * Google Fonts stylesheet URL for the families used, or '' when none apply.
	 */
	public static function url( array $families ) {
		if ( ! Settings::get( 'google_fonts' ) ) {
			return '';
		}
		$google = self::google();
		$query  = array();
		foreach ( array_unique( $families ) as $family ) {
			$family = trim( $family, " \"'" );
			if ( isset( $google[ $family ] ) ) {
				$query[] = 'family=' . str_replace( ' ', '+', $family ) . ':wght@300;400;500;600;700;800';
			}
		}
		if ( ! $query ) {
			return '';
		}
		// Filtered so fonts can be self-hosted or skipped (see Perf\LocalFonts).
		return (string) apply_filters( 'brik/fonts_url', 'https://fonts.googleapis.com/css2?' . implode( '&', $query ) . '&display=swap', $families );
	}
}

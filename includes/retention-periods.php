<?php
/**
 * Country-specific data retention periods.
 *
 * @package UserSelfDelete
 * @since 2.0.0
 */

declare(strict_types=1);

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages country-specific retention periods for tax and legal compliance.
 *
 * @since 2.0.0
 */
final class User_Self_Delete_Retention_Periods {

	/**
	 * Get all countries with their retention periods.
	 *
	 * HOW TO ADD NEW COUNTRIES:
	 * ========================
	 * To add a new country, simply add a new entry to the array below with:
	 * - Country code (ISO 3166-1 alpha-2, e.g., 'FR', 'US', 'JP')
	 * - Country name
	 * - Retention period in years (based on tax/legal requirements)
	 * - Region for grouping in the admin UI
	 *
	 * Example:
	 * 'FR' => array(
	 *     'name'   => 'France',
	 *     'years'  => 10,
	 *     'region' => 'EU',
	 * ),
	 *
	 * Available regions: 'EU', 'EEA', 'UK', 'Europe', 'North America',
	 *                    'South America', 'Asia', 'Oceania', 'Middle East', 'Africa'
	 *
	 * Note: The retention period should reflect the longest legal requirement
	 * for keeping business/tax records in that country.
	 *
	 * @return array<string, array{name: string, years: int, region: string}> Country data.
	 */
	public static function get_countries(): array {
		return array(
			// EU Countries (alphabetically).
			'AT' => array(
				'name'   => 'Austria',
				'years'  => 7,
				'region' => 'EU',
			),
			'BE' => array(
				'name'   => 'Belgium',
				'years'  => 7,
				'region' => 'EU',
			),
			'BG' => array(
				'name'   => 'Bulgaria',
				'years'  => 5,
				'region' => 'EU',
			),
			'HR' => array(
				'name'   => 'Croatia',
				'years'  => 11,
				'region' => 'EU',
			),
			'CY' => array(
				'name'   => 'Cyprus',
				'years'  => 6,
				'region' => 'EU',
			),
			'CZ' => array(
				'name'   => 'Czech Republic',
				'years'  => 10,
				'region' => 'EU',
			),
			'DK' => array(
				'name'   => 'Denmark',
				'years'  => 5,
				'region' => 'EU',
			),
			'EE' => array(
				'name'   => 'Estonia',
				'years'  => 7,
				'region' => 'EU',
			),
			'FI' => array(
				'name'   => 'Finland',
				'years'  => 6,
				'region' => 'EU',
			),
			'FR' => array(
				'name'   => 'France',
				'years'  => 10,
				'region' => 'EU',
			),
			'DE' => array(
				'name'   => 'Germany',
				'years'  => 10,
				'region' => 'EU',
			),
			'GR' => array(
				'name'   => 'Greece',
				'years'  => 5,
				'region' => 'EU',
			),
			'HU' => array(
				'name'   => 'Hungary',
				'years'  => 8,
				'region' => 'EU',
			),
			'IE' => array(
				'name'   => 'Ireland',
				'years'  => 6,
				'region' => 'EU',
			),
			'IT' => array(
				'name'   => 'Italy',
				'years'  => 10,
				'region' => 'EU',
			),
			'LV' => array(
				'name'   => 'Latvia',
				'years'  => 5,
				'region' => 'EU',
			),
			'LT' => array(
				'name'   => 'Lithuania',
				'years'  => 10,
				'region' => 'EU',
			),
			'LU' => array(
				'name'   => 'Luxembourg',
				'years'  => 10,
				'region' => 'EU',
			),
			'MT' => array(
				'name'   => 'Malta',
				'years'  => 5,
				'region' => 'EU',
			),
			'NL' => array(
				'name'   => 'Netherlands',
				'years'  => 7,
				'region' => 'EU',
			),
			'PL' => array(
				'name'   => 'Poland',
				'years'  => 5,
				'region' => 'EU',
			),
			'PT' => array(
				'name'   => 'Portugal',
				'years'  => 10,
				'region' => 'EU',
			),
			'RO' => array(
				'name'   => 'Romania',
				'years'  => 10,
				'region' => 'EU',
			),
			'SK' => array(
				'name'   => 'Slovakia',
				'years'  => 10,
				'region' => 'EU',
			),
			'SI' => array(
				'name'   => 'Slovenia',
				'years'  => 10,
				'region' => 'EU',
			),
			'ES' => array(
				'name'   => 'Spain',
				'years'  => 6,
				'region' => 'EU',
			),
			'SE' => array(
				'name'   => 'Sweden',
				'years'  => 7,
				'region' => 'EU',
			),

			// EEA Countries (non-EU).
			'IS' => array(
				'name'   => 'Iceland',
				'years'  => 7,
				'region' => 'EEA',
			),
			'LI' => array(
				'name'   => 'Liechtenstein',
				'years'  => 10,
				'region' => 'EEA',
			),
			'NO' => array(
				'name'   => 'Norway',
				'years'  => 5,
				'region' => 'EEA',
			),

			// Post-Brexit UK.
			'GB' => array(
				'name'   => 'United Kingdom',
				'years'  => 6,
				'region' => 'UK',
			),

			// Other European Countries.
			'CH' => array(
				'name'   => 'Switzerland',
				'years'  => 10,
				'region' => 'Europe',
			),

			// North America.
			'US' => array(
				'name'   => 'United States',
				'years'  => 7,
				'region' => 'North America',
			),
			'CA' => array(
				'name'   => 'Canada',
				'years'  => 6,
				'region' => 'North America',
			),
			'MX' => array(
				'name'   => 'Mexico',
				'years'  => 5,
				'region' => 'North America',
			),

			// Oceania.
			'AU' => array(
				'name'   => 'Australia',
				'years'  => 5,
				'region' => 'Oceania',
			),
			'NZ' => array(
				'name'   => 'New Zealand',
				'years'  => 7,
				'region' => 'Oceania',
			),

			// Asia.
			'JP' => array(
				'name'   => 'Japan',
				'years'  => 7,
				'region' => 'Asia',
			),
			'SG' => array(
				'name'   => 'Singapore',
				'years'  => 5,
				'region' => 'Asia',
			),
			'HK' => array(
				'name'   => 'Hong Kong',
				'years'  => 7,
				'region' => 'Asia',
			),
			'KR' => array(
				'name'   => 'South Korea',
				'years'  => 5,
				'region' => 'Asia',
			),
			'IN' => array(
				'name'   => 'India',
				'years'  => 8,
				'region' => 'Asia',
			),

			// Middle East.
			'AE' => array(
				'name'   => 'United Arab Emirates',
				'years'  => 5,
				'region' => 'Middle East',
			),
			'IL' => array(
				'name'   => 'Israel',
				'years'  => 7,
				'region' => 'Middle East',
			),

			// South America.
			'BR' => array(
				'name'   => 'Brazil',
				'years'  => 5,
				'region' => 'South America',
			),
			'AR' => array(
				'name'   => 'Argentina',
				'years'  => 10,
				'region' => 'South America',
			),
			'CL' => array(
				'name'   => 'Chile',
				'years'  => 6,
				'region' => 'South America',
			),

			// Africa.
			'ZA' => array(
				'name'   => 'South Africa',
				'years'  => 5,
				'region' => 'Africa',
			),
		);
	}

	/**
	 * Get countries grouped by region.
	 *
	 * @return array<string, array<string, array{name: string, years: int}>> Countries grouped by region.
	 */
	public static function get_countries_by_region(): array {
		$countries = self::get_countries();
		$grouped   = array();

		foreach ( $countries as $code => $data ) {
			$region = $data['region'];
			if ( ! isset( $grouped[ $region ] ) ) {
				$grouped[ $region ] = array();
			}
			$grouped[ $region ][ $code ] = array(
				'name'  => $data['name'],
				'years' => $data['years'],
			);
		}

		// Sort regions for better UI.
		$order = array( 'EU', 'EEA', 'UK', 'Europe', 'North America', 'South America', 'Asia', 'Oceania', 'Middle East', 'Africa' );
		$sorted = array();
		foreach ( $order as $region ) {
			if ( isset( $grouped[ $region ] ) ) {
				$sorted[ $region ] = $grouped[ $region ];
			}
		}

		return $sorted;
	}

	/**
	 * Calculate maximum retention period from selected countries.
	 *
	 * @param array<string> $country_codes Selected country codes.
	 * @return int Maximum retention period in years.
	 */
	public static function calculate_max_retention( array $country_codes ): int {
		if ( empty( $country_codes ) ) {
			return 0; // No retention if no countries selected.
		}

		$countries = self::get_countries();
		$max_years = 0;

		foreach ( $country_codes as $code ) {
			if ( isset( $countries[ $code ] ) ) {
				$max_years = max( $max_years, $countries[ $code ]['years'] );
			}
		}

		return $max_years;
	}

	/**
	 * Get country names from selected codes.
	 *
	 * @param array<string> $country_codes Selected country codes.
	 * @return array<string> Country names.
	 */
	public static function get_country_names( array $country_codes ): array {
		$countries = self::get_countries();
		$names     = array();

		foreach ( $country_codes as $code ) {
			if ( isset( $countries[ $code ] ) ) {
				$names[] = $countries[ $code ]['name'];
			}
		}

		return $names;
	}

	/**
	 * Get countries with maximum retention period.
	 *
	 * @param array<string> $country_codes Selected country codes.
	 * @return array<string> Country names with max retention.
	 */
	public static function get_max_retention_countries( array $country_codes ): array {
		if ( empty( $country_codes ) ) {
			return array();
		}

		$countries = self::get_countries();
		$max_years = self::calculate_max_retention( $country_codes );
		$max_countries = array();

		foreach ( $country_codes as $code ) {
			if ( isset( $countries[ $code ] ) && $countries[ $code ]['years'] === $max_years ) {
				$max_countries[] = $countries[ $code ]['name'];
			}
		}

		return $max_countries;
	}
}

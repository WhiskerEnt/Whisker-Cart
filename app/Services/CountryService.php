<?php
namespace App\Services;

use Core\Database;

/**
 * WHISKER — Countries and where the store ships
 *
 * The full ISO 3166-1 alpha-2 list, plus the store owner's decision about
 * which of them they will post to. Three ways to answer that, because most
 * shops only need the first:
 *
 *   domestic  the store country only
 *   selected  a list the owner picks
 *   all       anywhere
 *
 * Checkout reads shippable() for its dropdown and canShipTo() to check what
 * was submitted, so a country cannot be forced through by editing the form.
 */
class CountryService
{
    public const MODE_DOMESTIC = 'domestic';
    public const MODE_SELECTED = 'selected';
    public const MODE_ALL      = 'all';

    /** ISO 3166-1 alpha-2 => English short name. */
    private const COUNTRIES = [
        'AF' => 'Afghanistan',
        'AX' => 'Aland Islands',
        'AL' => 'Albania',
        'DZ' => 'Algeria',
        'AS' => 'American Samoa',
        'AD' => 'Andorra',
        'AO' => 'Angola',
        'AI' => 'Anguilla',
        'AQ' => 'Antarctica',
        'AG' => 'Antigua and Barbuda',
        'AR' => 'Argentina',
        'AM' => 'Armenia',
        'AW' => 'Aruba',
        'AU' => 'Australia',
        'AT' => 'Austria',
        'AZ' => 'Azerbaijan',
        'BS' => 'Bahamas',
        'BH' => 'Bahrain',
        'BD' => 'Bangladesh',
        'BB' => 'Barbados',
        'BY' => 'Belarus',
        'BE' => 'Belgium',
        'BZ' => 'Belize',
        'BJ' => 'Benin',
        'BM' => 'Bermuda',
        'BT' => 'Bhutan',
        'BO' => 'Bolivia',
        'BQ' => 'Bonaire, Sint Eustatius and Saba',
        'BA' => 'Bosnia and Herzegovina',
        'BW' => 'Botswana',
        'BV' => 'Bouvet Island',
        'BR' => 'Brazil',
        'IO' => 'British Indian Ocean Territory',
        'BN' => 'Brunei Darussalam',
        'BG' => 'Bulgaria',
        'BF' => 'Burkina Faso',
        'BI' => 'Burundi',
        'CV' => 'Cabo Verde',
        'KH' => 'Cambodia',
        'CM' => 'Cameroon',
        'CA' => 'Canada',
        'KY' => 'Cayman Islands',
        'CF' => 'Central African Republic',
        'TD' => 'Chad',
        'CL' => 'Chile',
        'CN' => 'China',
        'CX' => 'Christmas Island',
        'CC' => 'Cocos (Keeling) Islands',
        'CO' => 'Colombia',
        'KM' => 'Comoros',
        'CG' => 'Congo',
        'CD' => 'Congo (Democratic Republic)',
        'CK' => 'Cook Islands',
        'CR' => 'Costa Rica',
        'CI' => 'Cote d Ivoire',
        'HR' => 'Croatia',
        'CU' => 'Cuba',
        'CW' => 'Curacao',
        'CY' => 'Cyprus',
        'CZ' => 'Czechia',
        'DK' => 'Denmark',
        'DJ' => 'Djibouti',
        'DM' => 'Dominica',
        'DO' => 'Dominican Republic',
        'EC' => 'Ecuador',
        'EG' => 'Egypt',
        'SV' => 'El Salvador',
        'GQ' => 'Equatorial Guinea',
        'ER' => 'Eritrea',
        'EE' => 'Estonia',
        'SZ' => 'Eswatini',
        'ET' => 'Ethiopia',
        'FK' => 'Falkland Islands',
        'FO' => 'Faroe Islands',
        'FJ' => 'Fiji',
        'FI' => 'Finland',
        'FR' => 'France',
        'GF' => 'French Guiana',
        'PF' => 'French Polynesia',
        'TF' => 'French Southern Territories',
        'GA' => 'Gabon',
        'GM' => 'Gambia',
        'GE' => 'Georgia',
        'DE' => 'Germany',
        'GH' => 'Ghana',
        'GI' => 'Gibraltar',
        'GR' => 'Greece',
        'GL' => 'Greenland',
        'GD' => 'Grenada',
        'GP' => 'Guadeloupe',
        'GU' => 'Guam',
        'GT' => 'Guatemala',
        'GG' => 'Guernsey',
        'GN' => 'Guinea',
        'GW' => 'Guinea-Bissau',
        'GY' => 'Guyana',
        'HT' => 'Haiti',
        'HM' => 'Heard Island and McDonald Islands',
        'VA' => 'Holy See',
        'HN' => 'Honduras',
        'HK' => 'Hong Kong',
        'HU' => 'Hungary',
        'IS' => 'Iceland',
        'IN' => 'India',
        'ID' => 'Indonesia',
        'IR' => 'Iran',
        'IQ' => 'Iraq',
        'IE' => 'Ireland',
        'IM' => 'Isle of Man',
        'IL' => 'Israel',
        'IT' => 'Italy',
        'JM' => 'Jamaica',
        'JP' => 'Japan',
        'JE' => 'Jersey',
        'JO' => 'Jordan',
        'KZ' => 'Kazakhstan',
        'KE' => 'Kenya',
        'KI' => 'Kiribati',
        'KP' => 'Korea (North)',
        'KR' => 'Korea (South)',
        'KW' => 'Kuwait',
        'KG' => 'Kyrgyzstan',
        'LA' => 'Laos',
        'LV' => 'Latvia',
        'LB' => 'Lebanon',
        'LS' => 'Lesotho',
        'LR' => 'Liberia',
        'LY' => 'Libya',
        'LI' => 'Liechtenstein',
        'LT' => 'Lithuania',
        'LU' => 'Luxembourg',
        'MO' => 'Macao',
        'MG' => 'Madagascar',
        'MW' => 'Malawi',
        'MY' => 'Malaysia',
        'MV' => 'Maldives',
        'ML' => 'Mali',
        'MT' => 'Malta',
        'MH' => 'Marshall Islands',
        'MQ' => 'Martinique',
        'MR' => 'Mauritania',
        'MU' => 'Mauritius',
        'YT' => 'Mayotte',
        'MX' => 'Mexico',
        'FM' => 'Micronesia',
        'MD' => 'Moldova',
        'MC' => 'Monaco',
        'MN' => 'Mongolia',
        'ME' => 'Montenegro',
        'MS' => 'Montserrat',
        'MA' => 'Morocco',
        'MZ' => 'Mozambique',
        'MM' => 'Myanmar',
        'NA' => 'Namibia',
        'NR' => 'Nauru',
        'NP' => 'Nepal',
        'NL' => 'Netherlands',
        'NC' => 'New Caledonia',
        'NZ' => 'New Zealand',
        'NI' => 'Nicaragua',
        'NE' => 'Niger',
        'NG' => 'Nigeria',
        'NU' => 'Niue',
        'NF' => 'Norfolk Island',
        'MK' => 'North Macedonia',
        'MP' => 'Northern Mariana Islands',
        'NO' => 'Norway',
        'OM' => 'Oman',
        'PK' => 'Pakistan',
        'PW' => 'Palau',
        'PS' => 'Palestine',
        'PA' => 'Panama',
        'PG' => 'Papua New Guinea',
        'PY' => 'Paraguay',
        'PE' => 'Peru',
        'PH' => 'Philippines',
        'PN' => 'Pitcairn',
        'PL' => 'Poland',
        'PT' => 'Portugal',
        'PR' => 'Puerto Rico',
        'QA' => 'Qatar',
        'RE' => 'Reunion',
        'RO' => 'Romania',
        'RU' => 'Russia',
        'RW' => 'Rwanda',
        'BL' => 'Saint Barthelemy',
        'SH' => 'Saint Helena',
        'KN' => 'Saint Kitts and Nevis',
        'LC' => 'Saint Lucia',
        'MF' => 'Saint Martin',
        'PM' => 'Saint Pierre and Miquelon',
        'VC' => 'Saint Vincent and the Grenadines',
        'WS' => 'Samoa',
        'SM' => 'San Marino',
        'ST' => 'Sao Tome and Principe',
        'SA' => 'Saudi Arabia',
        'SN' => 'Senegal',
        'RS' => 'Serbia',
        'SC' => 'Seychelles',
        'SL' => 'Sierra Leone',
        'SG' => 'Singapore',
        'SX' => 'Sint Maarten',
        'SK' => 'Slovakia',
        'SI' => 'Slovenia',
        'SB' => 'Solomon Islands',
        'SO' => 'Somalia',
        'ZA' => 'South Africa',
        'GS' => 'South Georgia and the South Sandwich Islands',
        'SS' => 'South Sudan',
        'ES' => 'Spain',
        'LK' => 'Sri Lanka',
        'SD' => 'Sudan',
        'SR' => 'Suriname',
        'SJ' => 'Svalbard and Jan Mayen',
        'SE' => 'Sweden',
        'CH' => 'Switzerland',
        'SY' => 'Syria',
        'TW' => 'Taiwan',
        'TJ' => 'Tajikistan',
        'TZ' => 'Tanzania',
        'TH' => 'Thailand',
        'TL' => 'Timor-Leste',
        'TG' => 'Togo',
        'TK' => 'Tokelau',
        'TO' => 'Tonga',
        'TT' => 'Trinidad and Tobago',
        'TN' => 'Tunisia',
        'TR' => 'Turkiye',
        'TM' => 'Turkmenistan',
        'TC' => 'Turks and Caicos Islands',
        'TV' => 'Tuvalu',
        'UG' => 'Uganda',
        'UA' => 'Ukraine',
        'AE' => 'United Arab Emirates',
        'GB' => 'United Kingdom',
        'US' => 'United States',
        'UM' => 'United States Minor Outlying Islands',
        'UY' => 'Uruguay',
        'UZ' => 'Uzbekistan',
        'VU' => 'Vanuatu',
        'VE' => 'Venezuela',
        'VN' => 'Viet Nam',
        'VG' => 'Virgin Islands (British)',
        'VI' => 'Virgin Islands (U.S.)',
        'WF' => 'Wallis and Futuna',
        'EH' => 'Western Sahara',
        'YE' => 'Yemen',
        'ZM' => 'Zambia',
        'ZW' => 'Zimbabwe',
    ];

    /**
     * ISO 3166-1 alpha-2 => E.164 country calling code, without the plus.
     *
     * Several countries share one code (+1 covers the US, Canada and much of
     * the Caribbean; +7 covers Russia and Kazakhstan), so this maps one way
     * only — a code cannot be turned back into a single country.
     */
    private const DIAL_CODES = [
        'AF' => '93',   'AX' => '358',  'AL' => '355',  'DZ' => '213',  'AS' => '1684', 'AD' => '376',
        'AO' => '244',  'AI' => '1264', 'AQ' => '672',  'AG' => '1268', 'AR' => '54',   'AM' => '374',
        'AW' => '297',  'AU' => '61',   'AT' => '43',   'AZ' => '994',  'BS' => '1242', 'BH' => '973',
        'BD' => '880',  'BB' => '1246', 'BY' => '375',  'BE' => '32',   'BZ' => '501',  'BJ' => '229',
        'BM' => '1441', 'BT' => '975',  'BO' => '591',  'BQ' => '599',  'BA' => '387',  'BW' => '267',
        'BV' => '47',   'BR' => '55',   'IO' => '246',  'BN' => '673',  'BG' => '359',  'BF' => '226',
        'BI' => '257',  'CV' => '238',  'KH' => '855',  'CM' => '237',  'CA' => '1',    'KY' => '1345',
        'CF' => '236',  'TD' => '235',  'CL' => '56',   'CN' => '86',   'CX' => '61',   'CC' => '61',
        'CO' => '57',   'KM' => '269',  'CG' => '242',  'CD' => '243',  'CK' => '682',  'CR' => '506',
        'CI' => '225',  'HR' => '385',  'CU' => '53',   'CW' => '599',  'CY' => '357',  'CZ' => '420',
        'DK' => '45',   'DJ' => '253',  'DM' => '1767', 'DO' => '1809', 'EC' => '593',  'EG' => '20',
        'SV' => '503',  'GQ' => '240',  'ER' => '291',  'EE' => '372',  'SZ' => '268',  'ET' => '251',
        'FK' => '500',  'FO' => '298',  'FJ' => '679',  'FI' => '358',  'FR' => '33',   'GF' => '594',
        'PF' => '689',  'TF' => '262',  'GA' => '241',  'GM' => '220',  'GE' => '995',  'DE' => '49',
        'GH' => '233',  'GI' => '350',  'GR' => '30',   'GL' => '299',  'GD' => '1473', 'GP' => '590',
        'GU' => '1671', 'GT' => '502',  'GG' => '44',   'GN' => '224',  'GW' => '245',  'GY' => '592',
        'HT' => '509',  'HM' => '672',  'VA' => '39',   'HN' => '504',  'HK' => '852',  'HU' => '36',
        'IS' => '354',  'IN' => '91',   'ID' => '62',   'IR' => '98',   'IQ' => '964',  'IE' => '353',
        'IM' => '44',   'IL' => '972',  'IT' => '39',   'JM' => '1876', 'JP' => '81',   'JE' => '44',
        'JO' => '962',  'KZ' => '7',    'KE' => '254',  'KI' => '686',  'KP' => '850',  'KR' => '82',
        'KW' => '965',  'KG' => '996',  'LA' => '856',  'LV' => '371',  'LB' => '961',  'LS' => '266',
        'LR' => '231',  'LY' => '218',  'LI' => '423',  'LT' => '370',  'LU' => '352',  'MO' => '853',
        'MG' => '261',  'MW' => '265',  'MY' => '60',   'MV' => '960',  'ML' => '223',  'MT' => '356',
        'MH' => '692',  'MQ' => '596',  'MR' => '222',  'MU' => '230',  'YT' => '262',  'MX' => '52',
        'FM' => '691',  'MD' => '373',  'MC' => '377',  'MN' => '976',  'ME' => '382',  'MS' => '1664',
        'MA' => '212',  'MZ' => '258',  'MM' => '95',   'NA' => '264',  'NR' => '674',  'NP' => '977',
        'NL' => '31',   'NC' => '687',  'NZ' => '64',   'NI' => '505',  'NE' => '227',  'NG' => '234',
        'NU' => '683',  'NF' => '672',  'MK' => '389',  'MP' => '1670', 'NO' => '47',   'OM' => '968',
        'PK' => '92',   'PW' => '680',  'PS' => '970',  'PA' => '507',  'PG' => '675',  'PY' => '595',
        'PE' => '51',   'PH' => '63',   'PN' => '64',   'PL' => '48',   'PT' => '351',  'PR' => '1787',
        'QA' => '974',  'RE' => '262',  'RO' => '40',   'RU' => '7',    'RW' => '250',  'BL' => '590',
        'SH' => '290',  'KN' => '1869', 'LC' => '1758', 'MF' => '590',  'PM' => '508',  'VC' => '1784',
        'WS' => '685',  'SM' => '378',  'ST' => '239',  'SA' => '966',  'SN' => '221',  'RS' => '381',
        'SC' => '248',  'SL' => '232',  'SG' => '65',   'SX' => '1721', 'SK' => '421',  'SI' => '386',
        'SB' => '677',  'SO' => '252',  'ZA' => '27',   'GS' => '500',  'SS' => '211',  'ES' => '34',
        'LK' => '94',   'SD' => '249',  'SR' => '597',  'SJ' => '47',   'SE' => '46',   'CH' => '41',
        'SY' => '963',  'TW' => '886',  'TJ' => '992',  'TZ' => '255',  'TH' => '66',   'TL' => '670',
        'TG' => '228',  'TK' => '690',  'TO' => '676',  'TT' => '1868', 'TN' => '216',  'TR' => '90',
        'TM' => '993',  'TC' => '1649', 'TV' => '688',  'UG' => '256',  'UA' => '380',  'AE' => '971',
        'GB' => '44',   'US' => '1',    'UM' => '1',    'UY' => '598',  'UZ' => '998',  'VU' => '678',
        'VE' => '58',   'VN' => '84',   'VG' => '1284', 'VI' => '1340', 'WF' => '681',  'EH' => '212',
        'YE' => '967',  'ZM' => '260',  'ZW' => '263',
    ];

    /** @return array<string,string> code => dial code, in the order of all() */
    public static function dialCodes(): array
    {
        return array_intersect_key(self::DIAL_CODES, self::COUNTRIES);
    }

    /** The calling code for a country, without the plus. '' if unknown. */
    public static function dialCode(string $code): string
    {
        return self::DIAL_CODES[strtoupper(trim($code))] ?? '';
    }

    /**
     * A phone number as the shopper meant it: their own +country prefix if
     * they typed one, otherwise the code they picked from the list.
     */
    public static function joinPhone(?string $countryCode, ?string $number): string
    {
        $number = trim((string) $number);
        if ($number === '') return '';
        if (str_starts_with($number, '+')) return $number;

        $dial = self::dialCode((string) $countryCode);
        return $dial === '' ? $number : '+' . $dial . ' ' . ltrim($number, '0 ');
    }

    /** EU member states, offered as a one-click group in the admin. */
    public const EU = ['AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL', 'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE'];

    /** @return array<string,string> code => name, ordered by name */
    public static function all(): array
    {
        return self::COUNTRIES;
    }

    public static function name(string $code): string
    {
        $code = strtoupper(trim($code));
        return self::COUNTRIES[$code] ?? $code;
    }

    public static function exists(string $code): bool
    {
        return isset(self::COUNTRIES[strtoupper(trim($code))]);
    }

    public static function storeCountry(): string
    {
        $code = strtoupper((string) Database::setting('general', 'store_country', ''));
        return self::exists($code) ? $code : 'IN';
    }

    public static function mode(): string
    {
        $mode = (string) Database::setting('shipping', 'ship_mode', self::MODE_DOMESTIC);
        return in_array($mode, [self::MODE_DOMESTIC, self::MODE_SELECTED, self::MODE_ALL], true)
            ? $mode
            : self::MODE_DOMESTIC;
    }

    /** The codes saved against MODE_SELECTED, whether or not that mode is active. */
    public static function selectedCodes(): array
    {
        $raw = (string) Database::setting('shipping', 'ship_countries', '');
        $codes = array_filter(array_map(
            static fn($c) => strtoupper(trim($c)),
            explode(',', $raw)
        ), static fn($c) => self::exists($c));
        return array_values(array_unique($codes));
    }

    /**
     * Countries this store will post to, code => name.
     *
     * Never empty: a store that chose specific countries and then cleared them
     * all would otherwise have a checkout nobody can complete, so it falls
     * back to its own country.
     */
    public static function shippable(): array
    {
        $mode = self::mode();
        if ($mode === self::MODE_ALL) return self::COUNTRIES;

        $codes = $mode === self::MODE_SELECTED ? self::selectedCodes() : [self::storeCountry()];
        if (!$codes) $codes = [self::storeCountry()];

        $out = [];
        foreach (self::COUNTRIES as $code => $name) {
            if (in_array($code, $codes, true)) $out[$code] = $name;
        }
        return $out ?: [self::storeCountry() => self::name(self::storeCountry())];
    }

    public static function canShipTo(string $code): bool
    {
        return isset(self::shippable()[strtoupper(trim($code))]);
    }

    /** Default selection for a checkout dropdown. */
    public static function defaultShippingCountry(): string
    {
        $shippable = self::shippable();
        $store = self::storeCountry();
        return isset($shippable[$store]) ? $store : (string) array_key_first($shippable);
    }
}

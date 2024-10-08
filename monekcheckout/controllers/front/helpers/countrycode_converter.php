<?php
/**
 * Copyright (c) 2024 Monek Ltd
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 *  @author    Monek Ltd
 *  @copyright 2024 Monek Ltd
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Class CountryCodeConverter - helper class for converting country codes
 *
 * @package monek
 */
class CountryCodeConverter
{
    private $countryCodeMap = [
        'US' => '840',
        'CA' => '124',
        'GB' => '826',
        'FR' => '250',
        'DE' => '276',
        'JP' => '392',
        'CN' => '156',
        'IN' => '356',
        'RU' => '643',
        'AF' => '004',
        'AL' => '008',
        'DZ' => '012',
        'AS' => '016',
        'AD' => '020',
        'AO' => '024',
        'AI' => '660',
        'AQ' => '010',
        'AG' => '028',
        'AR' => '032',
        'AM' => '051',
        'AW' => '533',
        'AU' => '036',
        'AT' => '040',
        'AZ' => '031',
        'BS' => '044',
        'BH' => '048',
        'BD' => '050',
        'BB' => '052',
        'BY' => '112',
        'BE' => '056',
        'BZ' => '084',
        'BJ' => '204',
        'BM' => '060',
        'BT' => '064',
        'BO' => '068',
        'BA' => '070',
        'BW' => '072',
        'BR' => '076',
        'BN' => '096',
        'BG' => '100',
        'BF' => '854',
        'BI' => '108',
        'KH' => '116',
        'CM' => '120',
        'CV' => '132',
        'KY' => '136',
        'CF' => '140',
        'TD' => '148',
        'CL' => '152',
        'CO' => '170',
        'KM' => '174',
        'CG' => '178',
        'CD' => '180',
        'CR' => '188',
        'HR' => '191',
        'CU' => '192',
        'CY' => '196',
        'CZ' => '203',
        'DK' => '208',
        'DJ' => '262',
        'DM' => '212',
        'DO' => '214',
        'EC' => '218',
        'EG' => '818',
        'SV' => '222',
        'GQ' => '226',
        'ER' => '232',
        'EE' => '233',
        'ET' => '231',
        'FJ' => '242',
        'FI' => '246',
        'GA' => '266',
        'GM' => '270',
        'GE' => '268',
        'GH' => '288',
        'GR' => '300',
        'GL' => '304',
        'GD' => '308',
        'GU' => '316',
        'GT' => '320',
        'GN' => '324',
        'GW' => '624',
        'GY' => '328',
        'HT' => '332',
        'HN' => '340',
        'HK' => '344',
        'HU' => '348',
        'IS' => '352',
        'ID' => '360',
        'IR' => '364',
        'IQ' => '368',
        'IE' => '372',
        'IL' => '376',
        'IT' => '380',
        'JM' => '388',
        'JO' => '400',
        'KZ' => '398',
        'KE' => '404',
        'KI' => '296',
        'KP' => '408',
        'KR' => '410',
        'KW' => '414',
        'KG' => '417',
        'LA' => '418',
        'LV' => '428',
        'LB' => '422',
        'LS' => '426',
        'LR' => '430',
        'LY' => '434',
        'LI' => '438',
        'LT' => '440',
        'LU' => '442',
        'MO' => '446',
        'MG' => '450',
        'MW' => '454',
        'MY' => '458',
        'MV' => '462',
        'ML' => '466',
        'MT' => '470',
        'MH' => '584',
        'MR' => '478',
        'MU' => '480',
        'MX' => '484',
        'FM' => '583',
        'MD' => '498',
        'MC' => '492',
        'MN' => '496',
        'ME' => '499',
        'MA' => '504',
        'MZ' => '508',
        'MM' => '104',
        'NA' => '516',
        'NR' => '520',
        'NP' => '524',
        'NL' => '528',
        'NZ' => '554',
        'NI' => '558',
        'NE' => '562',
        'NG' => '566',
        'NO' => '578',
        'OM' => '512',
        'PK' => '586',
        'PW' => '585',
        'PA' => '591',
        'PG' => '598',
        'PY' => '600',
        'PE' => '604',
        'PH' => '608',
        'PL' => '616',
        'PT' => '620',
        'QA' => '634',
        'RO' => '642',
        'RW' => '646',
        'KN' => '659',
        'LC' => '662',
        'VC' => '670',
        'WS' => '882',
        'SM' => '674',
        'ST' => '678',
        'SA' => '682',
        'SN' => '686',
        'RS' => '688',
        'SC' => '690',
        'SL' => '694',
        'SG' => '702',
        'SK' => '703',
        'SI' => '705',
        'SB' => '090',
        'SO' => '706',
        'ZA' => '710',
        'SS' => '728',
        'ES' => '724',
        'LK' => '144',
        'SD' => '729',
        'SR' => '740',
        'SZ' => '748',
        'SE' => '752',
        'CH' => '756',
        'SY' => '760',
        'TW' => '158',
        'TJ' => '762',
        'TZ' => '834',
        'TH' => '764',
        'TL' => '626',
        'TG' => '768',
        'TO' => '776',
        'TT' => '780',
        'TN' => '788',
        'TR' => '792',
        'TM' => '795',
        'TV' => '798',
        'UG' => '800',
        'UA' => '804',
        'AE' => '784',
        'UY' => '858',
        'UZ' => '860',
        'VU' => '548',
        'VE' => '862',
        'VN' => '704',
        'YE' => '887',
        'ZM' => '894',
        'ZW' => '716',
    ];

    /**
    * Get the 3 digit country code from the 2 digit country code
	 *
	 * @param string $iso_code_2digit
	 * @return string|null
	 */
    public function getCountryCode3Digit(string $iso_code_2digit)
    {
        if (array_key_exists($iso_code_2digit, $this->countryCodeMap)) {
            return $this->countryCodeMap[$iso_code_2digit];
        } else {
            return null;
        }
    }
}

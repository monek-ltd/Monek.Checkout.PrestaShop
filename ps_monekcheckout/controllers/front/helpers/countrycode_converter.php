<?php
class CountryCodeConverter {
    private $countryCodeMap = [
        "US" => "840",
        "CA" => "124",
        "GB" => "826",
        "FR" => "250",
        "DE" => "276",
        "JP" => "392",
        "CN" => "156",
        "IN" => "356",
        "RU" => "643",
    ];

    public function getCountryCode3Digit($iso_code_2digit) {
        if (array_key_exists($iso_code_2digit, $this->countryCodeMap)) {
            return $this->countryCodeMap[$iso_code_2digit];
        } else {
            return null;
        }
    }
}

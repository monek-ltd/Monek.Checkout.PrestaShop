<?php

class TransactionHelper {
    
    public static function convert_decimal_to_flat($decimal_number) {
        $flat_number = (int) str_replace('.', '', $decimal_number);
        return $flat_number;
    }
}
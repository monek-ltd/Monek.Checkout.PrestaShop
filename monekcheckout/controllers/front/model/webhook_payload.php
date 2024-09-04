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

class WebhookPayload 
{
    public string $transaction_date_time;
    public string $payment_reference;
    public string $cross_reference;
    public string $response_code;
    public string $message;
    public float $amount;
    public string $currency_code;
    public string $integrity_digest;

    /**
     * @param array $data
     */
    public function __construct(array $data) 
    {
        $this->transaction_date_time = $this->validate_date_time($data['transactionDateTime'] ?? '');
        $this->payment_reference = $this->validate_string($data['paymentReference'] ?? '', 'Payment Reference');
        $this->cross_reference = $this->validate_string($data['crossReference'] ?? '', 'Cross Reference');
        $this->response_code = $this->validate_response_code($data['responseCode'] ?? '');
        $this->message = filter_var($data['message'] ?? '', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $this->amount = $this->validate_amount($data['amount'] ?? '');
        $this->currency_code = $this->validate_currency_code($data['currencyCode'] ?? '');
        $this->integrity_digest = filter_var($data['integrityDigest'] ?? '');
    }

    /**
     * Validate the webhook payload data is available
     *
     * @return bool
     */
    public function validate() : bool 
    {
        return !empty($this->transaction_date_time)
            && !empty($this->payment_reference)
            && !empty($this->cross_reference)
            && !empty($this->response_code)
            && !empty($this->message)
            && !empty($this->amount)
            && !empty($this->currency_code)
            && !empty($this->integrity_digest);
    }

    /**
     * Validate and sanitize a date-time string in RFC 3339 format
     *
     * @param string $dateTime
     * @return string
     * @throws InvalidArgumentException
     */
    private function validate_date_time(string $dateTime): string 
    {
        if (empty($dateTime)) {
            throw new InvalidArgumentException('Transaction date and time is required.');
        }

        $dateTime = filter_var($dateTime, FILTER_SANITIZE_FULL_SPECIAL_CHARS);

        // RFC 3339 format validation
        $dateTimeRegex = '/^(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2}))$/';
        if (!preg_match($dateTimeRegex, $dateTime)) {
            throw new InvalidArgumentException('Invalid transaction date and time format. Must be in RFC 3339 format.');
        }

        return $dateTime;
    }
    
    /**
     * Validate and sanitize a generic string field
     *
     * @param string $value
     * @param string $fieldName
     * @return string
     * @throws InvalidArgumentException
     */
    private function validate_string(string $value, string $fieldName) : string 
    {
        $value = filter_var($value, FILTER_SANITIZE_FULL_SPECIAL_CHARS);

        if (empty($value)) {
            throw new InvalidArgumentException(htmlspecialchars("$fieldName is required."));
        }

        return $value;
    }
    
    /**
     * Validate the response code (assuming it should be a two-digit numeric string)
     *
     * @param string $responseCode
     * @return string
     * @throws InvalidArgumentException
     */
    private function validate_response_code(string $responseCode) : string 
    {
        $responseCode = filter_var($responseCode, FILTER_SANITIZE_FULL_SPECIAL_CHARS);

        if (!preg_match('/^\d{2}$/', $responseCode)) {
            throw new InvalidArgumentException('Invalid response code format.');
        }

        return $responseCode;
    }

    /**
     * Validate and sanitize the amount
     *
     * @param string $amount
     * @return float
     * @throws InvalidArgumentException
     */
    private function validate_amount(string $amount) : float 
    {
        $amount = filter_var($amount, FILTER_VALIDATE_FLOAT);

        if (!is_numeric($amount)) {
            throw new InvalidArgumentException('Amount must be a valid number.');
        }

        return (float)$amount;
    }

    /**
     * Validate the currency code (assuming it should be a numeric ISO code)
     *
     * @param string $currencyCode
     * @return string
     * @throws InvalidArgumentException
     */
    private function validate_currency_code(string $currencyCode) : string 
    {
        $currencyCode = filter_var($currencyCode, FILTER_SANITIZE_FULL_SPECIAL_CHARS);

        if (!preg_match('/^\d{3}$/', $currencyCode)) {
            throw new InvalidArgumentException('Invalid currency code format.');
        }

        return $currencyCode;
    }
}
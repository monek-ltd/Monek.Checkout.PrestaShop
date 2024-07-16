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
 * THE SOFTWARE IS PROVIDED 'AS IS', WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
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

class CurlHelper
{
    private function log_error($context, $curl_errno, $curl_error, $http_code, $response)
    {
        PrestaShopLogger::addLog(
            sprintf(
                'Request failed. cURL error (%d): %s, HTTP code: %d, Response: %s',
                $curl_errno,
                $curl_error,
                $http_code,
                var_export($response, true)
            ),
            3,
            null,
            'ps_monekcheckout',
            (int) $context->cart->id
        );
    }

    public function remote_post($context, $url, $body_data, $headers)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($body_data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

        PrestaShopLogger::addLog('Executing curl.', 1, null, 'ps_monekcheckout', (int) $order->id);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        $curl_errno = curl_errno($ch);
        $curl_error = curl_error($ch);

        if ($http_code !== 200) {
            $this->log_error(
                $context,
                $curl_errno,
                $curl_error,
                $http_code,
                $response
            );
            $success = false;
        } else {
            $success = true;
        }

        curl_close($ch);

        return new RemotePostResponse($response, $http_code, $success);
    }
}

class RemotePostResponse
{
    public $body;
    public $httpCode;
    public $success;

    public function __construct($body, $httpCode, $success)
    {
        $this->body = $body;
        $this->httpCode = $httpCode;
        $this->success = $success;
    }
}

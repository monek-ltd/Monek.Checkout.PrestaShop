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

/**
 * Class CurlHelper - helper class for cURL requests
 *
 * @package monek
 */
class CurlHelper
{
    /**
	 * Logs the error
	 *
	 * @param Context $context
	 * @param int $curl_errno
	 * @param string $curl_error
	 * @param int $http_code
	 * @param string $response
	 * @return void
	 */
    private function log_error(Context $context, int $curl_errno, string $curl_error, int $http_code, string $response) : void
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
            'monekcheckout',
            (int) $context->cart->id
        );
    }

    /**
    * Executes a remote POST request
    *
    * @param Context $context
    * @param string $url
    * @param array $body_data
    * @param array $headers
    * @return RemotePostResponse
	*/
    public function remote_post(Context $context, string $url, array $body_data, array $headers) : RemotePostResponse
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($body_data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        PrestaShopLogger::addLog('Executing curl.', 1, null, 'monekcheckout', 0);
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
            $response = 'Request failed. cURL error';
        } else {
            $success = true;
        }

        curl_close($ch);

        return new RemotePostResponse($response, $http_code, $success);
    }
}

/**
 * Class RemotePostResponse - response from a remote POST request
 *
 * @package monek
 */
class RemotePostResponse
{
    public $body;
    public $httpCode;
    public $success;

    /**
    * Constructor
	 *
	 * @param string $body
	 * @param int $httpCode
	 * @param bool $success
	 */
    public function __construct(string $body, int $httpCode, bool $success)
    {
        $this->body = $body;
        $this->httpCode = $httpCode;
        $this->success = $success;
    }
}

<?php
class CurlHelper {
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
            (int)$context->cart->id
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

        
        PrestaShopLogger::addLog("Executing curl.", 1, null, 'ps_monekcheckout', (int)$order->id);
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
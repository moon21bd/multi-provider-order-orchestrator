<?php

namespace App\Traits;

use Illuminate\Support\Facades\Log;

trait HelperTrait
{

    public function moon()
    {
        return "Hello From Moon";
    }

    public function curlFetch($data = [], $method = 'POST')
    {
        $funcCallerName = debug_backtrace()[1]['function'];
        $logMsg = 'curlFetch-CALLED-FROM: ' . $funcCallerName;
        Log::info('curlFetch-REQUEST: ' . json_encode([$logMsg, $data, $method]));
        extract($data);

        $curl = curl_init();
        $curlOptions = array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_HTTPHEADER => array_merge(
                array("Content-Type: application/json"),
                $headers
            ),
        );

        if ($method === 'POST') {
            $curlOptions[CURLOPT_URL] = $url;
            $curlOptions[CURLOPT_CUSTOMREQUEST] = 'POST';
            $curlOptions[CURLOPT_POSTFIELDS] = json_encode($params);
        } else { // GET request
            $curlOptions[CURLOPT_URL] = $url . "?" . http_build_query($params);
            $curlOptions[CURLOPT_CUSTOMREQUEST] = 'GET';
        }

        curl_setopt_array($curl, $curlOptions);

        $response = curl_exec($curl);
        $err = curl_error($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        Log::info('doCurl-RESPONSE: ' . json_encode([$response, $err, $http_code]));

        return [
            'response' => $response,
            'err' => $err,
            'http_code' => $http_code,
        ];
    }

}
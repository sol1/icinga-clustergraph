<?php
/* Originally from Icinga Web 2 icingadb Module | GPLv2+ */

namespace Icinga\Module\Clustergraph\Common;


use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Icinga\Application\Config;
use Icinga\Exception\Http\HttpException;
use Icinga\Exception\Json\JsonDecodeException;
use Icinga\Util\Json;


class ApiZones
{

    protected function getUriFor($endpoint,$transport)
    {
        return sprintf('https://%s:%u/v1/%s', $transport['host'], $transport['port'], $endpoint);
    }
    /**
     * Send the given command to the icinga2's REST API
     *
     *
     * @return mixed
     */
    public function sendCommand($method="GET", $endpoint='objects/zones')
    {
        $transport =Config::module('clustergraph')->getSection("api")->toArray();

        $headers = ['Accept' => 'application/json'];
        if ($method !== 'POST') {
            $headers['X-HTTP-Method-Override'] = $method;
        }

        try {
            $response = (new Client())
                ->post($this->getUriFor($endpoint,$transport), [
                    'auth'          => [$transport['username'], $transport['password']],
                    'headers'       => $headers,
                    'http_errors'   => false,
                    'verify'        => false
                ]);
        } catch (GuzzleException $e) {

            throw new HttpException(
                'Can\'t connect to the Icinga 2 API: %u %s',
                $e->getCode(),
                $e->getMessage()
            );
        }

        try {
            $responseData = Json::decode((string) $response->getBody(), true);
        } catch (JsonDecodeException $e) {
            throw new HttpException(
                'Got invalid JSON response from the Icinga 2 API: %s',
                $e->getMessage()
            );
        }

        if (! isset($responseData['results']) || empty($responseData['results'])) {
            if (isset($responseData['error'])) {
                if($responseData['error'] === 409){
                    return [];
                }
                throw new HttpException(
                    'Can\'t send external Icinga command: %u %s',
                    $responseData['error'],
                    $responseData['status']
                );
            }

            return [];
        }

        $errorResult = $responseData['results'][0];
        if (isset($errorResult['code']) && ($errorResult['code'] < 200 || $errorResult['code'] >= 300)) {
            throw new HttpException(
                'Can\'t send external Icinga command: %u %s',
                $errorResult['code'],
                $errorResult['status']
            );
        }

        return $responseData['results'];
    }


}

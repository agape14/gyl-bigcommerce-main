<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Illuminate\Support\Facades\Log;

class ZohoCRMService
{
    private $url = "https://www.zohoapis.com/crm/v6/";
    private $zohoOAuthService;
    private $client;

    /**
     * ZohoCRMService constructor.
     */
    public function __construct()
    {
        $this->zohoOAuthService = new ZohoOAuthService();
        $this->client = new Client();
    }

    /**
     * @param $url
     * @return string
     */
    private function urlApi($url)
    {
        return $this->url . $url;
    }

    /**
     * @return array
     */
    private function headers()
    {
        return [
            'Accept' => 'application/json',
            'Authorization' => 'Zoho-oauthtoken ' . $this->zohoOAuthService->getAccessToken()->access_token,
        ];
    }

    /**
     * Get record by ID.
     *
     * @param $module
     * @param $id
     * @return array
     */
    public function getRecordById($module, $id)
    {
        $url = $this->urlApi("{$module}/{$id}");
        $response = $this->client->request('GET', $url, [
            'headers' => $this->headers(),
        ]);

        try {
            $decodedResponse = json_decode($response->getBody()->getContents(), true);
            return $decodedResponse['data'][0];
        } catch (\Exception $e) {
            Log::error('error', ['message' => $e->getMessage()]);
            return [
                'status' => false,
                'message' => 'FAILURE',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Search records.
     *
     * @param $module
     * @param $criteria
     * @return array
     */
    public function searchRecords($module, $criteria, $field = 'criteria')
    {
        try {
            $url = $this->urlApi("{$module}/search?{$field}={$criteria}");
            $response = $this->client->request('GET', $url, [
                'headers' => $this->headers(),
            ]);

            $decodedResponse = json_decode($response->getBody()->getContents(), true);
            return $decodedResponse;
        } catch (\Exception $e) {
            Log::error('error', ['message' => $e->getMessage()]);
            return [
                'status' => false,
                'message' => 'FAILURE',
                'error' => $e->getMessage()
            ];
        }
    }

    public function getRecords($module, $query = [])
    {
        $url = $this->urlApi("{$module}");
        $response = $this->client->request('GET', $url, [
            'headers' => $this->headers(),
            'query' => $query
        ]);

        try {
            $res = $response->getBody()->getContents();
            Log::info('response', ['message' => $res]);
            $decodedResponse = json_decode($res, true);
            return $decodedResponse;
        } catch (\Exception $e) {
            Log::error('error', ['message' => $e->getMessage()]);
            return [
                'status' => false,
                'message' => 'FAILURE',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Create records.
     *
     * @param $module
     * @param $criteria
     * @return array
     */
    public function createRecords($module, $data, $trigger = [])
    {
        $url = $this->urlApi("{$module}");
        $response = $this->client->request('POST', $url, [
            'headers' => $this->headers(),
            'body' => json_encode([
                'data' => count(array_filter(array_keys($data), 'is_string')) > 0 ? [$data] : $data,
                'trigger' => $trigger
            ])
        ]);

        try {
            $decodedResponse = json_decode($response->getBody()->getContents(), true);
            return $decodedResponse;
        } catch (\Exception $e) {
            Log::error('error', ['message' => $e->getMessage()]);
            return [
                'status' => false,
                'message' => 'FAILURE',
                'error' => $e->getMessage()
            ];
        }
    }
}

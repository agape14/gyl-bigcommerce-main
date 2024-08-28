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


    
    /**
     * @return array
     */
    private function headersUpload()
    {
        return [
            'Accept' => 'application/json',
            'Authorization' => 'Zoho-oauthtoken ' . $this->zohoOAuthService->getAccessToken()->access_token,
            'X-CRM-ORG' => '664991997',
            'feature' => 'bulk-write'

        ];
    }

    /**
     * Upload File CRM
     *
     * @param $module
     * @param $criteria
     * @return array
     */
    public function uploadFileCrm()
    {
        $url = $this->urlApi("/upload");
        $url = 'https://content.zohoapis.com/crm/v6/upload';
        $header_crm = $this->headersUpload();
        $header = $this->headers();
        $client = new Client();

        $fileName = 'tiny.zip';
        $relativeFilePath = 'public/touploadcrm/' . $fileName; // Ruta relativa dentro de storage/app/public/
        $absoluteFilePath = storage_path('app/' . $relativeFilePath); // Ruta absoluta para acceder al archivo      

        $fields["file"] = fopen($absoluteFilePath, 'rb');

        try {
            $response = $client->request('POST', $url, [
                'headers' => $header_crm,
                'multipart' => [
                    [
                        'name'     => 'file',
                        'contents' => fopen($absoluteFilePath, 'r'),
                        'filename' => $fileName,
                    ],
                ],
            ]);

                $responseBody = $response->getBody()->getContents();
                $responseData = json_decode($responseBody, true);
                if (isset($responseData['code']) && $responseData['code'] === 'FILE_UPLOAD_SUCCESS') {
                    $fileId = $responseData['details']['file_id'];
                   // echo "File ID: " . $fileId;
                } else {
                    echo "File upload failed.";
                }


                $zohoApiUrl = 'https://www.zohoapis.com/crm/v6/write';
                $moduleAPIName = 'Products'; 
                $jobData = [
                    "operation" => "insert",
                    "ignore_empty" => true,
                    "callBack" => [
                        "url" => "https://webhook.site/e47c1a07-9912-4a41-9901-7fc66b37ec2d",
                        "method" => "post"
                    ],
                    "resource" => [
                        [
                            "type" => "data",
                            "module" => [
                                "api_name" => "Products"
                            ],
                            "file_id" => $fileId,
                            "file_names" => [
                                "tiny.csv"
                            ],
                            "field_mappings" => [
                                [
                                    "api_name" => "Product_Name",
                                    "index" => 1
                                ],
                                [
                                    "api_name" => "Product_Category",
                                    "index" => 2
                                ]
                            ]
                        ]
                    ]
                ];
                
                $responseInsert = $client->request('POST', $zohoApiUrl, [
                    'headers' => $header,
                    'json' => $jobData
                ]);
                if (isset($responseContent['errors'])) {
                    echo "Errors: " . print_r($responseContent['errors'], true);
                } elseif (isset($responseContent['status']) && $responseContent['status'] != "success") {
                    echo "Request failed with message: " . $responseContent['message'];
                }
            
            //$responseBodyInsert = $responseInsert->getBody()->getContents();

            
        } catch (\Exception $e) {
            // Handle exception            
            return response()->json(['error' => $e->getMessage()], 500);
        }

        
    }





}

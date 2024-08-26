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
                    echo "File ID: " . $fileId;
                } else {
                    echo "File upload failed.";
                }

            //Now Insert Data Bulk
            $zohoApiUrl = 'https://www.zohoapis.com/crm/v6/bulk-write';
            $moduleAPIName = 'Products'; 
          /*  $jobData = [
                'operation'  => 'insert',
                "callback": {
                    "url": "http://requestbin.fullcontact.com/1fcimk51",
                    "method": "post"
                },
                'ignore_empty' => true,
                'resource' => [
                    [
                        'type' => 'data',
                        'module' => $moduleAPIName,
                        'file' => [
                            'file_id' => $fileId,
                        ],
                        'field_mappings' => [
                            [
                                'api_name' => 'Product_Name',
                                'index' => 1,
                            ],
                            [
                                'api_name' => 'Product_Category',
                                'index' => 2,
                            ],
                            [
                                'api_name' => 'Type',
                                'index' => 3,
                            ],
                        ],
                    ],
                ],
            ];*/


            $jobData = '{
                "operation": "insert",
                "ignore_empty": true,
                "callBack": {
                    "url": "http://requestbin.fullcontact.com/1bvgfh61",
                    "method": "post"
                },
                "resource": [
                    {
                        "type": "data",
                        "module": {
                            "api_name": "Products"
                        },
                        "find_by": "id",
                        "file_id": $fileId,
                        "field_mappings": [
                            {
                            "api_name": "Layout",
                            "default_value": {
                                "value": "5033280000006201250" 
                            }
                        },
                        {
                            "api_name": "Product_Name",
                            "index": 1
                        },
                        {
                            "api_name": "Product_Category",
                            "index": 2
                        },
                        {
                            "api_name": "Type",
                            "index": 3
                        }
                    ]
                }
            ]
            }';

            $responseInsert = $client->request('POST', $zohoApiUrl, [
                'headers' => $header,
                'body' => $jobData
            ]);


            $responseBodyInsert = $responseInsert->getBody()->getContents();
            dd($responseInsert);

            
        } catch (\Exception $e) {
            // Handle exception            
            return response()->json(['error' => $e->getMessage()], 500);
        }

        
    }





}

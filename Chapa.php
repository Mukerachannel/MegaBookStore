<?php
/**
 * Chapa Payment Gateway Integration
 * 
 * This class handles all interactions with the Chapa API
 */
class Chapa {
    private $secretKey;
    private $baseUrl = 'https://api.chapa.co/v1/transaction';
    
    /**
     * Constructor
     * 
     * @param string $secretKey Your Chapa secret key
     */
    public function __construct($secretKey) {
        $this->secretKey = $secretKey;
    }
    
    /**
     * Initialize a payment transaction
     * 
     * @param PostData $postData Payment data object
     * @return ResponseData Response from Chapa API
     */
    public function initialize(PostData $postData) {
        $url = $this->baseUrl . '/initialize';
        $response = $this->makeRequest($url, 'POST', $postData->getData());
        return new ResponseData($response);
    }
    
    /**
     * Verify a payment transaction
     * 
     * @param string $transactionRef Transaction reference
     * @return ResponseData Response from Chapa API
     */
    public function verify($transactionRef) {
        $url = $this->baseUrl . '/verify/' . $transactionRef;
        $response = $this->makeRequest($url, 'GET');
        return new ResponseData($response);
    }
    
    /**
     * Make HTTP request to Chapa API
     * 
     * @param string $url API endpoint
     * @param string $method HTTP method (GET, POST)
     * @param array $data Request data (for POST)
     * @return array Response data
     */
    private function makeRequest($url, $method = 'GET', $data = null) {
        $curl = curl_init();
        
        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->secretKey,
                'Content-Type: application/json'
            ],
        ];
        
        if ($method === 'POST' && $data) {
            $options[CURLOPT_POSTFIELDS] = json_encode($data);
        }
        
        curl_setopt_array($curl, $options);
        
        $response = curl_exec($curl);
        $statusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        
        curl_close($curl);
        
        if ($error) {
            return [
                'status_code' => 500,
                'status' => 'failed',
                'message' => 'cURL Error: ' . $error,
                'data' => null
            ];
        }
        
        $responseData = json_decode($response, true);
        $responseData['status_code'] = $statusCode;
        
        return $responseData;
    }
}

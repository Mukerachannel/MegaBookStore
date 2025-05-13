<?php
/**
 * ResponseData class for Chapa API responses
 */
class ResponseData {
    private $statusCode;
    private $status;
    private $message;
    private $data;
    private $rawJson;
    
    /**
     * Constructor
     * 
     * @param array $response Response from Chapa API
     */
    public function __construct($response) {
        $this->statusCode = $response['status_code'] ?? 500;
        $this->status = $response['status'] ?? 'failed';
        $this->message = $response['message'] ?? 'Unknown error';
        $this->data = $response['data'] ?? null;
        $this->rawJson = json_encode($response);
    }
    
    /**
     * Get HTTP status code
     * 
     * @return int Status code
     */
    public function getStatusCode() {
        return $this->statusCode;
    }
    
    /**
     * Get status (success/failed)
     * 
     * @return string Status
     */
    public function getStatus() {
        return $this->status;
    }
    
    /**
     * Get response message
     * 
     * @return string Message
     */
    public function getMessage() {
        return $this->message;
    }
    
    /**
     * Get response data
     * 
     * @return mixed Response data
     */
    public function getData() {
        return $this->data;
    }
    
    /**
     * Get raw JSON response
     * 
     * @return string Raw JSON
     */
    public function getRawJson() {
        return $this->rawJson;
    }
}

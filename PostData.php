<?php
/**
 * PostData class for Chapa payment data
 * 
 * Uses Fluent Interface for method chaining
 */
class PostData {
    private $data = [];
    
    /**
     * Set payment amount
     * 
     * @param string $amount Payment amount
     * @return $this
     */
    public function amount($amount) {
        $this->data['amount'] = $amount;
        return $this;
    }
    
    /**
     * Set currency
     * 
     * @param string $currency Currency code (default: ETB)
     * @return $this
     */
    public function currency($currency) {
        $this->data['currency'] = $currency;
        return $this;
    }
    
    /**
     * Set customer email
     * 
     * @param string $email Customer email address
     * @return $this
     */
    public function email($email) {
        $this->data['email'] = $email;
        return $this;
    }
    
    /**
     * Set customer first name
     * 
     * @param string $firstname Customer first name
     * @return $this
     */
    public function firstname($firstname) {
        $this->data['first_name'] = $firstname;
        return $this;
    }
    
    /**
     * Set customer last name
     * 
     * @param string $lastname Customer last name
     * @return $this
     */
    public function lastname($lastname) {
        $this->data['last_name'] = $lastname;
        return $this;
    }
    
    /**
     * Set customer phone number
     * 
     * @param string $phone Customer phone number
     * @return $this
     */
    public function phone($phone) {
        $this->data['phone_number'] = $phone;
        return $this;
    }
    
    /**
     * Set transaction reference
     * 
     * @param string $transactionRef Unique transaction reference
     * @return $this
     */
    public function transactionRef($transactionRef) {
        $this->data['tx_ref'] = $transactionRef;
        return $this;
    }
    
    /**
     * Set callback URL
     * 
     * @param string $callbackUrl URL to receive payment notification
     * @return $this
     */
    public function callbackUrl($callbackUrl) {
        $this->data['callback_url'] = $callbackUrl;
        return $this;
    }
    
    /**
     * Set return URL
     * 
     * @param string $returnUrl URL to redirect after payment
     * @return $this
     */
    public function returnUrl($returnUrl) {
        $this->data['return_url'] = $returnUrl;
        return $this;
    }
    
    /**
     * Set customizations
     * 
     * @param array $customizations Custom payment page settings
     * @return $this
     */
    public function customizations($customizations) {
        foreach ($customizations as $key => $value) {
            $this->data[$key] = $value;
        }
        return $this;
    }
    
    /**
     * Get all data
     * 
     * @return array Payment data
     */
    public function getData() {
        return $this->data;
    }
}

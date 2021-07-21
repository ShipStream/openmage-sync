<?php

/**
 * Helper class for communication with the warehouse API
 */
class ShipStream_Sync_Helper_Api extends Mage_Core_Helper_Abstract
{

    /**
     * @return bool
     */
    public function isConfigured()
    {
        return !! Mage::helper('shipstream')->getConfig('warehouse_api_url');
    }

    /**
     * Perform request to the warehouse API
     *
     * @param string $method
     * @param array $data
     * @return mixed
     * @throws Mage_Core_Exception
     */
    public function callback($method, $data = [])
    {
        $apiUrl = Mage::helper('shipstream')->getConfig('warehouse_api_url');
        if (empty($apiUrl)) {
            throw new Mage_Core_Exception(Mage::helper('shipstream')->__('The warehouse API URL is required.'));
        }
        $apiUrl = urldecode($apiUrl);
        if (FALSE === strpos($apiUrl, '{{method}}')) {
            throw new Mage_Core_Exception(Mage::helper('shipstream')->__('The warehouse API URL format is not valid.'));
        }
        $apiUrl = str_replace('{{method}}', $method, $apiUrl);
        $ch = $this->_curlInit($apiUrl, 'GET', $data);
        $data = $this->_curlExec($ch);
        return $data;
    }

    /**
     * Perform a cURL session
     *
     * @param CurlHandle $ch
     * @return array
     * @throws Mage_Core_Exception
     */
    protected function _curlExec($ch)
    {
        if (FALSE === ($response = curl_exec($ch))) {
            $e =  new Mage_Core_Exception(Mage::helper('shipstream')->__('Response error code: "%s". Error description: "%s".', curl_errno($ch), curl_error($ch)));
            curl_close($ch);
            throw $e;
        }
        if (FALSE === ($httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE))) {
            curl_close($ch);
            throw new Mage_Core_Exception(Mage::helper('shipstream')->__('Cannot get cURL response code.'));
        }
        curl_close($ch);
        if (200 !== $httpCode && 201 !== $httpCode) {
            $exceptionMessage = "Warehouse API Error ($httpCode).";
            $data = json_decode($response, TRUE);
            if (json_last_error() == JSON_ERROR_NONE) {
                if ( ! empty($data['errors'])) {
                    $errors = $data['errors'];
                    if (is_array($errors)) {
                        $exceptionMessage.= ': "'.implode('"; "', $errors).'"';
                    } else {
                        $exceptionMessage.= $errors;
                    }
                }
            } else if ( ! empty($response)) {
                $exceptionMessage.= ' Response: "'.$response.'"';
            }
            throw new Mage_Core_Exception($exceptionMessage, $httpCode);
        }
        $data = json_decode($response, TRUE);
        if (json_last_error() != JSON_ERROR_NONE) {
            throw new Mage_Core_Exception(Mage::helper('shipstream')->__('An error occurred while decoding JSON encoded string.'));
        }
        return $data;
    }

    /**
     * Initialize a cURL session
     *
     * @param string $url
     * @param string $type Allowed: GET, POST, PUT, DELETE.
     * @param array $data
     * @return CurlHandle
     * @throws Mage_Core_Exception
     */
    protected function _curlInit($url, $type, array $data = array())
    {
        if ( ! function_exists('curl_version')) {
            throw new Mage_Core_Exception(Mage::helper('shipstream')->__('cURL is not installed.'));
        }
        $type = strtoupper($type);
        if ( ! in_array($type, ['GET', 'POST', 'PUT', 'DELETE'])) {
            throw new Mage_Core_Exception(Mage::helper('shipstream')->__('Invalid custom request type.'));
        }
        if (in_array($type, ['GET','DELETE']) && $data) {
            $separator = strpos($url, '?') === false ? '?' : '&';
            $url .= $separator.http_build_query($data, '', '&');
        }
        $ch = curl_init($url);
        $header = [
            'Content-Type: application/json',
            'Accept: application/json',
        ];
        if (in_array($type, ['PUT', 'POST'])) {
            $json = empty($data) ? '{}' : json_encode($data);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
            $header[] = 'Content-Length: ' . strlen($json);
        }
        if (in_array($type, ['PUT','DELETE'])) {
            $header[] = 'X-HTTP-Method-Override: '.$type;
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $type);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        return $ch;
    }
}

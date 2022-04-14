<?php
namespace cURL;

class Response
{
    protected $ch;
    protected $error;
    protected $content = null;
    protected $headers;
    
    /**
     * Constructs response
     * 
     * @param Request $request Request
     * @param string  $content Content of reponse
     */
    public function __construct(Request $request, $content = null)
    {
        $this->ch = $request->getHandle();
        
        if ($content != null) {
            $header_size = $this->getInfo(CURLINFO_HEADER_SIZE);

            foreach (explode("\r\n", substr($content, 0, $header_size)) as $value) {
                if(false !== ($matches = explode(':', $value, 2))) {
                    if (count($matches) === 2) {
                        $headers_arr["{$matches[0]}"] = trim($matches[1]);
                    }
                }                
            }
            $this->headers = $headers_arr;

            $this->content = substr($content, $header_size);;
        }
    }
    
    /**
     * Get information regarding a current transfer
     * If opt is given, returns its value as a string
     * Otherwise, returns an associative array with
     * the following elements (which correspond to opt), or FALSE on failure.
     *
     * @param int $key One of the CURLINFO_* constants
     * @return mixed
     */
    public function getInfo($key = null)
    {
        return $key === null ? curl_getinfo($this->ch) : curl_getinfo($this->ch, $key);
    }
    
    /**
     * Returns content of request
     * 
     * @return string    Content
     */
    public function getContent()
    {
        return $this->content;
    }
    
    /**
     * Sets error instance
     * 
     * @param Error $error Error to set
     * @return void
     */
    public function setError(Error $error)
    {
        $this->error = $error;
    }
    
    /**
     * Returns a error instance
     * 
     * @return Error|null
     */
    public function getError()
    {
        return isset($this->error) ? $this->error : null;
    }
    
    /**
     * Returns the error number for the last cURL operation.    
     * 
     * @return int  Returns the error number or 0 (zero) if no error occurred. 
     */
    public function hasError()
    {
        return isset($this->error);
    }

    /**
     * Returns headers of request
     * 
     * @return array    Headers
     */
    public function getHeaders()
    {
        return $this->headers;
    }
}

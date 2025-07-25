<?php
namespace cURL;

use Symfony\Component\EventDispatcher\EventDispatcher;

class Request extends EventDispatcher implements RequestInterface
{
    /**
     * @var \CurlHandle cURL handler
     */
    protected $ch;
    
    /**
     * @var RequestsQueue Queue instance when requesting async
     */
    protected $queue;
    
    /**
     * @var Options Object containing options for current request
     */
    protected $options = null;

    /**
     * @var array Response Headers feed by the CURLOPT_HEADERFUNCTION callback
     */
    protected $responseHeaders;
    
    /**
     * Create new cURL handle
     *
     * @param string $url The URL to fetch.
     */
    public function __construct($url = null)
    {
        if ($url !== null) {
            $this->getOptions()->set(CURLOPT_URL, $url);
        }

        $this->getOptions()->set(CURLOPT_HEADERFUNCTION, function($ch, $headerLine) {
            $len = strlen($headerLine);
            
            // Handle HTTP status lines (e.g., HTTP/1.1 200 OK)
            if (preg_match('/^HTTP\/\d\.\d\s+\d+/', $headerLine)) {
                $this->responseHeaders = []; // Reset on redirect or multiple responses
            }

            $parts = explode(':', $headerLine, 2);
            if (count($parts) === 2) {
                $key = strtolower(trim($parts[0]));
                $value = trim($parts[1]);
                
                // Handle multiple headers with same name
                if (!isset($this->responseHeaders[$key])) {
                    $this->responseHeaders[$key] = $value;
                } else {
                    if (is_array($this->responseHeaders[$key])) {
                        $this->responseHeaders[$key][] = $value;
                    } else {
                        $this->responseHeaders[$key] = [$this->responseHeaders[$key], $value];
                    }
                }
            }

            return $len;
        });

        $this->ch = curl_init();
    }
    
    /**
     * Get the cURL\Options instance
     * Creates empty one if does not exist
     *
     * @return Options
     */
    public function getOptions()
    {
        if (!isset($this->options)) {
            $this->options = new Options();
        }
        return $this->options;
    }
    
    /**
     * Sets Options
     *
     * @param Options $options Options
     * @return void
     */
    public function setOptions(Options $options)
    {
        $this->options = $options;
    }

	/**
	 * Closes cURL resource and frees the memory.
	 * It is neccessary when you make a lot of requests
	 * and you want to avoid fill up the memory.
	 */
	public function __destruct() {
		if (isset($this->ch)) {
			curl_close($this->ch);
		}
	}
    
    /**
     * Returns cURL raw resource
     * 
     * @return \CurlHandle    cURL handle
     */
    public function getHandle()
    {
        return $this->ch;
    }
    
    /**
     * Get unique id of cURL handle
     * Useful for debugging or logging.
     *
     * @return int
     */
    public function getUID()
    {
        return (int)$this->ch;
    }

    /**
     * Get the response headers
     *
     * @return array
     */
    public function getResponseHeaders()
    {
        return $this->responseHeaders;
    }
    
    /**
     * Perform a cURL session.
     * Equivalent to curl_exec().
     * This function should be called after initializing a cURL
     * session and all the options for the session are set.
     *
     * Warning: it doesn't fire 'complete' event.
     *
     * @return Response
     */
    public function send()
    {
        if ($this->options instanceof Options) {
            $this->options->applyTo($this);
        }
        $content = curl_exec($this->ch);
        
        $response = new Response($this, $content);
        $response->setHeaders($this->responseHeaders);
        $errorCode = curl_errno($this->ch);
        if ($errorCode !== CURLE_OK) {
            $response->setError(new Error(curl_error($this->ch), $errorCode));
        }
        return $response;
    }

	/**
	 * Binds the request to a given RequestQueue.
	 *
	 * @param \cURL\RequestsQueue $requestsQueue
	 * @throws \cURL\Exception
	 */
	public function attachTo(RequestsQueue $requestsQueue) {
		if (isset($this->queue)) {
			throw new Exception('Already bound to a RequestQueue.');
		}
		$this->queue = $requestsQueue;
		$this->queue->attach($this);
	}

    /**
     * Creates new RequestsQueue with single Request attached to it
     * and calls RequestsQueue::socketPerform() method.
     *
     * @see RequestsQueue::socketPerform()
     */
    public function socketPerform()
    {
        if (!isset($this->queue)) {
            $this->queue = new RequestsQueue();
            $this->queue->attach($this);
        }
        return $this->queue->socketPerform();
    }

    /**
     * Calls socketSelect() on previously created RequestsQueue
     *
     * @see RequestsQueue::socketSelect()
     */
    public function socketSelect($timeout = 1)
    {
        if (!isset($this->queue)) {
            throw new Exception('You need to call socketPerform() before.');
        }
        return $this->queue->socketSelect($timeout);
    }
}

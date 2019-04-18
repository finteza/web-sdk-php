<?php

/**
 * Finteza is the system features real-time web analytics.
 * For more information, visit the official Finteza website:
 * https://www.finteza.com
 *
 * PHP Version 5.3.0
 *
 * @category Finteza
 * @package  FintezaAnalytics
 * @author   Finteza Ltd. <support@finteza.com>
 * @license  BSD License http://www.opensource.org/licenses/bsd-license.php
 * @link     https://www.finteza.com
 */

/**
 * FintezaAnalytics class
 * For proxy analytics requests and send events from server
 *
 * @category Finteza
 * @package  FintezaAnalytics
 * @author   Finteza Ltd. <support@finteza.com>
 * @license  BSD License http://www.opensource.org/licenses/bsd-license.php
 * @link     https://www.finteza.com
 */
class FintezaAnalytics
{
    /**
     * Prefix to cookies
     * Proxy method transfers finteza cookies to the first-party domain
     * The prefix is needed so that there would be no conflict with the other cookies
     *
     * @const string
     */
    const PROXY_COOKIES_PREFIX = '_fz_';

    /**
     * Default value for url
     * Used if url did not specified in functions call
     *
     * @@const string
     */
    const DEFAULT_URL = "https://content.mql5.com";

    /**
     * Proxy cookies list
     * List of finteza cookies that will be transferred
     * to the first-patry domain and back
     *
     * @var array
     */
    private $_proxyCookies = array('uniq');

    /**
     * Finteza server URL
     *
     * @var string
     */
    private $_url = '';

    /**
     * Finteza website token
     * The token is required to sign the header X-Forwarder-For.
     * The value of this header is passed from the client request.
     *
     * @var string
     */
    private $_token = '';

    /**
     * Proxying path
     * The path on the site that will be used for analytics requests.
     * Requests that start from this path will be proxied to the Finteza.
     *
     * @var string
     */
    private $_path = '';

    /**
     * Website ID in Finteza platform
     *
     * @var string
     */
    private $_websiteId = '';

    /**
     * Refefer for server events
     *
     * @var string
     */
    private $_referer = '';

    /**
     * Constructor
     *
     * @param string $url       Finteza server URL
     * @param string $path      Proxying path
     * @param string $websiteId Website ID in Finteza platform
     * @param string $referer   Refefer for server events
     * @param string $token     Finteza website token
     */
    private function __construct(
        $url,
        $path,
        $websiteId,
        $referer = null,
        $token = null
    ) {
        $this->_url = (is_null($url) || empty($url)) ? self::DEFAULT_URL : $url;
        $this->_token = $token;
        $this->_path = $path;
        $this->_websiteId = $websiteId;
        $this->_referer = $referer;
    }

    /**
     * Transfer client requests to Finteza server
     *
     * @param array $options Options
     *
     * @return null Nothing
     *
     * @see https://www.finteza.com/developer/sdks/php/
     */
    public static function proxy($options)
    {
        if (is_null($options)) {
            return;
        }

        $fsdk = new self(
            isset($options['url']) ? $options['url'] : null,
            isset($options['path']) ? $options['path'] : null,
            null,
            null,
            isset($options['token']) ? $options['token'] : null
        );

        $fsdk->_proxyTrack();
    }

    /**
     * Send server event to Finteza
     *
     * @param array $options Event options
     *
     * @return bool True if request was sent successfully; otherwise False;
     *
     * @see https://www.finteza.com/developer/sdks/php/
     */
    public static function event($options)
    {
        if (is_null($options)) {
            return false;
        }

        $fsdk = new self(
            isset($options['url']) ? $options['url'] : null,
            null,
            isset($options['websiteId']) ? $options['websiteId'] : null,
            isset($options['referer']) ? $options['referer'] : null,
            isset($options['token']) ? $options['token'] : null
        );

        return $fsdk->_sendEvent(
            isset($options['name']) ? $options['name'] : null,
            isset($options['backReferer']) ? $options['backReferer'] : null,
            isset($options['userIp']) ? $options['userIp'] : null,
            isset($options['userAgent']) ? $options['userAgent'] : null,
            isset($options['value']) ? $options['value'] : null,
            isset($options['unit']) ? $options['unit'] : null
        );
    }

    /**
     * Set headers and outputs proxy content
     *
     * @return null Nothing
     */
    private function _proxyTrack()
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $headers = $this->_createRequestHeaders();
        $params = $this->_createRequestParams($method);
        $url = $this->_createRequestUrl();
        $parsedUrl = parse_url($url);

        $path = $this->_path;
        $protocol = $_SERVER['REQUEST_SCHEME'].'://';
        //var_dump($_SERVER);
        if (substr($path, 0, 1) != '/') {
            $path = '/' . $path;
        }
        // Handle core.js
        if (preg_match('/core\.js$/', $url) !== false
            && preg_match('/core\.js$/', $url) !== 0
        ) {
            $params['host'] = $protocol . $_SERVER['HTTP_HOST'] . $path;
        }
        // Handle amp.js
        if (preg_match('/amp\.js$/', $url) !== false
            && preg_match('/amp\.js$/', $url) !== 0
        ) {
            $params['host'] = $protocol . $_SERVER['HTTP_HOST'] . $path;
        }
        // Append query string for GET requests
        if ($method == 'GET'
            && count($params) > 0
            && (!array_key_exists('query', $parsedUrl) || empty($parsedUrl['query']))
        ) {
            $url .= '?' . http_build_query($params);
        }
        // Send request
        $response = $this->_sendRequest($url, $headers, $params, $method);
        $responseContent = $this->_processResponse($response);

        // Remove headers
        header_remove('X-Powered-By');
        header_remove('Server');

        // Output result
        print($responseContent);
        exit;
    }

    /**
     * Send event to Finteza
     *
     * @param string      $name        Event name
     * @param string|null $backReferer Back referer for event
     * @param string|null $userIp      User client ip for event
     * @param string|null $userAgent   User-Agent for event
     * @param string|null $value       Value param
     * @param string|null $unit        Unit for value param
     *
     * @return bool True if event was sent successfully; otherwise, False
     */
    private function _sendEvent(
        $name,
        $backReferer = null,
        $userIp = null,
        $userAgent = null,
        $value = null,
        $unit = null
    ) {
        if (empty($name)) {
            return false;
        }

        $name = str_replace(' ', '+', $name);

        $url = $this->_url;
        if (!preg_match('/\/$/', $url)) {
            $url .= '/';
        }
        
        $url .= 'tr?';
        $query = 'id=' . urlencode($this->_websiteId);
        $query .= '&event=' . urlencode($name);
        $query .= '&ref=' . urlencode($this->_referer);
        if (!is_null($backReferer) && !empty($backReferer)) {
            $query .= '&back_ref=' . urlencode($backReferer);
        }
        if (!is_null($value) && !empty($value)) {
            $query .= '&value=' . urlencode($value);
        }
        if (!is_null($unit) && !empty($unit)) {
            $query .= '&unit=' . urlencode($unit);
        }

        $parts = parse_url($url);
        $parts['path'] .= '?'.$query;

        try {
            $fp = stream_socket_client(
                'ssl://'.$parts['host'].':443',
                $errno,
                $errstr,
                5
            );
            $parts['path'] .= '?'.$query;
            $out = "GET ".$parts['path']." HTTP/1.1\r\n";
            $out.= "Host: ".$parts['host']."\r\n";
            if (!is_null($userIp) && !empty($userIp)) {
                $out.= "X-Forwarded-For: ".$userIp."\r\n";
                $out.= "X-Forwarded-For-Sign: "
                    .md5($userIp . ':' . $this->_token)
                    ."\r\n";
            }
            if (!is_null($userAgent) && !empty($userAgent)) {
                $out.= "User-Agent: ".$userAgent."\r\n";
            }
            $out.= "Connection: Close\r\n\r\n";
            fwrite($fp, $out);
            fclose($fp);
            return true;
        } catch (Exception $ex) {
            return false;
        }
    }

    /**
     * Create request headers array for request to finteza server
     *
     * @return array Headers for request
     */
    private function _createRequestHeaders()
    {
        // Identify request headers
        $headers = array();
        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0 || strpos($key, 'CONTENT_') === 0) {

                $key = str_replace('HTTP_', '', $key);
                $headerName = str_replace('_', ' ', $key);
                $headerName = ucwords(strtolower($headerName));
                $headerName = str_replace(' ', '-', $headerName);

                // Ignore Cookies
                if (strcasecmp($headerName, 'Cookie') == 0) {
                    continue;
                }

                if (!in_array($headerName, array('Host', 'X-Proxy-Url'))) {
                    $headers[] = "$headerName: $value";
                }
            }
        }

        $ip = $_SERVER['REMOTE_ADDR'];

        // Set HTTP_X_FORWARDED_FOR header to transfer client IP
        $headers[] = 'X-Forwarded-For: ' . $ip;

        // Add signature
        $headers[] = 'X-Forwarded-For-Sign: ' . md5($ip . ':' . $this->_token);

        // Proxy only defined cookies
        $cookies = '';
        foreach ($this->_proxyCookies as $key => $cookie) {
            if (isset($_COOKIE[self::PROXY_COOKIES_PREFIX . $cookie])) {
                $cookieValue = $_COOKIE[self::PROXY_COOKIES_PREFIX . $cookie];
                $cookies .= $cookie . '=' . $cookieValue . ';';
            }
        }

        if (!empty($cookies)) {
            $headers[] = "Cookie: $cookies";
        }

        return $headers;
    }

    /**
     * Create request params array for request to finteza server
     *
     * @param string $method Request method
     *
     * @return string|null Request params array or query string
     */
    private function _createRequestParams($method)
    {
        if ('GET' == $method) {
            $params = $_GET;
        } elseif ('POST' == $method) {
            $params = $_POST;
            if (empty($params)) {
                $data = file_get_contents('php://input');
                if (!empty($data)) {
                    $params = $data;
                }
            }
        } else {
            $params = null;
        }

        return $params;
    }

    /**
     * Create request URL to finteza server
     *
     * @return string
     */
    private function _createRequestUrl()
    {
        $currentUri = parse_url($_SERVER['REQUEST_URI']);
        $requestUrl = '';
        $cPath = $currentUri['path'];

        if (substr($cPath, 0, strlen($this->_path)) == $this->_path) {
            $requestUrl = $this->_url . substr($cPath, strlen($this->_path));
        }

        return $requestUrl;
    }

    /**
     * Sends request to finteza server
     *
     * @param string       $url     Request URL
     * @param array        $headers Request headers string
     * @param array|string $params  Request params array or query string
     * @param string       $method  Request method
     *
     * @return string
     */
    private function _sendRequest($url, $headers, $params, $method)
    {
        // let the request begin
        $request = curl_init($url);

        // (re-)send headers
        curl_setopt($request, CURLOPT_HTTPHEADER, $headers);

        // return response
        curl_setopt($request, CURLOPT_RETURNTRANSFER, true);

        // enabled response headers
        curl_setopt($request, CURLOPT_HEADER, true);

        // add data for POST, PUT or DELETE requests
        if ('POST' == $method) {
            $postData = is_array($params) ? http_build_query($params) : $params;
            curl_setopt($request, CURLOPT_POST, true);
            curl_setopt($request, CURLOPT_POSTFIELDS, $postData);
        }
        
        // retrieve response (headers and content)
        $response = curl_exec($request);
        if (curl_errno($request)) {
            return '';
        }
        curl_close($request);
        if (false === $response) {
            $response = '';
        }
        return $response;
    }

    /**
     * Sets response headers and returns response content
     *
     * @param object $response Response handle object
     *
     * @return string Response content
     */
    private function _processResponse($response)
    {
        // split response to header and content
        list($headers, $content) = preg_split('/(\r\n){2}/', $response, 2);

        // (re-)send the headers
        $headers = preg_split('/(\r\n){1}/', $headers);
        foreach ($headers as $key => $header) {

            // Process non-cookies header
            if (substr($header, 0, strlen('Set-Cookie:')) !== 'Set-Cookie:') {
                if (!preg_match('/^(Transfer-Encoding):/', $header)) {
                    header($header, false);
                }
                continue;
            }

            $pattern = '/^^Set-Cookie:\s*([^=]*)(.*)(domain=[^;]*)(.*)/mi';
            preg_match_all($pattern, $header, $matches);

            $cookies = '';
            foreach ($matches[1] as $item) {

                if (!in_array($item, $this->_proxyCookies)) {
                    continue;
                }

                $cookies .= self::PROXY_COOKIES_PREFIX . $item . $matches[2][0];

                // Process domain
                if (isset($matches[3])) {
                    $paramLen = strlen('domain=');
                    if (substr($matches[3][0], 0, $paramLen) === 'domain=') {
                        $cookies .= 'domain=' . $_SERVER['SERVER_NAME'];
                    }
                }

                if (isset($matches[4])) {
                    $cookies .= $matches[4][0];
                }

                break;
            }

            if (!empty($cookies)) {
                header('Set-Cookie:' . $cookies, false);
            }

            continue;
        }

        return $content;
    }
}

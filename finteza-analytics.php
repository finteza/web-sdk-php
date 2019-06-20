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
     * @const string
     */
    const DEFAULT_URL = "https://content.mql5.com";

    /**
     * Default user-agent for server-side events
     * 
     * @const string
     */
    const DEFAULT_USER_AGENT = 'FintezaPHP/1.0';

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
        $referer,
        $token
    ) {

        // default values
        $url = (is_null($url) || empty($url)) ? self::DEFAULT_URL : $url;
        $path = (is_null($path) || empty($path)) ? '/' : $path;
        $websiteId = (is_null($websiteId) || empty($websiteId)) ? '' : $websiteId;

        // check referer
        if (is_null($referer) || empty($referer)) {
            $is_ssl = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
            $referer = $is_ssl ? 'https' : 'http';
            $referer .= "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
        }

        // check path
        if (substr($path, 0, 1) != '/') {
            $path = '/' . $path;
        }

        // save options
        $this->_url = $url;
        $this->_path = $path;
        $this->_websiteId = $websiteId;
        $this->_referer = $referer;
        $this->_token = $token;
    }

    /**
     * Send server event to Finteza
     *
     * @param array $options Event options
     *
     * @return bool True if request was sent successfully; otherwise False;
     *
     * @see https://www.finteza.com/en/integrations/php-sdk/php-sdk-events
     */
    public static function event($options)
    {
        if (is_null($options)) {
            return false;
        }

        $fsdk = new self(
            $options['url'],
            null,
            $options['websiteId'],
            $options['referer'],
            $options['token']
        );

        return $fsdk->_sendEvent(
            isset($options['name']) ? $options['name'] : null,
            isset($options['backReferer']) ? $options['backReferer'] : null,
            isset($options['userIp']) ? $options['userIp'] : null,
            isset($options['userAgent'])
                ? $options['userAgent']
                : DEFAULT_USER_AGENT,
            isset($options['value']) ? $options['value'] : null,
            isset($options['unit']) ? $options['unit'] : null
        );
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
        $backReferer,
        $userIp,
        $userAgent,
        $value,
        $unit
    ) {
        // check event name
        if (empty($name)) {
            return false;
        }

        // replace spaces
        $name = str_replace(' ', '+', $name);

        // add path
        $path = '/tr?';

        // add params
        $path .= 'id=' . urlencode($this->_websiteId);
        $path .= '&event=' . urlencode($name);
        $path .= '&ref=' . urlencode($this->_referer);

        if (!is_null($backReferer) && !empty($backReferer)) {
            $path .= '&back_ref=' . urlencode($backReferer);
        }
        if (!is_null($value) && !empty($value)) {
            $path .= '&value=' . urlencode($value);
        }
        if (!is_null($unit) && !empty($unit)) {
            $path .= '&unit=' . urlencode($unit);
        }

        // send request
        try
        {
            // get host
            $host = parse_url($this->_url)['host'];

            // create connect
            $errno = '';
            $errstr = '';
            $fp = stream_socket_client('ssl://'. $host . ':443', $errno, $errstr, 5);
            if ($fp === false) {
                return false;
            }

            // build headers
            $out  = "GET " . $path . " HTTP/1.1\r\n";
            $out .= "Host: " . $host . "\r\n";

            if (!is_null($userIp) && !empty($userIp)) {
                $out .= "X-Forwarded-For: " . $userIp . "\r\n";
                $out .= "X-Forwarded-For-Sign: "
                     . md5($userIp . ':' . $this->_token)
                     . "\r\n";
            }

            if (!is_null($userAgent) && !empty($userAgent)) {
                $out .= "User-Agent: " . $userAgent . "\r\n";
            }

            $out .= "Connection: Close\r\n\r\n";

            // connect and close
            fwrite($fp, $out);
            fclose($fp);
            return true;
        }
        catch (Exception $ex)
        {
            return false;
        }
    }

    /**
     * Transfer client requests to Finteza server
     *
     * @param array $options Options
     *
     * @return null Nothing
     *
     * @see https://www.finteza.com/en/integrations/php-sdk/php-sdk-proxy
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

        $fsdk->_proxyRequest();
    }

    /**
     * Set headers and outputs proxy content
     *
     * @return null Nothing
     */
    private function _proxyRequest()
    {
        // get request properties
        $request_method = $_SERVER['REQUEST_METHOD'];
        $request_host = $_SERVER['HTTP_HOST'];
        $request_uri = $_SERVER['REQUEST_URI'];
        $request_port = $_SERVER['SERVER_PORT'];
        $request_params = $_GET;
        $request_body = file_get_contents('php://input');

        // create proxy headers
        $proxy_headers = $this->_getProxyHeaders();

        // create proxy URI (without domain and params)
        $proxy_uri = $request_uri;
        if (substr($proxy_uri, 0, strlen($this->_path)) == $this->_path) {
            $proxy_uri = substr($proxy_uri, strlen($this->_path));
        }

        // counter scripts handler
        $has_corejs = preg_match('/core\.js$/', $proxy_uri) === 1;
        $has_ampjs = preg_match('/amp\.js$/', $proxy_uri) === 1;

        if ($has_corejs || $has_ampjs) {
            $proxy_host = '//' . $request_host;
            if ($request_port != 443 && $request_port != 80) {
                $proxy_host .= ':' . $request_port;
            }

            $has_params = strpos($proxy_uri, '?') !== false;
            $proxy_uri .= ($has_params ? '&' : '?');
            $proxy_uri .= 'host=' . urlencode($proxy_host) . $this->_path;
        }

        // execute request
        $response = $this->_sendProxyRequest(
            $request_method,
            $proxy_uri,
            $proxy_headers,
            $request_body
        );

        // parse response
        $responseContent = $this->_parseProxyResponse($request_method, $response);

        // remove headers
        header_remove('X-Powered-By');

        // return response
        print($responseContent);
        exit;
    }

    /**
     * Create request headers array for request to finteza server
     *
     * @return array Headers for request
     */
    private function _getProxyHeaders()
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
     * Sends request to finteza server
     *
     * @param string $method  Request method
     * @param string $uri     Request URI
     * @param array  $headers Request headers
     * @param string $body    Request body
     *
     * @return string
     */
    private function _sendProxyRequest($method, $uri, $headers, $body)
    {

        // let the request begin
        $request = curl_init($this->_url . $uri);

        // (re-)send headers
        curl_setopt($request, CURLOPT_HTTPHEADER, $headers);

        // return response
        curl_setopt($request, CURLOPT_RETURNTRANSFER, true);

        // enabled response headers
        curl_setopt($request, CURLOPT_HEADER, true);

        // set timeout
        curl_setopt($request, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($request, CURLOPT_TIMEOUT, 10);

        // add data for POST-request
        if ($method == 'POST') {
            curl_setopt($request, CURLOPT_POST, true);
            curl_setopt($request, CURLOPT_POSTFIELDS, $body);
            curl_setopt($request, CURLOPT_HTTPHEADER, array("Expect:"));
        }

        // retrieve response (headers and content)
        $response = curl_exec($request);

        // check errors
        if (curl_errno($request)) {
            return '';
        }

        // close connect
        curl_close($request);

        // check response
        if ($response == false) {
            $response = '';
        }

        return $response;
    }

    /**
     * Sets response headers and returns response content
     *
     * @param object $method   Request method
     * @param object $response Response handle object
     *
     * @return string Response content
     */
    private function _parseProxyResponse($method, $response)
    {
        // split response to header and content
        list($headers, $content) = preg_split('/(\r\n){2}/', $response, 2);

        // no parse POST-requests
        if ($method == 'POST') {
            return $content;
        }

        // (re-)send the headers
        $headers = preg_split('/(\r\n){1}/', $headers);
        foreach ($headers as $key => $header) {

            // Process non-cookies header
            if (substr($header, 0, strlen('Set-Cookie:')) !== 'Set-Cookie:') {
                if (!preg_match('/^(Transfer-Encoding):/', $header)) {
                    header($header, true);
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
                        $cookies .= 'domain=' . $_SERVER['HTTP_HOST'];
                    }
                }

                if (isset($matches[4])) {
                    $cookies .= $matches[4][0];
                }

                break;
            }

            if (!empty($cookies)) {
                header('Set-Cookie:' . $cookies, true);
            }

            continue;
        }

        return $content;
    }
}

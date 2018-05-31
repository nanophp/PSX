<?php
/*
 * PSX is a open source PHP framework to develop RESTful APIs.
 * For the current version and informations visit <http://phpsx.org>
 *
 * Copyright 2010-2018 Christoph Kappestein <christoph.kappestein@gmail.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace PSX\Oauth;

use PSX\Http\Client\ClientInterface;
use PSX\Http\Client\PostRequest;
use PSX\Oauth\Data\Response;
use PSX\Oauth\Signature;
use PSX\Uri\Url;
use RuntimeException;

/**
 * This is a consumer implementation of OAuth Core 1.0
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @author  Andy Smith <http://term.ie>
 * @license http://www.apache.org/licenses/LICENSE-2.0
 * @link    http://phpsx.org
 * @see     http://tools.ietf.org/html/rfc5849
 */
class Consumer
{
    /**
     * @var \PSX\Http\Client\ClientInterface
     */
    protected $client;

    /**
     * @var \PSX\Oauth\ResponseImporter
     */
    protected $importer;

    public function __construct(ClientInterface $client)
    {
        $this->client   = $client;
        $this->importer = new ResponseImporter();
    }

    /**
     * Requests a new "request token" from the $url using the consumer key and
     * secret. The $url must be valid request token endpoint. Returns an array
     * with all key values pairs from the response i.e.
     * <code>
     * $response = $oauth->requestToken(...);
     *
     * $token       = $response->getToken();
     * $tokenSecret = $response->getTokenSecret();
     * </code>
     *
     * @see http://tools.ietf.org/html/rfc5849#section-2.1
     * @param \PSX\Uri\Url $url
     * @param string $consumerKey
     * @param string $consumerSecret
     * @param string $method
     * @param string $callback
     * @return \PSX\Oauth\Data\Response
     */
    public function requestToken(Url $url, $consumerKey, $consumerSecret, $method = 'HMAC-SHA1', $callback = null)
    {
        $values = array(
            'oauth_consumer_key'     => $consumerKey,
            'oauth_signature_method' => $method,
            'oauth_timestamp'        => self::getTimestamp(),
            'oauth_nonce'            => self::getNonce(),
            'oauth_version'          => self::getVersion(),
        );

        // if we have an callback add them to the request
        if (!empty($callback)) {
            $values['oauth_callback'] = $callback;
        } else {
            $values['oauth_callback'] = 'oob';
        }

        // build the base string
        $requestMethod = 'POST';
        $params        = array_merge($values, $url->getParameters());
        $baseString    = self::buildBasestring($requestMethod, $url, $params);

        // get the signature object
        $signature = self::getSignature($method);

        // generate the signature
        $values['oauth_signature'] = $signature->build($baseString, $consumerSecret);

        // request unauthorized token
        $request  = new PostRequest($url, array(
            'Authorization' => 'OAuth realm="psx", ' . self::buildAuthString($values),
            'User-Agent'    => __CLASS__,
        ));
        $response = $this->client->request($request);

        // parse the response
        return $this->importer->import(new Response(), (string) $response->getBody());
    }

    /**
     * Exchange an request token with an access token. We receive the "token"
     * and "verifier" from the service provider wich redirects the user to the
     * callback in this redirect are the $token and $verifier. Returns an access
     * token and secret i.e.
     * <code>
     * $response = $oauth->accessToken(...);
     *
     * $token       = $response->getToken();
     * $tokenSecret = $response->getTokenSecret();
     * </code>
     *
     * @see http://tools.ietf.org/html/rfc5849#section-2.3
     * @param \PSX\Uri\Url $url
     * @param string $consumerKey
     * @param string $consumerSecret
     * @param string $token
     * @param string $tokenSecret
     * @param string $verifier
     * @param string $method
     * @return \PSX\Oauth\Data\Response
     */
    public function accessToken(Url $url, $consumerKey, $consumerSecret, $token, $tokenSecret, $verifier, $method = 'HMAC-SHA1')
    {
        $values = array(
            'oauth_consumer_key'     => $consumerKey,
            'oauth_token'            => $token,
            'oauth_signature_method' => $method,
            'oauth_timestamp'        => self::getTimestamp(),
            'oauth_nonce'            => self::getNonce(),
            'oauth_version'          => self::getVersion(),
            'oauth_verifier'         => $verifier,
        );

        // build the base string
        $requestMethod = 'POST';
        $params        = array_merge($values, $url->getParameters());
        $baseString    = self::buildBasestring($requestMethod, $url, $params);

        // get the signature object
        $signature = self::getSignature($method);

        // generate the signature
        $values['oauth_signature'] = $signature->build($baseString, $consumerSecret, $tokenSecret);

        // request access token
        $request  = new PostRequest($url, array(
            'Authorization' => 'OAuth realm="psx", ' . self::buildAuthString($values),
            'User-Agent'    => __CLASS__,
        ));
        $response = $this->client->request($request);

        // parse the response
        return $this->importer->import(new Response(), (string) $response->getBody());
    }

    /**
     * If you have established a token and token secret you can use this method
     * to get the authorization header. You can add the header to an http
     * request to make an valid oauth request i.e.
     * <code>
     * $header = array(
     * 	'Authorization: ' . $oauth->getAuthorizationHeader(...),
     * );
     * </code>
     *
     * @param \PSX\Uri\Url $url
     * @param string $consumerKey
     * @param string $consumerSecret
     * @param string $token
     * @param string $tokenSecret
     * @param string $method
     * @param string $requestMethod
     * @param array $post
     * @return string
     */
    public function getAuthorizationHeader(Url $url, $consumerKey, $consumerSecret, $token, $tokenSecret, $method = 'HMAC-SHA1', $requestMethod = 'GET', array $post = array())
    {
        $values = array(
            'oauth_consumer_key'     => $consumerKey,
            'oauth_token'            => $token,
            'oauth_signature_method' => $method,
            'oauth_timestamp'        => self::getTimestamp(),
            'oauth_nonce'            => self::getNonce(),
            'oauth_version'          => self::getVersion(),
        );

        // build the base string
        $params = array_merge($values, $url->getParameters());

        if ($requestMethod == 'POST' && !empty($post)) {
            $params = array_merge($params, $post);
        }

        $baseString = self::buildBasestring($requestMethod, $url, $params);

        // get the signature object
        $signature = self::getSignature($method);

        // generate the signature
        $values['oauth_signature'] = $signature->build($baseString, $consumerSecret, $tokenSecret);

        // build request
        $authorizationHeader = 'OAuth realm="psx", ' . self::buildAuthString($values);

        return $authorizationHeader;
    }

    /**
     * Returns the signature object based on the $method throws an exception if
     * the method is not supported
     *
     * @param string $method
     * @return \PSX\Oauth\SignatureAbstract
     */
    public static function getSignature($method)
    {
        switch ($method) {
            case 'HMAC-SHA1':
                return new Signature\HMACSHA1();
                break;

            case 'RSA-SHA1':
                return new Signature\RSASHA1();
                break;

            case 'PLAINTEXT':
                return new Signature\PLAINTEXT();
                break;

            default:
                throw new RuntimeException('Invalid signature method');
                break;
        }
    }

    /**
     * Build the string that we use in the authentication header
     *
     * @param array $data
     * @return string
     */
    public static function buildAuthString(array $data)
    {
        $str = array();

        foreach ($data as $k => $v) {
            $str[] = self::urlEncode($k) . '="' . self::urlEncode($v) . '"';
        }

        return implode(', ', $str);
    }

    /**
     * Builds the basestring for the signature.
     *
     * @see http://tools.ietf.org/html/rfc5849#section-3.4.1
     * @param string $method
     * @param \PSX\Uri\Url $url
     * @param array $data
     * @return string
     */
    public static function buildBasestring($method, Url $url, array $data)
    {
        $base = array();
        $base[] = self::urlEncode(self::getNormalizedMethod($method));
        $base[] = self::urlEncode(self::getNormalizedUrl($url));
        $base[] = self::urlEncode(self::getNormalizedParameters($data));

        return implode('&', $base);
    }

    /**
     * Returns the method in uppercase
     *
     * @param string $method
     * @return string
     */
    public static function getNormalizedMethod($method)
    {
        return strtoupper($method);
    }

    /**
     * Normalize the url like defined in
     *
     * @see http://tools.ietf.org/html/rfc5849#section-3.4.1.2
     * @param \PSX\Uri\Url $url
     * @return string
     */
    public static function getNormalizedUrl(Url $url)
    {
        $scheme = $url->getScheme();
        $host   = $url->getHost();
        $port   = $url->getPort();
        $path   = $url->getPath();

        // no port for 80 (http) and 443 (https)
        if ((($port == 80 || empty($port)) && strcasecmp($scheme, 'http') == 0) || (($port == 443 || empty($port)) && strcasecmp($scheme, 'https') == 0)) {
            $normalizedUrl = $scheme . '://' . $host . $path;
        } else {
            if (!empty($port)) {
                $normalizedUrl = $scheme . '://' . $host . ':' . $port . $path;
            } else {
                throw new RuntimeException('No port specified');
            }
        }

        return strtolower($normalizedUrl);
    }

    /**
     * Returns the parameters that we need to create the basestring
     *
     * @param array $data
     * @return string
     */
    public static function getNormalizedParameters(array $data)
    {
        $params = array();

        $keys   = array_map('PSX\Oauth\Consumer::urlEncode', array_keys($data));
        $values = array_map('PSX\Oauth\Consumer::urlEncode', array_values($data));
        $data   = array_combine($keys, $values);


        uksort($data, 'strnatcmp');


        foreach ($data as $k => $v) {
            if ($k != 'oauth_signature') {
                $params[] = $k . '=' . $v;
            }
        }

        return implode('&', $params);
    }

    /**
     * Encode values RFC3986
     *
     * @see http://tools.ietf.org/html/rfc5849#section-3.6
     * @param string $data
     * @return string
     */
    public static function urlEncode($data)
    {
        return str_replace('%7E', '~', rawurlencode($data));
    }

    /**
     * Decode values RFC3986
     *
     * @param string $data
     * @return string
     */
    public static function urlDecode($data)
    {
        return rawurldecode($data);
    }

    /**
     * Returns the current timestamp used in a request
     *
     * @return integer
     */
    public static function getTimestamp()
    {
        return time();
    }

    /**
     * Returns the nonce used in a request
     *
     * @return string
     */
    public static function getNonce()
    {
        return substr(md5(uniqid(mt_rand(), true)), 0, 16);
    }

    /**
     * Returns the current version use in a request
     *
     * @return string
     */
    public static function getVersion()
    {
        return '1.0';
    }

    /**
     * This method returns an array with the support methods to sign a request
     *
     * @return array
     */
    public static function getSupportedMethods()
    {
        return array('HMAC-SHA1', 'PLAINTEXT');
    }
}

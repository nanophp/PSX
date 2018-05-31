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

namespace PSX\Oauth\Tests;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PSX\Http\Authentication;
use PSX\Http\Client\Client;
use PSX\Http\Client\GetRequest;
use PSX\Oauth\Data;
use PSX\Oauth\Consumer;
use PSX\Uri\Url;

/**
 * ConsumerTest
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.apache.org/licenses/LICENSE-2.0
 * @link    http://phpsx.org
 */
class ConsumerTest extends \PHPUnit_Framework_TestCase
{
    const CONSUMER_KEY      = 'dpf43f3p2l4k3l03';
    const CONSUMER_SECRET   = 'kd94hf93k423kf44';

    const TMP_TOKEN         = 'hh5s93j4hdidpola';
    const TMP_TOKEN_SECRET  = 'hdhd0244k9j7ao03';
    const VERIFIER          = 'hfdp7dh39dks9884';
    const TOKEN             = 'nnch734d00sl2jdk';
    const TOKEN_SECRET      = 'pfkkdhi9sl3r4s00';

    public function testFlow()
    {
        $tmpToken       = self::TMP_TOKEN;
        $tmpTokenSecret = self::TMP_TOKEN_SECRET;
        $token          = self::TOKEN;
        $tokenSecret    = self::TOKEN_SECRET;

        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/x-www-form-urlencoded'], "oauth_token={$tmpToken}&oauth_token_secret={$tmpTokenSecret}&oauth_callback_confirmed=1"),
            new Response(200, ['Content-Type' => 'application/x-www-form-urlencoded'], "oauth_token={$token}&oauth_token_secret={$tokenSecret}"),
            new Response(200, ['Content-Type' => 'text/plain'], 'SUCCESS'),
        ]);

        $container = [];
        $history = Middleware::history($container);

        $stack = HandlerStack::create($mock);
        $stack->push($history);

        $client = new Client(['handler' => $stack]);
        $oauth  = new Consumer($client);

        // request token
        $url      = new Url('http://127.0.0.1/requestToken');
        $response = $oauth->requestToken($url, self::CONSUMER_KEY, self::CONSUMER_SECRET);

        $this->assertInstanceOf(Data\Response::class, $response);
        $this->assertEquals(self::TMP_TOKEN, $response->getToken());
        $this->assertEquals(self::TMP_TOKEN_SECRET, $response->getTokenSecret());

        // if we have optained temporary credentials we can redirect the user
        // to grant access to the credentials
        // $oauth->userAuthorization($url, array('oauth_token' => $response->getToken()))

        // if the user gets redirected back we can exchange the temporary
        // credentials to an access token we also get an verifier as GET
        // parameter
        $url      = new Url('http://127.0.0.1/accessToken');
        $response = $oauth->accessToken($url, self::CONSUMER_KEY, self::CONSUMER_SECRET, self::TMP_TOKEN, self::TMP_TOKEN, self::VERIFIER);

        $this->assertInstanceOf(Data\Response::class, $response);
        $this->assertEquals(self::TOKEN, $response->getToken());
        $this->assertEquals(self::TOKEN_SECRET, $response->getTokenSecret());

        // now we can make an request to the protected api
        $url      = new Url('http://127.0.0.1/api');
        $auth     = $oauth->getAuthorizationHeader($url, self::CONSUMER_KEY, self::CONSUMER_SECRET, self::TOKEN, self::TOKEN_SECRET, 'HMAC-SHA1', 'GET');
        $request  = new GetRequest($url, array('Authorization' => $auth));
        $response = $client->request($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('SUCCESS', (string) $response->getBody());

        $this->assertEquals(3, count($container));
        $transaction = array_shift($container);

        $header = $transaction['request']->getHeaderLine('Authorization');
        $auth   = Authentication::decodeParameters($header);

        $this->assertEquals(self::CONSUMER_KEY, $auth['oauth_consumer_key']);
        $this->assertEquals('HMAC-SHA1', $auth['oauth_signature_method']);
        $this->assertTrue(isset($auth['oauth_timestamp']));
        $this->assertTrue(isset($auth['oauth_nonce']));
        $this->assertEquals('1.0', $auth['oauth_version']);
        $this->assertEquals('oob', $auth['oauth_callback']);
        $this->assertTrue(isset($auth['oauth_signature']));
        
        $transaction = array_shift($container);

        $header = $transaction['request']->getHeaderLine('Authorization');
        $auth   = Authentication::decodeParameters($header);

        $this->assertEquals(self::CONSUMER_KEY, $auth['oauth_consumer_key']);
        $this->assertEquals(self::TMP_TOKEN, $auth['oauth_token']);
        $this->assertEquals('HMAC-SHA1', $auth['oauth_signature_method']);
        $this->assertTrue(isset($auth['oauth_timestamp']));
        $this->assertTrue(isset($auth['oauth_nonce']));
        $this->assertEquals('1.0', $auth['oauth_version']);
        $this->assertEquals(self::VERIFIER, $auth['oauth_verifier']);
        $this->assertTrue(isset($auth['oauth_signature']));

        $transaction = array_shift($container);

        $header = $transaction['request']->getHeaderLine('Authorization');
        $auth   = Authentication::decodeParameters($header);

        $this->assertEquals(self::CONSUMER_KEY, $auth['oauth_consumer_key']);
        $this->assertEquals(self::TOKEN, $auth['oauth_token']);
        $this->assertEquals('HMAC-SHA1', $auth['oauth_signature_method']);
        $this->assertTrue(isset($auth['oauth_timestamp']));
        $this->assertTrue(isset($auth['oauth_nonce']));
        $this->assertEquals('1.0', $auth['oauth_version']);
        $this->assertTrue(isset($auth['oauth_signature']));
    }

    public function testOAuthBuildAuthString()
    {
        $data = Consumer::buildAuthString(array('fo o' => 'b~ar'));

        $this->assertEquals('fo%20o="b~ar"', $data);
    }

    public function testOAuthGetNormalizedUrl()
    {
        $url = new Url('HTTP://Example.com:80/resource?id=123');

        $this->assertEquals('http://example.com/resource', Consumer::getNormalizedUrl($url));


        $url = new Url('http://localhost:8888/amun/public/index.php/api/auth/request');

        $this->assertEquals('http://localhost:8888/amun/public/index.php/api/auth/request', Consumer::getNormalizedUrl($url));
    }

    /**
     * Tests the getNormalizedParameters function by using the values from the
     * RFC. The one "a3" value is commented because we cant have two keys with
     * the same name in an array to make it work we have to change the
     * datastructure but by now no problems occured with this issue
     *
     * @see http://tools.ietf.org/html/rfc5849#section-3.4.2
     * @see http://wiki.oauth.net/w/page/12238556/TestCases
     */
    public function testOAuthGetNormalizedParameters()
    {
        $params = Consumer::getNormalizedParameters(array(

            'b5' => '=%3D',
            //'a3' => 'a',
            'c@' => '',
            'a2' => 'r b',
            'oauth_consumer_key' => '9djdj82h48djs9d2',
            'oauth_token' => 'kkk9d7dh3k39sjv7',
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_timestamp' => '137131201',
            'oauth_nonce' => '7d8f3e4a',
            'c2' => '',
            'a3' => '2 q',

        ));

        $expect = 'a2=r%20b&a3=2%20q&b5=%3D%253D&c%40=&c2=&oauth_consumer_key=9djdj82h48djs9d2&oauth_nonce=7d8f3e4a&oauth_signature_method=HMAC-SHA1&oauth_timestamp=137131201&oauth_token=kkk9d7dh3k39sjv7';

        $this->assertEquals($expect, $params);


        $params = array(
            'name='       => array('name' => ''),
            'a=b'         => array('a' => 'b'),
            'a=b&c=d'     => array('a' => 'b', 'c' => 'd'),
            'a=x%2By'     => array('a' => 'x+y'),
            'x=a&x%21y=a' => array('x!y' => 'a', 'x' => 'a'),
        );

        foreach ($params as $expect => $param) {
            $this->assertEquals($expect, Consumer::getNormalizedParameters($param));
        }
    }

    /**
     * Tests url encoding
     *
     * @see http://wiki.oauth.net/w/page/12238556/TestCases
     */
    public function testParameterEncoding()
    {
        $values = array(
            'abcABC123' => 'abcABC123',
            '-._~'      => '-._~',
            '%'         => '%25',
            '+'         => '%2B',
            '&=*'       => '%26%3D%2A',
            "\x0A"      => '%0A',
            "\x20"      => '%20',
            //"\x80"      => '%C2%80',
        );

        foreach ($values as $k => $v) {
            $this->assertEquals($v, Consumer::urlEncode($k));
        }
    }
}

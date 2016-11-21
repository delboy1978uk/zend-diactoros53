<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @see       http://github.com/zendframework/zend-diactoros for the canonical source repository
 * @copyright Copyright (c) 2015-2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-diactoros/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\Diactoros;

use InvalidArgumentException;
use PHPUnit_Framework_TestCase as TestCase;
use Zend\Diactoros\Request;
use Zend\Diactoros\Stream;
use Zend\Diactoros\Uri;

class RequestTest extends TestCase
{
    /**
     * @var Request
     */
    protected $request;

    public function setUp()
    {
        $this->request = new Request();
    }

    public function testMethodIsEmptyByDefault()
    {
        $this->assertSame('', $this->request->getMethod());
    }

    public function testMethodMutatorReturnsCloneWithChangedMethod()
    {
        $request = $this->request->withMethod('GET');
        $this->assertNotSame($this->request, $request);
        $this->assertEquals('GET', $request->getMethod());
    }

    public function testReturnsUnpopulatedUriByDefault()
    {
        $uri = $this->request->getUri();
        $this->assertInstanceOf('Psr\Http\Message\UriInterface', $uri);
        $this->assertInstanceOf('Zend\Diactoros\Uri', $uri);
        $this->assertEmpty($uri->getScheme());
        $this->assertEmpty($uri->getUserInfo());
        $this->assertEmpty($uri->getHost());
        $this->assertNull($uri->getPort());
        $this->assertEmpty($uri->getPath());
        $this->assertEmpty($uri->getQuery());
        $this->assertEmpty($uri->getFragment());
    }

    public function testConstructorRaisesExceptionForInvalidStream()
    {
        $this->setExpectedException('InvalidArgumentException');
        new Request(array('TOTALLY INVALID'));
    }

    public function testWithUriReturnsNewInstanceWithNewUri()
    {
        $request = $this->request->withUri(new Uri('https://example.com:10082/foo/bar?baz=bat'));
        $this->assertNotSame($this->request, $request);
        $request2 = $request->withUri(new Uri('/baz/bat?foo=bar'));
        $this->assertNotSame($this->request, $request2);
        $this->assertNotSame($request, $request2);
        $this->assertEquals('/baz/bat?foo=bar', (string) $request2->getUri());
    }

    public function testConstructorCanAcceptAllMessageParts()
    {
        $uri     = new Uri('http://example.com/');
        $body    = new Stream('php://memory');
        $headers = array(
            'x-foo' => array('bar'),
        );
        $request = new Request(
            $uri,
            'POST',
            $body,
            $headers
        );

        $this->assertSame($uri, $request->getUri());
        $this->assertEquals('POST', $request->getMethod());
        $this->assertSame($body, $request->getBody());
        $testHeaders = $request->getHeaders();
        foreach ($headers as $key => $value) {
            $this->assertArrayHasKey($key, $testHeaders);
            $this->assertEquals($value, $testHeaders[$key]);
        }
    }

    public function testDefaultStreamIsWritable()
    {
        $request = new Request();
        $request->getBody()->write("test");

        $this->assertSame("test", (string)$request->getBody());
    }

    public function invalidRequestUri()
    {
        return array(
            'true'     => array( true ),
            'false'    => array( false ),
            'int'      => array( 1 ),
            'float'    => array( 1.1 ),
            'array'    => array( array('http://example.com') ),
            'stdClass' => array( (object) array( 'href'         => 'http://example.com') ),
        );
    }

    /**
     * @dataProvider invalidRequestUri
     */
    public function testConstructorRaisesExceptionForInvalidUri($uri)
    {
        $this->setExpectedException('InvalidArgumentException', 'Invalid URI');
        new Request($uri);
    }

    public function invalidRequestMethod()
    {
        return array(
            'true'       => array( true ),
            'false'      => array( false ),
            'int'        => array( 1 ),
            'float'      => array( 1.1 ),
            'bad-string' => array( 'BOGUS METHOD' ),
            'array'      => array( array('POST') ),
            'stdClass'   => array( (object) array( 'method' => 'POST') ),
        );
    }

    /**
     * @dataProvider invalidRequestMethod
     */
    public function testConstructorRaisesExceptionForInvalidMethod($method)
    {
        $this->setExpectedException('InvalidArgumentException', 'Unsupported HTTP method');
        new Request(null, $method);
    }

    public function customRequestMethods()
    {
        return array(
            /* WebDAV methods */
            'TRACE'     => array('TRACE'),
            'PROPFIND'  => array('PROPFIND'),
            'PROPPATCH' => array('PROPPATCH'),
            'MKCOL'     => array('MKCOL'),
            'COPY'      => array('COPY'),
            'MOVE'      => array('MOVE'),
            'LOCK'      => array('LOCK'),
            'UNLOCK'    => array('UNLOCK'),
            'UNLOCK'    => array('UNLOCK'),
            /* Arbitrary methods */
            '#!ALPHA-1234&%' => array('#!ALPHA-1234&%'),
        );
    }

    /**
     * @dataProvider customRequestMethods
     * @group 29
     */
    public function testAllowsCustomRequestMethodsThatFollowSpec($method)
    {
        $request = new Request(null, $method);
        $this->assertSame($method, $request->getMethod());
    }

    public function invalidRequestBody()
    {
        return array(
            'true'       => array( true ),
            'false'      => array( false ),
            'int'        => array( 1 ),
            'float'      => array( 1.1 ),
            'array'      => array( array('BODY') ),
            'stdClass'   => array( (object) array( 'body' => 'BODY') ),
        );
    }

    /**
     * @dataProvider invalidRequestBody
     */
    public function testConstructorRaisesExceptionForInvalidBody($body)
    {
        $this->setExpectedException('InvalidArgumentException', 'stream');
        new Request(null, null, $body);
    }

    public function invalidHeaderTypes()
    {
        return array(
            'indexed-array' => array(array(array('INVALID')), 'header name'),
            'null' => array(array('x-invalid-null' => null)),
            'true' => array(array('x-invalid-true' => true)),
            'false' => array(array('x-invalid-false' => false)),
            'object' => array(array('x-invalid-object' => (object) array('INVALID'))),
        );
    }

    /**
     * @dataProvider invalidHeaderTypes
     * @group 99
     */
    public function testConstructorRaisesExceptionForInvalidHeaders($headers, $contains = 'header value type')
    {
        $this->setExpectedException('InvalidArgumentException', $contains);
        new Request(null, null, 'php://memory', $headers);
    }

    public function testRequestTargetIsSlashWhenNoUriPresent()
    {
        $request = new Request();
        $this->assertEquals('/', $request->getRequestTarget());
    }

    public function testRequestTargetIsSlashWhenUriHasNoPathOrQuery()
    {
        $request = new Request();
        $request->withUri(new Uri('http://example.com'));
        $this->assertEquals('/', $request->getRequestTarget());
    }

    public function requestsWithUri()
    {
        $absolute = new Request();
        $absoluteQuery = new Request();
        $relative = new Request();
        $relativeQuery = new Request();

        return array(
            'absolute-uri' => array(
                $absolute->withUri(new Uri('https://api.example.com/user'))
                ->withMethod('POST'),
                '/user'
            ),
            'absolute-uri-with-query' => array(
                $absoluteQuery->withUri(new Uri('https://api.example.com/user?foo=bar'))
                ->withMethod('POST'),
                '/user?foo=bar'
            ),
            'relative-uri' => array(
                $relative->withUri(new Uri('/user'))
                ->withMethod('GET'),
                '/user'
            ),
            'relative-uri-with-query' => array(
                $relativeQuery->withUri(new Uri('/user?foo=bar'))
                ->withMethod('GET'),
                '/user?foo=bar'
            ),
        );
    }

    /**
     * @dataProvider requestsWithUri
     */
    public function testReturnsRequestTargetWhenUriIsPresent($request, $expected)
    {
        $this->assertEquals($expected, $request->getRequestTarget());
    }

    public function validRequestTargets()
    {
        return array(
            'asterisk-form'         => array( '*' ),
            'authority-form'        => array( 'api.example.com' ),
            'absolute-form'         => array( 'https://api.example.com/users' ),
            'absolute-form-query'   => array( 'https://api.example.com/users?foo=bar' ),
            'origin-form-path-only' => array( '/users' ),
            'origin-form'           => array( '/users?id=foo' ),
        );
    }

    /**
     * @dataProvider validRequestTargets
     */
    public function testCanProvideARequestTarget($requestTarget)
    {
        $request = new Request();
        $request->withRequestTarget($requestTarget);
        $this->assertEquals($requestTarget, $request->getRequestTarget());
    }

    public function testRequestTargetCannotContainWhitespace()
    {
        $request = new Request();
        $this->setExpectedException('InvalidArgumentException', 'Invalid request target');
        $request->withRequestTarget('foo bar baz');
    }

    public function testRequestTargetDoesNotCacheBetweenInstances()
    {
        $request = new Request();
        $request->withUri(new Uri('https://example.com/foo/bar'));
        $original = $request->getRequestTarget();
        $newRequest = $request->withUri(new Uri('http://mwop.net/bar/baz'));
        $this->assertNotEquals($original, $newRequest->getRequestTarget());
    }

    public function testSettingNewUriResetsRequestTarget()
    {
        $request = new Request();
        $request->withUri(new Uri('https://example.com/foo/bar'));
        $request->getRequestTarget();
        $request->withUri(new Uri('http://mwop.net/bar/baz'));
    }

    /**
     * @group 39
     */
    public function testGetHeadersContainsHostHeaderIfUriWithHostIsPresent()
    {
        $request = new Request('http://example.com');
        $headers = $request->getHeaders();
        $this->assertArrayHasKey('Host', $headers);
        $this->assertContains('example.com', $headers['Host']);
    }

    /**
     * @group 39
     */
    public function testGetHeadersContainsNoHostHeaderIfNoUriPresent()
    {
        $request = new Request();
        $headers = $request->getHeaders();
        $this->assertArrayNotHasKey('Host', $headers);
    }

    /**
     * @group 39
     */
    public function testGetHeadersContainsNoHostHeaderIfUriDoesNotContainHost()
    {
        $uri = new Uri();
        $request = new Request($uri);
        $headers = $request->getHeaders();
        $this->assertArrayNotHasKey('Host', $headers);
    }

    /**
     * @group 39
     */
    public function testGetHostHeaderReturnsUriHostWhenPresent()
    {
        $request = new Request('http://example.com');
        $header = $request->getHeader('host');
        $this->assertEquals(array('example.com'), $header);
    }

    /**
     * @group 39
     */
    public function testGetHostHeaderReturnsEmptyArrayIfNoUriPresent()
    {
        $request = new Request();
        $this->assertSame(array(), $request->getHeader('host'));
    }

    /**
     * @group 39
     */
    public function testGetHostHeaderReturnsEmptyArrayIfUriDoesNotContainHost()
    {
        $uri = new Uri();
        $request = new Request($uri);
        $this->assertSame(array(), $request->getHeader('host'));
    }

    /**
     * @group 39
     */
    public function testGetHostHeaderLineReturnsUriHostWhenPresent()
    {
        $request = new Request('http://example.com');
        $header = $request->getHeaderLine('host');
        $this->assertContains('example.com', $header);
    }

    /**
     * @group 39
     */
    public function testGetHostHeaderLineReturnsEmptyStringIfNoUriPresent()
    {
        $request = new Request();
        $this->assertEmpty($request->getHeaderLine('host'));
    }

    /**
     * @group 39
     */
    public function testGetHostHeaderLineReturnsEmptyStringIfUriDoesNotContainHost()
    {
        $uri = new Uri();
        $request = new Request($uri);
        $this->assertEmpty($request->getHeaderLine('host'));
    }

    public function testHostHeaderSetFromUriOnCreationIfNoHostHeaderSpecified()
    {
        $request = new Request('http://www.example.com');
        $this->assertTrue($request->hasHeader('Host'));
        $this->assertEquals('www.example.com', $request->getHeaderLine('host'));
    }

    public function testHostHeaderNotSetFromUriOnCreationIfHostHeaderSpecified()
    {
        $request = new Request('http://www.example.com', null, 'php://memory', array('Host' => 'www.test.com'));
        $this->assertEquals('www.test.com', $request->getHeaderLine('host'));
    }

    public function testPassingPreserveHostFlagWhenUpdatingUriDoesNotUpdateHostHeader()
    {
        $request = new Request();
        $request->withAddedHeader('Host', 'example.com');

        $uri = new Uri();
        $uri->withHost('www.example.com');
        $new = $request->withUri($uri, true);

        $this->assertEquals('example.com', $new->getHeaderLine('Host'));
    }

    public function testNotPassingPreserveHostFlagWhenUpdatingUriWithoutHostDoesNotUpdateHostHeader()
    {
        $request = new Request();
        $request->withAddedHeader('Host', 'example.com');

        $uri = new Uri();
        $new = $request->withUri($uri);

        $this->assertEquals('example.com', $new->getHeaderLine('Host'));
    }

    public function testHostHeaderUpdatesToUriHostAndPortWhenPreserveHostDisabledAndNonStandardPort()
    {
        $request = new Request();
        $request->withAddedHeader('Host', 'example.com');

        $uri = new Uri();
        $uri->withHost('www.example.com')
            ->withPort(10081);
        $new = $request->withUri($uri);

        $this->assertEquals('www.example.com:10081', $new->getHeaderLine('Host'));
    }

    public function headersWithInjectionVectors()
    {
        return array(
            'name-with-cr'           => array("X-Foo\r-Bar", 'value'),
            'name-with-lf'           => array("X-Foo\n-Bar", 'value'),
            'name-with-crlf'         => array("X-Foo\r\n-Bar", 'value'),
            'name-with-2crlf'        => array("X-Foo\r\n\r\n-Bar", 'value'),
            'value-with-cr'          => array('X-Foo-Bar', "value\rinjection"),
            'value-with-lf'          => array('X-Foo-Bar', "value\ninjection"),
            'value-with-crlf'        => array('X-Foo-Bar', "value\r\ninjection"),
            'value-with-2crlf'       => array('X-Foo-Bar', "value\r\n\r\ninjection"),
            'array-value-with-cr'    => array('X-Foo-Bar', array("value\rinjection")),
            'array-value-with-lf'    => array('X-Foo-Bar', array("value\ninjection")),
            'array-value-with-crlf'  => array('X-Foo-Bar', array("value\r\ninjection")),
            'array-value-with-2crlf' => array('X-Foo-Bar', array("value\r\n\r\ninjection")),
        );
    }

    /**
     * @group ZF2015-04
     * @dataProvider headersWithInjectionVectors
     */
    public function testConstructorRaisesExceptionForHeadersWithCRLFVectors($name, $value)
    {
        $this->setExpectedException('InvalidArgumentException');
        new Request(null, null, 'php://memory', array($name =>  $value));
    }

    public function hostHeaderKeys()
    {
        return array(
            'lowercase'            => array('host'),
            'mixed-4'              => array('hosT'),
            'mixed-3-4'            => array('hoST'),
            'reverse-titlecase'    => array('hOST'),
            'uppercase'            => array('HOST'),
            'mixed-1-2-3'          => array('HOSt'),
            'mixed-1-2'            => array('HOst'),
            'titlecase'            => array('Host'),
            'mixed-1-4'            => array('HosT'),
            'mixed-1-2-4'          => array('HOsT'),
            'mixed-1-3-4'          => array('HoST'),
            'mixed-1-3'            => array('HoSt'),
            'mixed-2-3'            => array('hOSt'),
            'mixed-2-4'            => array('hOsT'),
            'mixed-2'              => array('hOst'),
            'mixed-3'              => array('hoSt'),
        );
    }

    /**
     * @group 91
     * @dataProvider hostHeaderKeys
     */
    public function testWithUriAndNoPreserveHostWillOverwriteHostHeaderRegardlessOfOriginalCase($hostKey)
    {
        $request = new Request();
        $request->withHeader($hostKey, 'example.com');

        $uri  = new Uri('http://example.org/foo/bar');
        $new  = $request->withUri($uri);
        $host = $new->getHeaderLine('host');
        $this->assertEquals('example.org', $host);
        $headers = $new->getHeaders();
        $this->assertArrayHasKey('Host', $headers);
        if ($hostKey !== 'Host') {
            $this->assertArrayNotHasKey($hostKey, $headers);
        }
    }
}

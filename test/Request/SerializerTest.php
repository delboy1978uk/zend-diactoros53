<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @see       http://github.com/zendframework/zend-diactoros for the canonical source repository
 * @copyright Copyright (c) 2015-2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-diactoros/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\Diactoros\Request;

use PHPUnit_Framework_TestCase as TestCase;
use Zend\Diactoros\Request;
use Zend\Diactoros\Request\Serializer;
use Zend\Diactoros\Stream;
use Zend\Diactoros\Uri;
use UnexpectedValueException;

class SerializerTest extends TestCase
{
    public function testSerializesBasicRequest()
    {
        $request = new Request();
        $request = $request->withMethod('GET');
        $request = $request->withUri(new Uri('http://example.com/foo/bar?baz=bat'));
        $request = $request->withAddedHeader('Accept', 'text/html');

        $message = Serializer::toString($request);
        $this->assertEquals(
            "GET /foo/bar?baz=bat HTTP/1.1\r\nHost: example.com\r\nAccept: text/html",
            $message
        );
    }

    public function testSerializesRequestWithBody()
    {
        $body   = json_encode(array('test' => 'value'));
        $stream = new Stream('php://memory', 'wb+');
        $stream->write($body);

        $request = new Request();
        $request = $request->withMethod('POST');
        $request = $request->withUri(new Uri('http://example.com/foo/bar'));
        $request = $request->withAddedHeader('Accept', 'application/json');
        $request = $request->withAddedHeader('Content-Type', 'application/json');
        $request = $request->withBody($stream);

        $message = Serializer::toString($request);
        $this->assertContains("POST /foo/bar HTTP/1.1\r\n", $message);
        $this->assertContains("\r\n\r\n" . $body, $message);
    }

    public function testSerializesMultipleHeadersCorrectly()
    {
        $request = new Request();
        $request = $request->withMethod('GET');
        $request = $request->withUri(new Uri('http://example.com/foo/bar?baz=bat'));
        $request = $request->withAddedHeader('X-Foo-Bar', 'Baz');
        $request = $request->withAddedHeader('X-Foo-Bar', 'Bat');

        $message = Serializer::toString($request);
        $this->assertContains("X-Foo-Bar: Baz", $message);
        $this->assertContains("X-Foo-Bar: Bat", $message);
    }

    public function originForms()
    {
        return array(
            'path-only'      => array(
                'GET /foo HTTP/1.1',
                '/foo',
                array('getPath' => '/foo'),
            ),
            'path-and-query' => array(
                'GET /foo?bar HTTP/1.1',
                '/foo?bar',
                array('getPath' => '/foo', 'getQuery' => 'bar'),
            ),
        );
    }

    /**
     * @dataProvider originForms
     */
    public function testCanDeserializeRequestWithOriginForm($line, $requestTarget, $expectations)
    {
        $message = $line . "\r\nX-Foo-Bar: Baz\r\n\r\nContent";
        $request = Serializer::fromString($message);

        $this->assertEquals('GET', $request->getMethod());
        $this->assertEquals($requestTarget, $request->getRequestTarget());

        $uri = $request->getUri();
        foreach ($expectations as $method => $expect) {
            $this->assertEquals($expect, $uri->{$method}());
        }
    }

    public function absoluteForms()
    {
        return array(
            'path-only'      => array(
                'GET http://example.com/foo HTTP/1.1',
                'http://example.com/foo',
                array(
                    'getScheme' => 'http',
                    'getHost'   => 'example.com',
                    'getPath'   => '/foo',
                ),
            ),
            'path-and-query' => array(
                'GET http://example.com/foo?bar HTTP/1.1',
                'http://example.com/foo?bar',
                array(
                    'getScheme' => 'http',
                    'getHost'   => 'example.com',
                    'getPath'   => '/foo',
                    'getQuery'  => 'bar',
                ),
            ),
            'with-port'      => array(
                'GET http://example.com:8080/foo?bar HTTP/1.1',
                'http://example.com:8080/foo?bar',
                array(
                    'getScheme' => 'http',
                    'getHost'   => 'example.com',
                    'getPort'   => 8080,
                    'getPath'   => '/foo',
                    'getQuery'  => 'bar',
                ),
            ),
            'with-authority' => array(
                'GET https://me:too@example.com:8080/foo?bar HTTP/1.1',
                'https://me:too@example.com:8080/foo?bar',
                array(
                    'getScheme'   => 'https',
                    'getUserInfo' => 'me:too',
                    'getHost'     => 'example.com',
                    'getPort'     => 8080,
                    'getPath'     => '/foo',
                    'getQuery'    => 'bar',
                ),
            ),
        );
    }

    /**
     * @dataProvider absoluteForms
     */
    public function testCanDeserializeRequestWithAbsoluteForm($line, $requestTarget, $expectations)
    {
        $message = $line . "\r\nX-Foo-Bar: Baz\r\n\r\nContent";
        $request = Serializer::fromString($message);

        $this->assertEquals('GET', $request->getMethod());

        $this->assertEquals($requestTarget, $request->getRequestTarget());

        $uri = $request->getUri();
        foreach ($expectations as $method => $expect) {
            $this->assertEquals($expect, $uri->{$method}());
        }
    }

    public function testCanDeserializeRequestWithAuthorityForm()
    {
        $message = "CONNECT www.example.com:80 HTTP/1.1\r\nX-Foo-Bar: Baz";
        $request = Serializer::fromString($message);
        $this->assertEquals('CONNECT', $request->getMethod());
        $this->assertEquals('www.example.com:80', $request->getRequestTarget());

        $uri = $request->getUri();
        $this->assertNotEquals('www.example.com', $uri->getHost());
        $this->assertNotEquals(80, $uri->getPort());
    }

    public function testCanDeserializeRequestWithAsteriskForm()
    {
        $message = "OPTIONS * HTTP/1.1\r\nHost: www.example.com";
        $request = Serializer::fromString($message);
        $this->assertEquals('OPTIONS', $request->getMethod());
        $this->assertEquals('*', $request->getRequestTarget());

        $uri = $request->getUri();
        $this->assertNotEquals('www.example.com', $uri->getHost());

        $this->assertTrue($request->hasHeader('Host'));
        $this->assertEquals('www.example.com', $request->getHeaderLine('Host'));
    }

    public function invalidRequestLines()
    {
        return array(
            'missing-method'   => array('/foo/bar HTTP/1.1'),
            'missing-target'   => array('GET HTTP/1.1'),
            'missing-protocol' => array('GET /foo/bar'),
            'simply-malformed' => array('What is this mess?'),
        );
    }

    /**
     * @dataProvider invalidRequestLines
     */
    public function testRaisesExceptionDuringDeserializationForInvalidRequestLine($line)
    {
        $message = $line . "\r\nX-Foo-Bar: Baz\r\n\r\nContent";
        $this->setExpectedException('UnexpectedValueException');
        Serializer::fromString($message);
    }

    public function testCanDeserializeResponseWithMultipleHeadersOfSameName()
    {
        $text = "POST /foo HTTP/1.0\r\nContent-Type: text/plain\r\nX-Foo-Bar: Baz\r\nX-Foo-Bar: Bat\r\n\r\nContent!";
        $request = Serializer::fromString($text);

        $this->assertInstanceOf('Psr\Http\Message\RequestInterface', $request);
        $this->assertInstanceOf('Zend\Diactoros\Request', $request);

        $this->assertTrue($request->hasHeader('X-Foo-Bar'));
        $values = $request->getHeader('X-Foo-Bar');
        $this->assertEquals(array('Baz', 'Bat'), $values);
    }

    public function headersWithContinuationLines()
    {
        return array(
            'space' => array("POST /foo HTTP/1.0\r\nContent-Type: text/plain\r\nX-Foo-Bar: Baz;\r\n Bat\r\n\r\nContent!"),
            'tab' => array("POST /foo HTTP/1.0\r\nContent-Type: text/plain\r\nX-Foo-Bar: Baz;\r\n\tBat\r\n\r\nContent!"),
        );
    }

    /**
     * @dataProvider headersWithContinuationLines
     */
    public function testCanDeserializeResponseWithHeaderContinuations($text)
    {
        $request = Serializer::fromString($text);

        $this->assertInstanceOf('Psr\Http\Message\RequestInterface', $request);
        $this->assertInstanceOf('Zend\Diactoros\Request', $request);

        $this->assertTrue($request->hasHeader('X-Foo-Bar'));
        $this->assertEquals('Baz;Bat', $request->getHeaderLine('X-Foo-Bar'));
    }

    public function messagesWithInvalidHeaders()
    {
        return array(
            'invalid-name' => array(
                "GET /foo HTTP/1.1\r\nThi;-I()-Invalid: value",
                'Invalid header detected'
            ),
            'invalid-format' => array(
                "POST /foo HTTP/1.1\r\nThis is not a header\r\n\r\nContent",
                'Invalid header detected'
            ),
            'invalid-continuation' => array(
                "POST /foo HTTP/1.1\r\nX-Foo-Bar: Baz\r\nInvalid continuation\r\nContent",
                'Invalid header continuation'
            ),
        );
    }

    /**
     * @dataProvider messagesWithInvalidHeaders
     */
    public function testDeserializationRaisesExceptionForMalformedHeaders($message, $exceptionMessage)
    {
        $this->setExpectedException('UnexpectedValueException', $exceptionMessage);
        Serializer::fromString($message);
    }

    public function testFromStreamThrowsExceptionWhenStreamIsNotReadable()
    {
        $this->setExpectedException('InvalidArgumentException');
        $stream = $this
            ->getMockBuilder('Psr\Http\Message\StreamInterface')
            ->getMock();

        $stream->expects($this->once())->method('isReadable')
            ->will($this->returnValue(false));

        Serializer::fromStream($stream);
    }

    public function testFromStreamThrowsExceptionWhenStreamIsNotSeekable()
    {
        $this->setExpectedException('InvalidArgumentException');
        $stream = $this
            ->getMockBuilder('Psr\Http\Message\StreamInterface')
            ->getMock();

        $stream->expects($this->once())->method('isReadable')
            ->will($this->returnValue(true));

        $stream->expects($this->once())->method('isSeekable')
            ->will($this->returnValue(false));

        Serializer::fromStream($stream);
    }

    public function testFromStreamStopsReadingAfterScanningHeader()
    {
        $headers = "POST /foo HTTP/1.0\r\nContent-Type: text/plain\r\nX-Foo-Bar: Baz;\r\n Bat\r\n\r\n";
        $payload = $headers . "Content!";

        $stream = $this
            ->getMockBuilder('Psr\Http\Message\StreamInterface')
            ->getMock();

        $stream->expects($this->once())->method('isReadable')
            ->will($this->returnValue(true));

        $stream->expects($this->once())->method('isSeekable')
            ->will($this->returnValue(true));

        // assert that full request body is not read, and returned as RelativeStream instead
        $stream->expects($this->exactly(strlen($headers)))
            ->method('read')
            ->with(1)
            ->will($this->returnCallback(function () use ($payload) {
                static $i = 0;
                return $payload[$i++];
            }));

        $stream = Serializer::fromStream($stream);
        $this->assertInstanceOf('Zend\Diactoros\RelativeStream', $stream->getBody());
    }

    /**
     * @expectedException UnexpectedValueException
     */
    public function testToStringRaisesExceptionOnEmptyMethod()
    {
        $uri = new Uri('http://example.com/foo/bar?baz=bat');
        $request = new Request();
        $request->withUri($uri);
        Serializer::toString($request);
    }
}

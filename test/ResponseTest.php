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
use Zend\Diactoros\Response;
use Zend\Diactoros\Stream;

class ResponseTest extends TestCase
{
    /**
     * @var Response
    */
    protected $response;

    public function setUp()
    {
        $this->response = new Response();
    }

    public function testStatusCodeIs200ByDefault()
    {
        $this->assertEquals(200, $this->response->getStatusCode());
    }

    public function testStatusCodeMutatorReturnsCloneWithChanges()
    {
        $response = $this->response->withStatus(400);
        $this->assertNotSame($this->response, $response);
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function invalidStatusCodes()
    {
        return array(
            'too-low' => array(99),
            'too-high' => array(600),
            'null' => array(null),
            'bool' => array(true),
            'string' => array('foo'),
            'array' => array(array(200)),
            'object' => array((object) array(200)),
        );
    }

    /**
     * @dataProvider invalidStatusCodes
     */
    public function testCannotSetInvalidStatusCode($code)
    {
        $this->setExpectedException('InvalidArgumentException');
        $response = $this->response->withStatus($code);
    }

    public function testReasonPhraseDefaultsToStandards()
    {
        $response = $this->response->withStatus(422);
        $this->assertEquals('Unprocessable Entity', $response->getReasonPhrase());
    }

    public function testCanSetCustomReasonPhrase()
    {
        $response = $this->response->withStatus(422, 'Foo Bar!');
        $this->assertEquals('Foo Bar!', $response->getReasonPhrase());
    }

    public function testConstructorRaisesExceptionForInvalidStream()
    {
        $this->setExpectedException('InvalidArgumentException');
        new Response(array( 'TOTALLY INVALID' ));
    }

    public function testConstructorCanAcceptAllMessageParts()
    {
        $body = new Stream('php://memory');
        $status = 302;
        $headers = array(
            'location' => array( 'http://example.com/' ),
        );

        $response = new Response($body, $status, $headers);
        $this->assertSame($body, $response->getBody());
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals($headers, $response->getHeaders());
    }

    public function invalidStatus()
    {
        return array(
            'true' => array( true ),
            'false' => array( false ),
            'float' => array( 100.1 ),
            'bad-string' => array( 'Two hundred' ),
            'array' => array( array( 200 ) ),
            'object' => array( (object) array( 'statusCode' => 200 ) ),
            'too-small' => array( 1 ),
            'too-big' => array( 600 ),
        );
    }

    /**
     * @dataProvider invalidStatus
     */
    public function testConstructorRaisesExceptionForInvalidStatus($code)
    {
        $this->setExpectedException('InvalidArgumentException', 'Invalid status code');
        new Response('php://memory', $code);
    }

    public function invalidResponseBody()
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
     * @dataProvider invalidResponseBody
     */
    public function testConstructorRaisesExceptionForInvalidBody($body)
    {
        $this->setExpectedException('InvalidArgumentException', 'stream');
        new Response($body);
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
        new Response('php://memory', 200, $headers);
    }

    public function testInvalidStatusCodeInConstructor()
    {
        $this->setExpectedException('InvalidArgumentException');

        new Response('php://memory', null);
    }

    public function testReasonPhraseCanBeEmpty()
    {
        $response = $this->response->withStatus(555);
        $this->assertInternalType('string', $response->getReasonPhrase());
        $this->assertEmpty($response->getReasonPhrase());
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
        new Response('php://memory', 200, array($name =>  $value));
    }
}

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
use Psr\Http\Message\MessageInterface;
use ReflectionMethod;
use Zend\Diactoros\Request;

class MessageTraitTest extends TestCase
{
    /**
     * @var MessageInterface
     */
    protected $message;

    public function setUp()
    {
        $this->message = new Request(null, null, $this->getMockBuilder('Psr\Http\Message\StreamInterface')->getMock());
    }

    public function testProtocolHasAcceptableDefault()
    {
        $this->assertEquals('1.1', $this->message->getProtocolVersion());
    }

    public function testProtocolMutatorReturnsCloneWithChanges()
    {
        $message = $this->message->withProtocolVersion('1.0');
        $this->assertNotSame($this->message, $message);
        $this->assertEquals('1.0', $message->getProtocolVersion());
    }


    public function invalidProtocolVersionProvider()
    {
        return array(
            'null'                 => array( null ),
            'true'                 => array( true ),
            'false'                => array( false ),
            'int'                  => array( 1 ),
            'float'                => array( 1.1 ),
            'array'                => array( array('1.1') ),
            'stdClass'             => array( (object) array( 'version' => '1.0') ),
            '1-without-minor'      => array( '1' ),
            '1-with-invalid-minor' => array( '1.2' ),
            '1-with-hotfix'        => array( '1.2.3' ),
            '2-with-minor'         => array( '2.0' ),
        );
    }

    /**
     * @dataProvider invalidProtocolVersionProvider
     */
    public function testWithProtocolVersionRaisesExceptionForInvalidVersion($version)
    {
        $this->setExpectedException('InvalidArgumentException');
        $request = new Request();
        $request->withProtocolVersion($version);
    }

    public function testUsesStreamProvidedInConstructorAsBody()
    {
        $stream  = $this->getMockBuilder('Psr\Http\Message\StreamInterface')->getMock();
        $message = new Request(null, null, $stream);
        $this->assertSame($stream, $message->getBody());
    }

    public function testBodyMutatorReturnsCloneWithChanges()
    {
        $stream  = $this->getMockBuilder('Psr\Http\Message\StreamInterface')->getMock();
        $message = $this->message->withBody($stream);
        $this->assertNotSame($this->message, $message);
        $this->assertSame($stream, $message->getBody());
    }

    public function testGetHeaderReturnsHeaderValueAsArray()
    {
        $message = $this->message->withHeader('X-Foo', array('Foo', 'Bar'));
        $this->assertNotSame($this->message, $message);
        $this->assertEquals(array('Foo', 'Bar'), $message->getHeader('X-Foo'));
    }

    public function testGetHeaderLineReturnsHeaderValueAsCommaConcatenatedString()
    {
        $message = $this->message->withHeader('X-Foo', array('Foo', 'Bar'));
        $this->assertNotSame($this->message, $message);
        $this->assertEquals('Foo,Bar', $message->getHeaderLine('X-Foo'));
    }

    public function testGetHeadersKeepsHeaderCaseSensitivity()
    {
        $message = $this->message->withHeader('X-Foo', array('Foo', 'Bar'));
        $this->assertNotSame($this->message, $message);
        $this->assertEquals(array( 'X-Foo' => array( 'Foo', 'Bar' ) ), $message->getHeaders());
    }

    public function testGetHeadersReturnsCaseWithWhichHeaderFirstRegistered()
    {
        $message = $this->message
            ->withHeader('X-Foo', 'Foo')
            ->withAddedHeader('x-foo', 'Bar');
        $this->assertNotSame($this->message, $message);
        $this->assertEquals(array( 'X-Foo' => array( 'Foo', 'Bar' ) ), $message->getHeaders());
    }

    public function testHasHeaderReturnsFalseIfHeaderIsNotPresent()
    {
        $this->assertFalse($this->message->hasHeader('X-Foo'));
    }

    public function testHasHeaderReturnsTrueIfHeaderIsPresent()
    {
        $message = $this->message->withHeader('X-Foo', 'Foo');
        $this->assertNotSame($this->message, $message);
        $this->assertTrue($message->hasHeader('X-Foo'));
    }

    public function testAddHeaderAppendsToExistingHeader()
    {
        $message  = $this->message->withHeader('X-Foo', 'Foo');
        $this->assertNotSame($this->message, $message);
        $message2 = $message->withAddedHeader('X-Foo', 'Bar');
        $this->assertNotSame($message, $message2);
        $this->assertEquals('Foo,Bar', $message2->getHeaderLine('X-Foo'));
    }

    public function testCanRemoveHeaders()
    {
        $message = $this->message->withHeader('X-Foo', 'Foo');
        $this->assertNotSame($this->message, $message);
        $this->assertTrue($message->hasHeader('x-foo'));
        $message2 = $message->withoutHeader('x-foo');
        $this->assertNotSame($this->message, $message2);
        $this->assertNotSame($message, $message2);
        $this->assertFalse($message2->hasHeader('X-Foo'));
    }

    public function testHeaderRemovalIsCaseInsensitive()
    {
        $message = $this->message
            ->withHeader('X-Foo', 'Foo')
            ->withAddedHeader('x-foo', 'Bar')
            ->withAddedHeader('X-FOO', 'Baz');
        $this->assertNotSame($this->message, $message);
        $this->assertTrue($message->hasHeader('x-foo'));

        $message2 = $message->withoutHeader('x-foo');
        $this->assertNotSame($this->message, $message2);
        $this->assertNotSame($message, $message2);
        $this->assertFalse($message2->hasHeader('X-Foo'));

        $headers = $message2->getHeaders();
        $this->assertEquals(0, count($headers));
    }

    public function invalidGeneralHeaderValues()
    {
        return array(
            'null'   => array(null),
            'true'   => array(true),
            'false'  => array(false),
            'int'    => array(1),
            'float'  => array(1.1),
            'array'  => array(array( 'foo' => array( 'bar' ) )),
            'object' => array((object) array( 'foo' => 'bar' )),
        );
    }

    /**
     * @dataProvider invalidGeneralHeaderValues
     */
    public function testWithHeaderRaisesExceptionForInvalidNestedHeaderValue($value)
    {
        $this->setExpectedException('InvalidArgumentException', 'Invalid header value');
        $message = $this->message->withHeader('X-Foo', array( $value ));
    }

    public function invalidHeaderValues()
    {
        return array(
            'null'   => array(null),
            'true'   => array(true),
            'false'  => array(false),
            'int'    => array(1),
            'float'  => array(1.1),
            'object' => array((object) array( 'foo' => 'bar' )),
        );
    }

    /**
     * @dataProvider invalidHeaderValues
     */
    public function testWithHeaderRaisesExceptionForInvalidValueType($value)
    {
        $this->setExpectedException('InvalidArgumentException', 'Invalid header value');
        $message = $this->message->withHeader('X-Foo', $value);
    }

    public function testWithHeaderReplacesDifferentCapitalization()
    {
        $this->message = $this->message->withHeader('X-Foo', array('foo'));
        $new = $this->message->withHeader('X-foo', array('bar'));
        $this->assertEquals(array('bar'), $new->getHeader('x-foo'));
        $this->assertEquals(array('X-foo' => array('bar')), $new->getHeaders());
    }

    /**
     * @dataProvider invalidGeneralHeaderValues
     */
    public function testWithAddedHeaderRaisesExceptionForNonStringNonArrayValue($value)
    {
        $this->setExpectedException('InvalidArgumentException', 'must be a string');
        $message = $this->message->withAddedHeader('X-Foo', $value);
    }

    public function testWithoutHeaderDoesNothingIfHeaderDoesNotExist()
    {
        $this->assertFalse($this->message->hasHeader('X-Foo'));
        $message = $this->message->withoutHeader('X-Foo');
        $this->assertNotSame($this->message, $message);
        $this->assertFalse($message->hasHeader('X-Foo'));
    }

    public function testHeadersInitialization()
    {
        $headers = array('X-Foo' => array('bar'));
        $message = new Request(null, null, 'php://temp', $headers);
        $this->assertSame($headers, $message->getHeaders());
    }

    public function testGetHeaderReturnsAnEmptyArrayWhenHeaderDoesNotExist()
    {
        $this->assertSame(array(), $this->message->getHeader('X-Foo-Bar'));
    }

    public function testGetHeaderLineReturnsEmptyStringWhenHeaderDoesNotExist()
    {
        $this->assertEmpty($this->message->getHeaderLine('X-Foo-Bar'));
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
     * @dataProvider headersWithInjectionVectors
     * @group ZF2015-04
     */
    public function testDoesNotAllowCRLFInjectionWhenCallingWithHeader($name, $value)
    {
        $this->setExpectedException('InvalidArgumentException');
        $this->message->withHeader($name, $value);
    }

    /**
     * @dataProvider headersWithInjectionVectors
     * @group ZF2015-04
     */
    public function testDoesNotAllowCRLFInjectionWhenCallingWithAddedHeader($name, $value)
    {
        $this->setExpectedException('InvalidArgumentException');
        $this->message->withAddedHeader($name, $value);
    }

    public function testWithHeaderAllowsHeaderContinuations()
    {
        $message = $this->message->withHeader('X-Foo-Bar', "value,\r\n second value");
        $this->assertEquals("value,\r\n second value", $message->getHeaderLine('X-Foo-Bar'));
    }

    public function testWithAddedHeaderAllowsHeaderContinuations()
    {
        $message = $this->message->withAddedHeader('X-Foo-Bar', "value,\r\n second value");
        $this->assertEquals("value,\r\n second value", $message->getHeaderLine('X-Foo-Bar'));
    }

    public function testNumericHeaderValues()
    {
        return array(
            'integer' => array( 123 ),
            'float'   => array( 12.3 ),
        );
    }

    /**
     * @dataProvider testNumericHeaderValues
     * @group 99
     */
    public function testFilterHeadersShouldAllowIntegersAndFloats($value)
    {
        $filter = new ReflectionMethod($this->message, 'filterHeaders');
        $filter->setAccessible(true);
        $headers = array(
            'X-Test-Array'  => array( $value ),
            'X-Test-Scalar' => $value,
        );
        $test = $filter->invoke($this->message, $headers);
        $this->assertEquals(array(
            array(
                'x-test-array'  => 'X-Test-Array',
                'x-test-scalar' => 'X-Test-Scalar',
            ),
            array(
                'X-Test-Array'  => array( $value ),
                'X-Test-Scalar' => array( $value ),
            )
        ), $test);
    }

    public function invalidHeaderValueTypes()
    {
        return array(
            'null'   => array(null),
            'true'   => array(true),
            'false'  => array(false),
            'object' => array((object) array('header' => array('foo', 'bar'))),
        );
    }

    public function invalidArrayHeaderValues()
    {
        $values = $this->invalidHeaderValueTypes();
        $valuesarray['array'] = array(array('INVALID'));
        return $values;
    }

    /**
     * @dataProvider invalidArrayHeaderValues
     * @group 99
     */
    public function testFilterHeadersShouldRaiseExceptionForInvalidHeaderValuesInArrays($value)
    {
        $filter = new ReflectionMethod($this->message, 'filterHeaders');
        $filter->setAccessible(true);
        $headers = array(
            'X-Test-Array'  => array( $value ),
        );
        $this->setExpectedException('InvalidArgumentException', 'header value type');
        $filter->invoke($this->message, $headers);
    }

    /**
     * @dataProvider invalidHeaderValueTypes
     * @group 99
     */
    public function testFilterHeadersShouldRaiseExceptionForInvalidHeaderScalarValues($value)
    {
        $filter = new ReflectionMethod($this->message, 'filterHeaders');
        $filter->setAccessible(true);
        $headers = array(
            'X-Test-Scalar' => $value,
        );
        $this->setExpectedException('InvalidArgumentException', 'header value type');
        $filter->invoke($this->message, $headers);
    }
}

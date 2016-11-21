<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @see       http://github.com/zendframework/zend-diactoros for the canonical source repository
 * @copyright Copyright (c) 2015-2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-diactoros/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\Diactoros\Response;

use PHPUnit_Framework_TestCase as TestCase;
use Zend\Diactoros\Response\JsonResponse;

class JsonResponseTest extends TestCase
{
    public function testConstructorAcceptsDataAndCreatesJsonEncodedMessageBody()
    {
        $data = array(
            'nested' => array(
                'json' => array(
                    'tree',
                ),
            ),
        );
        $json = '{"nested":{"json":["tree"]}}';

        $response = new JsonResponse($data);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeaderLine('content-type'));
        $this->assertSame($json, (string) $response->getBody());
    }

    public function scalarValuesForJSON()
    {
        return array(
            'null'         => array(null),
            'false'        => array(false),
            'true'         => array(true),
            'zero'         => array(0),
            'int'          => array(1),
            'zero-float'   => array(0.0),
            'float'        => array(1.1),
            'empty-string' => array(''),
            'string'       => array('string'),
        );
    }

    /**
     * @dataProvider scalarValuesForJSON
     */
    public function testScalarValuePassedToConstructorJsonEncodesDirectly($value)
    {
        $response = new JsonResponse($value);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeaderLine('content-type'));
        // 15 is the default mask used by JsonResponse
        $this->assertSame(json_encode($value, 15), (string) $response->getBody());
    }

    public function testCanProvideStatusCodeToConstructor()
    {
        $response = new JsonResponse(null, 404);
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testCanProvideAlternateContentTypeViaHeadersPassedToConstructor()
    {
        $response = new JsonResponse(null, 200, array('content-type' => 'foo/json'));
        $this->assertEquals('foo/json', $response->getHeaderLine('content-type'));
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testJsonErrorHandlingOfResources()
    {
        // Serializing something that is not serializable.
        $resource = fopen('php://memory', 'r');
        new JsonResponse($resource);
    }

    public function testJsonErrorHandlingOfBadEmbeddedData()
    {
        if (version_compare(PHP_VERSION, '5.5', 'lt')) {
            $this->markTestSkipped('Skipped as PHP versions prior to 5.5 are noisy about JSON errors');
        }

        if (defined('HHVM_VERSION')) {
            $this->markTestSkipped('Skipped as HHVM happily serializes embedded resources');
        }

        // Serializing something that is not serializable.
        $data = array(
            'stream' => fopen('php://memory', 'r'),
        );

        $this->setExpectedException('InvalidArgumentException', 'Unable to encode');
        new JsonResponse($data);
    }

    public function valuesToJsonEncode()
    {
        return array(
            'uri'    => array('https://example.com/foo?bar=baz&baz=bat', 'uri'),
            'html'   => array('<p class="test">content</p>', 'html'),
            'string' => array("Don't quote!", 'string'),
        );
    }

    /**
     * @dataProvider valuesToJsonEncode
     */
    public function testUsesSaneDefaultJsonEncodingFlags($value, $key)
    {
        $defaultFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES;

        $response = new JsonResponse(array($key => $value));
        $stream   = $response->getBody();
        $contents = (string) $stream;

        $expected = json_encode($value, $defaultFlags);
        $this->assertContains(
            $expected,
            $contents,
            sprintf('Did not encode %s properly; expected (%s), received (%s)', $key, $expected, $contents)
        );
    }

    public function testConstructorRewindsBodyStream()
    {
        $json = array('test' => 'data');
        $response = new JsonResponse($json);

        $actual = json_decode($response->getBody()->getContents(), true);
        $this->assertEquals($json, $actual);
    }
}

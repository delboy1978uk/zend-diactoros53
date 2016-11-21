<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @see       http://github.com/zendframework/zend-diactoros for the canonical source repository
 * @copyright Copyright (c) 2015-2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-diactoros/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Diactoros;

use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

/**
 * HTTP response encapsulation.
 *
 * Responses are considered immutable; all methods that might change state are
 * implemented such that they retain the internal state of the current
 * message and return a new instance that contains the changed state.
 */
class Response implements ResponseInterface
{
    /**
     * List of all registered headers, as key => array of values.
     *
     * @var array
     */
    protected $headers = array();

    /**
     * Map of normalized header name to original name used to register header.
     *
     * @var array
     */
    protected $headerNames = array();

    /**
     * @var string
     */
    private $protocol = '1.1';

    /**
     * @var StreamInterface
     */
    private $stream;

    /**
     * Retrieves the HTTP protocol version as a string.
     *
     * The string MUST contain only the HTTP version number (e.g., "1.1", "1.0").
     *
     * @return string HTTP protocol version.
     */
    public function getProtocolVersion()
    {
        return $this->protocol;
    }

    /**
     * Return an instance with the specified HTTP protocol version.
     *
     * The version string MUST contain only the HTTP version number (e.g.,
     * "1.1", "1.0").
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that has the
     * new protocol version.
     *
     * @param string $version HTTP protocol version
     * @return static
     */
    public function withProtocolVersion($version)
    {
        $this->validateProtocolVersion($version);
        $new = clone $this;
        $new->protocol = $version;
        return $new;
    }

    /**
     * Retrieves all message headers.
     *
     * The keys represent the header name as it will be sent over the wire, and
     * each value is an array of strings associated with the header.
     *
     *     // Represent the headers as a string
     *     foreach ($message->getHeaders() as $name => $values) {
     *         echo $name . ": " . implode(", ", $values);
     *     }
     *
     *     // Emit headers iteratively:
     *     foreach ($message->getHeaders() as $name => $values) {
     *         foreach ($values as $value) {
     *             header(sprintf('%s: %s', $name, $value), false);
     *         }
     *     }
     *
     * @return array Returns an associative array of the message's headers. Each
     *     key MUST be a header name, and each value MUST be an array of strings.
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * Checks if a header exists by the given case-insensitive name.
     *
     * @param string $header Case-insensitive header name.
     * @return bool Returns true if any header names match the given header
     *     name using a case-insensitive string comparison. Returns false if
     *     no matching header name is found in the message.
     */
    public function hasHeader($header)
    {
        return array_key_exists(strtolower($header), $this->headerNames);
    }

    /**
     * Retrieves a message header value by the given case-insensitive name.
     *
     * This method returns an array of all the header values of the given
     * case-insensitive header name.
     *
     * If the header does not appear in the message, this method MUST return an
     * empty array.
     *
     * @param string $header Case-insensitive header field name.
     * @return string[] An array of string values as provided for the given
     *    header. If the header does not appear in the message, this method MUST
     *    return an empty array.
     */
    public function getHeader($header)
    {
        if (! $this->hasHeader($header)) {
            return array();
        }

        $header = $this->headerNames[strtolower($header)];
        $value  = $this->headers[$header];
        $value  = is_array($value) ? $value : array($value);

        return $value;
    }

    /**
     * Retrieves a comma-separated string of the values for a single header.
     *
     * This method returns all of the header values of the given
     * case-insensitive header name as a string concatenated together using
     * a comma.
     *
     * NOTE: Not all header values may be appropriately represented using
     * comma concatenation. For such headers, use getHeader() instead
     * and supply your own delimiter when concatenating.
     *
     * If the header does not appear in the message, this method MUST return
     * an empty string.
     *
     * @param string $name Case-insensitive header field name.
     * @return string A string of values as provided for the given header
     *    concatenated together using a comma. If the header does not appear in
     *    the message, this method MUST return an empty string.
     */
    public function getHeaderLine($name)
    {
        $value = $this->getHeader($name);
        if (empty($value)) {
            return '';
        }

        return implode(',', $value);
    }

    /**
     * Return an instance with the provided header, replacing any existing
     * values of any headers with the same case-insensitive name.
     *
     * While header names are case-insensitive, the casing of the header will
     * be preserved by this function, and returned from getHeaders().
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that has the
     * new and/or updated header and value.
     *
     * @param string $header Case-insensitive header field name.
     * @param string|stringarray() $value Header value(s).
     * @return static
     * @throws \InvalidArgumentException for invalid header names or values.
     */
    public function withHeader($header, $value)
    {
        if (is_string($value)) {
            $value = array($value);
        }

        if (! is_array($value) || ! $this->arrayContainsOnlyStrings($value)) {
            throw new InvalidArgumentException(
                'Invalid header value; must be a string or array of strings'
            );
        }

        HeaderSecurity::assertValidName($header);
        self::assertValidHeaderValue($value);

        $normalized = strtolower($header);

        $new = clone $this;
        if ($new->hasHeader($header)) {
            unset($new->headers[$new->headerNames[$normalized]]);
        }
        $new->headerNames[$normalized] = $header;
        $new->headers[$header]         = $value;

        return $new;
    }

    /**
     * Return an instance with the specified header appended with the
     * given value.
     *
     * Existing values for the specified header will be maintained. The new
     * value(s) will be appended to the existing list. If the header did not
     * exist previously, it will be added.
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that has the
     * new header and/or value.
     *
     * @param string $header Case-insensitive header field name to add.
     * @param string|string[] $value Header value(s).
     * @return static
     * @throws \InvalidArgumentException for invalid header names or values.
     */
    public function withAddedHeader($header, $value)
    {
        if (is_string($value)) {
            $value = array( $value );
        }

        if (! is_array($value) || ! $this->arrayContainsOnlyStrings($value)) {
            throw new InvalidArgumentException(
                'Invalid header value; must be a string or array of strings'
            );
        }

        HeaderSecurity::assertValidName($header);
        self::assertValidHeaderValue($value);

        if (! $this->hasHeader($header)) {
            return $this->withHeader($header, $value);
        }

        $normalized = strtolower($header);
        $header     = $this->headerNames[$normalized];

        $new = clone $this;
        $new->headers[$header] = array_merge($this->headers[$header], $value);
        return $new;
    }

    /**
     * Return an instance without the specified header.
     *
     * Header resolution MUST be done without case-sensitivity.
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that removes
     * the named header.
     *
     * @param string $header Case-insensitive header field name to remove.
     * @return static
     */
    public function withoutHeader($header)
    {
        if (! $this->hasHeader($header)) {
            return clone $this;
        }

        $normalized = strtolower($header);
        $original   = $this->headerNames[$normalized];

        $new = clone $this;
        unset($new->headers[$original], $new->headerNames[$normalized]);
        return $new;
    }

    /**
     * Gets the body of the message.
     *
     * @return StreamInterface Returns the body as a stream.
     */
    public function getBody()
    {
        return $this->stream;
    }

    /**
     * Return an instance with the specified message body.
     *
     * The body MUST be a StreamInterface object.
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return a new instance that has the
     * new body stream.
     *
     * @param StreamInterface $body Body.
     * @return static
     * @throws \InvalidArgumentException When the body is not valid.
     */
    public function withBody(StreamInterface $body)
    {
        $new = clone $this;
        $new->stream = $body;
        return $new;
    }

    private function getStream($stream, $modeIfNotInstance)
    {
        if ($stream instanceof StreamInterface) {
            return $stream;
        }

        if (! is_string($stream) && ! is_resource($stream)) {
            throw new InvalidArgumentException(
                'Stream must be a string stream resource identifier, '
                . 'an actual stream resource, '
                . 'or a Psr\Http\Message\StreamInterface implementation'
            );
        }

        return new Stream($stream, $modeIfNotInstance);
    }

    /**
     * Test that an array contains only strings
     *
     * @param array $array
     * @return bool
     */
    private function arrayContainsOnlyStrings(array $array)
    {
        return array_reduce($array, array(__CLASS__, 'filterStringValue'), true);
    }

    /**
     * Filter a set of headers to ensure they are in the correct internal format.
     *
     * Used by message constructors to allow setting all initial headers at once.
     *
     * @param array $originalHeaders Headers to filter.
     * @return array Filtered headers and names.
     */
    private function filterHeaders(array $originalHeaders)
    {
        $headerNames = $headers = array();
        foreach ($originalHeaders as $header => $value) {
            if (! is_string($header)) {
                throw new InvalidArgumentException(sprintf(
                    'Invalid header name; expected non-empty string, received %s',
                    gettype($header)
                ));
            }

            if (! is_array($value) && ! is_string($value) && ! is_numeric($value)) {
                throw new InvalidArgumentException(sprintf(
                    'Invalid header value type; expected number, string, or array; received %s',
                    (is_object($value) ? get_class($value) : gettype($value))
                ));
            }

            if (is_array($value)) {
                array_walk($value, function ($item) {
                    if (! is_string($item) && ! is_numeric($item)) {
                        throw new InvalidArgumentException(sprintf(
                            'Invalid header value type; expected number, string, or array; received %s',
                            (is_object($item) ? get_class($item) : gettype($item))
                        ));
                    }
                });
            }

            if (! is_array($value)) {
                $value = array( $value );
            }

            $headerNames[strtolower($header)] = $header;
            $headers[$header] = $value;
        }

        return array($headerNames, $headers);
    }

    /**
     * Test if a value is a string
     *
     * Used with array_reduce.
     *
     * @param bool $carry
     * @param mixed $item
     * @return bool
     */
    private static function filterStringValue($carry, $item)
    {
        if (! is_string($item)) {
            return false;
        }
        return $carry;
    }

    /**
     * Assert that the provided header values are valid.
     *
     * @see http://tools.ietf.org/html/rfc7230#section-3.2
     * @param string[] $values
     * @throws InvalidArgumentException
     */
    private static function assertValidHeaderValue(array $values)
    {
        array_walk($values, __NAMESPACE__ . '\HeaderSecurity::assertValid');
    }

    /**
     * Validate the HTTP protocol version
     *
     * @param string $version
     * @throws InvalidArgumentException on invalid HTTP protocol version
     */
    private function validateProtocolVersion($version)
    {
        if (empty($version)) {
            throw new InvalidArgumentException(sprintf(
                'HTTP protocol version can not be empty'
            ));
        }
        if (! is_string($version)) {
            throw new InvalidArgumentException(sprintf(
                'Unsupported HTTP protocol version; must be a string, received %s',
                (is_object($version) ? get_class($version) : gettype($version))
            ));
        }

        // HTTP/1 uses a "<major>.<minor>" numbering scheme to indicate
        // versions of the protocol, while HTTP/2 does not.
        if (! preg_match('#^(1\.[01]|2)$#', $version)) {
            throw new InvalidArgumentException(sprintf(
                'Unsupported HTTP protocol version "%s" provided',
                $version
            ));
        }
    }

    /**
     * Map of standard HTTP status code/reason phrases
     *
     * @var array
     */
    private $phrases = array(
        // INFORMATIONAL CODES
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing',
        // SUCCESS CODES
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        207 => 'Multi-status',
        208 => 'Already Reported',
        226 => 'IM used',
        // REDIRECTION CODES
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        306 => 'Switch Proxy', // Deprecated to 306 => '(Unused)'
        307 => 'Temporary Redirect',
        308 => 'Permanent Redirect',
        // CLIENT ERROR
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Time-out',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        414 => 'Request-URI Too Large',
        415 => 'Unsupported Media Type',
        416 => 'Requested range not satisfiable',
        417 => 'Expectation Failed',
        418 => 'I\'m a teapot',
        421 => 'Misdirected Request',
        422 => 'Unprocessable Entity',
        423 => 'Locked',
        424 => 'Failed Dependency',
        425 => 'Unordered Collection',
        426 => 'Upgrade Required',
        428 => 'Precondition Required',
        429 => 'Too Many Requests',
        431 => 'Request Header Fields Too Large',
        444 => 'Connection Closed Without Response',
        451 => 'Unavailable For Legal Reasons',
        // SERVER ERROR
        499 => 'Client Closed Request',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Time-out',
        505 => 'HTTP Version not supported',
        506 => 'Variant Also Negotiates',
        507 => 'Insufficient Storage',
        508 => 'Loop Detected',
        510 => 'Not Extended',
        511 => 'Network Authentication Required',
        599 => 'Network Connect Timeout Error',
    );

    /**
     * @var string
     */
    private $reasonPhrase = '';

    /**
     * @var int
     */
    private $statusCode;

    /**
     * @param string|resource|StreamInterface $body Stream identifier and/or actual stream resource
     * @param int $status Status code for the response, if any.
     * @param array $headers Headers for the response, if any.
     * @throws InvalidArgumentException on any invalid element.
     */
    public function __construct($body = 'php://memory', $status = 200, array $headers = array())
    {
        $this->setStatusCode($status);
        $this->stream = $this->getStream($body, 'wb+');
        list($this->headerNames, $headers) = $this->filterHeaders($headers);
        $this->assertHeaders($headers);
        $this->headers = $headers;
    }

    /**
     * {@inheritdoc}
     */
    public function getStatusCode()
    {
        return (int) $this->statusCode;
    }

    /**
     * {@inheritdoc}
     */
    public function getReasonPhrase()
    {
        if (! $this->reasonPhrase
            && isset($this->phrases[$this->statusCode])
        ) {
            $this->reasonPhrase = $this->phrases[$this->statusCode];
        }

        return $this->reasonPhrase;
    }

    /**
     * {@inheritdoc}
     */
    public function withStatus($code, $reasonPhrase = '')
    {
        $new = clone $this;
        $new->setStatusCode($code);
        $new->reasonPhrase = $reasonPhrase;
        return $new;
    }

    /**
     * Validate a status code.
     *
     * @param int|string $code
     * @throws InvalidArgumentException on an invalid status code.
     */
    private function setStatusCode($code)
    {
        if (! is_numeric($code)
            || is_float($code)
            || $code < 100
            || $code >= 600
        ) {
            throw new InvalidArgumentException(sprintf(
                'Invalid status code "%s"; must be an integer between 100 and 599, inclusive',
                (is_scalar($code) ? $code : gettype($code))
            ));
        }
        $this->statusCode = $code;
    }

    /**
     * Ensure header names and values are valid.
     *
     * @param array $headers
     * @throws InvalidArgumentException
     */
    private function assertHeaders(array $headers)
    {
        foreach ($headers as $name => $headerValues) {
            HeaderSecurity::assertValidName($name);
            array_walk($headerValues, __NAMESPACE__ . '\HeaderSecurity::assertValid');
        }
    }
}

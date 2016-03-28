<?php
/**
 * @copyright Copyright (c)2006-2016 Nicholas K. Dionysopoulos
 * @license   GNU GPL version 3 or, at your option, any later version
 * @package   s3-client
 */

namespace Keek\S3\Connector;

/**
 * Base class for request signing objects.
 */
abstract class Signature
{
    /**
     * The request we will be signing
     *
     * @var  Request
     */
    protected $request = null;

    /**
     * Signature constructor.
     *
     * @param   Request  $request  The request we will be signing
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Returns the authorization header for the request
     *
     * @return  string
     */
    abstract public function getAuthorizationHeader();

    /**
     * Pre-process the request headers before we convert them to cURL-compatible format. Used by signature engines to
     * add custom headers, e.g. x-amz-content-sha256
     *
     * @param   array  $headers     The associative array of headers to process
     * @param   array  $amzHeaders  The associative array of amz-* headers to process
     *
     * @return  void
     */
    abstract public function preProcessHeaders(&$headers, &$amzHeaders);

    /**
     * Get a pre-signed URL for the request. Typically used to pre-sign GET requests to objects, i.e. give shareable
     * pre-authorized URLs for downloading files from S3.
     *
     * @param   integer  $lifetime    Lifetime in seconds
     * @param   boolean  $https       Use HTTPS ($hostBucket should be false for SSL verification)?
     *
     * @return  string  The presigned URL
     */
    abstract public function getAuthenticatedURL($lifetime = null, $https = false);

    /**
     * Get a signature object for the request
     *
     * @param   Request  $request  The request which needs signing
     * @param   string   $method   The signature method, "v2" or "v4"
     *
     * @return  Signature
     */
    public static function getSignatureObject(Request $request, $method = 'v2')
    {
        $className = '\\Akeeba\\Engine\\Postproc\\Connector\\S3v4\\Signature\\' . ucfirst($method);

        return new $className($request);
    }
}

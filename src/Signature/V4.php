<?php
/**
 * @copyright Copyright (c)2006-2016 Nicholas K. Dionysopoulos
 * @license   GNU GPL version 3 or, at your option, any later version
 * @package   s3-client
 */

namespace Keek\S3\Connector\Signature;

use Keek\S3\Connector\Signature;

/**
 * Implements the Amazon AWS v4 signatures
 *
 * @see http://docs.aws.amazon.com/general/latest/gr/signature-version-4.html
 */
class V4 extends Signature
{
    /**
     * Pre-process the request headers before we convert them to cURL-compatible format. Used by signature engines to
     * add custom headers, e.g. x-amz-content-sha256
     *
     * @param   array  $headers     The associative array of headers to process
     * @param   array  $amzHeaders  The associative array of amz-* headers to process
     *
     * @return  void
     */
    public function preProcessHeaders(&$headers, &$amzHeaders)
    {
        // Do we already have an SHA-256 payload hash?
        if (isset($amzHeaders['x-amz-content-sha256']))
        {
            return;
        }

        // Set the payload hash header
        $input = $this->request->getInput();

        if (is_object($input))
        {
            $requestPayloadHash = $input->getSha256();
        }
        else
        {
            $requestPayloadHash = hash('sha256', '', false);
        }

        $amzHeaders['x-amz-content-sha256'] = $requestPayloadHash;
    }

    /**
     * Get a pre-signed URL for the request. Typically used to pre-sign GET requests to objects, i.e. give shareable
     * pre-authorized URLs for downloading files from S3.
     *
     * @param   integer  $lifetime    Lifetime in seconds
     * @param   boolean  $https       Use HTTPS ($hostBucket should be false for SSL verification)?
     *
     * @return  string  The presigned URL
     */
    public function getAuthenticatedURL($lifetime = null, $https = false)
    {
        // Set the Expires header
        if (is_null($lifetime))
        {
            $lifetime = 10;
        }

        $this->request->setHeader('Expires', (int) $lifetime);

        $bucket           = $this->request->getBucket();
        $uri              = $this->request->getResource();
        $headers          = $this->request->getHeaders();
        $protocol         = $https ? 'https' : 'http';
        $serialisedParams = $this->getAuthorizationHeader();

        $search = '/' . $bucket;

        if (strpos($uri, $search) === 0)
        {
            $uri = substr($uri, strlen($search));
        }

        $queryParameters = unserialize($serialisedParams);

        $query = http_build_query($queryParameters);

        $url = $protocol . '://' . $headers['Host'] . $uri;
        $url .= (strpos($uri, '?') !== false) ? '&' : '?';
        $url .= $query;

        return $url;
    }

    /**
     * Returns the authorization header for the request
     *
     * @return  string
     */
    public function getAuthorizationHeader()
    {
        $verb           = strtoupper($this->request->getVerb());
        $resourcePath   = $this->request->getResource();
        $headers        = $this->request->getHeaders();
        $amzHeaders     = $this->request->getAmzHeaders();
        $parameters     = $this->request->getParameters();
        $bucket         = $this->request->getBucket();
        $isPresignedURL = false;

        // See the Connector class for the explanation behind this ugly workaround
        $amazonIsBraindead = isset($headers['workaround-braindead-error-from-amazon']);

        if ($amazonIsBraindead)
        {
            unset ($headers['workaround-braindead-error-from-amazon']);
        }

        // Get the credentials scope
        $signatureDate = new \DateTime($headers['Date']);

        $credentialScope = $signatureDate->format('Ymd') . '/' .
            $this->request->getConfiguration()->getRegion() . '/' .
            's3/aws4_request';

        /**
         * If the Expires header is set up we're pre-signing a download URL. The string to sign is a bit
         * different in this case and we have to pass certain headers as query string parameters.
         *
         * @see http://docs.aws.amazon.com/general/latest/gr/sigv4-create-string-to-sign.html
         */
        if (isset($headers['Expires']) && ($verb == 'GET'))
        {
            $gmtDate = clone $signatureDate;
            $gmtDate->setTimezone(new \DateTimeZone('GMT'));

            $parameters['X-Amz-Algorithm'] = "AWS4-HMAC-SHA256";
            $parameters['X-Amz-Credential'] = $this->request->getConfiguration()->getAccess() . '/' . $credentialScope;
            $parameters['X-Amz-Date'] = $gmtDate->format('Ymd\THis\Z');
            $parameters['X-Amz-Expires'] = sprintf('%u', $headers['Expires']);

            unset($headers['Expires']);
            unset($headers['Date']);
            unset($headers['Content-MD5']);
            unset($headers['Content-Type']);

            $isPresignedURL  = true;
        }

        // ========== Step 1: Create a canonical request ==========
        // See http://docs.aws.amazon.com/general/latest/gr/sigv4-create-canonical-request.html

        $canonicalHeaders = "";
        $signedHeadersArray = array();

        // Calculate the canonical headers and the signed headers
        if ($isPresignedURL)
        {
            // Presigned URLs use UNSIGNED-PAYLOAD instead
            unset($amzHeaders['x-amz-content-sha256']);
        }

        $allHeaders = array_merge($headers, $amzHeaders);
        ksort($allHeaders);

        foreach ($allHeaders as $k => $v)
        {
            $lowercaseHeaderName = strtolower($k);

            if ($amazonIsBraindead && ($lowercaseHeaderName == 'content-length'))
            {
                // No, it doesn't look stupid. It is FUCKING STUPID. But somehow Amazon requires me to do it and only
                // on some servers. Yeah, I had the same "WHAT THE ACTUAL FUCK?!" reaction myself, thank you very much.
                // I wasted an entire day on this shit. And then you wonder why I write my own connector libraries
                // instead of pulling something through Composer, huh? Because the official library doesn't deal with
                // this stupid shit, that's why.
                $v = "$v,$v";
            }

            $canonicalHeaders .= $lowercaseHeaderName . ':' . trim($v) . "\n";
            $signedHeadersArray[] = $lowercaseHeaderName;
        }

        $signedHeaders = implode(';', $signedHeadersArray);

        if ($isPresignedURL)
        {
            $parameters['X-Amz-SignedHeaders'] = $signedHeaders;
        }

        // The canonical URI is the resource path
        $canonicalURI     = $resourcePath;
        $bucketResource   = '/' . $bucket;
        $regionalHostname = ($headers['Host'] != 's3.amazonaws.com') && ($headers['Host'] != $bucket . '.s3.amazonaws.com');

        if (!$regionalHostname && (strpos($canonicalURI, $bucketResource) === 0))
        {
            if ($canonicalURI === $bucketResource)
            {
                $canonicalURI = '/';
            }
            else
            {
                $canonicalURI = substr($canonicalURI, strlen($bucketResource));
            }
        }

        // If the resource path has a query yank it and parse it into the parameters array
        $questionMarkPos = strpos($canonicalURI, '?');

        if ($questionMarkPos !== false)
        {
            $canonicalURI = substr($canonicalURI, 0, $questionMarkPos);
            $queryString = @substr($canonicalURI, $questionMarkPos + 1);
            @parse_str($queryString, $extraQuery);

            if (count($extraQuery))
            {
                $parameters = array_merge($parameters, $extraQuery);
            }
        }

        // The canonical query string is the string representation of $parameters, alpha sorted by key
        ksort($parameters);

        // We build the query the hard way because http_build_query in PHP 5.3 does NOT have the fourth parameter
        // (encoding type), defaulting to RFC 1738 encoding whereas S3 expects RFC 3986 encoding
        $canonicalQueryString = '';

        if (!empty($parameters))
        {
            $temp = array();

            foreach ($parameters as $k => $v)
            {
                $temp[] = $this->urlencode($k) . '=' . $this->urlencode($v);
            }

            $canonicalQueryString = implode('&', $temp);
        }

        // Get the payload hash
        $requestPayloadHash = 'UNSIGNED-PAYLOAD';

        if (isset($amzHeaders['x-amz-content-sha256']))
        {
            $requestPayloadHash = $amzHeaders['x-amz-content-sha256'];
        }

        // Calculate the canonical request
        $canonicalRequest = $verb . "\n" .
            $canonicalURI . "\n" .
            $canonicalQueryString . "\n" .
            $canonicalHeaders . "\n" .
            $signedHeaders . "\n" .
            $requestPayloadHash;

        $hashedCanonicalRequest = hash('sha256', $canonicalRequest);

        // ========== Step 2: Create a string to sign ==========
        // See http://docs.aws.amazon.com/general/latest/gr/sigv4-create-string-to-sign.html

        $stringToSign = "AWS4-HMAC-SHA256\n" .
            $headers['Date'] . "\n" .
            $credentialScope . "\n" .
            $hashedCanonicalRequest;

        if ($isPresignedURL)
        {
            $stringToSign = "AWS4-HMAC-SHA256\n" .
                $parameters['X-Amz-Date'] . "\n" .
                $credentialScope . "\n" .
                $hashedCanonicalRequest;
        }

        // ========== Step 3: Calculate the signature ==========
        // See http://docs.aws.amazon.com/general/latest/gr/sigv4-calculate-signature.html
        $kSigning = $this->getSigningKey($signatureDate);

        $signature = hash_hmac('sha256', $stringToSign, $kSigning, false);

        // ========== Step 4: Add the signing information to the Request ==========
        // See http://docs.aws.amazon.com/general/latest/gr/sigv4-add-signature-to-request.html

        $authorization = 'AWS4-HMAC-SHA256 Credential=' .
            $this->request->getConfiguration()->getAccess() . '/' . $credentialScope . ', ' .
            'SignedHeaders=' . $signedHeaders . ', ' .
            'Signature=' . $signature;

        // For presigned URLs we only return the Base64-encoded signature without the AWS format specifier and the
        // public access key.
        if ($isPresignedURL)
        {
            $parameters['X-Amz-Signature'] = $signature;

            return serialize($parameters);
        }

        return $authorization;
    }

    /**
     * Calculate the AWS4 signing key
     *
     * @param   \DateTime  $signatureDate  The date the signing key is good for
     *
     * @return  string
     */
    private function getSigningKey(\DateTime $signatureDate)
    {
        $kSecret  = $this->request->getConfiguration()->getSecret();
        $kDate    = hash_hmac('sha256', $signatureDate->format('Ymd'), 'AWS4' . $kSecret, true);
        $kRegion  = hash_hmac('sha256', $this->request->getConfiguration()->getRegion(), $kDate, true);
        $kService = hash_hmac('sha256', 's3', $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);

        return $kSigning;
    }

    private function urlencode($string)
    {
        return str_replace('+', '%20', urlencode($string));
    }
}

<?php
/**
 * Akeeba Engine
 * The modular PHP5 site backup engine
 *
 * @copyright Copyright (c)2006-2015 Nicholas K. Dionysopoulos
 * @license   GNU GPL version 3 or, at your option, any later version
 * @package   akeebaengine
 */

namespace Akeeba\Engine\Postproc\Connector\S3v4\Signature;

// Protection against direct access
defined('AKEEBAENGINE') or die();

use Akeeba\Engine\Postproc\Connector\S3v4\Signature;

/**
 * Implements the Amazon AWS v2 signatures
 *
 * @see http://docs.aws.amazon.com/AmazonS3/latest/dev/RESTAuthentication.html
 */
class V2 extends Signature
{
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
		$isPresignedURL = false;

		$amz = array();
		$amzString = '';

		// Collect AMZ headers for signature
		foreach ($amzHeaders as $header => $value)
		{
			if (strlen($value) > 0)
			{
				$amz[] = strtolower($header) . ':' . $value;
			}
		}

		// AMZ headers must be sorted and sent as separate lines
		if (sizeof($amz) > 0)
		{
			sort($amz);
			$amzString = "\n" . implode("\n", $amz);
		}

		// If the Expires query string parameter is set up we're pre-signing a download URL. The string to sign is a bit
		// different in this case; it does not include the Date, it includes the Expires.
		// See http://docs.aws.amazon.com/AmazonS3/latest/dev/RESTAuthentication.html#RESTAuthenticationQueryStringAuth
		if (isset($parameters['Expires']) && ($verb == 'GET'))
		{
			$headers['Date'] = $parameters['Expires'];
			$isPresignedURL  = true;
		}

		$stringToSign = $verb . "\n" .
						(isset($headers['Content-MD5']) ? $headers['Content-MD5'] : '') . "\n" .
						(isset($headers['Content-Type']) ? $headers['Content-Type'] : '') . "\n" .
		                $headers['Date'] .
		                $amzString . "\n" .
		                $resourcePath;

		// CloudFront only requires a date to be signed
		if ($headers['Host'] == 'cloudfront.amazonaws.com')
		{
			$stringToSign = $headers['Date'];
		}

		$amazonV2Hash = $this->amazonV2Hash($stringToSign);

		// For presigned URLs we only return the Base64-encoded signature without the AWS format specifier and the
		// public access key.
		if ($isPresignedURL)
		{
			return $amazonV2Hash;
		}

		return 'AWS ' .
		       $this->request->getConfiguration()->getAccess() . ':' .
		$amazonV2Hash;
	}

	/**
	 * Creates a HMAC-SHA1 hash. Uses the hash extension if present, otherwise falls back to slower, manual calculation.
	 *
	 * @param   string  $string  String to sign
	 *
	 * @return  string
	 */
	private function amazonV2Hash($string)
	{
		$secret = $this->request->getConfiguration()->getSecret();

		if (extension_loaded('hash'))
		{
			$raw = hash_hmac('sha1', $string, $secret, true);

			return base64_encode($raw);
		}

		$raw = pack('H*', sha1(
					(str_pad($secret, 64, chr(0x00)) ^ (str_repeat(chr(0x5c), 64))) .
					pack('H*', sha1(
						(str_pad($secret, 64, chr(0x00)) ^ (str_repeat(chr(0x36), 64))) . $string
						)
					)
				)
		);

		return base64_encode($raw);
	}

}
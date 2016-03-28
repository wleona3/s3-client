<?php
/**
 * @copyright Copyright (c)2006-2016 Nicholas K. Dionysopoulos
 * @license   GNU GPL version 3 or, at your option, any later version
 * @package   s3-client
 */

namespace Keek\S3\Connector\Exception;

use Exception;

/**
 * Invalid Amazon S3 endpoint
 */
class InvalidEndpoint extends ConfigurationError
{
    public function __construct($message = "", $code = 0, Exception $previous = null)
    {
        if (empty($message))
        {
            $message = 'The custom S3 endpoint provided is invalid. Do NOT include the protocol (http:// or https://). Valid examples are s3.example.com and www.example.com/s3Api';
        }

        parent::__construct($message, $code, $previous);
    }

}

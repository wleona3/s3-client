<?php
/**
 * @copyright Copyright (c)2006-2016 Nicholas K. Dionysopoulos
 * @license   GNU GPL version 3 or, at your option, any later version
 * @package   s3-client
 */

namespace Keek\S3\Connector\Exception;

use Exception;

/**
 * Invalid Amazon S3 secret key
 */
class InvalidSecretKey extends ConfigurationError
{
    public function __construct($message = "", $code = 0, Exception $previous = null)
    {
        if (empty($message))
        {
            $message = 'The Amazon S3 Secret Key provided is invalid';
        }

        parent::__construct($message, $code, $previous);
    }

}

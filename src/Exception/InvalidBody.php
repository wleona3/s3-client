<?php
/**
 * @copyright Copyright (c)2006-2016 Nicholas K. Dionysopoulos
 * @license   GNU GPL version 3 or, at your option, any later version
 * @package   s3-client
 */

namespace Keek\S3\Connector\Exception;

use Exception;

/**
 * Invalid response body type
 */
class InvalidBody extends \RuntimeException
{
    public function __construct($message = "", $code = 0, Exception $previous = null)
    {
        if (empty($message))
        {
            $message = 'Invalid response body type';
        }

        parent::__construct($message, $code, $previous);
    }

}

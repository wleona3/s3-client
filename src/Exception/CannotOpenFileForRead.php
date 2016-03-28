<?php
/**
 * @copyright Copyright (c)2006-2016 Nicholas K. Dionysopoulos
 * @license   GNU GPL version 3 or, at your option, any later version
 * @package   s3-client
 */

namespace Keek\S3\Connector\Exception;

use Exception;

class CannotOpenFileForRead extends \RuntimeException
{
    public function __construct($file = "", $code = 0, Exception $previous = null)
    {
        $message = "Cannot open $file for reading";

        parent::__construct($message, $code, $previous);
    }

}

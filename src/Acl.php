<?php
/**
 * @copyright Copyright (c)2006-2016 Nicholas K. Dionysopoulos
 * @license   GNU GPL version 3 or, at your option, any later version
 * @package   s3-client
 */

namespace Keek\S3\Connector;

/**
 * Shortcuts to often used access control privileges
 */
class Acl
{
    const ACL_PRIVATE = 'private';

    const ACL_PUBLIC_READ = 'public-read';

    const ACL_PUBLIC_READ_WRITE = 'public-read-write';

    const ACL_AUTHENTICATED_READ = 'authenticated-read';

    const ACL_BUCKET_OWNER_READ = 'bucket-owner-read';

    const ACL_BUCKET_OWNER_FULL_CONTROL = 'bucket-owner-full-control';
}

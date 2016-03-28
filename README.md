# Keek's Amazon S3 Connector

A lightweight Amazon S3 connector implementation for PHP 5.3 or later

**Special Thanks To:** [akeeba/s3](https://github.com/akeeba/s3) for the Original Fork.

After having a lot of impossible to debug problems with Amazon's Guzzle-based AWS SDK we decided to roll our own
connector for Amazon S3. This is by no means a complete implementation, just a small subset of S3's features which are
required by our software. The design goals are simplicity and low memory footprint.

This code is loosely based on S3.php written by Donovan Schonknecht and available at
http://undesigned.org.za/2007/10/22/amazon-s3-php-class under a BSD-like license. This repository no longer reflects
the original author's work and should not be confused with it.

This software is distributed under the GNU General Public License version 3 or, at your option, any
later version published by the Free Software Foundation (FSF). In short, it's "GPLv3+".

## Using the connector

### Get a connector object

```php
$configuration = new Configuration(
	'YourAmazonAccessKey',
	'YourAmazonSecretKey'
);

$connector = new Connector($configuration);
```

### Listing buckets

```php
$listing = $connector->listBuckets(true);
```

Returns an array like this:

```
array(2) {
  'owner' =>
  array(2) {
    'id' =>
    string(64) "0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef"
    'name' =>
    string(8) "someUserName"
  }
  'buckets' =>
  array(3) {
    [0] =>
    array(2) {
      'name' =>
      string(10) "mybucket"
      'time' =>
      int(1267730711)
    }
    [1] =>
    array(2) {
      'name' =>
      string(10) "anotherbucket"
      'time' =>
      int(1269516249)
    }
    [2] =>
    array(2) {
      'name' =>
      string(11) "differentbucket"
      'time' =>
      int(1354458048)
    }
  }
}
```

### Listing bucket contents

```php
$listing = $connector->getBucket('mybucket', 'path/to/list/');
```

If you want to list "subdirectories" you need to do

```php
$listing = $connector->getBucket('mybucket', 'path/to/list/', null, null, '/', true);
```

The last parameter (common prefixes) controls the listing of "subdirectories"

### Uploading (small) files

From a file:

```php
$input = Input::createFromFile($sourceFile);
$connector->putObject($input, 'mybucket', 'path/to/myfile.txt');
```

From a string:

```php
$input = Input::createFromData($sourceString);
$connector->putObject($input, 'mybucket', 'path/to/myfile.txt');
```

From a stream resource:

```php
$input = Input::createFromResource($streamHandle, false);
$connector->putObject($input, 'mybucket', 'path/to/myfile.txt');
```

In all cases the entirety of the file has to be loaded in memory.

### Uploading large file with multipart (chunked) uploads

Files are uploaded in 5Mb chunks.

```php
$input = Input::createFromFile($sourceFile);
$uploadId = $connector->startMultipart($input, 'mybucket', 'mypath/movie.mov');

$eTags = array();
$eTag = null;
$partNumber = 0;

do
{
	// IMPORTANT: You MUST create the input afresh before each uploadMultipart call
	$input = Input::createFromFile($sourceFile);
	$input->setUploadID($uploadId);
	$input->setPartNumber(++$partNumber);

	$eTag = $connector->uploadMultipart($input, 'mybucket', 'mypath/movie.mov');

	if (!is_null($eTag))
	{
		$eTags[] = $eTag;
	}
}
while (!is_null($eTag));

// IMPORTANT: You MUST create the input afresh before finalising the multipart upload
$input = Input::createFromFile($sourceFile);
$input->setUploadID($uploadId);
$input->setEtags($eTags);

$connector->finalizeMultipart($input, 'mybucket', 'mypath/movie.mov');
```

As long as you keep track of the UploadId, PartNumber and ETags you can have each uploadMultipart call in a separate
page load to prevent timeouts.

### Get presigned URLs

Allows browsers to download files directly without exposing your credentials and without going through your server:

```php
$preSignedURL = $connector->getAuthenticatedURL('mybucket', 'path/to/file.jpg', 60);
```

The last parameter controls how many seconds into the future this URL will be valid.

### Download

To a file with absolute path `$targetFile`

```php
$connector->getObject('mybucket', 'path/to/file.jpg', $targetFile);
```

To a string

```php
$content = $connector->getObject('mybucket', 'path/to/file.jpg', false);
```

### Delete an object

```php
$connector->deleteObject('mybucket', 'path/to/file.jpg');
```

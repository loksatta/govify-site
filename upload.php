<?php

require 'vendor/autoload.php';
require 'config.php';

use OpenCloud\Rackspace;
use OpenCloud\ObjectStore\Resource\DataObject;
use OpenCloud\Common\Constants\Datetime;
use Guzzle\Plugin\Log\LogPlugin;


$errors = array();

if($_POST['submit'])
{
	if(!$_POST['email'] || !filter_var($_POST['email'], FILTER_VALIDATE_EMAIL))
	{
		$errors[] = 'Please enter a valid email address.';
	}

	// If we still don't have any errors.
	if(!$errors)
	{
	    // Check $_FILES['upload']['error'] value.
	    switch ($_FILES['upload']['error']) {
	        case UPLOAD_ERR_OK:
	            break;
	        case UPLOAD_ERR_NO_FILE:
	            $errors[] = 'No file sent.';
	        case UPLOAD_ERR_INI_SIZE:
	        case UPLOAD_ERR_FORM_SIZE:
	            $errors[] = 'Exceeded filesize limit.';
	        default:
	            $errors[] = 'Unknown errors.';
	    }

	    // You should also check filesize here.
	    if ($_FILES['upload']['size'] > 1000000) {
	        $errors[] = 'Exceeded filesize limit.';
	    }

	    // DO NOT TRUST $_FILES['upload']['mime'] VALUE !!
	    // Check MIME Type by yourself.
	    $finfo = new finfo(FILEINFO_MIME_TYPE);

	    if (!in_array(
	        $finfo->file($_FILES['upload']['tmp_name']),
	        array(
	            'text/plain'
	        )
	    )) {
	        $errors[] = 'Invalid file format. Only plain text files are allowed at this time.';
	    }
	}
}


$body = '<h2>.gov.ify a file</h2>';

// If we have a valid submission.
if($_POST['submit'] && !count($errors))
{

	$client = new Rackspace(Rackspace::US_IDENTITY_ENDPOINT, array(
	    'username' => API_USERNAME,
	    'apiKey'   => API_KEY
	));

	// Create a service object to use the object store service.
	$file_service = $client->objectStoreService('cloudFiles', API_REGION);

	$container = $file_service->getContainer(API_FILES_IN);

	// echo $container->getObjectCount();

	// echo $container->getBytesUsed();

	$filename = uniqid();
	$meta = array(
	    'Author' => $_POST['email'],
	    'Filename' => $_FILES['upload']['name'],
	    'Tempname' => $filename
	);

	$metaHeaders = DataObject::stockHeaders($meta);

	// We only keep the file around for 1 day at most!
	// If our queue gets out of control, we'll need to add more
	// workers to handle this.
	$customHeaders = array('X-Delete-After' => API_FILE_LIFETIME);
	$allHeaders = $metaHeaders + $customHeaders;

	// Store the file.
	$container->uploadObject($filename, file_get_contents($_FILES['upload']['tmp_name']), $allHeaders);

	// Once the file is stored, we can add it to the queue.
	$queue_service = $client->queuesService('cloudQueues', API_REGION);
	$queue_service->setClientId();

	// Get our queue.
	$queue = $queue_service->getQueue(API_QUEUE);


	// Store our metadata from before in the message.
	$queue->createMessage(array(
	    'body' => (object) $meta,
	    'ttl'  => Datetime::HOUR * 6
	));

	if($_POST['signup'])
	{
		$body .= '<p>Signed up!</p>';
		// If they've signed up for the mailinglist, push this to the mailinglist API.
		// OK no time for love Dr. Jones, so just put this into a text file in a super-secure location.
		// Just randomize the name so we don't have to worry about concurrency and locks.
		$email_log = dirname(__FILE__) . '/data/' . uniqid() . '.txt';

		$body .= $email_log;

		file_put_contents($email_log, $_POST['email'] . "\n", FILE_APPEND);
	}

	$body .= '<p>Your file has been added to the queue for processing.  You will receive an email when it has been processed!</p>';
}
else
{

    $body .= '<div class="row">
        <div class="col-md-6">';

	if($errors)
	{
		$body .= '<h3>Errors</h3>';
		$body .= '<ul class="errors">';
		foreach($errors as $error)
		{
			$body .= '<li>' . $error . '</li>';
		}
		$body .= '</ul>';
	}

	$body .= '<form method="POST" role="form" enctype="multipart/form-data">
    	<input type="hidden" name="MAX_FILE_SIZE" value="' . MAX_FILE_SIZE . '" />

  		<div class="form-group">
			<p class="help-block">
				It takes a little while for us to <strong class="featured">.gov.ify</strong>
				your file, so we require you to enter your email address, and a link to the
				finished file will be emailed to you in a bit.
			</p>
			<p class="help-block">
				Only plain text files (.txt) that are less than 50K in size can be accepted.
				Need to <strong class="featured">.gov.ify</strong> a larger file, or a different
				type?<br/><a href="https://github.com/opengovfoundation/govify">Download the source.</a>
			</p>
		</div>

  		<div class="form-group">
			<label for="email">Email Address</label>
			<input name="email" id="email" class="form-control" type="email" value="' . $_POST['email'] . '"/>
		</div>

		<div class="form-group">
			<label for="upload">File</label>
			<input name="upload" id="upload" type="file">
		</div>

		 <div class="checkbox">
			<label>
				<input type="checkbox" id="signup" name="signup" value="1" checked="checked">
				Sign me up to receive emails about awesome OpenGov projects in the future!
			</label>
		</div>

		<input type="hidden" name="submit" id="submit" value="1">
		<button type="submit" class="btn btn-primary"">Upload</button>

	</form>';

	$body .= '</div></div>';
}

// Wrap everything in a template.
require 'template.php';

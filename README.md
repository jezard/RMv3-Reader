RMv3 Feed Reader
----------------
Handles various feeds from providers which do not necessarily conform to the RMv3 Specification. Will *NOT* handle  all bespoke variants, but it may be used as a starting point. 

Example Contents of config.php (in feedreeder dir)
--------------------------------------------------

<?php
	$FEEDS_DIR = $_SERVER['DOCUMENT_ROOT'].'/../../feeduploads/';
	$REL_PATH = '../../feeduploads/';
	$IMAGE_DIR = '../media/';
	$db['default']['hostname'] = 'localhost';
	$db['default']['username'] = 'root';
	$db['default']['password'] = '';
	$db['default']['database'] = 'my_database';
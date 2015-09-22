<?php

	require "../config.php";
	require "reader.php";

	//set up the database connection
	$con = mysqli_connect($db['default']['hostname'],$db['default']['username'],$db['default']['password'],$db['default']['database']);
	$con2 = mysqli_connect($db['default']['hostname'],$db['default']['username'],$db['default']['password'],'postcodes');

	// Check connection
	if (mysqli_connect_errno())
	{
		echo "Failed to connect to MySQL: " . mysqli_connect_error();
		exit();
	}

	//get the reader instance
	$reader = new reader();	

	//unzip the files
	//get the feed dir
	$dir = new DirectoryIterator($FEEDS_DIR);
	$count = 1;
	$unzipped = [];
	
	foreach ($dir as $fileinfo) {
		if (!$fileinfo->isDot()) {

			$relativefile = $REL_PATH.$fileinfo->getFilename();
			if(strtolower(pathinfo($relativefile, PATHINFO_EXTENSION)) == 'zip')
			{
				//create a directory for each of the zipped dirs
				$stamp = date('d-m-y--h-i-s').'_'.$count.'/';//or whatever it needs to be
				$dest = $REL_PATH.$stamp;
				mkdir($dest);
				$count++;

				$zip = new ZipArchive;
				if ($zip->open($relativefile) === true) {
				    for($i = 0; $i < $zip->numFiles; $i++) {
				        $filename = $zip->getNameIndex($i);
				        $fileinfo = pathinfo($filename);
				        if(strtolower(pathinfo($filename, PATHINFO_EXTENSION)) == 'blm')
				        {
				        	copy("zip://".$relativefile."#".$filename, $dest.$fileinfo['basename']);
				        }
				        else //media files etc
				        {
				        	copy("zip://".$relativefile."#".$filename, $IMAGE_DIR.$fileinfo['basename']);
				        }
				        
				    }
				    //save the names of the directories we created for the extracted zip files  
				    array_push($unzipped, $stamp);                 
				    $zip->close(); 
				    //unlink($relativeFile);                  
				}
			}
		}
	}
	//check dir for unzipped uploads
	processProperties($FEEDS_DIR);

	//and then loop through and check through any extracted files
	foreach($unzipped as $sourcedir){
		processProperties($FEEDS_DIR.$sourcedir);
	}


	/*function to process the directory name [with absolute path] supplied as an argument*/
	function processProperties($source){
		global $FEEDS_DIR, $reader, $con, $con2;
		$propertyDir = new DirectoryIterator($source);
		foreach ($propertyDir as $fileinfo) {
		    if (!$fileinfo->isDot()) {
		    	$thisfile = $source.$fileinfo->getFilename();
		    	if(pathinfo($thisfile, PATHINFO_EXTENSION) == 'BLM' || pathinfo($thisfile, PATHINFO_EXTENSION) == 'blm')
		    	{
		    		$reader->batch($thisfile, $con, $con2);
		    	}
		    }
		}
	}


	mysqli_close($con);
	mysqli_close($con2);
	echo $reader->logfile;
?>
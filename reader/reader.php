<?php
  	require "../config.php";

   	class Reader{

   		public $logfile = '';

   		public function batch($source, $con, $con2){
   			$this->logfile .= 'Processing file: '.$source.PHP_EOL;

			$fieldSeperator = '';
			$rowSeperator = '';
			$rowdata = '';
			$headerpos = 0;
			$datastart = 0;
			$header = [];
			$data = [];
			$datafirstline = 0;
			$hasdata = false;
			$latlon = false;

			//read the first few lines
			$linenumber = 0;
			$handle = @fopen($source, "r");
			if ($handle) {
			    while (($buffer = fgets($handle, 4096)) !== false) {
			    	//look for the End of Field seperator line
			        if(strpos($buffer, 'EOF') === 0)
			        {
			        	if (preg_match('/\'([^"]+)\'/', $buffer, $seperator)) {
						    $fieldSeperator = $seperator[1];
						    $this->logfile .= 'Using field seperator "'.$fieldSeperator.'"'.PHP_EOL;
						}
						else
						{
							$this->logfile .= 'Cannot determine field seperator'.PHP_EOL;
						}
			        }
			        //look for the End of Row seperator line
			        if(strpos($buffer, 'EOR') === 0)
			        {
			        	if (preg_match('/\'([^"]+)\'/', $buffer, $seperator)) {
						    $rowSeperator = $seperator[1];
						    $this->logfile .= 'Using row seperator "'.$rowSeperator.'"'.PHP_EOL;   
						}
						else
						{
							$this->logfile .= 'Cannot determine row seperator'.PHP_EOL;
						}
			        }
			        if(strpos($buffer, '#DEFINITION#') === 0)
			        {
			        	//get the position of the header row data which is 1 below the #DEFINITION# marker
			        	$headerpos = $linenumber+1;
			        }
			        if(strpos($buffer, '#DATA#') === 0)
			        {
			        	//get the position of the first data row data which is 1 below the #DATA# marker
			        	$datafirstline = $linenumber+1;
			        }


			        //if we've found the definition
			        if($headerpos > 0)
			        {
			        	$hasdata = true;
			        	//if we're on the correct line to get the header row
			        	 if($headerpos == $linenumber){
			        	 	//
				        	$header = explode($fieldSeperator, $buffer);
				        }
			        }

			        if(strpos($buffer, '#END#') === 0)
			        {
			        	//set end of data flag at #END# marker
			        	$hasdata = false;	        	
			        }

			        //if we've already found the first line of data, and we haven't reached the end of it...
			        if($datafirstline > 0 && $hasdata)
			        {
			        	 //if we're on row or line with data
				        if($linenumber >= $datafirstline)
				        {
				        	//add the record to list
				        	$rowdata .= $buffer;
				        }
			        }

			       $linenumber++;
			    }
			    if (!feof($handle)) {
			        echo "Error: unexpected fgets() fail\n";
			    }
			    fclose($handle);
			}

			//split the row data into records and fields
			$allRows = explode($rowSeperator, $rowdata);


			foreach ($allRows as $singleRow) {
				$row = explode($fieldSeperator, $singleRow);
				array_push($data, $row);
				
			}
			//remove the last element as it only contains the row seperator
			array_pop($data);

			//do some processing of the data
			$totalRows = count($data);
			$this->logfile .= 'Total Properties: '.$totalRows.PHP_EOL;

			//write the info to the database
			
			//for each property
			foreach($data as $property => $field)
			{
				//dev
				$fields = 'agentId,';
				$values = rand(0, 10).',';
				$images = [];
				$imageCaptions = [];
				$floorPlans = [];

				$count = 0;

				//need to store whether a field has been remapped previously in this property entry
				$a1_used = false;
				$a2_used = false;
				$a3_used = false;
				$a4_used = false;

				//reset the concatenated keywords string
				$keywords = '';

				//for each field
				foreach ($header as $key => $name) {
					$value = $data[$property][$key];
					//show name - value from header and data.
					//echo "Name: $name, Value: $value".PHP_EOL;
					$count++;
					//next we need to check our fields agains those of the database and map suitable values
					$value =  mysqli_real_escape_string($con, $value);

					//re-maps - this is a bit simple and we could look to improve this in the future
					switch($name){
						case 'TOWN':
							if(!$a3_used)
							{
								$name = 'ADDRESS_3';
								$a3_used = true;
							}
							elseif(!$a4_used)
							{
								$name = 'ADDRESS_4';
								$a4_used = true;
							}	
							break;
						case 'COUNTY':
							if(!$a3_used)
							{
								$name = 'ADDRESS_3';
								$a3_used = true;
							}
							elseif(!$a4_used)
							{
								$name = 'ADDRESS_4';
								$a4_used = true;
							}	
							break;
					}

					//find image fields - this is rather basic, but given time constraints..
					if(strpos($name, 'MEDIA_IMAGE_') === 0)
					{
						//extract the number n
						$image_n = str_replace('MEDIA_IMAGE_', '', $name);
						if(strpos($value, '.jpg') || strpos($value, '.png') || strpos($value, '.gif'))
						{
							//array_push($images, $value);
							array_push($images, array('url' => $value, 'n' => $image_n));
						}					
					}
					//also get the image text
					if(strpos($name, 'MEDIA_IMAGE_TEXT_') === 0)
					{
						//extract the number n
						$imageText_n = str_replace('MEDIA_IMAGE_TEXT_', '', $name);
						array_push($imageCaptions, array('value' => $value, 'n' => $imageText_n));
				
					}
					//floor plans
					if(strpos($name, 'MEDIA_FLOOR_PLAN') === 0){
						if(strpos($value, '.jpg') || strpos($value, '.png') || strpos($value, '.gif'))
						{
							array_push($floorPlans, $value);
						}
					}


					//map feed field to db field
					switch($name){
						case 'AGENT_REF':
							$fields .= 'agentRef,';
							$values .= "'$value',";
							break;
						case 'ADDRESS_1':
							$fields .= 'address1,';
							$values .= "'$value',";
							$a1_used = true;
							break;
						case 'ADDRESS_2':
							$fields .= 'address2,';
							$values .= "'$value',";
							$a2_used = true;
							break;
						case 'ADDRESS_3':
							$fields .= 'address3,';
							$values .= "'$value',";
							$a3_used = true;
							break;
						case 'ADDRESS_4':
							$fields .= 'address4,';
							$values .= "'$value',";
							$a4_used = true;
							break;			
						case 'POSTCODE1':
							$fields .= 'postcode1,';
							$values .= "'$value',";
							$postcode = $value;
							break;
						case 'POSTCODE2':
							$fields .= 'postcode2,';
							$values .= "'$value',";
							$postcode .= $value;
							break;
						case 'DISPLAY_ADDRESS':
							$fields .= 'displayAddress,';
							$values .= "'$value',";
							break;
						case 'FEATURE1':
							$fields .= 'feature1,';
							$values .= "'$value',";
							if($value != '')$keywords .= $value.' ';
							break;
						case 'FEATURE2':
							$fields .= 'feature2,';
							$values .= "'$value',";
							if($value != '')$keywords .= $value.' ';
							break;
						case 'FEATURE3':
							$fields .= 'feature3,';
							$values .= "'$value',";
							if($value != '')$keywords .= $value.' ';
							break;
						case 'FEATURE4':
							$fields .= 'feature4,';
							$values .= "'$value',";
							if($value != '')$keywords .= $value.' ';
							break;
						case 'FEATURE5':
							$fields .= 'feature5,';
							$values .= "'$value',";
							if($value != '')$keywords .= $value.' ';
							break;
						case 'FEATURE6':
							$fields .= 'feature6,';
							$values .= "'$value',";
							if($value != '')$keywords .= $value.' ';
							break;
						case 'FEATURE7':
							$fields .= 'feature7,';
							$values .= "'$value',";
							if($value != '')$keywords .= $value.' ';
							break;
						case 'FEATURE8':
							$fields .= 'feature8,';
							$values .= "'$value',";
							if($value != '')$keywords .= $value.' ';
							break;
						case 'FEATURE9':
							$fields .= 'feature9,';
							$values .= "'$value',";
							if($value != '')$keywords .= $value.' ';
							break;
						case 'FEATURE10':
							$fields .= 'feature10,';
							$values .= "'$value',";
							if($value != '')$keywords .= $value.' ';
							break;
						case 'SUMMARY':
							$fields .= 'summary,';
							$values .= "'$value',";
							break;
						case 'DESCRIPTION':
							$fields .= 'description,';
							$values .= "'$value',";
							break;
						case 'BRANCH_ID':
							$fields .= 'branchId,';
							$value = 1;//dev
							$values .= "$value,";
							break;
						case 'STATUS_ID':
							$fields .= 'statusId,';
							$values .= "$value,";
							break;
						case 'BEDROOMS':
							$fields .= 'bedrooms,';
							$values .= "$value,";
							break;
						case 'BATHROOMS':
							$fields .= 'bathrooms,';
							$values .= Reader::checkNull($value, 'int');
							break;
						case 'LIVING_ROOMS':
							$fields .= 'livingRooms,';
							$values .= Reader::checkNull($value, 'int');
							break;
						case 'PRICE':
							$fields .= 'price,';
							$value = intval($value);
							$values .= "$value,";
							break;
						case 'PROP_SUB_ID':
							$fields .= 'subtypeId,';
							$result = mysqli_query($con, "SELECT value FROM propertysubtypes WHERE subtypeId = '$value' LIMIT 1");
							$row = mysqli_fetch_assoc($result);
							$lookupval = $row["value"];
							if($lookupval != '')$keywords .= $lookupval.' ';
							//if there is a lookup value for our key
							if($row != NULL)
							{
								$values .= "$value,";
							}
							//set to unspecified
							else
							{
								$values .= "0,";
							}
							break;
						case 'PRICE_QUALIFIER':
							$fields .= 'priceQualifier,';
							$values .= "$value,";
							break;
						case 'CREATED_DATE':
							$fields .= 'created_at,';
							$values .= "'$value',";
							break;
						case 'UPDATE_DATE':
							$fields .= 'updated_at,';
							$values .= Reader::checkNull($value, 'timestamp');
							break;
						case 'PUBLISHED_FLAG':
							$fields .= 'publishedFlag,';
							$values .= "$value,";
							break;
						case 'LET_DATE_AVAILABLE':
							$fields .= 'letDateAvailable,';
							if($value != NULL)
								$values .= "'$value',";
							else
								$values .= "NULL,";
							break;
						case 'LET_BOND':
							$fields .= 'letBond,';
							$values .= Reader::checkNull($value, 'int');
							break;
						case 'LET_TYPE_ID':
							$fields .= 'letTypeId,';
							$values .= Reader::checkNull($value, 'int');
							break;
						case 'LET_FURN_ID':
							$fields .= 'letFurnId,';
							$values .= Reader::checkNull($value, 'int');
							$result = mysqli_query($con, "SELECT value FROM letfurnishedtypes WHERE letfurnid = '$value' LIMIT 1");
							$row = mysqli_fetch_assoc($result);
							$lookupval = $row["value"];
							if($lookupval != '')$keywords .= $lookupval.' ';
							break;
						case 'LET_RENT_FREQUENCY':
							$fields .= 'letRentFrequency,';
							$values .= Reader::checkNull($value, 'int');
							break;
						case 'TENURE_TYPE_ID':
							$fields .= 'tenureTypeId,';
							$values .= "$value,";
							$result = mysqli_query($con, "SELECT value FROM tenureTypes WHERE tenureTypeId = '$value' LIMIT 1");
							$row = mysqli_fetch_assoc($result);
							$lookupval = $row["value"];
							if($lookupval != '')$keywords .= $lookupval.' ';
							break;
						case 'TRANS_TYPE_ID':
							$fields .= 'transTypeId,';
							$values .= "$value,";
							break;	
						case 'NEW_HOME_FLAG':
							$fields .= 'newHomeFlag,';
							$values .= "'$value',";
							break;
						case 'LAT':
							$fields .= 'latitude,';
							$values .= "'$value',";
							$latlon = true;
							break;
						case 'LNG':
							$fields .= 'longitude,';
							$values .= "'$value',";
							break;
					}
				}
				//add the concatenated list of keywords and lookup values
				$fields .= 'keywords,';
				$values .= "'$keywords',";


				//add the lat-long vals
				if(!$latlon)//if false to geocode
				{
					$coords = Reader::getGeo($postcode);

					$fields .= 'latitude,longitude,';
					$values .= "'$coords[0]','$coords[1]',";				
				}
				

				// remove the last commas
				$fields = substr($fields, 0, -1);
				$values = substr($values, 0, -1);


				$query = "INSERT INTO propertys ($fields) VALUES ($values)";
				//echo $query.PHP_EOL;

				@mysqli_query($con, $query);
				$dberror = mysqli_error($con);
				if($dberror)
				{
					echo 'ERROR: '.$dberror;
				}
				$insertId = mysqli_insert_id($con);

				//retrive the floorplans if any
				foreach($floorPlans as $floorPlan){
					//add record to database
					$query = "INSERT INTO images (propertyId, imageFilename, imageCaption, isFloorplan, isMainImage ) VALUES ( $insertId, '$floorPlan', 'Floorplan', TRUE, FALSE )";
					@mysqli_query($con, $query);
					$dberror = mysqli_error($con);
					if($dberror)
					{
						echo 'ERROR: '.$dberror;
					}
				}

				//retrieve the images
				foreach($images as $image){
					$imageNum = $image['n'];
					
					//and for each image
					foreach($imageCaptions as $caption)
					{
						$captionNum = $caption['n'];

						//match the image to the caption
						if($captionNum == $imageNum)
						{
							$captionText = $caption['value'];
						}
						else
						{
							$captionText = 'Property image';
						}
						$imageURL = $image['url'];
						
					}
					//add record to database
					$query = "INSERT INTO images (propertyId, imageFilename, imageCaption, isFloorplan, isMainImage ) VALUES ( $insertId, '$imageURL', '$captionText', FALSE, FALSE )";
					@mysqli_query($con, $query);
				}
				$dberror = mysqli_error($con);
				if($dberror)
				{
					echo 'ERROR: '.$dberror;
				}
				
			}//end foreach property
   		}

   		//temporary function using postcode database to assign geo codes to properties
		protected function getGeo($postcode){
			global $con2;
			$query2 = "SELECT * FROM data WHERE postcode = '$postcode' LIMIT 1";
			$result = mysqli_query($con2, $query2);
			echo mysqli_error($con2);
			$row = mysqli_fetch_assoc($result);
			$lat = $row["latitude"];
			$lon = $row["longitude"];

			return [$lat,$lon];
		}

   		/* FUNCTION TO CHECK FIELD VALUE AND RETURN USABLE VALUE */
		protected function checkNull($val, $type){
			if($type == 'int')
			{
				if($val != NULL)
				{
					return "$val,";
				}
				else
				{
					return "NULL,";
				}
			}
			else
			{
				if($val != NULL)
				{
					return "'$val',";
				}
				else
				{
					return "NULL,";
				}
			}
		}

   }
<?php
################################################################################
# This script returns calculated scores of experimental and predicted localizations for a given AGI ID.
# This script was partially based on Hans Yu's suba3 python CGI scripts 
# Improvements include:
# 1) Dynamically checking predicted field column names in the SQL table versus the preivously hardcoded field names
# 2) Receiving and sending data via JSON as a POST request versus a GET which allows more genes to be searched
# Author: Vincent Lau (vincente.lau@mail.utoronto.ca), with help of Asher Pasha
# Date: Dec, 2017
# Usage: 
################################################################################

# External file for auth permissions
require_once('database_auth.php');

# CORS permissions
header("Access-Control-Allow-Origin: *");

# Respond to preflights, modified code excerpt from https://remysharp.com/2011/04/21/getting-cors-working/
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
	# return only the headers and not the content
	# only allow CORS if we're doing a POST - i.e. no saving for now.
	if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']) &&
		$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'] == 'POST') {
	  header('Access-Control-Allow-Origin: *');
	  header('Access-Control-Allow-Methods: POST');
	  header('Access-Control-Allow-Headers: content-type');
	}
	exit();
}

# Global variables here for easier modification

$predValue = 2;
$expValue = 10;
$expColNames = array("gfp", "mass_spec", "swissprot", "amigo", "tair", "gs");

# Entry point for PHP script
main();

################################################################################
# Functions
################################################################################

# A generic funtion to remove html/script from input. Acknowledgements: W3C Schools PHP tutorial
function cleanInput($data) {
	$data = trim($data); #Strip whitespace from the beginning and end of a string
	$data = stripslashes($data); #Un-quotes a quoted string
	$data = htmlspecialchars($data); #Convert special characters to HTML entities
	return $data;	
}

# A generic function to verify/sanitize AGI IDs array for use in the SQL query, prevent SQLi
function isAGIArrayValid($AGI){
	foreach($AGI as $gene) {
		if (!(preg_match("/^AT[1-5MC]G\d{5}$/i", $gene))) {
			return false;
		}
	}
	return true;
}

# If the service failed
function output($JSONErrorMSG) {
	$errorMSG['status'] = "fail";
	$errorMSG['result'] = $JSONErrorMSG;
	header('Content-Type: application/json');
	echo json_encode($errorMSG);
	exit();
}

# Take in a string (localizations, expected to be something like "cytosol,nucleus") to be split 
# by ',' and process the score for that gene for that particular subcellular localization
# adding by the score param (value) to the scores associative array
# Note that scores is passed by reference not value so that we can directly modify the 
# associative array to be used in future iterations (to keep memory of previous scores!)
function processLocalizations($localizations, &$scores, $value) {
	$locArray = explode(',', $localizations); # PHP 7.0's version of 'split' is 'explode'
	foreach($locArray as $loc) {
		if (isset($scores[$loc]) === false) { # First check if key exists in our scores ass arr
			$scores[$loc] = $value; # Initialize array key with the pred/exp value
		}
		else { # if key already exists
			$scores[$loc] += $value; # Add to the preexisting gene-location the pred/exp value
		}
	}
}

function main(){

	GLOBAL $db_name, $db_user, $db_pw, $predValue, $expValue, $expColNames;

	# Make sure that it is a POST request.
	if(strcasecmp($_SERVER['REQUEST_METHOD'], 'POST') != 0 ){
		output('Request method must be POST!');
	}

	# Make sure that the content type of the POST request has been set to application/json
	$contentType = isset($_SERVER["CONTENT_TYPE"]) ? trim($_SERVER["CONTENT_TYPE"]) : '';
	if(strcasecmp($contentType, 'application/json') != 0){
		output('Content type must be: application/json');
	}

	# Receive the RAW post data.
	$content = trim(file_get_contents("php://input"));

	# Attempt to decode the incoming RAW post data from JSON. Data now in an ass. array
	$decodedData = json_decode($content, true);

	# If json_decode failed, the JSON is invalid.
	if(!is_array($decodedData)){
		output('Received content contained invalid JSON!');
	}


	if (isAGIArrayValid($decodedData['AGI_IDs']) === false) {
		output('Array of AGI IDs were not properly formatted in the JSON request! ');
	}

	# Prevent PHP injection
	if ( is_bool($decodedData['include_predicted']) === false ) { 
		output('include_predicted flag was not set to either true or false boolean type!');		
	}

	# Process the JSON.
	# We added a .1 because the SUBA4 database accounts for alternate splicing variants, however
	# our legacy code uses eplant uses the 'main' splice variant which is generally assigned '.1'
	$whereInClause = "( '" . join(".1','", $decodedData['AGI_IDs'] ) . ".1' )";

	$con = mysqli_connect("localhost", $db_user, $db_pw, $db_name);
	
	if (mysqli_connect_errno()) {
		// $error['Error'] = "Failed to connect to database";
		output('Failed to connect to database');
	}
	mysqli_select_db($con, $db_name);	
		
	$sql = "SELECT * FROM BAR_suba4 WHERE gene_id IN $whereInClause";	
	
	$resJSON = array(); # declare return JSON structure

	if ($result = mysqli_query($con, $sql)) {

		# This if block is essentially grabbing the field column names that begin with 'pred_'
		if ($decodedData['include_predicted']) { # Check POST include_predicted flag is T/F
			$predMatches = array(); # Outer scope variable to match the predicted field names

			$finfo = mysqli_fetch_fields($result); # get field/column information
			foreach ($finfo as $field) {
				if (preg_match('/pred_/', $field->name)) { # Match ONLY predicted
					array_push($predMatches, $field->name);
				}
			}

		}

		# Let us process the MySQL data row by row
		while( $row = mysqli_fetch_assoc($result) ) {
			$singleJSONObject = array();

			$singleJSONObject['id'] = substr($row['gene_id'], 0, -2); # Assign id in JSON, chop '.1' from AGI ID
			$singleJSONOBject['data'] = array(); # Inner ass-array to store localization data


			$singleJSONObject['includes_predicted'] = "no"; # Default, we'll verify below	

			if($decodedData['include_predicted']) { # Run this code only if POST req field was set T
				foreach($predMatches as $predField) {
					$predColLocs = $row[$predField]; # NB: this is a string
					if ( $predColLocs !== NULL ) { #check if field value is NOT NULL
						processLocalizations($predColLocs, $singleJSONObject['data'] , $predValue);
						
						# If at least one of the 'pred_' columns are NOT NULL then we 
						# can be certain that 'includes_predicted' = "yes"
						$singleJSONObject['includes_predicted'] = "yes";						
					}
				}
			}

			$singleJSONObject['includes_experimental'] = "no"; # Default, we'll verify below			

			# Process experimental columns
			foreach($expColNames as $expCol){
				$expColLocs = $row[$expCol];
				if ( $expColLocs !== NULL ) { # check if field value is NOT NULL
					processLocalizations($expColLocs, $singleJSONObject['data'], $expValue);
					$singleJSONObject['includes_experimental'] = "yes";
				}
			}

			array_push($resJSON, $singleJSONObject); # Add to our final JSON response object

		}

		header('Content-Type: application/json');
		echo json_encode($resJSON);
	}
	else {
		// $error['Error'] = "Query failed to execute";
		output('Query failed to execute');
	}

	mysqli_close($con);	

}

	
?>

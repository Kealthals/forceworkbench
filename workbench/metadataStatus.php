<?php
require_once ('session.php');
require_once ('shared.php');

if(!apiVersionIsAtLeast(10.0)) {
	show_error("Metadata API not supported prior to version 10.0", true, true);
	exit;
}

if(!isset($_GET['asyncProcessId'])){
	require_once('header.php');
	print "<p/>";
	show_error("Parameter 'asyncProcessId' must be specified.",false,false);
	print 	"<p/>" . 
			"<form action='$_SERVER[PHP_SELF]' method='GET'>" . 
			"Async Process Id: <input type='text' name='asyncProcessId'/> &nbsp;" .  
			"<input type='submit' value='Get Status'".
			"</form>";
	include_once('footer.php');
	exit;
} 

$asyncProcessId = htmlentities($_GET['asyncProcessId']);

if(isset($_GET['downloadZip'])) {
	if(!isset($_SESSION['retrievedZips'][$asyncProcessId])) {
		show_error("No zip file found for async process id '$asyncProcessId'. Note, retrieve results are deleted after first download or navigating away from this page.", true, true);
	}
	
	header("Content-Type: application/zip");
	header("Content-Disposition: attachment; filename=retrieve_$asyncProcessId.zip");
	print $_SESSION['retrievedZips'][$asyncProcessId];
	unset($_SESSION['retrievedZips'][$asyncProcessId]);
	exit;
}

require_once('header.php');
print "<p class='instructions'>A Metadata API operation has been performed, which requires asynchronous processing as resources are available. " . 
	  "Bookmark and periodically view this page to view the latest status. Results will be available once processing is complete.</p><p/>";

require_once ('soapclient/SforceMetadataClient.php');
global $metadataConnection;
try {
	$asyncResults = $metadataConnection->checkStatus($asyncProcessId);
	
	if(!isset($asyncResults)) {
		show_error("No results returned for '$asyncProcessId'", false, true);	
	}

	if(!$asyncResults->done) {
		print "<input type='button' onclick='window.location.href=window.location.href;' value='Refresh' style='float:right;'/>";
	}
	
	$orderedAsyncResults = array("id"=>null,"done"=>null,"stateDetailLastModifiedDate"=>null,"state"=>null);
	foreach($asyncResults as $resultName => $resultValue) {
		$orderedAsyncResults[$resultName] = $resultValue;
	}
	
	print "<h3>Status</h3>";
	print "<table class='lightlyBoxed' cellpadding='5' width='100%'>\n";
	$rowNum = 0;
	foreach($orderedAsyncResults as $resultName => $resultValue) {
		if(++$rowNum % 2) {
			print "<tr>";
			printStatusCell($resultName, $resultValue);
		} else {
			printStatusCell($resultName, $resultValue);
			print "</td></tr>\n";
		}
	}
	if($rowNum % 2) {
		print "<td></td><td></td></tr>";
	}
	print "</table>\n";
	
	if($asyncResults->done) {
		print "<p>&nbsp;</p><h3>Results</h3>";
		
		//if they don't tell us the operation name, let's guess from the deploy-specific checkOnly flag (doesn't work for all api versions).
		$operation = isset($_REQUEST['op']) ? htmlentities($_REQUEST['op']) : (isset($asyncResults->checkOnly) ? "D" : "R");
		
		$results = $operation == "D" ? $metadataConnection->checkDeployStatus($asyncProcessId, $debugInfo) : $metadataConnection->checkRetrieveStatus($asyncProcessId, $debugInfo);
		
		$zipLink = null;
		if(isset($results->zipFile)) {
			show_info("Retrieve result ZIP file is ready for download.");
			print "<p/>";
			
			$zipLink = " | <a id='zipLink' href='$_SERVER[PHP_SELF]?asyncProcessId=$asyncResults->id&downloadZip' onclick='undownloadedZip=false;' style='text-decoration:none;'>" . 
					   "<span style='text-decoration:underline;'>Download ZIP File</span> <img src='images/downloadIconCompleted.gif' border='0'/>" . 
					   "</a></p>";
			$_SESSION['retrievedZips'][$asyncResults->id] = $results->zipFile;
			unset($results->zipFile);
		}
		
		printTree("metadataStatusResultsTree", processResults($results), true, $zipLink);
		
		if(isset($debugInfo["DebuggingInfo"]->debugLog)){
			print "<p>&nbsp;</p><h3>Debug Logs</h3>";
			print("<pre>" . addLinksToUiForIds(htmlspecialchars($debugInfo["DebuggingInfo"]->debugLog,ENT_QUOTES,'UTF-8')) . '</pre>');
		} 
	}
} catch (Exception $e) {
	show_error($e->getMessage(), false, true);
}
?>
<script>
var undownloadedZip = false;
window.onbeforeunload = function() {
  if (undownloadedZip) {
    return 'There is a ZIP file awaiting download. It will be deleted if you refresh or navigate away from this page.';
  }
}

if(document.getElementById("zipLink") != null) {
	undownloadedZip = true;
}
</script>
<?php

include_once('footer.php');

function printStatusCell($resultName, $resultValue) {
	print "<td style='text-align: right; padding-right: 2em; font-weight: bold;'>" . unCamelCase($resultName) . "</td><td>";
	if(is_bool($resultValue)) {
		print $resultValue ? "true" : "false";
	} else {
		print $resultValue;
	}
	print "</td>";
}
?>
<?php

// The file where you want to save the form data
$savedSittingFilename = 'lineup_results.txt';
// The file that might've come from TC
$filledPeopleFromTCFilename = 'filledPeopleFromTC.txt';

function readFileUploadedFromTCAndTransferToSavedPeople()
{
	global $savedSittingFilename;
	global $filledPeopleFromTCFilename;
	
	if (!file_exists($filledPeopleFromTCFilename))
		return;
	
	// Read all the lines from the file into an array
	$filledPeopleFromTC = file($filledPeopleFromTCFilename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

	if ($filledPeopleFromTC === false)
		// Not doing anything - there was probably some race and the file doesn't exist anymore.
		return;
		    
	// Open the saved sitting file for reading and writing; create it if it doesn't exist
	$savedSittingFile = fopen($savedSittingFilename, 'a+');
    if ($savedSittingFile === false) {
        // If opening fails, exit the function
        return;
    }
	
    // Try to lock the file to prevent other processes from interfering
    if (!flock($savedSittingFile, LOCK_EX)) {
        fclose($savedSittingFile);
        return;
    }
		
	// Remove the file after reading
	unlink($filledPeopleFromTCFilename);

    // Move back to the start of the file to read its contents
    rewind($savedSittingFile);
	
    // Read all existing sittings
    $savedSittings = [];
    while (!feof($savedSittingFile)) {
        $savedSittingLine = fgets($savedSittingFile);
        if ($savedSittingLine !== false) { // Exclude false lines, which indicate failed reading or end of file
			$parts = explode(';', $savedSittingLine);
			$parameters = implode(';', array_slice($parts, 0, 6));
			$personCanOverrideAndEntryTime = implode(';', array_slice($parts, 6, 3));
			$savedSittings[$parameters] = $personCanOverrideAndEntryTime;
        }
    }
	
    // Seek to the end of the file for appending
    fseek($savedSittingFile, 0, SEEK_END);
	
	foreach ($filledPeopleFromTC as $filledPersonFromTC)
	{
		$parts = explode(';', $filledPersonFromTC);
		$parameters = implode(';', array_slice($parts, 0, 6));
		$personCanOverrideAndEntryTime = implode(';', array_slice($parts, 6, 3));
		$canOverride = filter_var($parts[7], FILTER_VALIDATE_BOOLEAN);
		$entryTime = DateTime::createFromFormat(DateTime::ISO8601, $parts[8]);
		// If we can not override, it's an edit from TC that takes precedence, and we should just honor it
		// If there is no existing record, we should also put the things from TC
		// For other cases, it's probably a new edit or it's the same anyway
		if (!isset($savedSittings[$parameters]))
			fwrite($savedSittingFile, $filledPersonFromTC . PHP_EOL);
		else
		{
			$existingCanOverride = filter_var(explode(';', $savedSittings[$parameters])[1], FILTER_VALIDATE_BOOLEAN);
			$existingEntryTime = DateTime::createFromFormat(DateTime::ISO8601, explode(';', $savedSittings[$parameters])[2]);
			if (($canOverride === $existingCanOverride && $entryTime > $existingEntryTime) || (!$canOverride && $existingCanOverride))
				fwrite($savedSittingFile, $filledPersonFromTC . PHP_EOL);
		}
	}
	
    // Unlock the file and close
    flock($savedSittingFile, LOCK_UN);
    fclose($savedSittingFile);
}

readFileUploadedFromTCAndTransferToSavedPeople();
?>
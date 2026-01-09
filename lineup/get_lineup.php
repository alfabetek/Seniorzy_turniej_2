<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}

$participant = $_SESSION['participant'];
$isTd = $_SESSION['isTd'];
$error = false;

// Set timeout period in seconds
$inactive = 600; // 600 seconds = 10 minutes

$session_life = time() - $_SESSION['last_activity'];
if (!$_SESSION['rememberMe'] && $session_life > $inactive) {
	// If the session has been inactive longer than the allowed period, end the session and redirect to login page
	session_destroy();
	header("Location: login.php"); // Redirect to the login page
	exit();
}
$_SESSION['last_activity'] = time(); // Update the session's last activity time

require_once "read_file_uploaded_from_TC_and_transfer_to_saved_people.php";

$lineUpData = json_decode(file_get_contents('sessionsRoundsSegmentsTables.json'), true);
$userData = json_decode(file_get_contents('users.json'), true)['UsersByUsername'];

$lastSavedPeople = [];

// Extract GET parameters
$session = $_GET['session'];
$round = $_GET['round'];
$table = $_GET['table'];
$segment = $_GET['segment'];

// Identify host and guest teams for the specified session, table, and segment
$lineUpDetails = $lineUpData['LineUpRoundsSegmentsTablesBySession'][$session]['LineUpSegmentsTablesByRound'][$round]['LineUpRoundOnTableByTable'][$table];


if (!$isTd 
	&& $participant["Number"] !== $lineUpDetails['GuestTeamNumber'] 
	&& $participant["Number"] !== $lineUpDetails['HostTeamNumber'])
{
	echo("Can't access this table!");
    exit();
}

$hostTeamNumber = $lineUpDetails['HostTeamNumber'];
$guestTeamNumber = $lineUpDetails['GuestTeamNumber'];
$segmentIndex = array_search($segment, $lineUpDetails['Segments']);
$sittingType = $lineUpDetails['SittingTypeInSegments'][$segmentIndex];
$host = findTeamByNumber($hostTeamNumber, $userData);
$guest = findTeamByNumber($guestTeamNumber, $userData);
$hostTeamName = $host['_name'];
$guestTeamName = $guest['_name'];

function getParticipant($openOrClosed, $position)
{
	global $host;
	global $guest;
	
	return ($openOrClosed === "Open" && ($position == 'N' || $position == 'S')) || ($openOrClosed === "Closed" && ($position == 'W' || $position == 'E')) ? $host : $guest;
}

function getSittingPositions($openOrClosed, $participant)
{
	global $host;
	global $guest;
	
	return ($participant["Number"] === $host["Number"] && $openOrClosed === "Open") || ($participant["Number"] === $guest["Number"] && $openOrClosed === "Closed") ? ['N', 'S'] : ['E', 'W'];
}

function loadLastSavedPeople()
{
	global $savedSittingFilename;
	global $lastSavedPeople;
	global $session;
	global $round;
	global $table;
	global $segment;
	
	foreach (['Open', 'Closed'] as $openOrClosed) 
	{
		$lastSavedPeople[$openOrClosed] = [];
		foreach (['N','S','W','E'] as $position) 
			$lastSavedPeople[$openOrClosed][$position] = array(null, true, null);
	}

	// Check if the file exists and is readable
	if (is_readable($savedSittingFilename)) {
		// Read the file line by line
		$lines = file($savedSittingFilename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

		// Loop through each line from the end
		foreach ($lines as $line) {
			// Split the line into parts
			$parts = explode(';', $line);

			// Check if the line matches our criteria
			if ($parts[0] == $session && $parts[1] == $round && $parts[2] == $table &&
				$parts[3] == $segment) {
				$openOrClosed = $parts[4];
				$position = $parts[5];
				$pid = $parts[6];
				$canOverride = filter_var($parts[7], FILTER_VALIDATE_BOOLEAN);
				$entryTime = DateTime::createFromFormat(DateTime::ISO8601, $parts[8]);

				if ($pid === "")
				{
					$lastSavedPeople[$openOrClosed][$position] = array(null, true, $entryTime);
					continue;
				}

				// $pid is not empty here
				$participant = getParticipant($openOrClosed, $position);
				
				foreach ($participant["_people"] as $person)
					if ($pid == $person["_pid"]["Number"])
					{
						$lastSavedPeople[$openOrClosed][$position] = array($person, $canOverride, $entryTime);
					}
			}
		}
	}
}

loadLastSavedPeople();

function areAllPeopleFromTeamFilledAlreadyOnTable($participant, $openOrClosed)
{
	global $lastSavedPeople;
	
	$sittingPositions = getSittingPositions($openOrClosed, $participant);
	foreach ($sittingPositions as $position) 
	{
		list($lastPersonSelected, $canOverride, $entryTime) = $lastSavedPeople[$openOrClosed][$position];
		if ($lastPersonSelected === null)
			return false;
	}
	
	return true;
}

function areAllPeopleFromTeamFilledAlready($participant)
{
	return areAllPeopleFromTeamFilledAlreadyOnTable($participant, "Open") && areAllPeopleFromTeamFilledAlreadyOnTable($participant, "Closed");
}

function checkFormWasPosted()
{
	global $participant;
	global $isTd;
	global $lineUpData;
	global $savedSittingFilename;
	global $lineUpDetails;
	global $session;
	global $round;
	global $table;
	global $segment;
	global $lastSavedPeople;
	global $error;
	global $success;
	
	// Check if the form has been submitted
	if ($_SERVER['REQUEST_METHOD'] !== 'POST') 
		return;
	
	// Retrieve data from the form submission
	$sessionPost = $_POST['session'];
	$roundPost = $_POST['round'];
	$tablePost = $_POST['table'];
	$segmentPost = $_POST['segment'];
	
	if ($session !== $sessionPost || $round !== $roundPost || $table !== $tablePost || $segment !== $segmentPost)
	{
		echo("Form details don't match requested table!");
		exit();
	}
	
	if (!$isTd && areAllPeopleFromTeamFilledAlready($participant))
	{
		echo("All players are already filled, can't change!");
		exit();
	}
	
	$file = false;
	$retries = 5;
	while (!$file && $retries > 0)
	{
		// Open the file for appending
		$file = fopen($savedSittingFilename, 'a'); // 'a' mode opens the file for writing only and places the file pointer at the end of the file
		$retries = $retries - 1;
	}
	
	// Check if the file was opened successfully
	if (!$file) {
		// File could not be opened
		echo "Error: Unable to open the file.";
		exit();
	}
	
	$peopleSitted = [];
	$linesToAddToFile = [];
	
	// Process each form field and write the data to the file
	foreach (['Open', 'Closed'] as $openOrClosed) 
	{
		$positions = $isTd ? ['N', 'S', 'W', 'E'] : getSittingPositions($openOrClosed, $participant);
		foreach ($positions as $position) 
		{
			list($lastPersonSelected, $canOverride, $entryTime) = $lastSavedPeople[$openOrClosed][$position];
			$personSelected = $_POST[$openOrClosed . '_' . $position]; // Retrieve the selected person for this position
			if ($personSelected === "null")
			{
				if ($lastPersonSelected !== null)
					array_push($peopleSitted, $lastPersonSelected);
			}
			else
			{
				$team = getParticipant($openOrClosed, $position);
				$personIsInTeam = false;
				foreach ($team["_people"] as $person)
					if ($person["_pid"]["Number"] == $personSelected)
					{
						$personIsInTeam = true;
						array_push($peopleSitted, $person);
						break;
					}
				if (!$personIsInTeam)
				{
					echo "Error! Selected person is not in a team: $personSelected!";
					exit();
				}
				
				if (!$isTd && !$canOverride)
				{
					echo "Error! Person can't be overridden!";
					exit();
				}
				
				// Format the data as a semicolon-separated string
				// The last true indicates that this can be overridden
				// We don't check the entry time, as now should be after whatever we already have
				$newCanOverride = $isTd ? "False" : "True";
				$entryTime = (new DateTime())->format(DateTime::ATOM);
				$dataString = "$session;$round;$table;$segment;$openOrClosed;$position;$personSelected;$newCanOverride;$entryTime\n";
				array_push($linesToAddToFile, $dataString);
			}
		}
	}
	
	$peopleSittedSerialized = array_map('serialize', $peopleSitted);
	if(count($peopleSittedSerialized) !== count(array_unique($peopleSittedSerialized))) {
		$error = "Sitted players are duplicated!";
		// Close the file
		fclose($file);
	} 
	else if(count($peopleSittedSerialized) !== 4 && !$isTd) 
	{
		$error = "You have to enter all players!";
		// Close the file
		fclose($file);
	}
	else
	{		
		foreach ($linesToAddToFile as $dataString)
		{
			// Write the string to the file
			fwrite($file, $dataString);
		}
		
		// Optionally, display a message to the user
		$success = "Form data saved successfully!";
		// Close the file
		fclose($file);

		// We need to reload if people were changed
		loadLastSavedPeople();
	}
}

checkFormWasPosted();

function findTeamByNumber($teamNumber, $userData) {
    foreach ($userData as $username => $user) {
        if ($user['Participant']['Number'] == $teamNumber) {
            return $user['Participant'];
        }
    }
    return "Unknown Team";
}

function createOnePlayerDropdownOrName($openOrClosed, $direction)
{
    global $host;
    global $guest;
    global $participant;
    global $isTd;
    global $sittingType;
	global $session;
	global $round;
	global $table;
	global $segment;
	global $lastSavedPeople;
	
	if (($openOrClosed === "Open" && ($direction == "N" || $direction == "S"))
		|| ($openOrClosed === "Closed" && ($direction == "W" || $direction == "E")))
	{
		$participantForDropdownOrName = $host;
	}
	else
	{
		$participantForDropdownOrName = $guest;
	}
	list($lastPersonSelected, $canOverride) = $lastSavedPeople[$openOrClosed][$direction];
	$lastPersonSelectedName = $lastPersonSelected == null ? "not filled yet" : $lastPersonSelected["_firstName"] . " " . $lastPersonSelected["_lastName"];
	
	if ($isTd || $participant["Number"] == $participantForDropdownOrName["Number"])
	{
		if (!$isTd && (!$canOverride || areAllPeopleFromTeamFilledAlready($participant)))
			return $lastPersonSelectedName;
		
		// TODO choose the one that's already choosen
		$result = "<select id='{$openOrClosed}_{$direction}' name='{$openOrClosed}_{$direction}' class='form-control mx-auto' style='max-width: 300px;'>";
		$result = $result . "<option value='null'>---</option>";
		foreach ($participantForDropdownOrName["_people"] as $person) {
			$name = $person["_firstName"] . " " . $person["_lastName"];
			$pid = $person["_pid"]["Number"];
			$selected = $lastPersonSelected !== null && $pid == $lastPersonSelected["_pid"]["Number"] ? "selected" : "";
			$result = $result . "<option value='{$pid}' $selected>{$name}</option>";
		}
		$result = $result . "</select>";
		return $result;
	}
	else
	{
		if (areAllPeopleFromTeamFilledAlready($participant))
			return $lastPersonSelectedName;
		
		switch ($sittingType)
		{
			case 0:
				return "BLIND LINE-UP";
			case 1:
				if ($participantForDropdownOrName == $guest)
					return "HOST FILLS IN FIRST";
				else
					return $lastPersonSelectedName;
			case 2:
				if ($participantForDropdownOrName == $guest)
					return $lastPersonSelectedName;
				else
					return "GUEST FILLS IN FIRST";
			case 3:
					return $lastPersonSelectedName;
		}
	}
}

function createOneTableForm($openOrClosed)
{
	echo "<div class='card' style='height: 100%;'>"; // Start of card for table section
    echo "<div class='card-header'>{$openOrClosed} Table</div>";
    echo "<div class='card-body'>";
    echo "<div class='table-responsive' style='height: 100%;'>";
    echo "<table class='table mb-0' style='height: 100%;'>";
    echo "<tbody>";
    echo "<tr class='text-center'><td colspan='3' class='align-middle'>" . createOnePlayerDropdownOrName($openOrClosed, "N") . "</td></tr>";
    echo "<tr class='text-center'><td class='align-middle'>" . createOnePlayerDropdownOrName($openOrClosed, "W") . "</td>";
    echo "<td class='align-middle'><img class='img-fluid' src='https://cdn.tournamentcalculator.com/NSWE.png' onerror='imgError(this, 'NSWE.png');' alt='Table layout' style='max-height: 140px;'></td>";
    echo "<td class='align-middle'>" . createOnePlayerDropdownOrName($openOrClosed, "E") . "</td></tr>";
    echo "<tr class='text-center'><td colspan='3' class='align-middle'>" . createOnePlayerDropdownOrName($openOrClosed, "S") . "</td></tr>";
    echo "</tbody>";
    echo "</table>";
    echo "</div>"; // End of table-responsive
    echo "</div>"; // End of card-body
    echo "</div>"; // End of card
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
	
    <script>
        window.primaryPath = "https://cdn.tournamentcalculator.com/";
        window.secondaryPath = "https://www.pzbs.pl/tc/";
    </script>

	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
	<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="../main.js"></script>
    <link rel="stylesheet" href="../main.css">
</head>
<body>

<div id="isLineUp" class="d-none"></div>

<div class="position-fixed mt-2 me-2" style="top: 0; right: 0; z-index: 1000;">
    <span class="me-2"><?php echo $_SESSION['username']; ?></span>
    <a href="logout.php" class="btn btn-primary">Logout</a>
</div>

<div class="container-fluid my-5">
    <!-- HEADERS -->
    <div class="row d-md-none">
        <div class="col-12" id="mobileHeader">
        </div>
    </div>
    <div class="row d-none d-md-flex">
        <div class="col-12" id="pcHeader">
        </div>
    </div>
	<div class="row">
		<div class="col-lg-auto d-none d-lg-block" style="max-width: 420px;">
			<?php require 'list_rounds_shared.php'; ?>
		</div>

		<div class="col">
			<a href="list_rounds.php" class="btn btn-primary mt-2 me-2 d-lg-none">Back to list</a>
			<?php
			if (isset($nextSession))
				echo "<a href='get_lineup.php?session={$nextSession}&round={$nextRound}&table={$nextTable}&segment={$nextSegment}' class='btn btn-primary mt-2'>Next segment</a>";
			?>

			<?php
			// Display host and guest information
			/*$segmentHum = $segment+1;
			echo "<h2>Session: $session, Round: $round, Table: $table, Segment: $segmentHum</h2>";
			echo "<h3>Host: $hostTeamNumber - $hostTeamName</h3>";
			echo "<h3>Guest: $guestTeamNumber - $guestTeamName</h3>";*/
			
			$segmentHum = $segment + 1;
			echo "<div class='my-4'>"; // Margin for spacing
			echo "<h2 class='text-center mb-3'>Session $session, Round $round, Table $table, Segment $segmentHum</h2>";

			echo "<div class='card'>";
			echo "<div class='card-body'>";
			echo "<div class='row justify-content-center mb-2'>";
			echo "<div class='col-sm-6 text-center'>";
			echo "<h5 class='card-title'>Host</h5>";
			echo "<p class='card-text'>$hostTeamNumber - $hostTeamName</p>";
			echo "</div>";
			echo "<div class='col-sm-6 text-center'>";
			echo "<h5 class='card-title'>Guest</h5>";
			echo "<p class='card-text'>$guestTeamNumber - $guestTeamName</p>";
			echo "</div>";
			echo "</div>"; // End row
			echo "</div>"; // End card body
			echo "</div>"; // End card
			echo "</div>"; // End container div
			?>

			<?php
			if ($error) {
				echo '<div class="alert alert-danger" role="alert">' . $error . '</div>';
			}
			?>
			<?php
			if ($success) {
				echo '<div class="alert alert-success" role="alert">' . $success . '</div>';
			}
			?>

			<form method="post" class="needs-validation" novalidate>
				<?php
				echo "<input type='hidden' name='session' value='{$session}'>";
				echo "<input type='hidden' name='round' value='{$round}'>";
				echo "<input type='hidden' name='table' value='{$table}'>";
				echo "<input type='hidden' name='segment' value='{$segment}'>";
				?>

				<div class="row">
                    <div class="col-xxl-6 mb-3">
                        <?php createOneTableForm("Open"); ?>
                    </div>
                    <div class="col-xxl-6 mb-3">
                        <?php createOneTableForm("Closed"); ?>
                    </div>
                </div>
                <button type="submit" class="btn btn-success">Submit</button>
			</form>
		</div>
	</div>
	<footer class="border-top pt-2 mt-2">
        <div class="row g-0" id="tdInfo">
            <div class="col text-end">
                <div class="d-flex h-100 flex-row-reverse">
                    <span class="align-self-center" id="tdName"></span>
                </div>
            </div>
            <div class="col-auto">
                <a class="material-icons align-middle clickable" id="tdMail">mail_outline</a>
            </div>
        </div>
        <div class="row" style="max-width: 100%">
            <div class="col-7 col-sm text-end">
                <div class="d-flex h-100 flex-row-reverse">
                    <span class="align-self-center">
                        Tournament Calculator<br>
                        S. Mączka<br>
                        v.: <span id="programVersion"></span><br>
                        gen.: <span id="generatedTime"></span><br />
                        <span id="generatedTime">Double-dummy analysis by <a href="http://bcalc.w8.pl">BCalc</a></span><br />
                        <a id="tcWebpage" href="http://tournamentcalculator.com/">tournamentcalculator.com</a>
                    </span>
                </div>
            </div>
            <div class="col-5 col-sm text-start">
                <img src="https://cdn.tournamentcalculator.com/TC.png" alt="Tournament Calculator logo" onerror="imgError(this, 'TC.png');" class="img-fluid" id="footerImg">
            </div>
        </div>
    </footer>
</div>
</body>
</html>

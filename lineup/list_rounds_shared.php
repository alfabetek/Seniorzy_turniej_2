<?php
require_once "read_file_uploaded_from_TC_and_transfer_to_saved_people.php";

$isTd = $_SESSION['isTd'];
// Get the current participant's number
$participantNumber = $isTd ? null : $_SESSION['participant']['Number'];

$currentSession = isset($session) ? $session : null;
$currentRound = isset($round) ? $round : null;
$currentTable = isset($table) ? $table : null;
$currentSegment = isset($segment) ? $segment : null;

// Returns a dictionary {<session;round;table;segment>, {position, isFilled}}
function currentlyFilledTables()
{
	global $savedSittingFilename;

	$filledPeople = [];
	
	// Check if the file exists and is readable
	if (is_readable($savedSittingFilename)) {
		// Read the file line by line
		$lines = file($savedSittingFilename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

		// Loop through each line from the end
		foreach ($lines as $line) {
			// Split the line into parts
			$parts = explode(';', $line);
			$openOrClosed = $parts[4];
			$position = $parts[5];
			$pid = $parts[6];

			$hostOrGuest = ($openOrClosed === "Open" && ($position == 'N' || $position == 'S')) || ($openOrClosed === "Closed" && ($position == 'W' || $position == 'E')) ? "host" : "guest";
			
			$sessionRoundTableSemgentHostOrGuest = implode(';', array_slice($parts, 0, 4)) . ';' . $hostOrGuest;
			
			if (!isset($filledPeople[$sessionRoundTableSemgentHostOrGuest]))
				$filledPeople[$sessionRoundTableSemgentHostOrGuest] = [];

			$key = $openOrClosed . ';' . $position;

			if ($pid === "")
			{
				unset($filledPeople[$sessionRoundTableSemgentHostOrGuest][$key]);
				continue;
			}

			$filledPeople[$sessionRoundTableSemgentHostOrGuest][$key] = true;
		}
	}

	$filledTables = [];

	foreach ($filledPeople as $key => $data)
	{
		if (count($data) === 4)
			$filledTables[$key] = true;
	}

	return $filledTables;
}

function generateList() {
    global $participantNumber, $currentSession, $currentRound, $currentTable, $currentSegment, $isTd;
    
    // Load the JSON data
    $data = json_decode(file_get_contents('sessionsRoundsSegmentsTables.json'), true);
    $filledTables = currentlyFilledTables();

    // Start the list of sessions
    echo '<div class="accordion" id="sessionAccordion">';

    foreach ($data['LineUpRoundsSegmentsTablesBySession'] as $session => $sessionInfo) {
        $sessionShown = false;
		
        foreach ($sessionInfo['LineUpSegmentsTablesByRound'] as $round => $roundInfo) {
			$roundShown = false;
            
            foreach ($roundInfo['LineUpRoundOnTableByTable'] as $table => $tableInfo) {
                if ($isTd || $participantNumber == $tableInfo['GuestTeamNumber'] || $participantNumber == $tableInfo['HostTeamNumber']) {
					if (!$sessionShown)
					{
						// Session card
						echo "<div class='card mt-2'>
								<div class='card-header' id='heading$session'>
									<h2 class='mb-0'>
										<button class='btn btn-link btn-block text-left' type='button' data-toggle='collapse' data-target='#collapse$session' aria-expanded='true' aria-controls='collapse$session'>
											<strong>Session $session</strong>
										</button>
									</h2>
								</div>
								<div id='collapse$session' class='collapse show' aria-labelledby='heading$session' data-parent='#sessionAccordion'>
									<div class='card-body p-2'>";
						$sessionShown = true;
					}
					
					if (!$roundShown)
					{
						// Round
						echo "<div class='card border-left-primary mb-2'>
								<div class='card-header'><strong>Round $round</strong></div>
								<div class='card-body p-3'>";
						$roundShown = true;
					}
				
                    // Table
                    echo "<h5 class='card-title'>Table $table</h5>";
                    echo "<div aria-label='Segment Buttons'>";
                    
                    foreach ($tableInfo['Segments'] as $index => $segment) {
                        $segmentHum = $segment + 1;
                        $isActive = ($session == $currentSession && $round == $currentRound && $table == $currentTable && $segment == $currentSegment);

						$sessionRoundTableSegment = $session . ';' . $round . ';' . $table . ';' . $segment . ';';
						if ($isTd) {
							$isFilled = isset($filledTables[$sessionRoundTableSegment . 'host']) && isset($filledTables[$sessionRoundTableSegment . 'guest']);
						}
						else
						{
							$hostOrGuest = $participantNumber == $tableInfo['HostTeamNumber'] ? 'host' : 'guest';
							$isFilled = isset($filledTables[$sessionRoundTableSegment . $hostOrGuest]);
						}

                        $btnClass = $isActive ? 'btn-primary' : ($isFilled ? 'btn-success' : 'btn-secondary');
                        $text = "Segment $segmentHum";
                        
                        echo "<a href='get_lineup.php?session=$session&round=$round&table=$table&segment=$segment' class='btn $btnClass m-1'>$text</a>";
                    }

                    echo "</div>"; // Close button group
                }
            }
			if ($roundShown)
				echo "</div></div>"; // Close card-body and card
        }
		if ($sessionShown)
			echo "</div></div></div>"; // Close card-body, collapse, and card
    }
    echo '</div>'; // Close the accordion
}

generateList();
?>
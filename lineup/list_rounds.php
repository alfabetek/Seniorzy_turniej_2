<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}

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

<div class="container mt-5">
    <!-- HEADERS -->
    <div class="row d-md-none">
        <div class="col-12" id="mobileHeader">
        </div>
    </div>
    <div class="row d-none d-md-flex">
        <div class="col-12" id="pcHeader">
        </div>
    </div>
    <?php require 'list_rounds_shared.php'; ?>
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

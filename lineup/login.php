<?php
session_start();

// Load user data
$userData = json_decode(file_get_contents('users.json'), true)["UsersByUsername"];

if (isset($_SESSION['username'])) {
	header('Location: list_rounds.php');
	exit();
}

$error = ''; // Initialize error message

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
	$username = $_POST['username'];
	$password = $_POST['password'];
    $rememberMe = isset($_POST['rememberMe']);

	if (isset($userData[$username]))
	{
		$user = $userData[$username];
		if (password_verify($password, $user['PasswordHash'])) {
			// Authentication successful
			$_SESSION['username'] = $username;
			$_SESSION['isTd'] = $user['IsTdAccount'];
			$_SESSION['participant'] = $user['Participant'];
			$_SESSION['last_activity'] = time(); // Update the session's last activity time
            $_SESSION['rememberMe'] = $rememberMe;
					
			header('Location: list_rounds.php');
			exit();
        } else {
            $error = 'Invalid username or password';
        }
    } else {
        $error = 'Invalid username or password';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
	<style>
        .center-screen {
            min-height: 100vh; /* Fallback for browsers that do not support Custom Properties */
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .auth-wrapper {
            width: 100%;
            max-width: 400px;
            padding: 20px;
            background: #f7f7f7;
            border-radius: 15px;
        }
    </style>

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
<body class="d-flex flex-column min-vh-100">

<div id="isLineUp" class="d-none"></div>

<!-- HEADERS -->
<div class="container">
    <div class="row d-md-none">
        <div class="col-12" id="mobileHeader">
        </div>
    </div>
    <div class="row d-none d-md-flex">
        <div class="col-12" id="pcHeader">
        </div>
    </div>
</div>
<main class="container my-auto py-3">
    <div class="row justify-content-center">
        <div class="auth-wrapper" style="max-width: 300px;">
		    <?php
		    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
			    if ($error) {
				    echo '<div class="alert alert-danger" role="alert">' . $error . '</div>';
			    }
		    }
		    ?>

		    <form method="post">
			    <div class="form-group">
				    <label for="username">Username</label>
				    <input type="text" class="form-control" id="username" name="username" autocapitalize="none">
			    </div>
			    <div class="form-group">
				    <label for="password">Password</label>
				    <input type="password" class="form-control" id="password" name="password" autocapitalize="none">
			    </div>
	            <div class="form-group d-flex justify-content-between mt-3">
                    <div class="custom-control custom-checkbox align-self-center">
                        <input type="checkbox" class="custom-control-input" id="rememberMe" name="rememberMe">
                        <label class="custom-control-label" for="rememberMe">Remember me</label>
                    </div>
                    <button type="submit" class="btn btn-primary">Login</button>
                </div>
		    </form>
        </div>
    </div>
</main>
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
</body>
</html>

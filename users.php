<?php
header("Content-Type: application/json");
require_once('../server.php'); // Adjust the path to server.php as needed

// Helper function to send JSON response
function sendResponse($status, $data) {
	http_response_code($status);
	echo json_encode($data);
	exit();
}

// Parse the request URI
$request_uri = $_SERVER['REQUEST_URI'];

// Remove query string
$request_uri = strtok($request_uri, '?');
$base_path = '/api/users.php';
$endpoint = '';

// Extract the endpoint
if (strpos($request_uri, $base_path) === 0) {
	$endpoint = str_replace($base_path, '', $request_uri); // Extract anything after /api/user.php
	$endpoint = trim($endpoint, '/'); // Remove leading or trailing slashes
}

// Get all users
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $endpoint === '') {
	$query = "SELECT * FROM user";
	$result = mysqli_query($db, $query);

	if ($result && mysqli_num_rows($result) > 0) {
		$coupons = mysqli_fetch_all($result, MYSQLI_ASSOC);
		sendResponse(200, $coupons);
	} else {
		sendResponse(404, ['error' => 'No coupons found']);
	}
}

// Handle POST request for user login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $endpoint === 'login') {
	$input = json_decode(file_get_contents('php://input'), true);

	if (empty($input['email']) || empty($input['password'])) {
		sendResponse(400, ['error' => 'Email and password are required']);
	}

	$email = mysqli_real_escape_string($db, $input['email']);
	$password = mysqli_real_escape_string($db, $input['password']);

	$query = "
        SELECT m.id, u.name, u.email, u.status, u.type, u.theme, mp.photo, m.coins
        FROM `user` u
		LEFT JOIN `member` m
		ON u.email = m.email
        LEFT JOIN `memberphoto` mp
        ON m.id = mp.m_id
        WHERE u.password='$password' AND u.email='$email' AND u.status='1';
    ";

	$results = mysqli_query($db, $query);

	if (mysqli_num_rows($results) == 1) {
		$row = mysqli_fetch_assoc($results);
		$user_id = $row['id'];
		$userName = $row['name'];
		$email = $row['email'];
		$coins = $row['coins'];
		$profile_picture = $row['photo'] != '' ? $row['photo'] : 'assets/img/faces/blacklogo.png';
		$theme = $row['theme'];
		$type = $row['type'];

		sendResponse(200, [
			'user_id' => $user_id,
			'username' => $userName,
			'email' => $email,
			'coins' => $coins,
			'profile_picture' => $profile_picture,
			'theme' => $theme,
			'type' => $type,
			'message' => 'Login successful'
		]);
	} else {
		sendResponse(401, ['error' => 'Wrong username/password combination']);
	}
}

// Handle POST request for adding a member
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $endpoint === 'addMember') {
	$input = json_decode(file_get_contents('php://input'), true);

	$required_fields = [
		'name', 'email', 'lastName', 'Tussenvoegsel', 'address', 'houseNumber', 'residence', 'telephone', 'mobile',
		'dateOfBirth', 'agreementStartDate', 'agreementEndDate', 'cName', 'cRelation', 'cTelephone', 'type', 'note'
	];

	$errors = [];
	foreach ($required_fields as $field) {
		if (empty($input[$field])) {
			$errors[] = ucfirst($field) . ' is required';
		}
	}

	if (!empty($errors)) {
		sendResponse(400, ['errors' => $errors]);
	}

	$email = mysqli_real_escape_string($db, $input['email']);
	$query = "SELECT * FROM member WHERE email='$email'";
	$results = mysqli_query($db, $query);

	if (mysqli_num_rows($results) == 1) {
		sendResponse(400, ['error' => 'Duplicate entry for ' . $email]);
	}

	$name = mysqli_real_escape_string($db, $input['name']);
	$lastName = mysqli_real_escape_string($db, $input['lastName']);
	$Tussenvoegsel = mysqli_real_escape_string($db, $input['Tussenvoegsel']);
	$address = mysqli_real_escape_string($db, $input['address']);
	$houseNumber = mysqli_real_escape_string($db, $input['houseNumber']);
	$residence = mysqli_real_escape_string($db, $input['residence']);
	$telephone = mysqli_real_escape_string($db, $input['telephone']);
	$mobile = mysqli_real_escape_string($db, $input['mobile']);
	$dateOfBirth = mysqli_real_escape_string($db, $input['dateOfBirth']);
	$agreementStartDate = mysqli_real_escape_string($db, $input['agreementStartDate']);
	$agreementEndDate = mysqli_real_escape_string($db, $input['agreementEndDate']);
	$cName = mysqli_real_escape_string($db, $input['cName']);
	$cRelation = mysqli_real_escape_string($db, $input['cRelation']);
	$cTelephone = mysqli_real_escape_string($db, $input['cTelephone']);
	$type = mysqli_real_escape_string($db, $input['type']);
	$note = mysqli_real_escape_string($db, $input['note']);

	$query2 = "INSERT INTO `member`(`name`, `lastName`, `Tussenvoegsel`, `dateOfBirth`, `address`, `houseNumber`, `residence`, `telephone`, `mobile`,
                `email`, `agreementStartDate`, `agreementEndDate`, `cName`, `cRelation`, `cTelephone`, `note`, `type`) 
                VALUES ('$name','$lastName','$Tussenvoegsel','$dateOfBirth','$address','$houseNumber','$residence','$telephone','$mobile',
                '$email','$agreementStartDate','$agreementEndDate','$cName','$cRelation','$cTelephone','$note','$type')";

	$query1 = mysqli_query($db, $query2);

	if ($query1) {
		$password = "velitt@2022";
		$query3 = "INSERT INTO `user`(name, `email`, `password`, type) 
                   VALUES ('$name', '$email', '$password', '$type')";
		$query4 = mysqli_query($db, $query3);

		if ($query4) {
			// Include the email sending logic here if needed
			sendResponse(201, ['message' => 'Member added successfully and email has been sent to ' . $email]);
		} else {
			sendResponse(500, ['error' => 'Failed to create user']);
		}
	} else {
		sendResponse(500, ['error' => 'Failed to add member']);
	}
}

// If no valid endpoint is matched
sendResponse(404, ['error' => 'Invalid endpoint: ' . $request_uri]);
?>

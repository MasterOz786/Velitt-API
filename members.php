<?php
header("Content-Type: application/json");
require_once('../server.php');

// Helper function to send JSON response
function sendResponse($status, $data) {
	http_response_code($status);
	echo json_encode($data);
	exit();
}

// Helper function to validate member access
//function validateMemberAccess($db, $memberId) {
//	if (!isset($_SESSION['username']) || $_SESSION['type'] != "4") {
//		sendResponse(403, ['error' => 'Unauthorized access']);
//	}
//
//	$email = $_SESSION['email'];
//	$query = "SELECT id FROM member WHERE email = ? AND id = ?";
//	$stmt = mysqli_prepare($db, $query);
//	mysqli_stmt_bind_param($stmt, 'si', $email, $memberId);
//	mysqli_stmt_execute($stmt);
//	$result = mysqli_stmt_get_result($stmt);
//
//	if (!$result || mysqli_num_rows($result) === 0) {
//		sendResponse(403, ['error' => 'Unauthorized access']);
//	}
//}

// Parse the request URI
$request_uri = $_SERVER['REQUEST_URI'];
$request_uri = strtok($request_uri, '?');
$base_path = '/api/members.php';
$endpoint = '';

if (strpos($request_uri, $base_path) === 0) {
	$endpoint = str_replace($base_path, '', $request_uri);
	$endpoint = trim($endpoint, '/');
}

// GET request to fetch all parameters for a member
if ($_SERVER['REQUEST_METHOD'] === 'GET' && preg_match('/^parameters\/([0-9]+)$/', $endpoint, $matches)) {
	$memberId = intval($matches[1]);
//	validateMemberAccess($db, $memberId);

	$parameters = [
		'bodyWeight' => [],
		'height' => [],
		'BMI' => [],
		'bodyFat' => [],
		'FFBWeight' => [],
		'bodyWater' => [],
		'protein' => [],
		'muscleMass' => [],
		'waist_circumference' => [],
		'blood_pressure' => [],
		'pulse' => [],
		'glucose' => []
	];

	$query = "SELECT * FROM memberparameters WHERE memberId = ? ORDER BY date";
	$stmt = mysqli_prepare($db, $query);
	mysqli_stmt_bind_param($stmt, 'i', $memberId);
	mysqli_stmt_execute($stmt);
	$result = mysqli_stmt_get_result($stmt);

	if ($result) {
		while ($row = mysqli_fetch_assoc($result)) {
			foreach ($parameters as $key => &$data) {
				if (isset($row[$key])) {
					$data[] = [
						'date' => $row['date'],
						'value' => $key === 'blood_pressure' ?
							explode('/', $row[$key]) :
							floatval($row[$key])
					];
				}
			}
		}
		sendResponse(200, $parameters);
	} else {
		sendResponse(500, ['error' => 'Failed to fetch member parameters']);
	}
}

// POST request to add new parameters
if ($_SERVER['REQUEST_METHOD'] === 'POST' && preg_match('/^parameters\/([0-9]+)$/', $endpoint, $matches)) {
	$memberId = intval($matches[1]);
	validateMemberAccess($db, $memberId);

	$input = json_decode(file_get_contents('php://input'), true);

	if (!isset($input['date'])) {
		sendResponse(400, ['error' => 'Date is required']);
	}

	$allowedFields = [
		'bodyWeight', 'height', 'BMI', 'bodyFat', 'FFBWeight',
		'bodyWater', 'protein', 'muscleMass', 'waist_circumference',
		'blood_pressure', 'pulse', 'glucose'
	];

	$fields = [];
	$values = [];
	$types = '';
	$params = [];

	foreach ($allowedFields as $field) {
		if (isset($input[$field])) {
			$fields[] = $field;
			$values[] = '?';
			$types .= 's';
			$params[] = $input[$field];
		}
	}

	if (empty($fields)) {
		sendResponse(400, ['error' => 'No valid parameters provided']);
	}

	$fields[] = 'memberId';
	$fields[] = 'date';
	$values[] = '?';
	$values[] = '?';
	$types .= 'is';
	array_push($params, $memberId, $input['date']);

	$query = "INSERT INTO memberparameters (" . implode(', ', $fields) . ") 
              VALUES (" . implode(', ', $values) . ")";

	$stmt = mysqli_prepare($db, $query);
	mysqli_stmt_bind_param($stmt, $types, ...$params);

	if (mysqli_stmt_execute($stmt)) {
		sendResponse(201, ['message' => 'Parameters added successfully']);
	} else {
		sendResponse(500, ['error' => 'Failed to add parameters']);
	}
}

// PUT request to update parameters
if ($_SERVER['REQUEST_METHOD'] === 'PUT' && preg_match('/^parameters\/([0-9]+)\/([0-9-]+)$/', $endpoint, $matches)) {
	$memberId = intval($matches[1]);
	$date = $matches[2];
	validateMemberAccess($db, $memberId);

	$input = json_decode(file_get_contents('php://input'), true);

	$allowedFields = [
		'bodyWeight', 'height', 'BMI', 'bodyFat', 'FFBWeight',
		'bodyWater', 'protein', 'muscleMass', 'waist_circumference',
		'blood_pressure', 'pulse', 'glucose'
	];

	$updates = [];
	$types = '';
	$params = [];

	foreach ($allowedFields as $field) {
		if (isset($input[$field])) {
			$updates[] = "$field = ?";
			$types .= 's';
			$params[] = $input[$field];
		}
	}

	if (empty($updates)) {
		sendResponse(400, ['error' => 'No valid parameters provided']);
	}

	$types .= 'is';
	array_push($params, $memberId, $date);

	$query = "UPDATE memberparameters SET " . implode(', ', $updates) .
		" WHERE memberId = ? AND date = ?";

	$stmt = mysqli_prepare($db, $query);
	mysqli_stmt_bind_param($stmt, $types, ...$params);

	if (mysqli_stmt_execute($stmt)) {
		sendResponse(200, ['message' => 'Parameters updated successfully']);
	} else {
		sendResponse(500, ['error' => 'Failed to update parameters']);
	}
}

// DELETE request to remove parameters
if ($_SERVER['REQUEST_METHOD'] === 'DELETE' && preg_match('/^parameters\/([0-9]+)\/([0-9-]+)$/', $endpoint, $matches)) {
	$memberId = intval($matches[1]);
	$date = $matches[2];
	validateMemberAccess($db, $memberId);

	$query = "DELETE FROM memberparameters WHERE memberId = ? AND date = ?";
	$stmt = mysqli_prepare($db, $query);
	mysqli_stmt_bind_param($stmt, 'is', $memberId, $date);

	if (mysqli_stmt_execute($stmt)) {
		sendResponse(200, ['message' => 'Parameters deleted successfully']);
	} else {
		sendResponse(500, ['error' => 'Failed to delete parameters']);
	}
}

// If no valid endpoint is matched
sendResponse(404, ['error' => 'Invalid endpoint']);
?>


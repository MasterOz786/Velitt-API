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
$request_uri = strtok($request_uri, '?'); // Remove query string

// Extract the endpoint
$endpoint = str_replace('/api/training-members.php/', '', $request_uri);

// Handle GET request to fetch members for a training session
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $endpoint === 'get_members') {
	if (!isset($_GET['training_id'])) {
		sendResponse(400, ['error' => 'training_id is required']);
	}

	$trainingId = intval($_GET['training_id']);

	// Fetch members for the training session
	$query = "SELECT member.id, CONCAT(member.name, ' ', member.lastName) AS name, trainingmember.attended 
              FROM trainingmember 
              INNER JOIN member ON trainingmember.member_id = member.id 
              WHERE trainingmember.TM_TL_id = '$trainingId'";

	$result = mysqli_query($db, $query);

	if (!$result) {
		sendResponse(500, ['error' => 'Failed to fetch members']);
	}

	$members = [];
	while ($row = mysqli_fetch_assoc($result)) {
		$members[] = $row;
	}

	sendResponse(200, $members);
}

// Handle POST request to award coins to a member
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $endpoint === 'award-coins') {
	$input = json_decode(file_get_contents('php://input'), true);

	if (!isset($input['member_id']) || !isset($input['training_id'])) {
		sendResponse(400, ['error' => 'member_id and training_id are required']);
	}

	$memberId = intval($input['member_id']);
	$trainingId = intval($input['training_id']);

	// Start a transaction to ensure both updates succeed or fail together
	mysqli_begin_transaction($db);

	try {
		// Update the member's coin balance
		$updateCoinsQuery = "UPDATE member SET coins = coins + 1 WHERE id = ?";
		$stmtCoins = mysqli_prepare($db, $updateCoinsQuery);
		mysqli_stmt_bind_param($stmtCoins, 'i', $memberId);

		if (!mysqli_stmt_execute($stmtCoins)) {
			throw new Exception("Error updating coins.");
		}

		// Mark the member as attended in the trainingmember table
		$updateAttendanceQuery = "UPDATE trainingmember SET attended = 1 WHERE member_id = ? AND TM_TL_id = ?";
		$stmtAttendance = mysqli_prepare($db, $updateAttendanceQuery);
		mysqli_stmt_bind_param($stmtAttendance, 'ii', $memberId, $trainingId);

		if (!mysqli_stmt_execute($stmtAttendance)) {
			throw new Exception("Error updating attendance.");
		}

		// Commit the transaction
		mysqli_commit($db);

		// Return success response
		sendResponse(200, ['success' => true, 'message' => 'Coins rewarded successfully']);
	} catch (Exception $e) {
		// Rollback the transaction on error
		mysqli_rollback($db);
		sendResponse(500, ['error' => $e->getMessage()]);
	}
}

// If no valid endpoint is matched
sendResponse(404, ['error' => 'Invalid endpoint: ' . $endpoint]);
?>
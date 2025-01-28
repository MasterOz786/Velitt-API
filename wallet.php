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

// Remove query string from the URI
$request_uri = strtok($request_uri, '?');

// Extract the endpoint
$endpoint = str_replace('/api/wallet.php/', '', $request_uri);

// Handle GET request to fetch wallet balance
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $endpoint === 'balance') {
	// Get member_id from query parameters
	if (!isset($_GET['member_id'])) {
		sendResponse(400, ['error' => 'member_id is required']);
	}

	$member_id = intval($_GET['member_id']);

	// Use member_id to fetch the balance
	$query = "SELECT coins FROM member WHERE id = '$member_id'";
	$result = mysqli_query($db, $query);

	if ($result && mysqli_num_rows($result) > 0) {
		$row = mysqli_fetch_assoc($result);
		sendResponse(200, ['balance' => $row['coins'] * 1.5]);
	} else {
		sendResponse(404, ['error' => 'User not found']);
	}
}

// Handle GET request to fetch wallet history
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $endpoint === 'history') {
	// Get member_id from query parameters
	if (!isset($_GET['member_id'])) {
		sendResponse(400, ['error' => 'member_id is required']);
	}

	$member_id = intval($_GET['member_id']);

	// Fetch wallet history from the database
	$query = "
        SELECT 
            w.number_of_coins AS coins,
            DATE(w.created_at) AS date,
            TIME(w.created_at) AS time,
            e.evt_text AS event
        FROM 
            wallet w
        LEFT JOIN 
            events e ON w.event_id = e.evt_id
        WHERE 
            w.user_id = '$member_id'
        ORDER BY 
            w.created_at DESC;
    ";
	$result = mysqli_query($db, $query);

	if ($result && mysqli_num_rows($result) > 0) {
		$transactions = [];
		while ($row = mysqli_fetch_assoc($result)) {
			$transactions[] = $row;
		}
		sendResponse(200, ['transactions' => $transactions]);
	} else {
		sendResponse(404, ['error' => 'No transactions found']);
	}
}

// Handle POST request to redeem coins
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $endpoint === 'redeem') {
	// Get input data from the request body
	$input = json_decode(file_get_contents('php://input'), true);

	// Validate required parameters
	if (!isset($input['member_id']) || !isset($input['coupon_id'])) {
		sendResponse(400, ['error' => 'member_id and coupon_id are required']);
	}

	$member_id = intval($input['member_id']);
	$coupon_id = intval($input['coupon_id']);

	// Insert redemption request into the database
	$query = "INSERT INTO coupon_requests (member_id, coupon_id, status) 
              VALUES ('$member_id', '$coupon_id', 'pending')";
	$result = mysqli_query($db, $query);

	if (!$result) {
		sendResponse(500, ['error' => 'Failed to submit redemption request']);
	} else {
		sendResponse(200, ['message' => 'Redemption request submitted successfully']);
	}
}

// If no valid endpoint is matched
sendResponse(404, ['error' => 'Invalid endpoint: ' . $endpoint]);
?>
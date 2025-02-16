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
$base_path = '/api/coupons.php';
$endpoint = '';

// Extract the endpoint
if (strpos($request_uri, $base_path) === 0) {
	$endpoint = str_replace($base_path, '', $request_uri); // Extract anything after /api/coupons.php
	$endpoint = trim($endpoint, '/'); // Remove leading or trailing slashes
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $endpoint === '') {
	$query = "SELECT * FROM coupons";
	$result = mysqli_query($db, $query);

	if ($result && mysqli_num_rows($result) > 0) {
		$coupons = mysqli_fetch_all($result, MYSQLI_ASSOC);
		sendResponse(200, $coupons);
	} else {
		sendResponse(404, ['error' => 'No coupons found']);
	}
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && preg_match('/^([0-9]+)$/', $endpoint, $matches)) {
	$coupon_id = intval($matches[1]);

	$query = "SELECT * FROM coupons WHERE id = ?";
	$stmt = mysqli_prepare($db, $query);
	mysqli_stmt_bind_param($stmt, 'i', $coupon_id);
	mysqli_stmt_execute($stmt);
	$result = mysqli_stmt_get_result($stmt);

	if ($result && mysqli_num_rows($result) > 0) {
		$coupon = mysqli_fetch_assoc($result);
		sendResponse(200, $coupon);
	} else {
		sendResponse(404, ['error' => 'Coupon not found']);
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

// Handle POST request to update coupon status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $endpoint === 'update-status') {
	$input = json_decode(file_get_contents('php://input'), true);

	if (empty($input['coupon_id']) || empty($input['new_status'])) {
		sendResponse(400, ['error' => 'coupon_id and new_status are required']);
	}

	$coupon_id = intval($input['coupon_id']);
	$new_status = $input['new_status'];
	$allowed_statuses = ['pending', 'approved', 'rejected'];

	if (!in_array($new_status, $allowed_statuses)) {
		sendResponse(400, ['error' => 'Invalid status']);
	}

	$query = "UPDATE coupon_requests SET status = ? WHERE id = ?";
	$stmt = mysqli_prepare($db, $query);
	mysqli_stmt_bind_param($stmt, 'si', $new_status, $coupon_id);
	$result = mysqli_stmt_execute($stmt);

	if ($result) {
		sendResponse(200, ['message' => 'Coupon status updated successfully']);
	} else {
		sendResponse(500, ['error' => 'Failed to update coupon status']);
	}
}

// Handle POST requests to redeem coupons
if ($_SERVER['REQUEST_METHOD'] === 'POST' && preg_match('/^redeem\/([a-z-]+)$/', $endpoint, $matches)) {
	$redeem_type = $matches[1];
	$input = json_decode(file_get_contents('php://input'), true);

	if (empty($input['coupon_id']) || empty($input['member_id'])) {
		sendResponse(400, ['error' => 'coupon_id and member_id are required']);
	}

	$coupon_id = intval($input['coupon_id']);
	$member_id = intval($input['member_id']);

	// Build query based on redeem type
	$columns = ['member_id' => $member_id, 'coupon_id' => $coupon_id, 'status' => 'pending'];

	switch ($redeem_type) {
		case 'phone':
			if (empty($input['phone'])) sendResponse(400, ['error' => 'phone is required']);
			$columns['phone'] = $input['phone'];
			break;
		case 'bank':
			if (empty($input['bank_name']) || empty($input['account_number']) || empty($input['account_name'])) {
				sendResponse(400, ['error' => 'bank_name, account_number, and account_name are required']);
			}
			$columns['bank_name'] = $input['bank_name'];
			$columns['account_number'] = $input['account_number'];
			$columns['account_name'] = $input['account_name'];
			break;
		case 'email':
			if (empty($input['email'])) sendResponse(400, ['error' => 'email is required']);
			$columns['email'] = $input['email'];
			break;
		case 'gift-card':
			if (empty($input['gift_card_number']) || empty($input['gift_card_pin'])) {
				sendResponse(400, ['error' => 'gift_card_number and gift_card_pin are required']);
			}
			$columns['gift_card_number'] = $input['gift_card_number'];
			$columns['gift_card_pin'] = $input['gift_card_pin'];
			break;
		default:
			sendResponse(400, ['error' => 'Invalid redeem type']);
	}

	// Prepare the SQL query
	$keys = implode(", ", array_keys($columns));
	$placeholders = implode(", ", array_fill(0, count($columns), '?'));
	$query = "INSERT INTO coupon_requests ($keys) VALUES ($placeholders)";
	$stmt = mysqli_prepare($db, $query);

	// Bind parameters dynamically
	$types = str_repeat('s', count($columns)); // Assuming all are strings
	$params = array_merge([$types], array_values($columns));
	mysqli_stmt_bind_param($stmt, ...$params);

	if (mysqli_stmt_execute($stmt)) {
		sendResponse(200, ['message' => 'Coupon request submitted successfully']);
	} else {
		sendResponse(500, ['error' => 'Failed to submit coupon request']);
	}
}

// Handle DELETE request to delete a coupon
if ($_SERVER['REQUEST_METHOD'] === 'DELETE' && preg_match('/^deleteCoupon\/([0-9]+)$/', $endpoint, $matches)) {
	$couponId = intval($matches[1]);

	$query = "DELETE FROM coupons WHERE id = '$couponId'";
	$result = mysqli_query($db, $query);

	if ($result) {
		sendResponse(200, ['message' => 'Coupon deleted successfully']);
	} else {
		sendResponse(500, ['error' => 'Failed to delete coupon']);
	}
}

// Handle POST request to create a new coupon
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $endpoint === 'addCoupon') {
	$input = json_decode(file_get_contents('php://input'), true);

	if (!isset($input['coupon_name'], $input['description'], $input['value'], $input['type'], $input['created_by'])) {
		sendResponse(400, ['error' => 'All fields are required']);
	}

	$name = mysqli_real_escape_string($db, $input['coupon_name']);
	$description = mysqli_real_escape_string($db, $input['description']);
	$value = mysqli_real_escape_string($db, $input['value']);
	$type = mysqli_real_escape_string($db, $input['type']);
	$created_by = mysqli_real_escape_string($db, $input['created_by']);
	$picture = isset($input['picture']) ? mysqli_real_escape_string($db, $input['picture']) : null;

	$query = "INSERT INTO coupons (name, description, picture, value, type, created_by) 
              VALUES ('$name', '$description', '$picture', '$value', '$type', '$created_by')";
	$result = mysqli_query($db, $query);

	if ($result) {
		sendResponse(201, ['message' => 'Coupon created successfully']);
	} else {
		sendResponse(500, ['error' => 'Failed to create coupon']);
	}
}

// Handle PUT request to update a coupon
if ($_SERVER['REQUEST_METHOD'] === 'PUT' && preg_match('/^updateCoupon\/([0-9]+)$/', $endpoint, $matches)) {
	$couponId = intval($matches[1]);
	$input = json_decode(file_get_contents('php://input'), true);

	if (!isset($input['coupon_name'], $input['description'], $input['value'], $input['type'])) {
		sendResponse(400, ['error' => 'All fields are required']);
	}

	$name = mysqli_real_escape_string($db, $input['coupon_name']);
	$description = mysqli_real_escape_string($db, $input['description']);
	$value = mysqli_real_escape_string($db, $input['value']);
	$type = mysqli_real_escape_string($db, $input['type']);
	$picture = isset($input['picture']) ? mysqli_real_escape_string($db, $input['picture']) : null;

	$query = "UPDATE coupons SET 
              name = '$name', 
              description = '$description', 
              picture = '$picture', 
              value = '$value', 
              type = '$type' 
              WHERE id = '$couponId'";
	$result = mysqli_query($db, $query);

	if ($result) {
		sendResponse(200, ['message' => 'Coupon updated successfully']);
	} else {
		sendResponse(500, ['error' => 'Failed to update coupon']);
	}
}

// If no valid endpoint is matched
sendResponse(404, ['error' => 'Invalid endpoint: ' . $endpoint]);
?>
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
            w.member_id = '$member_id'
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

// Handle GET request to fetch redemptions history
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $endpoint === 'redemptions') {
	// Get member_id from query parameters
	if (!isset($_GET['member_id'])) {
		sendResponse(400, ['error' => 'member_id is required']);
	}

	$member_id = intval($_GET['member_id']);

	// Query to fetch redemptions history by joining coupon_requests and coupons tables.
	$query = "
        SELECT 
            m.name AS name,
            c.name AS coupon,
            DATE(cr.created_at) AS date,
            TIME(cr.created_at) AS time,
            cr.status AS status
        FROM
            coupon_requests cr
        INNER JOIN 
            coupons c ON cr.coupon_id = c.id
        INNER JOIN
		    member m on cr.member_id = m.id
        WHERE 
            cr.member_id = '$member_id'
        ORDER BY 
            cr.created_at DESC;
    ";
	$result = mysqli_query($db, $query);

	if ($result && mysqli_num_rows($result) > 0) {
		$redemptions = [];
		while ($row = mysqli_fetch_assoc($result)) {
			$redemptions[] = $row;
		}
		sendResponse(200, ['redemptions' => $redemptions]);
	} else {
		sendResponse(404, ['error' => 'No redemptions found']);
	}
}

// PUT request to update member balance
if ($_SERVER['REQUEST_METHOD'] === 'PUT' && preg_match('/^balance\/([0-9]+)$/', $endpoint, $matches)) {
    $memberId = intval($matches[1]);
    // validateMemberAccess($db, $memberId);
    
    $input = json_decode(file_get_contents('php://input'), true);
    if (!isset($input['coins'])) {
        sendResponse(400, ['error' => 'Coins amount is required']);
    }
    
    $query = "UPDATE member SET coins = ? WHERE id = ?";
    $stmt = mysqli_prepare($db, $query);
    // Assuming balance is a decimal value and member id is an integer.
    mysqli_stmt_bind_param($stmt, 'di', $input['coins'], $memberId);
    
    if (mysqli_stmt_execute($stmt)) {
        sendResponse(200, ['message' => 'Coins updated successfully']);
    } else {
        sendResponse(500, ['error' => 'Failed to update coins']);
    }
}

// If no valid endpoint is matched
sendResponse(404, ['error' => 'Invalid endpoint: ' . $endpoint]);
?>

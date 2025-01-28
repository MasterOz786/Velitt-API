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
$endpoint = str_replace('/api/profile.php/', '', $request_uri);

// Handle GET request to fetch profile data
if ($_SERVER['REQUEST_METHOD'] === 'GET' && preg_match('/^get_profile\/([0-9]+)\/([a-z]+)$/', $endpoint, $matches)) {
	$id = intval($matches[1]);
	$module = $matches[2]; // 'member', 'coach', or 'equipment'
	$table = strtolower($module);
	$tablecontracts = $table . "contracts";

	// Fetch profile data
	$query = "SELECT * FROM $module LEFT JOIN ${table}photo ON id = m_id WHERE id = ?";
	$stmt = mysqli_prepare($db, $query);
	mysqli_stmt_bind_param($stmt, 'i', $id);
	mysqli_stmt_execute($stmt);
	$result = mysqli_stmt_get_result($stmt);

	if ($result && mysqli_num_rows($result) > 0) {
		$profile = mysqli_fetch_assoc($result);
		sendResponse(200, $profile);
	} else {
		sendResponse(404, ['error' => 'Profile not found']);
	}
}

// Handle POST request to update profile data
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $endpoint === 'update_profile') {
	$input = json_decode(file_get_contents('php://input'), true);

	if (!isset($input['id'], $input['module'], $input['data'])) {
		sendResponse(400, ['error' => 'id, module, and data are required']);
	}

	$id = intval($input['id']);
	$module = $input['module']; // 'member', 'coach', or 'equipment'
	$data = $input['data']; // Array of fields to update

	// Build the SQL query
	$table = strtolower($module);
	$updates = [];
	foreach ($data as $key => $value) {
		$updates[] = "$key = '" . mysqli_real_escape_string($db, $value) . "'";
	}
	$updates = implode(', ', $updates);

	$query = "UPDATE $table SET $updates WHERE id = ?";
	$stmt = mysqli_prepare($db, $query);
	mysqli_stmt_bind_param($stmt, 'i', $id);

	if (mysqli_stmt_execute($stmt)) {
		sendResponse(200, ['message' => 'Profile updated successfully']);
	} else {
		sendResponse(500, ['error' => 'Failed to update profile']);
	}
}

// Handle POST request to upload profile picture
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $endpoint === 'upload_profile_picture') {
	if (!isset($_FILES['picture']) || !isset($_POST['id']) || !isset($_POST['module'])) {
		sendResponse(400, ['error' => 'picture, id, and module are required']);
	}

	$id = intval($_POST['id']);
	$module = $_POST['module']; // 'member', 'coach', or 'equipment'
	$table = strtolower($module);

	// Handle file upload
	$target_dir = "uploads/$table/";
	$target_file = $target_dir . basename($_FILES["picture"]["name"]);

	if (move_uploaded_file($_FILES["picture"]["tmp_name"], $target_file)) {
		// Update the profile picture in the database
		$query = "UPDATE ${table}photo SET photo = ? WHERE m_id = ?";
		$stmt = mysqli_prepare($db, $query);
		mysqli_stmt_bind_param($stmt, 'si', $target_file, $id);

		if (mysqli_stmt_execute($stmt)) {
			sendResponse(200, ['message' => 'Profile picture uploaded successfully']);
		} else {
			sendResponse(500, ['error' => 'Failed to update profile picture']);
		}
	} else {
		sendResponse(500, ['error' => 'Failed to upload file']);
	}
}

// Handle GET request to fetch documents
if ($_SERVER['REQUEST_METHOD'] === 'GET' && preg_match('/^get_documents\/([0-9]+)\/([a-z]+)$/', $endpoint, $matches)) {
	$id = intval($matches[1]);
	$module = $matches[2]; // 'member', 'coach', or 'equipment'
	$tablecontracts = $module . "contracts";

	// Fetch documents
	$query = "SELECT * FROM $tablecontracts WHERE ${module}Id = ?";
	$stmt = mysqli_prepare($db, $query);
	mysqli_stmt_bind_param($stmt, 'i', $id);
	mysqli_stmt_execute($stmt);
	$result = mysqli_stmt_get_result($stmt);

	$documents = [];
	while ($row = mysqli_fetch_assoc($result)) {
		$documents[] = $row;
	}

	sendResponse(200, $documents);
}

// Handle POST request to upload documents
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $endpoint === 'upload_documents') {
	if (!isset($_FILES['files']) || !isset($_POST['id']) || !isset($_POST['module'])) {
		sendResponse(400, ['error' => 'files, id, and module are required']);
	}

	$id = intval($_POST['id']);
	$module = $_POST['module']; // 'member', 'coach', or 'equipment'
	$tablecontracts = $module . "contracts";

	// Handle file uploads
	$uploaded_files = [];
	foreach ($_FILES['files']['tmp_name'] as $key => $tmp_name) {
		$file_name = $_FILES['files']['name'][$key];
		$target_file = "uploads/$tablecontracts/" . basename($file_name);

		if (move_uploaded_file($tmp_name, $target_file)) {
			// Insert document into the database
			$query = "INSERT INTO $tablecontracts (${module}Id, contract) VALUES (?, ?)";
			$stmt = mysqli_prepare($db, $query);
			mysqli_stmt_bind_param($stmt, 'is', $id, $target_file);
			mysqli_stmt_execute($stmt);

			$uploaded_files[] = $file_name;
		}
	}

	if (!empty($uploaded_files)) {
		sendResponse(200, ['message' => 'Documents uploaded successfully', 'files' => $uploaded_files]);
	} else {
		sendResponse(500, ['error' => 'Failed to upload documents']);
	}
}

// Handle DELETE request to delete a document
if ($_SERVER['REQUEST_METHOD'] === 'DELETE' && preg_match('/^delete_document\/([0-9]+)\/([a-z]+)\/([0-9]+)$/', $endpoint, $matches)) {
	$id = intval($matches[1]);
	$module = $matches[2]; // 'member', 'coach', or 'equipment'
	$documentId = intval($matches[3]);
	$tablecontracts = $module . "contracts";

	// Delete the document
	$query = "DELETE FROM $tablecontracts WHERE id = ?";
	$stmt = mysqli_prepare($db, $query);
	mysqli_stmt_bind_param($stmt, 'i', $documentId);

	if (mysqli_stmt_execute($stmt)) {
		sendResponse(200, ['message' => 'Document deleted successfully']);
	} else {
		sendResponse(500, ['error' => 'Failed to delete document']);
	}
}

// If no valid endpoint is matched
sendResponse(404, ['error' => 'Invalid endpoint: ' . $endpoint]);
?>
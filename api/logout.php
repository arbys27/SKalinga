<?php
// Start session
session_start();

// Destroy the session
session_destroy();

// Return success
echo json_encode(['success' => true, 'message' => 'Logged out successfully']);
?>
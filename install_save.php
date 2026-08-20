<?php
session_start();
require 'env_helper.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $api_token = $_POST['api_token'] ?? '';
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if ($api_token && $username && $password) {
        $hashed = password_hash($password, PASSWORD_BCRYPT);
        setEnvData([
            'API_TOKEN' => $api_token,
            'USERNAME' => $username,
            'PASSWORD_HASH' => $hashed
        ]);
        $_SESSION['client_logged_in'] = true;
        $_SESSION['force_refresh'] = true;
        echo json_encode(["status" => "success"]);
    } else {
        http_response_code(400);
        echo json_encode(["error" => "Data tidak lengkap"]);
    }
}
?>

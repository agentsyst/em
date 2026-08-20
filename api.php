<?php
session_start();
require 'env_helper.php';
require 'config.php';

error_reporting(0);
set_time_limit(30);

$action = $_REQUEST['action'] ?? '';

if (empty($action)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(["error" => "Parameter action wajib disertakan."]);
    exit;
}

if ($action === 'get_server_config') {
    header('Content-Type: application/json');
    echo json_encode([
        "status" => "success",
        "master_url" => rtrim(SERVER_DOMAIN, '/'),
        "api_token" => MASTER_API_TOKEN
    ]);
    exit;
}

if ($action === 'get_local_cache') {
    header('Content-Type: application/json');
    if (!isInstalled()) { http_response_code(401); exit; }
    $env = getEnvData();
    $cache_file = __DIR__ . "/data_" . md5($env['USERNAME']) . ".json";
    if (file_exists($cache_file)) {
        echo file_get_contents($cache_file);
    } else {
        echo json_encode(["status" => "empty"]);
    }
    exit;
}

if ($action === 'save_local_cache') {
    header('Content-Type: application/json');
    if (!isInstalled()) { http_response_code(401); exit; }
    $env = getEnvData();
    $cache_file = __DIR__ . "/data_" . md5($env['USERNAME']) . ".json";
    $data_to_save = $_POST['data'] ?? '{}';
    file_put_contents($cache_file, $data_to_save);
    echo json_encode(["status" => "success"]);
    exit;
}

$api_actions = ['get_metered_quota', 'get_activity_stats', 'get_ice_servers', 'request_live'];
$public_actions = ['register_request', 'check_payment', 'cancel_deposit'];

$target_file = '/sess.php';
$server_url = rtrim(SERVER_DOMAIN, '/') . $target_file;

$post_data = $_POST;
$post_data['action'] = $action;

if (in_array($action, $public_actions)) {
    $post_data['api_token'] = MASTER_API_TOKEN;
} else {
    if (!isInstalled()) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(["error" => "Aplikasi belum diinstal."]);
        exit;
    }
    $env = getEnvData();
    $post_data['api_token'] = $env['API_TOKEN'];
    $post_data['client_username'] = $env['USERNAME'];
    $post_data['target_user'] = $env['USERNAME'];
}

$query_params = $_GET;
$query_params['action'] = $action;
if (isset($env)) {
    $query_params['target_user'] = $env['USERNAME'];
}
$query_string = http_build_query($query_params);
$url = $server_url . '?' . $query_string;

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
}

curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/x-www-form-urlencoded",
    "Accept: application/json"
]);

$response = curl_exec($ch);

if ($response === false) {
    $error = curl_error($ch);
    http_response_code(502);
    header('Content-Type: application/json');
    echo json_encode(["error" => "Gagal menghubungi server: " . $error]);
    curl_close($ch);
    exit;
}

$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$response_body = substr($response, $header_size);
curl_close($ch);

if ($http_code == 401 || $http_code == 403) {
    $res_json = json_decode($response_body, true);
    if ($res_json && isset($res_json['error'])) {
        if (strpos(strtolower($res_json['error']), 'expired') !== false ||
            strpos(strtolower($res_json['error']), 'lunas') !== false) {
            http_response_code($http_code);
            header('Content-Type: application/json');
            echo json_encode(["error" => "EXPIRED_OR_UNPAID", "message" => $res_json['error']]);
            exit;
        }
    }
}

http_response_code($http_code);
header('Content-Type: application/json');
echo $response_body;
?>
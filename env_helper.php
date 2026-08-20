<?php
function getEnvData() {
    $env_file = __DIR__ . '/.env';
    if (!file_exists($env_file)) return [];
    $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $data = [];
    foreach ($lines as $line) {
        list($name, $value) = explode('=', $line, 2);
        $data[trim($name)] = trim($value);
    }
    return $data;
}

function setEnvData($newData) {
    $env_file = __DIR__ . '/.env';
    $data = getEnvData();
    foreach ($newData as $key => $val) {
        $data[$key] = $val;
    }
    $content = "";
    foreach ($data as $key => $val) {
        $content .= "{$key}={$val}\n";
    }
    file_put_contents($env_file, $content);
}

function isInstalled() {
    $env = getEnvData();
    return !empty($env['API_TOKEN']) && !empty($env['USERNAME']) && !empty($env['PASSWORD_HASH']);
}
?>

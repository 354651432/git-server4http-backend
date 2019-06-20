<?php
/*
 * git http server , proxy of git-http-backend
 */
$reposRoot = '/mnt/hgfs/code/repos.adeaz.com/repos';

$user = $_SERVER["PHP_AUTH_USER"] ?: null;
$password = $_SERVER["PHP_AUTH_PW"] ?: null;

$users = file("../users.txt");
$users = array_map("trim", $users);
if (!in_array("$user:$password", $users)) {
    header('WWW-Authenticate: Basic realm="git"');
    header("HTTP/1.1 401");
    return;
}

$env = [
    "PATH" => getenv("PATH"),
    "REQUEST_METHOD" => $_SERVER["REQUEST_METHOD"],
    "QUERY_STRING" => $_SERVER["QUERY_STRING"] ?? null,
    "REMOTE_ADDR" => $_SERVER["REMOTE_ADDR"],
    "REMOTE_USER" => $_SERVER["USER"] ?? "dual",
    "CONTENT_TYPE" => $_SERVER["CONTENT_TYPE"] ?? $_SERVER["HTTP_CONTENT_TYPE"] ?? null,
    "GIT_HTTP_EXPORT_ALL" => "1",
    "GIT_PROJECT_ROOT" => $reposRoot,
    "PATH_INFO" => strstr($_SERVER["REQUEST_URI"], "?", true)
        ?: $_SERVER["REQUEST_URI"],
];

$process = proc_open('git http-backend', [
    ["pipe", "r"],
    ["pipe", "w"],
    ["pipe", "w"],
], $pipes, null, $env);

if (!is_resource($process)) {
    header("HTTP/1.1 403");
    return;
}

fwrite($pipes[0], file_get_contents("php://input"));
fclose($pipes[0]);

$content = stream_get_contents($pipes[1]);
fclose($pipes[1]);
$return_value = proc_close($process);
if ($return_value) {
    $err = stream_get_contents($pipes[2]);
    return;
}

// http body proc
list($header, $body) = explode("\r\n\r\n", $content);

foreach (explode("\n", $header) as $item) {
    header(trim($item));
}

echo $body;

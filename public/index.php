<?php
/*
 * git http server , proxy of git-http-backend
 */
const REPOS_Root = '/mnt/hgfs/code/repos.adeaz.com/repos';

include_once "../GitServer.php";

$server = new GitServer(REPOS_Root);
$pathInfo = strstr($_SERVER["REQUEST_URI"], "?", true);
$pathInfo || $pathInfo = $_SERVER["REQUEST_URI"];

$server->onAuthorize(function ($user, $password) {
    $users = file("../users.txt");
    $users = array_map("trim", $users);

    return $user && $password && in_array("$user:$password", $users);
});

$server->runAndSend($pathInfo);

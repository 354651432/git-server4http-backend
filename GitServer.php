<?php

/**
 * git-http-backend wrapper
 * Class GitServer
 */
class GitServer
{
    /**
     * @var string
     */
    protected $root = '';

    /**
     * @var array
     */
    protected $headers = [];
    /**
     * @var string
     */
    protected $body = '';
    /**
     * @var int
     */
    protected $statusCode = 200;
    /**
     * @var string
     */
    protected $errorMessage = '';

    /**
     * @var callable
     */
    protected $authCallback;

    /**
     * GitServer constructor.
     * @param $root
     */
    public function __construct($root)
    {
        $this->root = $root;
    }

    /**
     * @param $pathInfo
     */
    public function run($pathInfo)
    {
        $env = [
            "PATH" => getenv("PATH"),
            "REQUEST_METHOD" => $_SERVER["REQUEST_METHOD"],
            "QUERY_STRING" => $_SERVER["QUERY_STRING"] ?? null,
            "REMOTE_ADDR" => $_SERVER["REMOTE_ADDR"],
            "REMOTE_USER" => $_SERVER["USER"] ?? null,
            "CONTENT_TYPE" => $_SERVER["CONTENT_TYPE"] ?? $_SERVER["HTTP_CONTENT_TYPE"] ?? null,
            "GIT_HTTP_EXPORT_ALL" => "1",
            "GIT_PROJECT_ROOT" => $this->root,
            "PATH_INFO" => $pathInfo,
        ];

        $process = proc_open('git http-backend', [
            ["pipe", "r"],
            ["pipe", "w"],
            ["pipe", "w"],
        ], $pipes, null, $env);

        if (!is_resource($process)) {
            $this->error(510, 'create process error');
            return;
        }

        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            fwrite($pipes[0], file_get_contents("php://input"));
            fclose($pipes[0]);
        }

        $content = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $return_value = proc_close($process);
        if ($return_value) {
            $err = stream_get_contents($pipes[2]);
            $this->error(510, $err);
            return;
        }

        list($header, $body) = explode("\r\n\r\n", $content);
        $this->headers = explode("\n", $header);
        $this->headers = array_map("trim", $this->headers);

        $this->body = $body;
    }

    /**
     * @param $code
     * @param string $message
     */
    protected function error($code, $message = '')
    {
        $this->statusCode = $code;
        $this->errorMessage = $message;
    }

    /**
     * @return array
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * @return string
     */
    public function getBody()
    {
        return $this->body;
    }

    /**
     * @return int
     */
    public function getStatusCode()
    {
        return $this->statusCode;
    }

    /**
     * @return string
     */
    public function getErorrMessage()
    {
        return $this->errorMessage;
    }

    /**
     * @param $pathInfo
     */
    public function runAndSend($pathInfo)
    {
        // authorize
        if (is_callable($this->authCallback)) {
            $user = $_SERVER["PHP_AUTH_USER"] ?? null;
            $password = $_SERVER["PHP_AUTH_PW"] ?? null;

            if (!call_user_func($this->authCallback, $user, $password)) {
                header("HTTP/1.1 401");
                header('WWW-Authenticate: Basic realm="git"');
                return;
            }
        }

        // run git command
        $this->run($pathInfo);

        // error handle
        if ($this->statusCode != 200) {
            header("HTTP/1.1 $this->statusCode");
            echo $this->errorMessage;
            return;
        }

        // http header handle
        foreach ($this->headers as $header) {
            header($header);
        }

        // http body
        echo $this->body;
    }

    /**
     * function ($user,$password) -> bool;
     * @param $callback
     */
    public function onAuthorize($callback)
    {
        $this->authCallback = $callback;
    }
}

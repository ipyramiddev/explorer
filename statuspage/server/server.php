<?php
class HttpServer
{
    public $http;
    public static $instance;
    public static $level = 1;	//压缩等级，范围是1-9，等级越高压缩后的尺寸越小，但CPU消耗更多。默认为1
    
    /**
     * 初始化
     */
    private function __construct()
    {
        define('CISWOOLE', TRUE);
        register_shutdown_function(array($this, 'handleFatal'));
        $http = new swoole_http_server("0.0.0.0", 9501);
        $http->set (array(
            'worker_num' => 8,		//worker进程数量
            'daemonize' => false,	//守护进程设置成true
            'max_request' => 10000,	//最大请求次数，当请求大于它时，将会自动重启该worker
            'dispatch_mode' => 1
        ));
        $http->on('WorkerStart', array($this, 'onWorkerStart'));
        $http->on('request', array($this, 'onRequest'));
        $http->on('start', array($this, 'onStart'));
        $http->start();
    }
    
    /**
     * server start的时候调用
     * @param unknown $serv
     */
    public function onStart($serv)
    {
        echo 'swoole version'.swoole_version().PHP_EOL;
    }
    /**
     * worker start时调用
     * @param unknown $serv
     * @param int $worker_id
     */
    public function onWorkerStart($serv, $worker_id)
    {
        global $argv;
        if($worker_id >= $serv->setting['worker_num']) {
            swoole_set_process_name("php {$argv[0]}: task");
        } else {
            swoole_set_process_name("php {$argv[0]}: worker");
        }
        echo "WorkerStart: MasterPid={$serv->master_pid}|Manager_pid={$serv->manager_pid}|WorkerId={$serv->worker_id}|WorkerPid={$serv->worker_pid}\n";
        define('APPLICATION_PATH', dirname(__DIR__));
        include APPLICATION_PATH.'/www/httpindex.php';
    }
    
    /**
     * 当request时调用
     * @param unknown $request
     * @param unknown $response
     */
    public function onRequest($request, $response)
    {
        try {
            ob_start();
            Httpindex::getInstance($request, $response);
            // 			include 'test.php';
            $result = ob_get_contents();
            ob_end_clean();
            $response->header("Content-Type", "text/html;charset=utf-8");
            $result = empty($result) ? 'No message' : $result;
            !$GLOBALS['ISEND'] && $response->end($result);
            unset($result);
        } catch (Exception $e) {
            $response->end($e->getMessage());
        }
    }
    
    /**
     * 致命错误处理
     */
    public function handleFatal()
    {
        $error = error_get_last();
        if (isset($error['type'])) {
            switch ($error['type']) {
                case E_ERROR :
                    $severity = 'ERROR:Fatal run-time errors. Errors that can not be recovered from. Execution of the script is halted';
                    break;
                case E_PARSE :
                    $severity = 'PARSE:Compile-time parse errors. Parse errors should only be generated by the parser';
                    break;
                case E_DEPRECATED:
                    $severity = 'DEPRECATED:Run-time notices. Enable this to receive warnings about code that will not work in future versions';
                    break;
                case E_CORE_ERROR :
                    $severity = 'CORE_ERROR :Fatal errors at PHP startup. This is like an E_ERROR in the PHP core';
                    break;
                case E_COMPILE_ERROR :
                    $severity = 'COMPILE ERROR:Fatal compile-time errors. This is like an E_ERROR generated by the Zend Scripting Engine';
                    break;
                default:
                    $severity = 'OTHER ERROR';
                    break;
            }
            $message = $error['message'];
            $file = $error['file'];
            $line = $error['line'];
            $log = "$message ($file:$line)\nStack trace:\n";
            $trace = debug_backtrace();
            foreach ($trace as $i => $t) {
                if (!isset($t['file'])) {
                    $t['file'] = 'unknown';
                }
                if (!isset($t['line'])) {
                    $t['line'] = 0;
                }
                if (!isset($t['function'])) {
                    $t['function'] = 'unknown';
                }
                $log .= "#$i {$t['file']}({$t['line']}): ";
                if (isset($t['object']) && is_object($t['object'])) {
                    $log .= get_class($t['object']) . '->';
                }
                $log .= "{$t['function']}()\n";
            }
            if (isset($_SERVER['REQUEST_URI'])) {
                $log .= '[QUERY] ' . $_SERVER['REQUEST_URI'];
            }
            ob_start();
            include 'error_php.php';
            $log = ob_get_contents();
            ob_end_clean();
            $GLOBALS['RESPONSE']->end($log);
        }
    }
    
    public static function getInstance()
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}
HttpServer::getInstance ();
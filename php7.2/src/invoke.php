<?php
namespace ServerlessFC;

use Psr\Http\Message\ServerRequestInterface;
use GuzzleHttp\Psr7\ServerRequest;
use RingCentral\Psr7\Response;
use RingCentral\Psr7\Uri;

dl('native_rapis_client.' . PHP_SHLIB_SUFFIX);
if (!function_exists('get_next')) {
    require dirname(__DIR__) . "/src/rapis.php";
    echo 'Use non-extension plugin.' . PHP_EOL;
}

trait FcErrorTrait {
    function fc_exception_error_handler($errno, $errstr, $errfile, $errline) {
        // http://php.net/manual/en/errorfunc.constants.php
        if ($errno > E_STRICT) {
            return false;
        }

        $errInfo = array(
            "errorMessage" => $errstr,
            "errorType"    => \ServerlessFC\friendly_error_type($errno),
            "stackTrace"   => array(
                "file" => $errfile,
                "line" => $errline,
            ),
        );
        throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
    }

    public function set_fc_error_handler() {
        set_error_handler(array($this, 'fc_exception_error_handler'));
    }
}

trait FcLoadTrait {
    public function load($__fc_func_file) {

        //loaded mods can be reloaded
        include_once $__fc_func_file;

        //copy defined global vars to global scope
        $defined = get_defined_vars();

        unset($defined["__fc_func_file"]);

        $GLOBALS += $defined;
    }

}

// NormalHandler include initialize and invoke request
class NormalHandler {

    use FcErrorTrait;
    use FcLoadTrait;

    private $func_name;

    private $file_name;

    public function __construct($file, $func) {
        $this->set_fc_error_handler();

        $this->file_name = $file;
        $this->func_name = $func;

        $this->load($this->file_name);
    }

    public function invoke($event, $context) {

        $fname = $this->func_name;

        if (!function_exists($fname)) {
            throw new \Exception("function " . $fname . " not found");
        }
        $ret = $fname($event, $context);
        if (!is_string($ret)) {
            $ret = json_encode($ret, JSON_UNESCAPED_UNICODE);
        }
        return $ret;

    }

    public function invokeHook($context) {

        $fname = $this->func_name;

        if (!function_exists($fname)) {
            throw new \Exception("function " . $fname . " not found");
        }
        $ret = $fname($context);
        if (!is_string($ret)) {
            $ret = json_encode($ret, JSON_UNESCAPED_UNICODE);
        }
        return $ret;

    }
}

class HttpInvokeHandler {

    use FcErrorTrait;
    use FcLoadTrait;

    private $func_name;

    private $file_name;

    public function __construct($file, $func) {
        $this->set_fc_error_handler();

        $this->file_name = $file;
        $this->func_name = $func;

        $this->load($this->file_name);
    }

    public function handle(string $body, array $headers, array $context): array {
        $request  = $this->prepareRequest($body, $headers, $context);
        $fname    = $this->func_name;
        $response = $fname($request, $context);
        assert($response instanceof Response);
        return HttpWrapper::parserOutResult($request, $response);
    }

    private function prepareRequest(string $body, array $headers, array $context) {
        $functionName = $this->func_name;
        if (!function_exists($functionName)) {
            throw new \Exception("function " . $functionName . " not found");
        }
        return HttpWrapper::createHttpRequest($body, $headers, $context);
    }
}

abstract class InvokerBase {
    public abstract function get_next(): array;
    public abstract function post_invocation_response(string $request_id,
                                                         string $resp,
                                                         string $content_type,
                                                         string $http_params);
    public abstract function post_error(string $request_id,
                                           string $resp,
                                           string $tracer_fault);
    public abstract function post_init_error(string $request_id,
                                                string $resp,
                                                string $tracer_fault);
}

final class Invoker extends InvokerBase {
    public function get_next(): array
    {
        return get_next();
    }

    public function post_invocation_response(string $request_id, string $resp, string $content_type, string $http_params)
    {
        post_invocation_response($request_id, $resp, $content_type, $http_params);
    }

    public function post_error(string $request_id, string $resp, string $tracer_fault)
    {
        post_error($request_id, $resp, $tracer_fault);
    }

    public function post_init_error(string $request_id, string $resp, string $tracer_fault)
    {
        post_init_error($request_id, $resp, $tracer_fault);
    }
}

final class InvocationHandler
{
    private $invoker;
    public function __construct($invoker=NULL){
        if (is_null($invoker)) {
            $this->invoker = new Invoker();
        } else {
            $this->invoker = $invoker;
        }
    }

    public function __destruct(){
    }

    public function handleInvocation(): bool {
        $invoke_request = $this->invoker->get_next();
        $body = $invoke_request["body"];
        $headers = $invoke_request["headers"];
        $headers  = array_change_key_case($headers, CASE_LOWER);

        if ($GLOBALS["CreateFromSnapshot"]) {
            $GLOBALS['fcSysLogger']->info('Restore from snapshot');
            chdir($GLOBALS["defaultWorkDir"]);
            //restore the temporary environment
            if (array_key_exists(FC_ACCESS_KEY_ID, $headers)) {
                if (getenv("ALIBABA_CLOUD_ACCESS_KEY_ID") !== false) {
                    putenv("ALIBABA_CLOUD_ACCESS_KEY_ID" . "=" . $headers[FC_ACCESS_KEY_ID]);
                    $_ENV["ALIBABA_CLOUD_ACCESS_KEY_ID"] = $headers[FC_ACCESS_KEY_ID];
                }
                if (getenv("accessKeyID") !== false) {
                    putenv("accessKeyID" . "=" . $headers[FC_ACCESS_KEY_ID]);
                    $_ENV["accessKeyID"] = $headers[FC_ACCESS_KEY_ID];
                }
            }
            if (array_key_exists(FC_ACCESS_KEY_SECRET, $headers)) {
                if (getenv("ALIBABA_CLOUD_ACCESS_KEY_SECRET") !== false) {
                    putenv("ALIBABA_CLOUD_ACCESS_KEY_SECRET" . "=" . $headers[FC_ACCESS_KEY_SECRET]);
                    $_ENV["ALIBABA_CLOUD_ACCESS_KEY_SECRET"] = $headers[FC_ACCESS_KEY_SECRET];
                }
                if (getenv("accessKeySecret") !== false) {
                    putenv("accessKeySecret" . "=" . $headers[FC_ACCESS_KEY_SECRET]);
                    $_ENV["accessKeySecret"] = $headers[FC_ACCESS_KEY_SECRET];
                }
            }
            if (array_key_exists(FC_SECURITY_TOKEN, $headers)) {
                if (getenv("ALIBABA_CLOUD_SECURITY_TOKEN") !== false) {
                    putenv("ALIBABA_CLOUD_SECURITY_TOKEN" . "=" . $headers[FC_SECURITY_TOKEN]);
                    $_ENV["ALIBABA_CLOUD_SECURITY_TOKEN"] = $headers[FC_SECURITY_TOKEN];
                }
                if (getenv("securityToken") !== false) {
                    putenv("securityToken" . "=" . $headers[FC_SECURITY_TOKEN]);
                    $_ENV["securityToken"] = $headers[FC_SECURITY_TOKEN];
                }
            }
            if (array_key_exists(FC_INSTANCE_ID, $headers)) {
                if (getenv("FC_INSTANCE_ID") !== false) {
                    putenv("FC_INSTANCE_ID" . "=" . $headers[FC_INSTANCE_ID]);
                    $_ENV["FC_INSTANCE_ID"] = $headers[FC_INSTANCE_ID];
                }
            }
            $GLOBALS["CreateFromSnapshot"] = false;
            unset($_ENV["FC_SNAPSHOT_FLAG"]);
            putenv("FC_SNAPSHOT_FLAG");
        }

        $requestId = array_key_exists(FC_REQUEST_ID, $headers) ? $headers[FC_REQUEST_ID] : "unknown_request_id";
        $functionType = array_key_exists(FC_FUNCTION_TYPE, $headers) ? $headers[FC_FUNCTION_TYPE] : 0;
        $GLOBALS['requestId'] = $requestId;
        $this->printStartLog($functionType, $requestId);
        //TODO, need clean here?
        if (ob_get_length()) {
            ob_clean();
        }
        try {
            $context = gen_fc_context($headers);
            $entry_point = array_key_exists(FC_FUNCTION_HANDLER, $headers) ? $headers[FC_FUNCTION_HANDLER] : '';
            $retArr = get_module_path($entry_point);
            if ($retArr[0]) {
                $this->printEndLog($functionType, $requestId);
                $this->returnError($requestId, $retArr[0]);
                return false;
            }
            $ret = "";
            if (array_key_exists(FC_HTTP_PARAMS, $headers)) {
                $httpHandler = new HttpInvokeHandler($retArr[1], $retArr[2]);
                // todo: make $invoke_request a psr7 http request
                $ret = $httpHandler->handle($body, $headers, $context);
                $this->printEndLog($functionType, $requestId);
                $httpParams     = array_key_exists('httpParams', $ret) ? $ret['httpParams'] : '';
                $body     = array_key_exists('body', $ret) ? $ret['body'] : '';
                $this->returnHttpResult($requestId, $httpParams, $body );
            } else {
                $normalHandler = new NormalHandler($retArr[1], $retArr[2]);
                if ($functionType == HANDLE_FUNCTION) {
                    $ret = $normalHandler->invoke($body, $context);
                } else {
                    $ret = $normalHandler->invokeHook($context);
                }
                $this->printEndLog($functionType, $requestId);
                $this->returnResult($requestId, $ret);
            }
        } catch (\Throwable $e) {
            $this->printEndLog($functionType, $requestId);
            $this->returnError($requestId, $e);
        }

        return true;
    }

    public function returnError(string $requestId, $e){
        $errStr = "";
        if (gettype($e) == "string") {
            $errStr = $e;
        } elseif (gettype($e) == "array") {
            // todo: remove
            $errStr = var_export($e, true);
        } else {
            $traceStr = $e->getTraceAsString();
            $pos = strpos($traceStr, "/var/fc/runtime/php7/src/bootstrap.php");
            $traceStr = substr($traceStr, 0, $pos);
            $err = array(
                "errorMessage" => $e->getMessage(),
                "errorType" => get_class($e),
                "stackTrace" => array(
                    "file" => $e->getFile(),
                    "line" => $e->getLine(),
                    "traceString" => $traceStr,
                ),
            );
            if (ob_get_length()) ob_clean();
            var_export($err);
            $errStr = json_encode($err, JSON_UNESCAPED_UNICODE);
        }

        $fcLogger =  $GLOBALS['fcSysLogger'];
        $fcLogger->error($errStr);

        $this->invoker->post_error($requestId, $errStr, "");
    }

    private function returnResult(string $requestId, $result){
        $this->invoker->post_invocation_response($requestId, $result, "application/octet-stream", "");
    }

    private function returnHttpResult(string $requestId, $httpParams, $body){
        $this->invoker->post_invocation_response($requestId, $body, "application/octet-stream", $httpParams);
    }

    private function printStartLog(int $functionType, string $requestId)
    {
        global $fcSysLogger;
        if ($functionType == INIT_FUNCTION) {
            echo FC_LOG_INITIALIZER_TAIL_START_PREFIX . $requestId . PHP_EOL;
            $fcSysLogger->info(FC_LOG_INITIALIZER_TAIL_START_PREFIX . $requestId);
        } elseif ($functionType == HANDLE_FUNCTION) {
            echo FC_LOG_TAIL_START_PREFIX . $requestId . PHP_EOL;
            $fcSysLogger->info(FC_LOG_TAIL_START_PREFIX . $requestId);
        } elseif ($functionType == PRESTOP_FUNCTION) {
            echo FC_LOG_PRE_STOP_TAIL_START_PREFIX . $requestId . PHP_EOL;
            $fcSysLogger->info(FC_LOG_PRE_STOP_TAIL_START_PREFIX . $requestId);
        } else {
            echo FC_LOG_PRE_FREEZE_TAIL_START_PREFIX . $requestId . PHP_EOL;
            $fcSysLogger->info(FC_LOG_PRE_FREEZE_TAIL_START_PREFIX . $requestId);
        }
    }

    private function printEndLog(int $functionType, string $requestId)
    {
        $err = false;
        $msg = "";
        if ($functionType == INIT_FUNCTION) {
            $msg = FC_LOG_INITIALIZER_TAIL_END_PREFIX . $requestId;
        } elseif ($functionType == HANDLE_FUNCTION) {
            $msg = FC_LOG_TAIL_END_PREFIX . $requestId;
        } elseif ($functionType == PRESTOP_FUNCTION) {
            $msg = FC_LOG_PRE_STOP_TAIL_END_PREFIX . $requestId;
        } else {
            $msg = FC_LOG_PRE_FREEZE_TAIL_END_PREFIX . $requestId;
        }
        if($err) {
            $msg = $msg . ", Error: Unhandled function error";
        }
        // TODO if (ob_get_length()) ob_clean();
        $fcSysLogger =  $GLOBALS['fcSysLogger'];
        echo $msg . PHP_EOL;
        $fcSysLogger->info($msg);
    }
}

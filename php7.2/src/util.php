<?php
namespace ServerlessFC;

function startsWith($haystack, $needle) {
    $length = strlen($needle);
    return (substr($haystack, 0, $length) === $needle);
}

function endsWith($haystack, $needle) {
    $length = strlen($needle);

    return $length === 0 ||
        (substr($haystack, -$length) === $needle);
}

function gen_fc_context($headers) {
    $requestId       = array_key_exists(FC_REQUEST_ID, $headers) ? $headers[FC_REQUEST_ID] : "unknown_request_id";
    $accessKeyId     = array_key_exists(FC_ACCESS_KEY_ID, $headers) ? $headers[FC_ACCESS_KEY_ID] : '';
    $accessKeySecret = array_key_exists(FC_ACCESS_KEY_SECRET, $headers) ? $headers[FC_ACCESS_KEY_SECRET] : '';
    $securityToken   = array_key_exists(FC_SECURITY_TOKEN, $headers) ? $headers[FC_SECURITY_TOKEN] : '';

    $name                  = array_key_exists(FC_FUNCTION_NAME, $headers) ? $headers[FC_FUNCTION_NAME] : '';
    $handler               = array_key_exists(FC_FUNCTION_HANDLER, $headers) ? $headers[FC_FUNCTION_HANDLER] : '';
    $memory                = array_key_exists(FC_FUNCTION_MEMORY, $headers) ? intval($headers[FC_FUNCTION_MEMORY]) : '';
    $timeout               = array_key_exists(FC_FUNCTION_TIMEOUT, $headers) ? intval($headers[FC_FUNCTION_TIMEOUT]) : '';
    $initializer           = array_key_exists(FC_FUNCTION_INITIALIZER, $headers) ? $headers[FC_FUNCTION_INITIALIZER] : '';
    $initializationTimeout = array_key_exists(FC_FUNCTION_INITIALIZATION_TIMEOUT, $headers) ? intval($headers[FC_FUNCTION_INITIALIZATION_TIMEOUT]) : '';

    $service_name        = array_key_exists(FC_SERVICE_NAME, $headers) ? $headers[FC_SERVICE_NAME] : '';
    $service_region      = array_key_exists(FC_REGION, $headers) ? $headers[FC_REGION] : '';
    $service_log_project = array_key_exists(FC_SERVICE_LOG_PROJECT, $headers) ? $headers[FC_SERVICE_LOG_PROJECT] : '';
    $service_log_store   = array_key_exists(FC_SERVICE_LOG_STORE, $headers) ? $headers[FC_SERVICE_LOG_STORE] : '';
    $accountId           = array_key_exists(FC_ACCOUNT_ID, $headers) ? $headers[FC_ACCOUNT_ID] : '';
    $qualifier           = array_key_exists(FC_QUALIFIER, $headers) ? $headers[FC_QUALIFIER] : '';
    $versionId           = array_key_exists(FC_VERSION_ID, $headers) ? $headers[FC_VERSION_ID] : '';
    $retry_count         = array_key_exists(RETRY_COUNT, $headers) ? intval($headers[RETRY_COUNT]) : 0;
    $span_context        = array_key_exists(FC_OPENTRACING_SPAN_CONTEXT, $headers) ? $headers[FC_OPENTRACING_SPAN_CONTEXT] : '';
    $jaeger_endpoint     = array_key_exists(FC_JAEGER_ENDPOINT, $headers) ? $headers[FC_JAEGER_ENDPOINT] : '';

    $ctx = array(
        "accountId"   => $accountId,
        "requestId"   => $requestId,
        "credentials" => array(
            "accessKeyId"     => $accessKeyId,
            "accessKeySecret" => $accessKeySecret,
            "securityToken"   => $securityToken,
        ),
        "function"    => array(
            "name"                  => $name,
            "handler"               => $handler,
            "memory"                => $memory,
            "timeout"               => $timeout,
            "initializer"           => $initializer,
            "initializationTimeout" => $initializationTimeout,
        ),
        "service"     => array(
            "name"       => $service_name,
            "logProject" => $service_log_project,
            "logStore"   => $service_log_store,
            "qualifier"  => $qualifier,
            "versionId"  => $versionId,
        ),
        "region"      => $service_region,
        "retryCount"  => $retry_count,
        "tracing" => array(
            "openTracingSpanContext"  => $span_context,
            "jaegerEndpoint"          => $jaeger_endpoint,
            "openTracingSpanBaggages" => parseOpenTracingBaggages($headers)
        )
    );

    return $ctx;
}

// for initialize and invoke operation
function get_module_path($entry_point) {
    // get function code_path and handler
    $index = strripos($entry_point, ".");

    $err = "";

    if ($index == false) {
        $err = sprintf('Invalid handler \'%s\'', $entry_point);
        return array($err, '', '');
    }

    $codePath = $_ENV[FC_FUNC_CODE_PATH] ?: "/code";

    $moduleName   = substr($entry_point, 0, $index);
    $functionName = substr($entry_point, $index + 1);

    $moduleName = str_replace(".", "/", $moduleName);

    $modulePath = join_paths($codePath, $moduleName . '.php');

    if (!file_exists($modulePath)) {
        $err = sprintf('Module \'%s\' is missing.', $modulePath);
    }

    return array($err, $modulePath, $functionName);
}

function join_paths() {
    $paths = array();

    foreach (func_get_args() as $arg) {
        if ($arg !== '') {$paths[] = $arg;}
    }

    return preg_replace('#/+#', '/', join('/', $paths));
}

// refrence http://php.net/manual/en/errorfunc.constants.php
function friendly_error_type($type) {
    switch ($type) {
    case E_ERROR: // 1 //
        return 'E_ERROR';
    case E_WARNING: // 2 //
        return 'E_WARNING';
    case E_PARSE: // 4 //
        return 'E_PARSE';
    case E_NOTICE: // 8 //
        return 'E_NOTICE';
    case E_CORE_ERROR: // 16 //
        return 'E_CORE_ERROR';
    case E_CORE_WARNING: // 32 //
        return 'E_CORE_WARNING';
    case E_COMPILE_ERROR: // 64 //
        return 'E_COMPILE_ERROR';
    case E_COMPILE_WARNING: // 128 //
        return 'E_COMPILE_WARNING';
    case E_USER_ERROR: // 256 //
        return 'E_USER_ERROR';
    case E_USER_WARNING: // 512 //
        return 'E_USER_WARNING';
    case E_USER_NOTICE: // 1024 //
        return 'E_USER_NOTICE';
    case E_STRICT: // 2048 //
        return 'E_STRICT';
    case E_RECOVERABLE_ERROR: // 4096 //
        return 'E_RECOVERABLE_ERROR';
    case E_DEPRECATED: // 8192 //
        return 'E_DEPRECATED';
    case E_USER_DEPRECATED: // 16384 //
        return 'E_USER_DEPRECATED';
    }
    return "";
}

function parseOpenTracingBaggages($headers){
    $span_baggages = array_key_exists(FC_OPENTRACING_SPAN_BAGGAGES, $headers) ? $headers[FC_OPENTRACING_SPAN_BAGGAGES] : '';

    $baggages = array();
    if ($span_baggages != '') {
        // php will not throw exception though decode fails
        $str_baggages = base64_decode($span_baggages);
        $baggages = json_decode($str_baggages, $assoc = true);
    }
    return $baggages;
}

// this will never generate traffic because its udp
function get_local_ip():string {
    $ip = '127.0.0.1';
    $sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
    socket_connect($sock, $ip, 53);
    socket_getsockname($sock, $ip);
    socket_close($sock);
    return $ip;
}
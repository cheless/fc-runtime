<?php

namespace ServerlessFC;

// headers
define('FC_REQUEST_ID', 'x-fc-request-id');
define('FC_ACCESS_KEY_ID', 'x-fc-access-key-id');
define('FC_ACCESS_KEY_SECRET', 'x-fc-access-key-secret');
define('FC_SECURITY_TOKEN', 'x-fc-security-token');
define('FC_FUNCTION_NAME', 'x-fc-function-name');
define('FC_FUNCTION_HANDLER', 'x-fc-function-handler');
define('FC_FUNCTION_MEMORY', 'x-fc-function-memory');
define('FC_FUNCTION_TIMEOUT', 'x-fc-function-timeout');
define('FC_FUNCTION_INITIALIZER', 'x-fc-function-initializer');
define('FC_FUNCTION_INITIALIZATION_TIMEOUT', 'x-fc-initialization-timeout');
define('FC_FUNCTION_PRE_STOP', 'x-fc-instance-lifecycle-pre-stop-handler');
define('FC_FUNCTION_PRE_FREEZE', 'x-fc-instance-lifecycle-pre-freeze-handler');

define('FC_SERVICE_NAME', 'x-fc-service-name');
define('FC_SERVICE_LOG_PROJECT', 'x-fc-service-logproject');
define('FC_SERVICE_LOG_STORE', 'x-fc-service-logstore');

define('FC_HTTP_PARAMS', 'x-fc-http-params');

define('FC_REGION', 'x-fc-region');
define('FC_ACCOUNT_ID', 'x-fc-account-id');

define('FC_QUALIFIER', 'x-fc-qualifier');
define('FC_VERSION_ID', 'x-fc-version-id');
define('RETRY_COUNT', 'x-fc-retry-count');
define('FUNCTION_TYPE', 'x-fc-function-type');

define('FC_OPENTRACING_SPAN_CONTEXT', 'x-fc-tracing-opentracing-span-context');
define('FC_OPENTRACING_SPAN_BAGGAGES', 'x-fc-tracing-opentracing-span-baggages');
define('FC_JAEGER_ENDPOINT', 'x-fc-tracing-jaeger-endpoint');

define('FC_FUNCTION_TYPE', 'x-fc-function-type');
define('HANDLE_FUNCTION', 0);
define('INIT_FUNCTION', 1);
define('PRESTOP_FUNCTION', 2);

define('FC_INSTANCE_ID', 'x-fc-instance-id');

define('FC_LOG_FRAME_TYPE', 0xeddbac9b);

// env
define('FC_SERVER_PORT', 'FC_SERVER_PORT'); // Server port number.
define('FC_SERVER_LOG_PATH', 'FC_SERVER_LOG_PATH'); // Server app log path.
define('FC_SERVER_LOG_LEVEL', 'FC_SERVER_LOG_LEVEL'); // Server log level.
define('FC_FUNC_CODE_PATH', 'FC_FUNC_CODE_PATH'); // Function code path.
define('FC_FUNC_LOG_PATH', 'FC_FUNC_LOG_PATH'); // Function log path.

// safe env that function can rely on
define('FC_SAFE_ENV', array(
        'FC_FUNC_CODE_PATH',
        'FC_FUNCTION_MEMORY_SIZE',
        'FC_RUNTIME_VERSION',
        'FC_ACCOUNT_ID',
        'FC_FUNCTION_HANDLER',
        'FC_FUNCTION_NAME',
        'FC_SERVICE_NAME',
        'FC_VER_ID',
        'FC_QUALIFIER',
        'FC_REGION',
        'FC_INSTANCE_ID'));

// log
define('FC_LOG_TAIL_START_PREFIX', 'FC Invoke Start RequestId: '); // Start of log tail mark
define('FC_LOG_TAIL_END_PREFIX', '\nFC Invoke End RequestId: '); // End of log tail mark
define('FC_LOG_INITIALIZER_TAIL_START_PREFIX', 'FC Initialize Start RequestId: ');  // Start of initialize log tail mark
define('FC_LOG_INITIALIZER_TAIL_END_PREFIX', '\nFC Initialize End RequestId: ');  // End of initialize log tail mark
define('FC_LOG_PRE_STOP_TAIL_START_PREFIX', 'FC PreStop Start RequestId: ');  // Start of preStop log tail mark
define('FC_LOG_PRE_STOP_TAIL_END_PREFIX', '\nFC PreStop End RequestId: ');  // End of preStop log tail mark
define('FC_LOG_PRE_FREEZE_TAIL_START_PREFIX', 'FC PreFreeze Start RequestId: ');  // Start of preFreeze log tail mark
define('FC_LOG_PRE_FREEZE_TAIL_END_PREFIX', '\nFC PreFreeze End RequestId: ');  // End of preFreeze log tail mark

// phpcgi
define('FC_PHP_CGI__PORT', '9527');
define('FC_CGI_REMOTE_PORT', '1234'); // fake
define('FC_CGI_SERVER_ADDR', '127.0.0.1');
define('FC_CGI_SERVER_PORT', '80');
define('FC_CGI_SERVER_PROTOCOL', 'HTTP/1.1');
define('FC_CGI_GATEWAY_INTERFACE', 'CGI/1.1');
define('FC_CGI_SERVER_SOFTWARE', 'php/fcgiclient');

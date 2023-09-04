# -*- coding: utf-8 -*-

class Constant:
    # headers
    REQUEST_ID           = 'x-fc-request-id'
    ACCESS_KEY_ID        = 'x-fc-access-key-id'
    ACCESS_KEY_SECRET    = 'x-fc-access-key-secret'
    SECURITY_TOKEN       = 'x-fc-security-token'
    FUNCTION_NAME        = 'x-fc-function-name'
    FUNCTION_HANDLER     = 'x-fc-function-handler'
    FUNCTION_MEMORY      = 'x-fc-function-memory'
    FUNCTION_TIMEOUT     = 'x-fc-function-timeout'

    SERVICE_NAME         = 'x-fc-service-name'
    SERVICE_LOG_PROJECT  = 'x-fc-service-logproject'
    SERVICE_LOG_STORE    = 'x-fc-service-logstore'

    HTTP_PARAMS          = 'x-fc-http-params'

    REGION               = 'x-fc-region'
    ACCOUNT_ID           = 'x-fc-account-id'

    QUALIFIER            = 'x-fc-qualifier'
    VERSION_ID           = 'x-fc-version-id'

    RETRY_COUNT          = 'x-fc-retry-count'

    CONTENT_LENGTH       = 'Content-Length'
    CONNECTION           = 'Connection'

    OPENTRACING_SPAN_CONTEXT = 'x-fc-tracing-opentracing-span-context'
    OPENTRACING_SPAN_BAGGAGES = 'x-fc-tracing-opentracing-span-baggages'
    JAEGER_ENDPOINT = 'x-fc-tracing-jaeger-endpoint'

    # env
    SERVER_PORT          = 'FC_SERVER_PORT'      # Server port number.
    SERVER_LOG_PATH      = 'FC_SERVER_LOG_PATH'  # Server app log path.
    SERVER_LOG_LEVEL     = 'FC_SERVER_LOG_LEVEL' # Server log level.
    FUNC_CODE_PATH       = 'FC_FUNC_CODE_PATH'   # Function code path.
    FUNC_LOG_PATH        = 'FC_FUNC_LOG_PATH'    # Function log path.

    # safe env that function can rely on
    SAFE_ENV = ['FC_FUNC_CODE_PATH', 'FC_FUNCTION_MEMORY_SIZE', 'FC_RUNTIME_VERSION']

    # log
    LOG_TAIL_START_PREFIX = 'FC Invoke Start RequestId: ' # Start of log tail mark
    LOG_TAIL_END_PREFIX   = 'FC Invoke End RequestId: '   # End of log tail mark
    LOG_TAIL_START_PREFIX_INITIALIZE = 'FC Initialize Start RequestId: ' # Start of initialize log tail mark
    LOG_TAIL_END_PREFIX_INITIALIZE = 'FC Initialize End RequestId: ' # End of initialize log tail mark
    LOG_TAIL_START_PREFIX_PRE_STOP = 'FC PreStop Start RequestId: '  # Start of preStop log tail mark
    LOG_TAIL_END_PREFIX_PRE_STOP = 'FC PreStop End RequestId: '  # End of preStop log tail mark

    # wsgi
    HEADER_FUNCTION_HANDLER = 'HTTP_X_FC_FUNCTION_HANDLER'
    HEADER_HTTP_PARAMS = "HTTP_X_FC_HTTP_PARAMS"

    # system define key
    WSGI_PATH_INFO = 'PATH_INFO'
    WSGI_REQUEST_METHOD = 'REQUEST_METHOD'
    WSGI_QUERY_STRING = 'QUERY_STRING'
    WSGI_CONTENT_LENGTH = 'CONTENT_LENGTH'
    WSGI_CONTENT_TYPE = 'CONTENT_TYPE'
    WSGI_CLIENT_IP = "REMOTE_ADDR"

    # user define key
    WSGI_REQUEST_URI = "fc.request_uri"
    WSGI_CONTEXT = "fc.context"
    FC_CONTEXT = "fc.context"

    # legacy
    FC_LEGACY_HTTP_FUNCTION = 'X_FC_LEGACY_HTTP_FUNCTION'

    # life cycle function_type
    FUNCTION_TYPE = "x-fc-function-type"
    HANDLE_FUNCTION = 0
    INIT_FUNCTION = 1
    PRESTOP_FUNCTION = 2

    # Content type
    CONTENT_TYPE = 'application/octet-stream'

    # Log Magic Number
    FC_LOG_FRAME_TYPE = 0xeddbac9b

    #EXIT_CODE
    INIT_ERROR = 129 #128 + 1 
    SIGTERM_ERROR = 143  #128 +15 

    #LIMIT
    MAX_LOG_BYTES= 1024 * 32

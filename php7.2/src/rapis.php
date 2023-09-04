<?php
namespace ServerlessFC;

// get_next, post_invocation_response, post_error, post_init_error

const LOG_TAG = "[native_http_client]";
const REQUEST_ID_HEADER = "x-fc-request-id";
const REQUEST_ACCESS_KEY_HEADER = "x-fc-access-key-id";
const REQUEST_ACCESS_SECRET_HEADER = "x-fc-access-key-secret";
const REQUEST_SECURITY_TOKEN_HEADER = "x-fc-security-token";
const FUNCTION_NAME_HEADER = "x-fc-function-name";
const FUNCTION_HANDLER_HEADER = "x-fc-function-handler";
const FUNCTION_MEMORY_HEADER = "x-fc-function-memory";
const FUNCTION_TYPE_HEADER = "x-fc-function-type";
const FUNCTION_TIMEOUT_HEADER = "x-fc-function-timeout";
const FUNCTION_DEADLINE_HEADER = "x-fc-function-deadline";
const FUNCTION_VERSION_HEADER = "x-fc-version-id";
const FUNCTION_QUALIFIER_HEADER = "x-fc-qualifier";

const SERVICE_NAME_HEADER = "x-fc-service-name";
const SERVICE_LOG_PROJECT = "x-fc-service-logproject";
const SERVICE_LOG_STORE = "x-fc-service-logstore";

const REGION_HEADER = "x-fc-region";
const ACCOUNT_ID_HEADER = "x-fc-account-id";

const TRACE_ID_HEADER = "x-fc-trace-id";
const CLIENT_IP_HEADER = "x-fc-client-ip";
const HTTP_PARAMS_HEADER = "x-fc-http-params";
const RETRY_COUNT = "x-fc-retry-count";

const OPENTRACING_SPAN_CONTEXT = "x-fc-tracing-opentracing-span-context";
const OPENTRACING_SPAN_BAGGAGES = "x-fc-tracing-opentracing-span-baggages";
const JAEGER_ENDPOINT = "x-fc-tracing-jaeger-endpoint";

const REQUEST_NOT_MADE = -1;
const CONTINUE_VALUE = 100;
const SWITCHING_PROTOCOLS = 101;
const PROCESSING = 102;
const OK = 200;
const CREATED = 201;
const ACCEPTED = 202;
const NON_AUTHORITATIVE_INFORMATION = 203;
const NO_CONTENT = 204;
const RESET_CONTENT = 205;
const PARTIAL_CONTENT = 206;
const MULTI_STATUS = 207;
const ALREADY_REPORTED = 208;
const IM_USED = 226;
const MULTIPLE_CHOICES = 300;
const MOVED_PERMANENTLY = 301;
const FOUND = 302;
const SEE_OTHER = 303;
const NOT_MODIFIED = 304;
const USE_PROXY = 305;
const SWITCH_PROXY = 306;
const TEMPORARY_REDIRECT = 307;
const PERMANENT_REDIRECT = 308;
const BAD_REQUEST = 400;
const UNAUTHORIZED = 401;
const PAYMENT_REQUIRED = 402;
const FORBIDDEN = 403;
const NOT_FOUND = 404;
const METHOD_NOT_ALLOWED = 405;
const NOT_ACCEPTABLE = 406;
const PROXY_AUTHENTICATION_REQUIRED = 407;
const REQUEST_TIMEOUT = 408;
const CONFLICT = 409;
const GONE = 410;
const LENGTH_REQUIRED = 411;
const PRECONDITION_FAILED = 412;
const REQUEST_ENTITY_TOO_LARGE = 413;
const REQUEST_URI_TOO_LONG = 414;
const UNSUPPORTED_MEDIA_TYPE = 415;
const REQUESTED_RANGE_NOT_SATISFIABLE = 416;
const EXPECTATION_FAILED = 417;
const IM_A_TEAPOT = 418;
const AUTHENTICATION_TIMEOUT = 419;
const METHOD_FAILURE = 420;
const UNPROC_ENTITY = 422;
const LOCKED = 423;
const FAILED_DEPENDENCY = 424;
const UPGRADE_REQUIRED = 426;
const PRECONDITION_REQUIRED = 427;
const TOO_MANY_REQUESTS = 429;
const REQUEST_HEADER_FIELDS_TOO_LARGE = 431;
const LOGIN_TIMEOUT = 440;
const NO_RESPONSE = 444;
const RETRY_WITH = 449;
const BLOCKED = 450;
const REDIRECT = 451;
const REQUEST_HEADER_TOO_LARGE = 494;
const CERT_ERROR = 495;
const NO_CERT = 496;
const HTTP_TO_HTTPS = 497;
const CLIENT_CLOSED_TO_REQUEST = 499;
const INTERNAL_SERVER_ERROR = 500;
const NOT_IMPLEMENTED = 501;
const BAD_GATEWAY = 502;
const SERVICE_UNAVAILABLE = 503;
const GATEWAY_TIMEOUT = 504;
const HTTP_VERSION_NOT_SUPPORTED = 505;
// todo modify 506 code
const VARIANT_ALSO_NEGOTIATES = 506;
const INSUFFICIENT_STORAGE = 506;
const LOOP_DETECTED = 508;
const BANDWIDTH_LIMIT_EXCEEDED = 509;
const NOT_EXTENDED = 510;
const NETWORK_AUTHENTICATION_REQUIRED = 511;
const NETWORK_READ_TIMEOUT = 598;
const NETWORK_CONNECT_TIMEOUT = 599;

class OutCome {
    private $outcome;
    private $response_code;

    public function getOutcome() {
        return $this->outcome;
    }

    public function setOutcome($outcome): void {
        $this->outcome = $outcome;
    }

    public function getResponseCode() {
        return $this->response_code;
    }

    public function setResponseCode($response_code): void {
        $this->response_code = $response_code;
    }
}

class RapisClient {
    private static $instance = null;
    private static $ch = null;
    public static $endpoints = null;
    private static $fcLogger = null;

    private function __construct() {
        self::$fcLogger = $GLOBALS['fcSysLogger'];

        $address = getenv("FC_RUNTIME_API") ? getenv("FC_RUNTIME_API") : "127.0.0.1:19001";
        self::$endpoints = array(
            'INIT' => $address . "/2020-11-11/runtime/init/error",
            'NEXT' => $address . "/2020-11-11/runtime/invocation/next",
            'RESULT' => $address . "/2020-11-11/runtime/invocation/"
        );
        self::$ch = curl_init();
        curl_setopt(self::$ch, CURLOPT_HTTPHEADER, array(
            'Connection: Keep-Alive'
        ));
        curl_setopt(self::$ch, CURLOPT_RETURNTRANSFER, 1);
    }

    function __destruct() {
        if (!is_null(self::$ch)) {
            curl_close(self::$ch);
            self::$ch = null;
        }
    }

    private static function readData($ch, $fp, $len, array &$ctx): string {
        if (is_null($ch)) {
            return "";
        }
        $data = substr($ctx['payload'], $ctx['pos'], $len);
        $ctx['pos'] += strlen($data);
        return $data;
    }

    private static function writeData($ch, $data, array &$resp): int {
        if (is_null($ch)) {
            return 0;
        }
        $resp['body'] .= $data;
        return strlen($data);
    }

    private static function writeHeader($ch, $rawHeader, array &$res): int {
        if (is_null($ch)) {
            return 0;
        }
        $header = explode(':', $rawHeader, 2);
        if (count($header) < 2) {
            return strlen($rawHeader);
        }
        $res['headers'][$header[0]] = trim($header[1]);
        return strlen($rawHeader);
    }

    public static function isSuccess($code): bool {
        return $code >= 200 && $code <= 299;
    }

    private static function set_curl_next_options(array &$resp) {
        curl_reset(self::$ch);
        curl_setopt(self::$ch, CURLOPT_TIMEOUT, 0);
        curl_setopt(self::$ch, CURLOPT_CONNECTTIMEOUT, 1);
        curl_setopt(self::$ch, CURLOPT_NOSIGNAL, 1);
        curl_setopt(self::$ch, CURLOPT_TCP_NODELAY, 1);
        curl_setopt(self::$ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);

        curl_setopt(self::$ch, CURLOPT_HTTPGET, 1);
        curl_setopt(self::$ch, CURLOPT_URL, self::$endpoints['NEXT']);

        curl_setopt(self::$ch, CURLOPT_WRITEFUNCTION, function($ch, $data) use(&$resp) {
            return self::writeData($ch, $data, $resp);
        });
        curl_setopt(self::$ch, CURLOPT_HEADERFUNCTION, function ($ch, $header) use(&$resp) {
            return self::writeHeader($ch, $header, $resp);
        });

        curl_setopt(self::$ch, CURLOPT_PROXY, "");
    }

    private static function set_curl_post_result_options(array &$resp) {
        curl_reset(self::$ch);
        curl_setopt(self::$ch, CURLOPT_TIMEOUT, 0);
        curl_setopt(self::$ch, CURLOPT_CONNECTTIMEOUT, 1);
        curl_setopt(self::$ch, CURLOPT_NOSIGNAL, 1);
        curl_setopt(self::$ch, CURLOPT_TCP_NODELAY, 1);
        curl_setopt(self::$ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);

        curl_setopt(self::$ch, CURLOPT_POST, 1);
        // set CURLOPT_READFUNCTION before send data
        curl_setopt(self::$ch, CURLOPT_WRITEFUNCTION, function($ch, $data) use(&$resp) {
            return self::writeData($ch, $data, $resp);
        });
        curl_setopt(self::$ch, CURLOPT_HEADERFUNCTION, function ($ch, $header) use(&$resp) {
            return self::writeHeader($ch, $header, $resp);
        });

        curl_setopt(self::$ch, CURLOPT_PROXY, "");
    }

    public static function getInstance(): ?RapisClient {
        if (self::$instance == null) {
            self::$instance = new RapisClient();
        }
        return self::$instance;
    }

    public static function get_next(): OutCome {
        $out_come = new OutCome();
        $resp = array(
            'headers' => array(), 'body' => '', 'content_type' => '', 'response_code' => 0,
        );
        self::set_curl_next_options($resp);

        curl_setopt(self::$ch, CURLOPT_HTTPHEADER, array(
            'User-Agent: fc_runtime_native/0.1.1'
        ));
        self::$fcLogger->info(LOG_TAG . " Making request to " . self::$endpoints['NEXT']);
        $curl_res = curl_exec(self::$ch);

        if ($curl_res == false) {
            self::$fcLogger->info(LOG_TAG . " Failed to get next invocation. code " . curl_errno(self::$ch) . " - " . curl_error(self::$ch));
            $out_come->setResponseCode(REQUEST_NOT_MADE);
            return $out_come;
        }

        $curl_info = curl_getinfo(self::$ch);
        $response_code = $curl_info['http_code'];
        $content_type = $curl_info['content_type'];
        $resp['response_code'] = $response_code;
        $resp['content_type'] = $content_type;

        if (!self::isSuccess($response_code)) {
            self::$fcLogger->error(
                LOG_TAG.
                "Failed to get next invocation. Http Response code: ".
                $response_code);
            $out_come->setResponseCode($response_code);
            return $out_come;
        }

        if (!array_key_exists(REQUEST_ID_HEADER, $resp['headers'])) {
            self::$fcLogger->error(LOG_TAG. "Failed to find header " . REQUEST_ID_HEADER . " in response");
            $out_come->setResponseCode(REQUEST_NOT_MADE);
            return $out_come;
        }
        
        $req = array(
            'headers' => array(), 'body' => '',
        );
        $req['body'] = $resp['body'];
        $req['headers'][REQUEST_ID_HEADER] = $resp['headers'][REQUEST_ID_HEADER];
        $req['headers']['Content-Type'] = $resp['content_type'];

        if (array_key_exists(TRACE_ID_HEADER, $resp['headers'])) {
            $req['headers'][TRACE_ID_HEADER] = $resp['headers'][TRACE_ID_HEADER];
        }
        if (array_key_exists(REQUEST_ACCESS_KEY_HEADER, $resp['headers'])) {
            $req['headers'][REQUEST_ACCESS_KEY_HEADER] = $resp['headers'][REQUEST_ACCESS_KEY_HEADER];
        }
        if (array_key_exists(REQUEST_ACCESS_SECRET_HEADER, $resp['headers'])) {
            $req['headers'][REQUEST_ACCESS_SECRET_HEADER] = $resp['headers'][REQUEST_ACCESS_SECRET_HEADER];
        }
        if (array_key_exists(REQUEST_SECURITY_TOKEN_HEADER, $resp['headers'])) {
            $req['headers'][REQUEST_SECURITY_TOKEN_HEADER] = $resp['headers'][REQUEST_SECURITY_TOKEN_HEADER];
        }
        if (array_key_exists(FUNCTION_NAME_HEADER, $resp['headers'])) {
            $req['headers'][FUNCTION_NAME_HEADER] = $resp['headers'][FUNCTION_NAME_HEADER];
        }
        if (array_key_exists(FUNCTION_HANDLER_HEADER, $resp['headers'])) {
            $req['headers'][FUNCTION_HANDLER_HEADER] = $resp['headers'][FUNCTION_HANDLER_HEADER];
        }
        if (array_key_exists(FUNCTION_MEMORY_HEADER, $resp['headers'])) {
            $req['headers'][FUNCTION_MEMORY_HEADER] = $resp['headers'][FUNCTION_MEMORY_HEADER];
        }

        // int values
        if (array_key_exists(FUNCTION_TYPE_HEADER, $resp['headers'])) {
            $req['headers'][FUNCTION_TYPE_HEADER] = intval($resp['headers'][FUNCTION_TYPE_HEADER]);
        }
        if (array_key_exists(FUNCTION_TIMEOUT_HEADER, $resp['headers'])) {
            $req['headers'][FUNCTION_TIMEOUT_HEADER] = intval($resp['headers'][FUNCTION_TIMEOUT_HEADER]);
        }
        if (array_key_exists(RETRY_COUNT, $resp['headers'])) {
            $req['headers'][RETRY_COUNT] = intval($resp['headers'][RETRY_COUNT]);
        }
        if (array_key_exists(FUNCTION_DEADLINE_HEADER, $resp['headers'])) {
            $ms = intval($resp['headers'][FUNCTION_DEADLINE_HEADER]);
            assert($ms > 0);
            assert($ms < PHP_INT_MAX);
            $resp['headers'][FUNCTION_DEADLINE_HEADER] = $ms;

            $MAX_PRINT_SIZE = 512;
            if (strlen($req['body']) <= $MAX_PRINT_SIZE){
                self::$fcLogger->info(
                    LOG_TAG.
                    "Received request_id: ". $req['headers'][REQUEST_ID_HEADER] .
                    ", payload: ". $req['body'] . ", Time remaining: " . strval($ms));
            }else {
                self::$fcLogger->info(
                    LOG_TAG.
                    "Received request_id: ". $req['headers'][REQUEST_ID_HEADER] .
                    ", payload: too large(". strval(strlen($req['body'])) . " >" .
                    strval($MAX_PRINT_SIZE) .") to display, Time remaining: " . strval($ms));
            }
        }
        if (array_key_exists(FUNCTION_VERSION_HEADER, $resp['headers'])) {
            $req['headers'][FUNCTION_VERSION_HEADER] = $resp['headers'][FUNCTION_VERSION_HEADER];
        }
        if (array_key_exists(FUNCTION_QUALIFIER_HEADER, $resp['headers'])) {
            $req['headers'][FUNCTION_QUALIFIER_HEADER] = $resp['headers'][FUNCTION_QUALIFIER_HEADER];
        }

        if (array_key_exists(SERVICE_NAME_HEADER, $resp['headers'])) {
            $req['headers'][SERVICE_NAME_HEADER] = $resp['headers'][SERVICE_NAME_HEADER];
        }
        if (array_key_exists(SERVICE_LOG_PROJECT, $resp['headers'])) {
            $req['headers'][SERVICE_LOG_PROJECT] = $resp['headers'][SERVICE_LOG_PROJECT];
        }
        if (array_key_exists(SERVICE_LOG_STORE, $resp['headers'])) {
            $req['headers'][SERVICE_LOG_STORE] = $resp['headers'][SERVICE_LOG_STORE];
        }
        if (array_key_exists(REGION_HEADER, $resp['headers'])) {
            $req['headers'][REGION_HEADER] = $resp['headers'][REGION_HEADER];
        }
        if (array_key_exists(ACCOUNT_ID_HEADER, $resp['headers'])) {
            $req['headers'][ACCOUNT_ID_HEADER] = $resp['headers'][ACCOUNT_ID_HEADER];
        }
        if (array_key_exists(CLIENT_IP_HEADER, $resp['headers'])) {
            $req['headers'][CLIENT_IP_HEADER] = $resp['headers'][CLIENT_IP_HEADER];
        }
        if (array_key_exists(TRACE_ID_HEADER, $resp['headers'])) {
            $req['headers'][TRACE_ID_HEADER] = $resp['headers'][TRACE_ID_HEADER];
        }
        if (array_key_exists(HTTP_PARAMS_HEADER, $resp['headers']) &&
            strlen($resp['headers'][HTTP_PARAMS_HEADER]) > 0) {
            $req['headers'][HTTP_PARAMS_HEADER] = $resp['headers'][HTTP_PARAMS_HEADER];
        }

        if (array_key_exists(OPENTRACING_SPAN_CONTEXT, $resp['headers'])) {
            $req['headers'][OPENTRACING_SPAN_CONTEXT] = $resp['headers'][OPENTRACING_SPAN_CONTEXT];
        }
        if (array_key_exists(OPENTRACING_SPAN_BAGGAGES, $resp['headers'])) {
            $req['headers'][OPENTRACING_SPAN_BAGGAGES] = $resp['headers'][OPENTRACING_SPAN_BAGGAGES];
        }
        if (array_key_exists(JAEGER_ENDPOINT, $resp['headers'])) {
            $req['headers'][JAEGER_ENDPOINT] = $resp['headers'][JAEGER_ENDPOINT];
        }

        $out_come->setOutcome($req);
        $out_come->setResponseCode($response_code);
        return $out_come;
    }

    public static function do_post(string $url, string $request_id, array &$response): OutCome {
        $out_come = new OutCome();
        $resp = array();
        self::set_curl_post_result_options($resp);

        curl_setopt(self::$ch, CURLOPT_URL, $url);
        self::$fcLogger->debug(LOG_TAG. "Making request to ". $url);

        $headers = array();
        if (!array_key_exists('content_type', $response) ||
            is_null($response['content_type']) ||
            strlen($response['content_type'] == 0)) {
            array_push($headers, "content-type: application/octet-stream");
        } else {
            array_push($headers, "content-type: ". $response['content_type']);
        }

        if (array_key_exists('http_params', $response) &&
            !is_null($response['http_params']) &&
            strlen($response['http_params'] > 0)) {
            array_push($headers, "x-fc-http-params: ". $response['http_params']);
        }

        array_push($headers, "fc-runtime-function-tracer-error-cause: ". $response['tracer_response']);
        array_push($headers, "Expect:");
        array_push($headers, "transfer-encoding:");
        array_push($headers, "User-Agent: fc_runtime_native/0.1.1");
        $payload = $response['payload'];
        $content_length = strval(strlen($payload));
        self::$fcLogger->debug(
            LOG_TAG. "calculating content length... content-length: " . $content_length);
        array_push($headers, "content-length: " . $content_length);

        $ctx = array('payload' => $payload, 'pos' => 0);
        curl_setopt(self::$ch, CURLOPT_READFUNCTION, function ($ch, $fp, $len) use(&$ctx) {
            return self::readData($ch, $fp, $len, $ctx);
        });
        curl_setopt(self::$ch, CURLOPT_HTTPHEADER, $headers);

        $curl_res = curl_exec(self::$ch);
        if ($curl_res == false) {
            self::$fcLogger->error(
                LOG_TAG.
                "CURL returned error code " . curl_errno(self::$ch) . " - " . curl_error(self::$ch) .
                ", for invocation " . $request_id . ", payload size " . $content_length);
            $out_come->setResponseCode(REQUEST_NOT_MADE);
            return $out_come;
        }

        $curl_info = curl_getinfo(self::$ch);
        $response_code = $curl_info['http_code'];
        $resp['response_code'] = $response_code;

        if (!self::isSuccess($response_code)) {
            self::$fcLogger->error(
                LOG_TAG.
                "Failed to post handler success response for invocation ".
                $request_id .". Http response code: ". strval($response_code) .
                ", payload size: ". $content_length);
            $out_come->setResponseCode($response_code);
            return $out_come;
        }

        $out_come->setResponseCode($response_code);
        return $out_come;
    }
}

function get_next() {
    $out_come = RapisClient::getInstance()::get_next();
    if (!RapisClient::isSuccess($out_come->getResponseCode())) {
        return "failed to get next";
    }
    return $out_come->getOutcome();
}

function post_invocation_response($request_id, &$resp, $content_type, $http_params) {
    $response = array(
        'payload' => $resp, 'content_type' => $content_type, 'success' => true,
        'http_params' => $http_params, 'tracer_response' => ''
    );
    $url = RapisClient::$endpoints['RESULT'] . $request_id . "/response";
    $out_come = RapisClient::do_post($url, $request_id, $response);
    if (!RapisClient::isSuccess($out_come->getResponseCode())) {
        return "failed to post_invocation_response";
    }
    return 0;
}

function post_error($request_id, $resp, $tracer_fault) {
    $url = RapisClient::$endpoints['RESULT'] . $request_id . "/error";
    $response = array(
        'payload' => $resp, 'content_type' => 'application/json', 'success' => false,
        'http_params' => '', 'tracer_response' => $tracer_fault,
    );
    $out_come = RapisClient::do_post($url, $request_id, $response);
    if (!RapisClient::isSuccess($out_come->getResponseCode())) {
        return "failed to post_error";
    }
    return 0;
}

function post_init_error(string $request_id, string $resp, string $tracer_fault) {
    $url = RapisClient::$endpoints['INIT'];
    $response = array(
        'payload' => $resp, 'content_type' => 'application/json', 'success' => false,
        'http_params' => '', 'tracer_response' => $tracer_fault,
    );
    $out_come = RapisClient::do_post($url, $request_id, $response);
    if (!RapisClient::isSuccess($out_come->getResponseCode())) {
        return "failed to post_error";
    }
    return 0;
}

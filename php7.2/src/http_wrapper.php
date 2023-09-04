<?php
namespace ServerlessFC;

use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\Http\Io\ServerRequest;
use RingCentral\Psr7\Uri;

final class HttpWrapper
{
    public static function createHttpRequest(string $body, array $fcHeaders, array $context): ServerRequestInterface
    {
        $functionName = array_key_exists(FC_FUNCTION_NAME, $fcHeaders) ? $fcHeaders[FC_HTTP_PARAMS] : 'unknown_function';
        $httpParams = array_key_exists(FC_HTTP_PARAMS, $fcHeaders) ? $fcHeaders[FC_HTTP_PARAMS] : NULL;
        if (is_null($httpParams)) {
            throw new \Exception($functionName."httpParams is necessary!");
        }
        $httpParamsJsonStr = base64_decode($httpParams);
        if(is_null($httpParamsJsonStr)) {
            $fcLogger = $GLOBALS['fcSysLogger'];
            $fcLogger->error("http params decode base64 error: " . $httpParams);
            throw new \Exception($functionName."http params not valid!");
        }
        $requestArr        = json_decode($httpParamsJsonStr, $assoc = true);  //json
        if(is_null($requestArr)) {
            $fcLogger = $GLOBALS['fcSysLogger'];
            $fcLogger->error("http params decode json error: " . $httpParamsJsonStr);
            throw new \Exception($functionName."http params not valid!");
        }
        $method     = array_key_exists('method', $requestArr) ? $requestArr['method'] : '';
        $path       = array_key_exists('path', $requestArr) ? $requestArr['path'] : '';
        $requestURI = array_key_exists('requestURI', $requestArr) ? $requestArr['requestURI'] : '';
        $queryString = "";
        if (strpos($requestURI, '?') !== false) {
            $queryString = explode('?', $requestURI, 2)[1];
        }
        $clientIP   = array_key_exists('clientIP', $requestArr) ? $requestArr['clientIP'] : '';
        $protocolVersion = 'HTTP/1.1';

        $serverParams = [
                    'SERVER_PROTOCOL' => $protocolVersion,
                    'SERVER_ADDR' => '127.0.0.1',
                    'SERVER_PORT' => '19001',
                    'REQUEST_METHOD' => $method ,
                    'REQUEST_TIME' => time(),
                    'REQUEST_TIME_FLOAT' => microtime(true),
                    'QUERY_STRING' => $queryString,
                    'DOCUMENT_ROOT' => getcwd(),
                    'REQUEST_URI' => $requestURI,
                    'REMOTE_ADDR' => $clientIP,
                ];

        $format  = '%s.%s.fc.aliyuncs.com%s';
        $url     = sprintf($format, $context['accountId'], $context['region'], $requestURI);
        $fcUri   = new Uri($url);

        // use client header to replace header
        // header: arr: (key(string) val(string))
        // headersMap: arr: (key(string) val(arr:string))
        // only use headers in headersMap, see python runtime
        $headerOfRequest = [];
        $headersMap = array_key_exists('headersMap', $requestArr) ? $requestArr['headersMap'] : [];
        foreach ($headersMap as $h => $v) {
            $headerOfRequest[$h] = $v;
        }
        if (isset($headerOfRequest['Host'])) {
            $serverParams['HTTP_HOST'] = $headerOfRequest['Host'];
        }

        // set default content-type
        // only compatible with old php 7 runtime http GET method
        if (explode('.', PHP_VERSION)[0] == '7' &&
            !array_key_exists('content-type', array_change_key_case($headerOfRequest))) {
            $headerOfRequest['Content-Type'] = 'application/octet-stream';
        }

        $bodyStream = Utils::streamFor($body);
        $bodyStream->rewind();

        $request = new ServerRequest(
            $method,
            $fcUri,
            $headerOfRequest,
            $bodyStream,
            $protocolVersion,
            $serverParams
        );

        $get_arr_first = function ($n) {
            if (is_array($n) && count($n) == 1) {
                return $n[0];
            } else {
                return $n;
            }
        };

        $queriesMap = array_key_exists('queriesMap', $requestArr) ? $requestArr['queriesMap'] : [];
        if ($queriesMap) {
            $queries = array_map($get_arr_first, $queriesMap);
            $request = $request->withQueryParams($queries);
        }

        $request = $request->withAttribute("clientIP", $clientIP)
            ->withAttribute("requestURI", $requestURI)
            ->withAttribute("path", $path);

        return $request;
    }


    public static function parserOutResult(ServerRequestInterface $request, ResponseInterface $response): array
    {
        // return early and close response body if connection is already closed
        $body = $response->getBody();
        $method = $request->getMethod();

        // assign HTTP protocol version from request automatically
        $version = $request->getProtocolVersion();
        $response = $response->withProtocolVersion($version);

        // assign default "X-Powered-By" header automatically
        if (!$response->hasHeader('X-Powered-By')) {
            $response = $response->withHeader('X-Powered-By', 'fc/php-runtime');
        } elseif ($response->getHeaderLine('X-Powered-By') === ''){
            $response = $response->withoutHeader('X-Powered-By');
        }

        // assign default "Date" header from current time automatically
        if (!$response->hasHeader('Date')) {
            // IMF-fixdate  = day-name "," SP date1 SP time-of-day SP GMT
            $response = $response->withHeader('Date', gmdate('D, d M Y H:i:s') . ' GMT');
        } elseif ($response->getHeaderLine('Date') === ''){
            $response = $response->withoutHeader('Date');
        }

        // assign "Content-Length" and "Transfer-Encoding" headers automatically
        $code = $response->getStatusCode();
        $chunked = false;
        if (($method === 'CONNECT' && $code >= 200 && $code < 300) || ($code >= 100 && $code < 200) || $code === 204) {
            // 2xx response to CONNECT and 1xx and 204 MUST NOT include Content-Length or Transfer-Encoding header
            $response = $response->withoutHeader('Content-Length')->withoutHeader('Transfer-Encoding');
        } elseif (!$body instanceof HttpBodyStream) {
            // assign Content-Length header when using a "normal" buffered body string
            $response = $response->withHeader('Content-Length', (string)$body->getSize())->withoutHeader('Transfer-Encoding');
        } elseif (!$response->hasHeader('Content-Length') && $version === '1.1') {
            // assign chunked transfer-encoding if no 'content-length' is given for HTTP/1.1 responses
            $response = $response->withHeader('Transfer-Encoding', 'chunked');
            $chunked = true;
        } else {
            // remove any Transfer-Encoding headers unless automatically enabled above
            $response = $response->withoutHeader('Transfer-Encoding');
        }

        // assign "Connection" header automatically
        if ($code === 101) {
            // 101 (Switching Protocols) response uses Connection: upgrade header
            // used for upgrade to websocket
            $response = $response->withHeader('Connection', 'upgrade');
        } elseif ($version === '1.1') {
            // HTTP/1.1 assumes persistent connection support by default
            // we do not support persistent connections, so let the client know
            // todo: we will support persist transfer
            // $response = $response->withHeader('Connection', 'close');
        } else {
            // remove any Connection headers unless automatically enabled above
            // this may conflict with old runtime, so it is removed
            // $response = $response->withoutHeader('Connection');
        }

        global $fcSysLogger;
        if ($chunked) {
             echo $GLOBALS['requestId'] . "Warning: Transfer-Encoding used but not support" . PHP_EOL;
             $fcSysLogger->info($GLOBALS['requestId'] . "Warning: Transfer-Encoding used but not support" . PHP_EOL);
        }

        $headers = $response->getHeaders();
        // response to HEAD and 1xx, 204 and 304 responses MUST NOT include a body
        // exclude status 101 (Switching Protocols) here for Upgrade request handling above
        if ($method === 'HEAD' || $code === 100 || ($code > 101 && $code < 200) || $code === 204 || $code === 304) {
            $body = '';
        } else {
            $body = $response->getBody()->getContents();
        }
        //$response->getBody()->rewind();

        $code = $response->getStatusCode();
        //TODO, actually it is headers, not headersMap here
        $respParams  = array(
            'status'     => $code,
            'headersMap' => $headers,
        );

        $paramsStr = json_encode($respParams);
        $httpParams = base64_encode($paramsStr);

        $responseHeaders = [];
        $responseHeaders[FC_HTTP_PARAMS] = $httpParams;

        return array(
            'httpParams' => $httpParams,
            'body' => $body,
        );
    }

}
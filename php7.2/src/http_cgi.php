<?php
namespace ServerlessFC;

use RingCentral\Psr7\Response;
use ServerlessFC\FastCGI\Client;

/*
 * List of mime types for common file extensions
 * https://www.sitepoint.com/mime-types-complete-list/
 * https://developer.mozilla.org/en-US/docs/Web/HTTP/Basics_of_HTTP/MIME_types/Common_types
 */

const MIME_TYPES = array(
    "323"     => "text/h323",
    "acx"     => "application/internet-property-stream",
    "ai"      => "application/postscript",
    "aif"     => "audio/x-aiff",
    "aifc"    => "audio/x-aiff",
    "aiff"    => "audio/x-aiff",
    'apk'     => "application/vnd.android.package-archive",
    "asf"     => "video/x-ms-asf",
    "asr"     => "video/x-ms-asf",
    "asx"     => "video/x-ms-asf",
    "au"      => "audio/basic",
    "avi"     => "video/quicktime",
    "axs"     => "application/olescript",
    "bas"     => "text/plain",
    "bcpio"   => "application/x-bcpio",
    "bin"     => "application/octet-stream",
    "bmp"     => "image/bmp",
    "c"       => "text/plain",
    "cat"     => "application/vnd.ms-pkiseccat",
    "cdf"     => "application/x-cdf",
    "cer"     => "application/x-x509-ca-cert",
    "class"   => "application/octet-stream",
    "clp"     => "application/x-msclip",
    "cmx"     => "image/x-cmx",
    "cod"     => "image/cis-cod",
    "cpio"    => "application/x-cpio",
    "crd"     => "application/x-mscardfile",
    "crl"     => "application/pkix-crl",
    "crt"     => "application/x-x509-ca-cert",
    "csh"     => "application/x-csh",
    "css"     => "text/css",
    "dcr"     => "application/x-director",
    "der"     => "application/x-x509-ca-cert",
    "dir"     => "application/x-director",
    "dll"     => "application/x-msdownload",
    "dms"     => "application/octet-stream",
    "doc"     => "application/msword",
    "dot"     => "application/msword",
    "dvi"     => "application/x-dvi",
    "dxr"     => "application/x-director",
    "eps"     => "application/postscript",
    "etx"     => "text/x-setext",
    "evy"     => "application/envoy",
    "exe"     => "application/octet-stream",
    "fif"     => "application/fractals",
    "flr"     => "x-world/x-vrml",
    "gif"     => "image/gif",
    "gtar"    => "application/x-gtar",
    "gz"      => "application/x-gzip",
    "h"       => "text/plain",
    "hdf"     => "application/x-hdf",
    "hlp"     => "application/winhlp",
    "hqx"     => "application/mac-binhex40",
    "hta"     => "application/hta",
    "htc"     => "text/x-component",
    "htm"     => "text/html",
    "html"    => "text/html",
    "htt"     => "text/webviewhtml",
    "ico"     => "image/x-icon",
    "ief"     => "image/ief",
    "iii"     => "application/x-iphone",
    "ins"     => "application/x-internet-signup",
    "isp"     => "application/x-internet-signup",
    "jfif"    => "image/pipeg",
    "jpe"     => "image/jpeg",
    "jpeg"    => "image/jpeg",
    "jpg"     => "image/jpeg",
    "js"      => "application/x-javascript",
    "latex"   => "application/x-latex",
    "lha"     => "application/octet-stream",
    "lsf"     => "video/x-la-asf",
    "lsx"     => "video/x-la-asf",
    "lzh"     => "application/octet-stream",
    "m13"     => "application/x-msmediaview",
    "m14"     => "application/x-msmediaview",
    "m3u"     => "audio/x-mpegurl",
    "man"     => "application/x-troff-man",
    "mdb"     => "application/x-msaccess",
    "me"      => "application/x-troff-me",
    "mht"     => "message/rfc822",
    "mhtml"   => "message/rfc822",
    "mid"     => "audio/mid",
    "mny"     => "application/x-msmoney",
    "mov"     => "video/quicktime",
    "movie"   => "video/x-sgi-movie",
    "mp2"     => "video/mpeg",
    "mp3"     => "audio/mpeg",
    'mp4'     => 'video/mp4',
    "mpa"     => "video/mpeg",
    "mpe"     => "video/mpeg",
    "mpeg"    => "video/mpeg",
    "mpg"     => "video/mpeg",
    "mpp"     => "application/vnd.ms-project",
    "mpv2"    => "video/mpeg",
    "ms"      => "application/x-troff-ms",
    "mvb"     => "application/x-msmediaview",
    "nws"     => "message/rfc822",
    "oda"     => "application/oda",
    'ogg'     => 'video/ogg',
    'ogv'     => 'video/ogg',
    "p10"     => "application/pkcs10",
    "p12"     => "application/x-pkcs12",
    "p7b"     => "application/x-pkcs7-certificates",
    "p7c"     => "application/x-pkcs7-mime",
    "p7m"     => "application/x-pkcs7-mime",
    "p7r"     => "application/x-pkcs7-certreqresp",
    "p7s"     => "application/x-pkcs7-signature",
    "pbm"     => "image/x-portable-bitmap",
    "pdf"     => "application/pdf",
    "pfx"     => "application/x-pkcs12",
    "pgm"     => "image/x-portable-graymap",
    "pko"     => "application/ynd.ms-pkipko",
    "pma"     => "application/x-perfmon",
    "pmc"     => "application/x-perfmon",
    "pml"     => "application/x-perfmon",
    "pmr"     => "application/x-perfmon",
    "pmw"     => "application/x-perfmon",
    "png"     => "image/png",
    "pnm"     => "image/x-portable-anymap",
    "pot"     => "application/vnd.ms-powerpoint",
    "ppm"     => "image/x-portable-pixmap",
    "pps"     => "application/vnd.ms-powerpoint",
    "ppt"     => "application/vnd.ms-powerpoint",
    "prf"     => "application/pics-rules",
    "ps"      => "application/postscript",
    "pub"     => "application/x-mspublisher",
    "qt"      => "video/quicktime",
    "ra"      => "audio/x-pn-realaudio",
    "ram"     => "audio/x-pn-realaudio",
    "ras"     => "image/x-cmu-raster",
    "rgb"     => "image/x-rgb",
    "rmi"     => "audio/mid",
    "roff"    => "application/x-troff",
    "rtf"     => "application/rtf",
    "rtx"     => "text/richtext",
    "scd"     => "application/x-msschedule",
    "sct"     => "text/scriptlet",
    "setpay"  => "application/set-payment-initiation",
    "setreg"  => "application/set-registration-initiation",
    "sh"      => "application/x-sh",
    "shar"    => "application/x-shar",
    "sit"     => "application/x-stuffit",
    "snd"     => "audio/basic",
    "spc"     => "application/x-pkcs7-certificates",
    "spl"     => "application/futuresplash",
    "src"     => "application/x-wais-source",
    "sst"     => "application/vnd.ms-pkicertstore",
    "stl"     => "application/vnd.ms-pkistl",
    "stm"     => "text/html",
    "svg"     => "image/svg+xml",
    "sv4cpio" => "application/x-sv4cpio",
    "sv4crc"  => "application/x-sv4crc",
    "t"       => "application/x-troff",
    "tar"     => "application/x-tar",
    "tcl"     => "application/x-tcl",
    "tex"     => "application/x-tex",
    "texi"    => "application/x-texinfo",
    "texinfo" => "application/x-texinfo",
    "tgz"     => "application/x-compressed",
    "tif"     => "image/tiff",
    "tiff"    => "image/tiff",
    "tr"      => "application/x-troff",
    "trm"     => "application/x-msterminal",
    "tsv"     => "text/tab-separated-values",
    "txt"     => "text/plain",
    "uls"     => "text/iuls",
    "ustar"   => "application/x-ustar",
    "vcf"     => "text/x-vcard",
    "vrml"    => "x-world/x-vrml",
    "wav"     => "audio/x-wav",
    "wcm"     => "application/vnd.ms-works",
    "wdb"     => "application/vnd.ms-works",
    'webm'    => 'video/webm',
    "wks"     => "application/vnd.ms-works",
    "wmf"     => "application/x-msmetafile",
    "wps"     => "application/vnd.ms-works",
    "wri"     => "application/x-mswrite",
    "wrl"     => "x-world/x-vrml",
    "wrz"     => "x-world/x-vrml",
    "xaf"     => "x-world/x-vrml",
    "xbm"     => "image/x-xbitmap",
    "xla"     => "application/vnd.ms-excel",
    "xlc"     => "application/vnd.ms-excel",
    "xlm"     => "application/vnd.ms-excel",
    "xls"     => "application/vnd.ms-excel",
    "xlt"     => "application/vnd.ms-excel",
    "xlw"     => "application/vnd.ms-excel",
    "xof"     => "x-world/x-vrml",
    "xpm"     => "image/x-xpixmap",
    "xwd"     => "image/x-xwindowdump",
    "z"       => "application/x-compress",
    "zip"     => "application/zip",
    "ttf"     => "font/ttf",
    "docx"    => "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
    "xlsx"    => "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
    "pptx"    => "application/vnd.openxmlformats-officedocument.presentationml.presentation",

);

class PhpCgiProxy {

    private $is_start_php_cgi = false;
    public function getMimeType($filename) {
        $pathinfo  = pathinfo($filename);
        $extension = strtolower($pathinfo['extension']);
        return MIME_TYPES[$extension];
    }

    private function tryStartPhpCgi() {
        if ($this->is_start_php_cgi) {
            return;
        }

        // try to start php-cgi
        exec("ps aux | grep /var/fc/lang/php7.2/bin/php-cgi | grep -v grep", $output, $return_var);
        if (count($output) == 1) {
            // Forced to restart
            exec("kill -s 9 `ps -aux | grep /var/fc/lang/php7.2/bin/php-cgi | awk '{print $2}'`");
        }

        $cmd = sprintf("nohup /var/fc/lang/php7.2/bin/php-cgi -b 127.0.0.1:%s > /tmp/fc-php-cgi.log 2>&1 &", FC_PHP_CGI__PORT);
        exec($cmd, $output, $status);
        if ($status != 0) {
            $outputStr = json_encode($output, JSON_UNESCAPED_UNICODE);
            $logstr    = file_get_contents("/tmp/fc-php-cgi.log");
            $infoStr   = "status: " . $status . "; \n output: " . $outputStr . ";\n php-cgi output: " . $logstr;
            echo $infoStr . PHP_EOL;
            $GLOBALS['fcSysLogger']->error($infoStr);
        } else {
            usleep(1200000);
            $this->is_start_php_cgi = true;
        }
    }

    // request php-cgi and get stdout
    public function requestPhpCgi($request, $docRoot, $phpFile = "index.php", $fastCgiParams = [], $options = []): Response{
        $this->tryStartPhpCgi();

        $stdin      = $request->getBody()->getContents();
        $uri        = $request->getUri();
        $requestURI = $request->getAttribute("requestURI");
        $tmp        = explode('?', $requestURI);
        $tmp        = explode('/', $tmp[0]);
        $m          = 5; // default: /2016-08-15/proxy/f-service/f-func/
        for ($i = count($tmp) - 1; $i >= 0; $i--) {
            if (endsWith($tmp[$i], '.php')) {
                $m = $i + 1;
                break;
            }
        }
        $scriptName = implode('/', array_slice($tmp, 0, $m));

        if (!endsWith($scriptName, ".php")) {
            $scriptName = $scriptName . '/' . $phpFile;
        }

        $defaultCgiParams = array(
            'GATEWAY_INTERFACE' => FC_CGI_GATEWAY_INTERFACE,
            'SERVER_SOFTWARE'   => FC_CGI_SERVER_SOFTWARE,
            'DOCUMENT_ROOT'     => $docRoot,
            'SCRIPT_FILENAME'   => $docRoot . $scriptName,
            'SCRIPT_NAME'       => $scriptName,
            'REQUEST_URI'       => $requestURI,
            'QUERY_STRING'      => $uri->getQuery(),
            'REQUEST_METHOD'    => $request->getMethod(),
            'CONTENT_TYPE'      => $request->getHeaderLine('CONTENT-TYPE'),
            'CONTENT_LENGTH'    => $request->getHeaderLine('CONTENT-LENGTH'),
            'REMOTE_ADDR'       => $request->getAttribute("clientIP"),
            'REMOTE_PORT'       => FC_CGI_REMOTE_PORT,
            'SERVER_ADDR'       => FC_CGI_SERVER_ADDR,
            'SERVER_PORT'       => FC_CGI_SERVER_PORT,
            'SERVER_PROTOCOL'   => FC_CGI_SERVER_PROTOCOL,
        );

        // todo: check why add this in old runtime
        $request = $request->withoutHeader('CONTENT-TYPE')
            ->withoutHeader('CONTENT-LENGTH');

        // http://php.net/manual/en/reserved.variables.server.php
        $requestHeaders = $request->getHeaders();
        foreach ($requestHeaders as $h => $v) {
            $value                   = implode(', ', $v);
            $name                    = str_replace("-", "_", strtoupper($h));
            $name                    = 'HTTP_' . $name;
            $defaultCgiParams[$name] = $value;
        }

        $cgiParams = array_merge($defaultCgiParams, $fastCgiParams);

        if (isset($options['debug_show_cgi_params']) && $options['debug_show_cgi_params']) {
            var_export(json_encode($cgiParams));
        }
        try {
            $fcFastCgiClient  = new Client('127.0.0.1', FC_PHP_CGI__PORT);
            $readWriteTimeout = isset($options['readWriteTimeout']) ? $options['readWriteTimeout'] : 5000;
            $fcFastCgiClient->setReadWriteTimeout($readWriteTimeout);

            $rawResponse = $fcFastCgiClient->request($cgiParams, $stdin);
            $headers = [];
            $body    = '';

            $HEADER_PATTERN = '#^([^\:]+):(.*)$#';
            $lines          = explode(PHP_EOL, $rawResponse);
            $offset         = 0;
            foreach ($lines as $i => $line) {
                if (preg_match($HEADER_PATTERN, $line, $matches)) {
                    $offset = $i;
                    $key    = trim($matches[1]);
                    $value  = trim($matches[2]);
                    if (array_key_exists($key, $headers)) {
                        $headers[$key][] = $value;
                    } else {
                        $headers[$key] = array($value);
                    }
                    continue;
                }
                break;
            }

            $body = implode(PHP_EOL, \array_slice($lines, $offset + 2));

            $status = isset($headers['Status']) ? $headers['Status'][0] : '200 OK';

            $status = explode(" ", $status);

            $statusCode = intval($status[0]);

            unset($headers['Status']);

            return new Response($statusCode, $headers, $body);

        } catch (Exception $e) {
            // try to restart whatever went wrong
            $this->is_start_php_cgi = false;

            $logstr = file_get_contents("/tmp/fc-php-cgi.log");
            echo "php-cgi output: " . $logstr . PHP_EOL . PHP_EOL;
            $GLOBALS['fcSysLogger']->error("php-cgi output: " . $logstr);

            $traceStr = $e->getTraceAsString();
            $pos      = strpos($traceStr, "/var/fc/runtime/php7.2/src/bootstrap.php");
            $traceStr = substr($traceStr, 0, $pos);

            $err = array(
                "errorMessage" => $e->getMessage(),
                "errorType"    => get_class($e),
                "stackTrace"   => array(
                    "file"        => $e->getFile(),
                    "line"        => $e->getLine(),
                    "traceString" => $traceStr,
                ),
            );

            var_export($err);
            $errStr   = json_encode($err, JSON_UNESCAPED_UNICODE);
            $fcLogger = $GLOBALS['fcSysLogger'];
            $fcLogger->error($errStr);

            return new Response(
                404,
                array(
                    'Content-Type'   => 'application/octet-stream',
                    'Content-Length' => strlen($errStr),
                    'Connection'     => 'keep-alive',
                ),
                $errStr
            );
        }
    }
}
<?php
namespace ServerlessFC;

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

$fcLogger      = null;
$fcSysLogger   = null;
$fcPhpCgiProxy = null;

class FcLineFormatter extends LineFormatter {
    public function format(array $record) {
        $vars   = parent::format($record);
        $output = str_replace('[]', $GLOBALS['requestId'], $vars);
        return $output;
    }
}

class FcSysLogFormatter extends LineFormatter {
    public function format(array $record) {
        $vars   = parent::format($record);
        $id = array_key_exists('requestId', $GLOBALS) ? $GLOBALS['requestId'] : "dummyId";
        $output = str_replace('[]', $id, $vars);

        $frameType = pack('N', FC_LOG_FRAME_TYPE);
        $logLen = mb_strlen($output, '8bit');
        $logLenBytes = pack('N', $logLen);

        $log_msg = $frameType.$logLenBytes.$output;
        return $log_msg;
    }
}

class FcLogger extends Logger {
    public function setLevel($level) {
        $handlers = $this->getHandlers();
        foreach ($handlers as &$h) {
            $h->setLevel($level);
        }
    }
}

function init_logging($out = 'php://output') {
    // for users
    $output        = "%datetime% %context% [%level_name%] %message%\n";
    $formatter     = new FcLineFormatter($output, "Y-m-d\TH:i:s\Z");
    $streamHandler = new StreamHandler($out, Logger::INFO);
    $streamHandler->setFormatter($formatter);
    // add setLevel method in logger/vendor/momolog/src/Logger.php
    global $fcLogger;
    $fcLogger = new FcLogger('fcLogger');
    $fcLogger->pushHandler($streamHandler);

    $sysOutput        = "%datetime% [%level_name%] %message%\n";
    $sysFormatter     = new FcSysLogFormatter($sysOutput, "Y-m-d H:i:s.u");
    $sysLogStream = sprintf("php://fd/%s", getenv('_FC_LOG_FD'));
    $sysStreamHandler = new StreamHandler($sysLogStream, Logger::INFO);
    $sysStreamHandler->setFormatter($sysFormatter);
    global $fcSysLogger;
    $fcSysLogger = new Logger('fcSysLogger');
    $fcSysLogger->pushHandler($sysStreamHandler);
}

function sanitize_envs() {
    foreach ($_ENV as $key => $val) {
        if (startsWith($key, "FC_") and (!in_array($key, FC_SAFE_ENV))) {
            unset($_ENV[$key]);
        }
    }
    // todo: compatible with newer runtime
    putenv("FC_SERVER_PATH=/var/fc/runtime/php7.2");
    $_SERVER['FC_SERVER_PATH'] = '/var/fc/runtime/php7.2';
    $_ENV['FC_SERVER_PATH'] = '/var/fc/runtime/php7.2';
}

function init_fc_cgi_proxy() {
    global $fcPhpCgiProxy;
    $fcPhpCgiProxy = new PhpCgiProxy();
}

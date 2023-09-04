<?php
namespace ServerlessFC;

// https://aone.alibaba-inc.com/issue/19237985, Make sure the server starts
// https://www.php.net/manual/zh/function.dl.php

$ext_arr = array(
    "json",
    "ctype",
    "bcmath",
    "bz2",
    "mbstring",
);
foreach ($ext_arr as $ext) {
    if (!extension_loaded($ext)) {
        $so = $ext . ".so";
        dl($so);
    }
}

// https://work.aone.alibaba-inc.com/issue/23453185
require "autoload.php";

sanitize_envs();
init_logging();
init_fc_cgi_proxy();

echo 'FunctionCompute php7.2 runtime inited.' . PHP_EOL;
$GLOBALS['fcSysLogger']->info("FunctionCompute php7.2 runtime inited.");

$handler = new InvocationHandler;

$mem_limit = getenv("FC_FUNCTION_MEMORY_SIZE");
if (intval($mem_limit) > 3*1024) {
  ini_set('memory_limit', getenv("FC_FUNCTION_MEMORY_SIZE").'M');
}

// reserved the workspace path and change to rootfs for checkpoint/restore
$GLOBALS["CreateFromSnapshot"] = false;
$snap_env = getenv("FC_SNAPSHOT_FLAG");
if (is_string($snap_env) && $snap_env == "1") {
    $GLOBALS["CreateFromSnapshot"] = true;
}
$GLOBALS["defaultWorkDir"] = getcwd();
if ($GLOBALS["CreateFromSnapshot"]) {
    $GLOBALS['fcSysLogger']->info("During snapshot creating");
    chdir("/");
}

while (true) {
    try {
        $handler->handleInvocation();
    } catch (\Throwable $e) {
        $fcLogger = $GLOBALS['fcSysLogger'];
        $fcLogger->error($GLOBALS['requestId'] . "should not reach here");
        $handler->returnError($GLOBALS['requestId'], $e);
    } finally {
        unset($GLOBALS['requestId']);
    }
}

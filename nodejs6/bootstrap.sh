#!/bin/bash
exec /var/fc/lang/nodejs6/bin/node --max-old-space-size=$(expr $FC_FUNCTION_MEMORY_SIZE \* 9 / 10 )  --max-http-header-size=16384 --expose-gc /var/fc/runtime/nodejs6/bootstrap.js 2>&1
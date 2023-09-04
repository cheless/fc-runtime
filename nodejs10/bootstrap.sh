#!/bin/bash
exec /var/fc/lang/nodejs10/bin/node --max-old-space-size=$(expr $FC_FUNCTION_MEMORY_SIZE \* 9 / 10 )  --max-http-header-size=16384 --expose-gc /var/fc/runtime/nodejs10/bootstrap.js

#if did not set --max-old-space-size option, boot time will reduce about 50ms
#exec /var/fc/lang/nodejs12/bin/node  /var/fc/runtime/nodejs12/bootstrap.js

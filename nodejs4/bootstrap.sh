#!/bin/bash
exec /var/fc/lang/nodejs4/bin/node --max-old-space-size=$(expr $FC_FUNCTION_MEMORY_SIZE \* 9 / 10 )  /var/fc/runtime/nodejs4/bootstrap.js 2>&1


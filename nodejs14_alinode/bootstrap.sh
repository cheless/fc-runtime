#!/bin/bash
bootstrap_wrapper="$BOOTSTRAP_WRAPPER"
if [ -z $bootstrap_wrapper ]; then
  exec /var/fc/lang/nodejs14_alinode/bin/node --max-old-space-size=$(expr $FC_FUNCTION_MEMORY_SIZE \* 9 / 10 )  --max-http-header-size=16384  --expose-gc /var/fc/runtime/nodejs14_alinode/bootstrap.js
else
  exec $bootstrap_wrapper /var/fc/lang/nodejs14_alinode/bin/node --max-old-space-size=$(expr $FC_FUNCTION_MEMORY_SIZE \* 9 / 10 )  --max-http-header-size=16384  --expose-gc /var/fc/runtime/nodejs14_alinode/bootstrap.js
fi





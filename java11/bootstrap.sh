#!/bin/bash

bootstrap_wrapper="$BOOTSTRAP_WRAPPER"
# Save FC_* vars in local vars with keys lower-cased
fc_envs=`/usr/bin/env | /bin/grep -e ^FC_`
while read ent;
do
    key=$(echo $ent | /usr/bin/cut -d= -f1)
    value="$(echo $ent | /usr/bin/cut -d= -f2)"
    eval "$(echo $key | /usr/bin/tr 'A-Z' 'a-z')=\"$value\""
done <<< $fc_envs


params=" "
if [[ -n "${JAVA_TOOL_OPTIONS}" ]]; then
    params+="${JAVA_TOOL_OPTIONS} "
    DISABLE_JAVA11_QUICKSTART=true
fi

if [[ -z ${fc_extensions_arms_app_name} ]]; then
  fc_extensions_arms_app_name="FC:${fc_service_name}/${fc_function_name}"
fi

if [[ -n "${fc_extensions_arms_license_key}" ]]; then
    params+="-Darms.licenseKey=${fc_extensions_arms_license_key} "
    params+="-Darms.appName=${fc_extensions_arms_app_name} "
    params+="-DJM.LOG.PATH=/tmp/ "
    params+="-Xshare:off "
fi

if [[ "${DISABLE_JAVA11_QUICKSTART}" != "true" ]]; then
    params+="-Xquickstart:path=/var/fc/runtime/java11/alibaba.quickstart.sharedcache "
    params+="-Xlog:quickstart:file=/tmp/alibaba.quickstart.log "
    params+="-XX:+IgnoreAppCDSDirCheck "
fi

if [ -z $bootstrap_wrapper ]; then
    exec /var/fc/lang/java11/bin/java ${params} \
        -Xmx${fc_max_server_heap_size} -Xms${fc_min_server_heap_size} \
        -XX:+UseSerialGC \
        -Xshare:on \
        -Djava.security.egd=file:/dev/./urandom \
        --add-opens java.base/java.io=ALL-UNNAMED \
        -Dfc.runtime.rapis.client.lib.path=/var/fc/runtime/java11/_native_rapis_client.so \
        -Dfc.func.code.path=/code/ \
        -Dfile.encoding=UTF-8 \
        -Dsun.jnu.encoding=UTF-8 \
        -cp /var/fc/runtime/java11 com.aliyun.serverless.runtime.Bootstrap
else
    params=""
    exec $bootstrap_wrapper /var/fc/lang/java11/bin/java ${params} \
        -Xmx${fc_max_server_heap_size} -Xms${fc_min_server_heap_size} \
        -XX:+UseSerialGC \
        -Xshare:on \
        -Djava.security.egd=file:/dev/./urandom \
        --add-opens java.base/java.io=ALL-UNNAMED \
        -Dfc.runtime.rapis.client.lib.path=/var/fc/runtime/java11/_native_rapis_client.so \
        -Dfc.func.code.path=/code/ \
        -Dfile.encoding=UTF-8 \
        -Dsun.jnu.encoding=UTF-8 \
        -cp /var/fc/runtime/java11 com.aliyun.serverless.runtime.Bootstrap
fi

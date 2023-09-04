#!/bin/bash

# Save FC_* vars in local vars with keys lower-cased
for ent in $(/usr/bin/env | /bin/grep -e ^FC_); do
    key=$(echo $ent | /usr/bin/cut -d= -f1)
    value=$(echo $ent | /usr/bin/cut -d= -f2)
    eval "$(echo $key | /usr/bin/tr 'A-Z' 'a-z')=$value"
done

params=" "

# ARMS agent is incompatible with shared archive
# see https://docs.oracle.com/javase/8/docs/technotes/guides/vm/class-data-sharing.html
if [[ -n "${fc_extensions_arms_license_key}" ]]; then
    params+="-Dfc.instanceId=$HOSTNAME@`hostname -i` "
    # arms agent path
    if [[ -n "${JAVA_TOOL_OPTIONS}" ]]; then
        params+="${JAVA_TOOL_OPTIONS} "
    else
        params+="-javaagent:/var/fc-extension/java8-arms-agent/ArmsAgent/arms-bootstrap-1.7.0-SNAPSHOT.jar "
    fi
    params+="-Darms.licenseKey=${fc_extensions_arms_license_key} "
    if [[ -z ${fc_extensions_arms_app_name} ]]; then
      fc_extensions_arms_app_name="FC:${fc_service_name}/${fc_function_name}"
    fi
    params+="-Darms.appName=${fc_extensions_arms_app_name} "
    params+="-DJM.LOG.PATH=/tmp/ "
    params+="-Xshare:off "
else
    if [[ -n "${JAVA_TOOL_OPTIONS}" ]]; then
        params+="${JAVA_TOOL_OPTIONS} "
    fi
    params+="-Xshare:on "
fi

exec /var/fc/lang/java8/bin/java ${params} \
    -Xmx${fc_max_server_heap_size} -Xms${fc_min_server_heap_size} \
    -XX:+UseSerialGC \
    -Djava.security.egd=file:/dev/./urandom \
    -Dfc.runtime.rapis.client.lib.path=/var/fc/runtime/java8/_native_rapis_client.so \
    -Dfc.func.code.path=/code/ \
    -cp /var/fc/runtime/java8 com.aliyun.serverless.runtime.Bootstrap

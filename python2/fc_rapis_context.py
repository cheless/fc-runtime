# -*- coding: utf-8 -*-

import json


class Credentials:
    def __init__(self, access_key_id, access_key_secret, security_token):
        self.accessKeyId = access_key_id
        self.accessKeySecret = access_key_secret
        self.securityToken = security_token
        self.access_key_id = access_key_id
        self.access_key_secret = access_key_secret
        self.security_token = security_token

    def to_dict(self):
        return  {
            "accessKeyId": self.access_key_id,
            "accessKeySecret": self.access_key_secret,
            "securityToken": self.security_token
        }

class ServiceMeta:
    def __init__(self, service_name, log_project, log_store, qualifier, version_id):
        self.name = service_name
        self.log_project = log_project
        self.log_store = log_store
        self.qualifier = qualifier
        self.version_id = version_id

    def to_dict(self):
        return {
            "name": self.name,
            "logProject": self.log_project,
            "logStore": self.log_store,
            "qualifier": self.qualifier,
            "versionId": self.version_id,
        }

class FunctionMeta:
    def __init__(self, name, handler, memory, timeout):
        self.name = name
        self.handler = handler
        self.memory = memory
        self.timeout = timeout

    def to_dict(self):
        return {
            "name": self.name,
            "handler": self.handler,
            "memory": self.memory,
            "timeout": self.timeout,
        }

class Tracing:
    def __init__(self, span_context, base64_baggages, jaeger_endpoint):
        self.span_context = span_context
        self.jaeger_endpoint = jaeger_endpoint
        self.span_baggages = self.parseOpenTracingBaggages(base64_baggages)

    def parseOpenTracingBaggages(self, base64_baggages):
        span_baggages = {}
        # None || '' returns false
        if base64_baggages:
            try:
                import base64
                str_baggages = base64.b64decode(base64_baggages)
                span_baggages = json.loads(str_baggages)
            except Exception as e:
                import logging
                fc_sys_logger = logging.getLogger('fc_sys_logger')
                fc_sys_logger.error('Failed to parse base64 opentracing baggages:[{}], err: {}'.format(base64_baggages, e))
        return span_baggages

    def to_dict(self):
        return {
            "openTracingSpanContext": self.span_context,
            "openTracingSpanBaggages": self.span_baggages,
            "jaegerEndpoint": self.jaeger_endpoint,
        }



class FCContext:
    def __init__(self, account_id, request_id, credentials, function_meta, service_meta, region, tracing,
                retry_count=0):
        self.requestId = request_id
        self.credentials = credentials
        self.function = function_meta
        self.request_id = request_id
        self.service = service_meta
        self.region = region
        self.account_id = account_id
        self.retry_count = retry_count
        self.tracing = tracing

    def to_json(self):
        return json.dumps({"requestId": self.request_id,
                           "credentials": self.credentials.to_dict(),
                           "function": self.function.to_dict(),
                           "service": self.service.to_dict(),
                           "region": self.region,
                           "accountId": self.account_id,
                           "retryCount": self.retry_count,
                           "tracing": self.tracing.to_dict(),
                           })

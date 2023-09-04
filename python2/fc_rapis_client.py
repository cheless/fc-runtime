
import _native_rapis_client

class RequestAsDict(object):
    def __init__(self, **kwds):
        self.__dict__.update(kwds)

    def __eq__(self, other):
        return self.__dict__ == other.__dict__

class FcRuntimeClient(object):
    def __init__(self, fc_runtime_address):
        self.fc_runtime_address = fc_runtime_address

    def return_init_error(self, invoke_id, error_response_data, tracer_fault=None):
        tracer_fault = ""
        _native_rapis_client.post_init_error(invoke_id, error_response_data, tracer_fault)

    def wait_for_invocation(self):
        response_body, headers = _native_rapis_client.next()
        return RequestAsDict(
            request_id=headers.get("x-fc-request-id"),
            access_key_id=headers.get("x-fc-access-key-id"),
            access_key_secret=headers.get("x-fc-access-key-secret"),
            security_token=headers.get("x-fc-security-token"),
            function_name=headers.get("x-fc-function-name"),
            function_handler=headers.get("x-fc-function-handler"),
            function_memory=headers.get("x-fc-function-memory"),
            function_type=headers.get("x-fc-function-type"),
            function_timeout=headers.get("x-fc-function-timeout"),
            retry_count=headers.get("x-fc-retry-count"),
            version_id=headers.get("x-fc-version-id"),
            qualifier=headers.get("x-fc-qualifier"),
            service_name=headers.get("x-fc-service-name"),
            log_project=headers.get("x-fc-service-logproject"),
            log_store=headers.get("x-fc-service-logstore"),
            region=headers.get("x-fc-region"),
            account_id=headers.get("x-fc-account-id"),
            client_ip=headers.get("x-fc-client-ip"),
            trace_id=headers.get("x-fc-trace-id"),
            content_type=headers.get("Content-Type"),
            http_params=headers.get("x-fc-http-params"),
            span_context=headers.get("x-fc-tracing-opentracing-span-context"),
            jaeger_endpoint=headers.get("x-fc-tracing-jaeger-endpoint"),
            base64_baggages=headers.get("x-fc-tracing-opentracing-span-baggages"),
            event_body=response_body
        )

    def return_invocation_result(self, invoke_id, result_data, content_type='application/json'):
        _native_rapis_client.post_invocation_result(invoke_id, result_data if isinstance(result_data, bytes) else result_data.encode('utf-8'), content_type, "")

    def return_http_result(self, invoke_id, http_params,  result_data, content_type='application/json'):
        _native_rapis_client.post_invocation_result(invoke_id, result_data if isinstance(result_data, bytes) else result_data.encode('utf-8'), content_type, http_params)

    def return_invocation_error(self, invoke_id, error_response_data, tracer_fault=None):
        tracer_fault = ""
        _native_rapis_client.post_error(invoke_id, error_response_data, tracer_fault)

import base64
import json
import logging
import sys
import traceback
import os
from io import StringIO, BytesIO
from fc_rapis_constant import Constant
from fc_rapis_context import Credentials, FunctionMeta, FCContext, ServiceMeta

logger = logging.getLogger()


def get_environ(event, context, request_http_params):
    default_environ = {
        'CONTENT_LENGTH': str(len(event) or 0),
        'HTTP': 'on',
        'SCRIPT_NAME': '',
        'SERVER_NAME': 'FCRUNTIME',
        'SERVER_PORT': '9000',
        'SERVER_PROTOCOL': 'HTTP/1.1',
        'SERVER_SOFTWARE': 'FCRUNTIME/1.0',
        'CONTENT_TYPE': 'application/octet-stream',
        'QUERY_STRING': '',
        'wsgi.version': (1, 0),
        'wsgi.input': BytesIO(event),
        'wsgi.errors': sys.stderr,
        'wsgi.url_scheme': 'http',
        'wsgi.multithread': False,
        'wsgi.multiprocess': False,
        'wsgi.run_once': False,
    }
    environ = os.environ.copy()
    environ.update(default_environ)
    obj_str = base64.b64decode(request_http_params)
    obj = json.loads(obj_str)
    path = obj.get('path') or "/"
    method = obj.get('method')
    if not (path and method):
        raise Exception('request method or path is empty')

    environ[Constant.WSGI_PATH_INFO] = path
    environ[Constant.WSGI_REQUEST_METHOD] = method
    request_uri = obj.get('requestURI', "")
    environ[Constant.WSGI_REQUEST_URI] = request_uri
    client_ip = obj.get('clientIP', "")
    environ[Constant.WSGI_CLIENT_IP] = client_ip
    headersMap = obj.get('headersMap') or {}
    if headersMap:  # multi value
        ct_key = 'Content-Type'
        for k in headersMap.keys():
            if k.lower() == 'content-type':
                ct_key = k
        content_type = headersMap.pop(ct_key, None) or []
        if content_type:
            environ[Constant.WSGI_CONTENT_TYPE] = ','.join(content_type)
        # process as python wsgi server, https://github.com/python/cpython/blob/master/Lib/wsgiref/simple_server.py#L102-L109
        for k, v in headersMap.items():
            key = "HTTP_" + k.upper().replace("-", "_")
            environ[key] = ','.join(v)

    if "?" in request_uri:
        _, query = request_uri.split('?', 1)
        environ[Constant.WSGI_QUERY_STRING] = query

    environ[Constant.HEADER_FUNCTION_HANDLER] = context.function.handler
    environ[Constant.WSGI_CONTEXT] = context
    return environ


class StartResponseWrapper:
    def __init__(self):
        self.http_params = ""
        # receive response in local buffer, it will be send later by runtime
        self.body = BytesIO()

    def __call__(self, status, headers, exc_info=None):
        resp_status = int(status.split()[0].strip())
        http_params = {}
        for k, v in headers:
            v_list = http_params.get(k) or []
            v_list.append(v)
            http_params[k] = v_list
        resp_params = {
            'status': resp_status,
            'headersMap': http_params,
        }
        params_str = json.dumps(resp_params)
        self.http_params = base64.b64encode(params_str)
        return self.body.write

    def response(self, output):
        try:
            body = self.body.getvalue() + b''.join(output)
            return self.http_params, body
        except:
            raise Exception("invalid response type")

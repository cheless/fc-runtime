# -*- coding: utf-8 -*-

from __future__ import absolute_import
import imp
import os
import json
import sys
import traceback
import logging
import time
import decimal

import fc_rapis_exception as FcException
from fc_rapis_constant import Constant
from fc_rapis_client import FcRuntimeClient
from fc_rapis_context import Credentials, FunctionMeta, FCContext, ServiceMeta, Tracing

import signal

timeout_msg = "Dumping stack trace after function timeout"


# not_exit when unit test
def dump_stacks(sig, frame, not_exit=False):
    """get python runtime stack before ca exit"""
    logger = logging.getLogger()
    logger.error(timeout_msg)
    get_fc_sys_logger().error(timeout_msg)
    for thread_id, stack in sys._current_frames().items():
        thread_msg = "request id = {}, thread id = {}".format(
            global_request_id, thread_id)
        detail_msg = traceback.format_stack(stack)

        logger.error(thread_msg)
        logger.error(detail_msg)

        get_fc_sys_logger().error(thread_msg)
        get_fc_sys_logger().error(detail_msg)
    if not_exit is False:
        sys.exit(Constant.SIGTERM_ERROR)


def register_exit_dump_stacks():
    signal.signal(signal.SIGTERM, dump_stacks)


fc_sys_logger = None

global_request_id = None
global_log_retry_count = 0
global_handlers = {}


# logging filter for adding requestid to the content


class FCLoggerFilter(logging.Filter):
    # inherit from super
    def filter(self, record):
        record.fc_request_id = global_request_id or ""
        return True


def make_fault_handler(status, ex):
    exc_type, exc_value, exc_traceback = sys.exc_info()
    tb = traceback.format_exception_only(exc_type, exc_value)
    trace_list = []
    for idx, trace_line in enumerate(tb):
        trace_line_lst = trace_line.split('\n')
        for item in trace_line_lst:
            r_item = item.replace('^', '').strip()
            if not r_item:
                continue
            if idx != 0:
                trace_list.append(r_item)
            else:
                # python stack trace file and line info in the first
                # like '  File "/code/hello_world.py", line 3\n', ' , need split by ,
                for content in r_item.split(','):
                    r_content = content.strip()
                    if r_content:
                        trace_list.append(r_content)
    msg = make_error(ex, trace_list)

    def result(*args):
        return status, msg

    return result


def make_error(ex, trace_list):  # stackTrace is an array
    result = {}
    err_msg = ex.message or str(ex)
    result['errorMessage'] = str(err_msg)
    result['errorType'] = ex.__class__.__name__
    if trace_list:
        result['stackTrace'] = trace_list
    return result


def format_trace_list(trace_list):
    def format_trace_item(trace_item):
        first_split = trace_item.split('\n')
        result = []
        for item in first_split[0].split(','):
            result.append(item.strip())

        result.append(first_split[1].strip())
        return result

    def as_no_break_line(line):
        ident_chars_count = 0
        for c in line:
            if c != ' ':
                break
            ident_chars_count += 1

        return ('\u00a0' * ident_chars_count) + line[ident_chars_count:]

    for index in range(len(trace_list)):
        trace_list[index] = format_trace_item(as_no_break_line(trace_list[index]))
    return trace_list


def load_handler_failed_handler(e, modname):
    if isinstance(e, ImportError):
        return make_fault_handler(404, FcException.ImportModuleError("Unable to import module '{}'".format(modname)))
    elif isinstance(e, SyntaxError):
        return make_fault_handler(404, FcException.UserCodeSyntaxError("Syntax error in module '{}'".format(modname)))
    else:
        return make_fault_handler(404, FcException.UserCodeError("Module initialization error: '%s'" % str(e)))


class NumberStr(float):
    def __init__(self, o):
        self.o = o


def decimal_serializer(o):
    if isinstance(o, decimal.Decimal):
        return NumberStr(o)
    raise TypeError(repr(o) + " is not JSON serializable")


def to_json(obj):
    return json.dumps(obj, default=decimal_serializer, indent=4, sort_keys=True)


def _get_handler(handler):
    # 0. check if handler is already loaded
    if global_handlers.has_key(handler):
        return True, global_handlers[handler]

    pathname = os.getenv(Constant.FUNC_CODE_PATH)
    # 1. validate handler
    #     main.func => func in main.py
    #     test.test_code.main.func => func in test/test_code/main.py
    try:
        (modname, fname) = handler.rsplit('.', 1)
    except ValueError as e:
        request_handler = make_fault_handler(
            404, FcException.InvalidHandleName("Invalid handler '{}'".format(handler)))
        return False, request_handler

    # 2. get code path
    file_handle, desc = None, None
    # 3. find and load
    try:
        pos = modname.rfind("/")
        if pos != -1:
            path_suffix = modname[:pos]
            segments = modname[pos + 1:]
            if segments:
                pathname = pathname + "/" + path_suffix
                segments = segments.split(".")
        else:
            segments = modname.split(".")
        for segment in segments:
            if pathname is not None:
                pathname = [pathname]
            file_handle, pathname, desc = imp.find_module(segment, pathname)
        if modname in sys.modules:
            m = sys.modules[modname]
        else:
            m = imp.load_module(modname, file_handle, pathname, desc)
    except Exception as load_ex:
        request_handler = load_handler_failed_handler(load_ex, modname)
        return False, request_handler
    finally:
        if file_handle is not None:
            file_handle.close()

    try:
        request_handler = getattr(m, fname)
    except AttributeError as e:
        return False, make_fault_handler(404, FcException.HandlerNotFound(
            "Handler '{}' is missing on module '{}'".format(fname, modname)))

    global_handlers[handler] = request_handler
    return True, request_handler


def execute_request_handler(function_type, request_handler, req, ctx):
    result = 0
    try:
        if function_type == Constant.HANDLE_FUNCTION:
            result = request_handler(req, ctx)
        else:
            result = request_handler(ctx)

    except Exception as user_ex:
        exc_info = sys.exc_info()
        trace = traceback.format_list(traceback.extract_tb(exc_info[2])[1:])
        trace_list = format_trace_list(trace)
        return False, to_json(make_error(user_ex, trace_list))
    ret = result
    if not isinstance(result, basestring):
        try:
            ret = to_json(result)
        except TypeError as json_ex:
            exc_info = sys.exc_info()
            trace = traceback.format_list(traceback.extract_tb(exc_info[2]))
            trace_list = format_trace_list(trace)
            return False, to_json(make_error(json_ex, trace_list))
    return True, ret


class AsFlushWriter(object):
    def __init__(self, writer):
        self.writer = writer

    def __enter__(self):
        return self

    def __exit__(self, exc_type, exc_value, exc_tb):
        pass

    def __getattr__(self, attr):
        return getattr(self.writer, attr)

    def write(self, data):
        log_len = len(data)
        if log_len > Constant.MAX_LOG_BYTES - 1:
            log_len = Constant.MAX_LOG_BYTES - 1
            data = data[:log_len - 1]
            data = data + '\n'
        self.writer.write(data)
        try:
            self.writer.flush()
        except IOError as ex:
            # TODO, it is ugly here, find better way to avoid sleep
            time.sleep(0.01)
            global global_log_retry_count
            global_log_retry_count = global_log_retry_count + 1
            self.writer.write(data)
            self.writer.flush()

    def writelines(self, lines):
        self.writer.writelines(lines)
        try:
            self.writer.flush()
        except IOError as ex:
            time.sleep(0.01)
            global global_log_retry_count
            global_log_retry_count = global_log_retry_count + 1
            self.writer.writelines(lines)
            self.writer.flush()


def to_bytes(n, length, endianess='big'):
    h = '%x' % n
    s = ('0' * (len(h) % 2) + h).zfill(length * 2).decode('hex')
    return s if endianess == 'big' else s[::-1]


class FcSysLogSink(object):
    def __init__(self, out):
        self.filename = "syslog"
        if out is None:
            fd = os.environ['_FC_LOG_FD']
            del os.environ['_FC_LOG_FD']
            self.file = os.fdopen(int(fd), 'w', 0)
        else:
            self.file = out
        self.frame_type = to_bytes(Constant.FC_LOG_FRAME_TYPE, 4)

    def __enter__(self):
        return self

    def __exit__(self, exc_type, exc_value, exc_tb):
        self.file.close()

    def log(self, msg):
        encoded_msg = msg.encode("utf8")
        log_msg = self.frame_type + to_bytes(len(encoded_msg), 4) + encoded_msg
        self.file.write(log_msg)

    def log_error(self, msg_lines):
        error_message = '\n'.join(msg_lines)
        self.log(error_message)


class FcUserLogSink(object):
    def __init__(self, out):
        self.filename = "userlog"
        self.frame_type = to_bytes(Constant.FC_LOG_FRAME_TYPE, 4)
        if out is None:
            self.file = sys.stdout
        else:
            self.file = out

    def __enter__(self):
        return self

    def __exit__(self, exc_type, exc_value, exc_tb):
        self.file.close()

    def log(self, msg):
        log_len = len(msg)
        if log_len > Constant.MAX_LOG_BYTES - 1:
            log_len = Constant.MAX_LOG_BYTES - 1
            msg = msg[:log_len - 1]
            msg = msg + '\n'
        encoded_msg = msg.encode("utf8")
        log_msg = self.frame_type + to_bytes(len(encoded_msg), 4) + encoded_msg
        self.file.write(log_msg)

    def log_error(self, msg_lines):
        error_message = '\n'.join(msg_lines)
        self.log(error_message)


class FcLoggerHandler(logging.Handler):
    def __init__(self, log_sink):
        logging.Handler.__init__(self)
        self.log_sink = log_sink

    def emit(self, msg):
        msg = self.format(msg)
        self.log_sink.log(msg)


def init_logging(usrlog_out=None, syslog_out=None):
    # sample: how to use logger in user code:
    #       import logging
    #       logger = logging.getLogger("my_handler")
    #       logger.setLevel(logging.INFO)
    #       logger.info("CCC")
    init_fc_user_logger(usrlog_out)
    init_fc_sys_logger(syslog_out)


def get_fc_sys_logger():
    global fc_sys_logger
    return fc_sys_logger


def init_fc_sys_logger(syslog_out):
    sys_log_sink = FcSysLogSink(syslog_out)
    sys_log_handler = FcLoggerHandler(sys_log_sink)
    sys_formatter = logging.Formatter(
        '%(asctime)s.%(msecs)03d [%(levelname)s] [bootstrap]: %(message)s\n', '%Y-%m-%d %H:%M:%S')
    sys_log_handler.setFormatter(sys_formatter)
    sys_log_handler.setLevel(logging.INFO)
    # the logger print runtime init; FC start end to file
    global fc_sys_logger
    fc_sys_logger = logging.getLogger("fc_sys_logger")
    fc_sys_logger.handlers = []
    fc_sys_logger.setLevel(logging.INFO)
    fc_sys_logger.addHandler(sys_log_handler)
    fc_sys_logger.parent = None


def init_fc_user_logger(usrlog_out):
    user_log_sink = FcUserLogSink(usrlog_out)
    user_log_handler = FcLoggerHandler(user_log_sink)
    user_log_handler.setFormatter(logging.Formatter(
        '%(asctime)s.%(msecs)03dZ %(fc_request_id)s [%(levelname)s] %(message)s\n', '%Y-%m-%dT%H:%M:%S'))
    user_log_handler.addFilter(FCLoggerFilter())
    logging.Formatter.converter = time.gmtime
    logger = logging.getLogger()
    logger.handlers = []
    logger.addHandler(user_log_handler)
    log_level = os.getenv(Constant.SERVER_LOG_LEVEL) or "INFO"
    logger.setLevel(log_level)


class FCHandler():
    def __init__(self, request, fc_runtime_client):
        self.request = request
        self.request_id = request.request_id
        self.function_type = request.function_type
        if self.function_type is None:
            self.function_type = Constant.HANDLE_FUNCTION

        self.fc_runtime_client = fc_runtime_client

    def gen_fc_context(self):
        account_id = self.request.account_id

        requestId = self.request.request_id
        accessKeyId = self.request.access_key_id
        accessKeySecret = self.request.access_key_secret
        securityToken = self.request.security_token
        name = self.request.function_name
        handler = self.request.function_handler
        memory = self.request.function_memory
        timeout = self.request.function_timeout

        service_name = self.request.service_name
        service_log_project = self.request.log_project
        service_log_store = self.request.log_store
        qualifier = self.request.qualifier
        version_id = self.request.version_id
        service_region = self.request.region
        retry_count = self.request.retry_count

        span_context = self.request.span_context
        jaeger_endpoint = self.request.jaeger_endpoint
        base64_baggages = self.request.base64_baggages

        ctx = FCContext(
            account_id,
            requestId,
            Credentials(accessKeyId, accessKeySecret, securityToken),
            FunctionMeta(name, handler, int(memory), timeout),
            ServiceMeta(service_name, service_log_project,
                        service_log_store, qualifier, version_id),
            service_region,
            Tracing(span_context, base64_baggages, jaeger_endpoint),
            retry_count,
        )
        return ctx

    def get_handler(self):
        # get context from request headers
        ctx = self.gen_fc_context()
        event = self.request.event_body
        handler = self.request.function_handler
        return event, ctx, handler

    def invalid_handler(self, request_handler, logger):
        status, message = request_handler()
        logger.error(message)
        get_fc_sys_logger().error("{} {}".format(self.request_id, message))
        self.print_end_log(False)
        self.fc_runtime_client.return_invocation_error(
            self.request_id, to_json(message))

    def send_error(self, request_id, message):
        self.print_end_log(False)
        self.fc_runtime_client.return_invocation_error(request_id, message)

    def print_start_log(self):
        msg = Constant.LOG_TAIL_START_PREFIX + self.request_id
        if self.function_type == Constant.INIT_FUNCTION:
            msg = Constant.LOG_TAIL_START_PREFIX_INITIALIZE + self.request_id
        if self.function_type == Constant.PRESTOP_FUNCTION:
            msg = Constant.LOG_TAIL_START_PREFIX_PRE_STOP + self.request_id
        print(msg)
        get_fc_sys_logger().info(msg)

    def print_end_log(self, isHandled):
        suffix = ""
        if not isHandled:
            suffix = ", Error: Unhandled function error"
        if self.request_id is None:
            self.request_id = "None"
        msg = Constant.LOG_TAIL_END_PREFIX + self.request_id + suffix
        if self.function_type == Constant.INIT_FUNCTION:
            msg = Constant.LOG_TAIL_END_PREFIX_INITIALIZE + self.request_id + suffix
        if self.function_type == Constant.PRESTOP_FUNCTION:
            msg = Constant.LOG_TAIL_END_PREFIX_PRE_STOP + self.request_id + suffix

        print(msg)
        sys.stdout.flush()
        get_fc_sys_logger().info(msg)

        if global_log_retry_count > 0:
            get_fc_sys_logger().info("log flush retried {} times".format(global_log_retry_count))

    def handle_request(self):
        global global_request_id
        global_request_id = self.request_id
        logger = logging.getLogger()
        try:
            self.print_start_log()
            event, ctx, handler = self.get_handler()
            # load user handler
            valid_handler, request_handler = _get_handler(handler)
            # execute handler
            if not valid_handler:
                return self.invalid_handler(request_handler, logger)

            if self.function_type == Constant.HANDLE_FUNCTION and self.request.http_params:
                # http function
                succeed, resp = wsgi_wrapper(self.request_id, request_handler, event, ctx, self.request.http_params)
                if succeed:
                    self.print_end_log(True)
                    self.fc_runtime_client.return_http_result(self.request_id, resp[0], resp[1], Constant.CONTENT_TYPE)
                    return
            else:
                succeed, resp = execute_request_handler(self.function_type, request_handler, event, ctx)
                if succeed:
                    self.print_end_log(True)
                    self.fc_runtime_client.return_invocation_result(self.request_id, resp, Constant.CONTENT_TYPE)
                    return
            logger.error(resp)
            get_fc_sys_logger().error("{} {}".format(self.request_id, resp))
            self.send_error(self.request_id, resp)
        except Exception as ex:
            exc_info = sys.exc_info()
            trace = traceback.format_list(traceback.extract_tb(exc_info[2]))
            trace_list = format_trace_list(trace)
            ret = to_json(make_error(ex, trace_list))
            logger.error(ret)
            get_fc_sys_logger().error("{} {}".format(self.request_id, ret))
            self.send_error(self.request_id, ret)


# for test
def _set_global_request_id(request_id):
    global global_request_id
    global_request_id = request_id


def _init_log(syslog_out=None):
    init_logging(syslog_out=syslog_out)


def _sanitize_envs():
    envs = []
    for k in os.environ:
        if k.startswith("FC_") and k not in Constant.SAFE_ENV:
            envs.append(k)
    for k in envs:
        del os.environ[k]


def wsgi_wrapper(request_id, request_handler, event, context, request_http_params):
    from fc_rapis_wsgi import StartResponseWrapper, get_environ
    start_response = StartResponseWrapper()
    environ = get_environ(event, context, request_http_params)
    logger = logging.getLogger()
    try:
        body = request_handler(environ, start_response)
        from collections import Iterable
        if isinstance(body, Iterable):
            body = list(body)
        return True, start_response.response(body)
    except Exception as user_ex:
        rid_msg = 'exception on handling request {}:'.format(request_id)
        trace_msg = traceback.format_exc()
        logger.error(trace_msg)
        get_fc_sys_logger().error(rid_msg + " " + trace_msg)
        exc_info = sys.exc_info()
        trace = traceback.format_list(traceback.extract_tb(exc_info[2])[1:])
        trace_list = format_trace_list(trace)
        return False, to_json(make_error(user_ex, trace_list))


###############################################
def main():
    reload(sys)
    sys.stdout = AsFlushWriter(sys.stdout)
    sys.stderr = AsFlushWriter(sys.stderr)
    sys.setdefaultencoding('utf-8')
    register_exit_dump_stacks()
    sys.path.insert(0, "/opt/python")
    sys.path.insert(0,
                    '/opt/python/lib/python{}.{}/site-packages'.format(sys.version_info.major, sys.version_info.minor))
    sys.path.insert(0, os.environ[Constant.FUNC_CODE_PATH])
    fc_runtime_client = None
    try:
        _init_log()
        init_msg = 'FunctionCompute python runtime inited.'
        get_fc_sys_logger().info(init_msg)
        print init_msg
        fc_api_addr = os.environ['FC_RUNTIME_API']
        fc_runtime_client = FcRuntimeClient(fc_api_addr)
        del os.environ['FC_RUNTIME_API']
        _sanitize_envs()
    except Exception as e:
        exc_info = sys.exc_info()
        trace = traceback.format_list(traceback.extract_tb(exc_info[2])[1:])
        trace_list = format_trace_list(trace)
        msg = to_json(make_error(e, trace_list))
        get_fc_sys_logger().info("failed to start due to" + msg)
        if fc_runtime_client:
            fc_runtime_client.return_init_error("start_runtime", msg)
        return
    # loop to wait for invoke
    while True:
        try:
            request = fc_runtime_client.wait_for_invocation()
            _set_global_request_id(request.request_id)
            global global_log_retry_count
            global_log_retry_count = 0
            handler = FCHandler(request, fc_runtime_client)
            handler.handle_request()
        except Exception as e:
            exc_info = sys.exc_info()
            trace = traceback.format_list(
                traceback.extract_tb(exc_info[2])[1:])
            trace_list = format_trace_list(trace)
            msg = to_json(make_error(e, trace_list))
            get_fc_sys_logger().info("failed to invoke function due to" + msg)


if __name__ == '__main__':
    main()

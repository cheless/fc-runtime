# -*- coding: utf-8 -*-
import imp
import os, io
import sys
import traceback
import logging
import time
from imp import reload

from fc_rapis_constant import Constant
import fc_rapis_exception as FcException
from fc_rapis_util import to_json, make_fault_handler, load_handler_failed_handler, get_error_response
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


def print_traceback(limit=30):
    traces = traceback.format_tb(sys.exc_info()[2])
    i = 0
    for item in traces:
        get_fc_sys_logger().error(item)
        i += 1
        if i > limit:
            get_fc_sys_logger().error(
                "stacks have been truncated to avoid exceed limit {}, lines num: {}.".format(limit, len(traces)))
            return


fc_sys_logger = None

global_request_id = None
global_log_retry_count = 0
global_handlers = {}
global_concurrent_limit = 1
global_concurrent_lock = None

status_ok = 200
status_err = 404

# checkpoint/restore
root_fs_path = '/'
global_fc_workdir_redirect = False
global_default_workdir = ''


def _concurrent_get_handler(handler):
    global global_handlers
    # 0. check if handler is already loaded
    if handler in global_handlers:
        return True, global_handlers[handler]

    if global_concurrent_lock is not None:
        global_concurrent_lock.acquire()
        valid_handler, request_handler = _get_handler(handler)
        global_concurrent_lock.release()
        return valid_handler, request_handler
    else:
        return _get_handler(handler)


def _get_handler(handler):
    global global_handlers
    # 0. check if handler is already loaded
    if handler in global_handlers:
        return True, global_handlers[handler]

    pathname = os.getenv(Constant.FUNC_CODE_PATH)
    # 1. validate handler
    #     main.func => func in main.py
    #     test.test_code.main.func => func in test/test_code/main.py
    try:
        (modname, fname) = handler.rsplit('.', 1)
    except ValueError as e:
        return False, load_handler_failed_handler(e, handler)

    # 2. get code path
    file_handle, desc = None, None
    # 3. find and load
    try:
        pos = modname.rfind("/")
        if pos != -1:
            path_suffix, segments = modname[:pos], modname[pos + 1:]
            pathname = os.path.join(pathname, path_suffix)
            if segments:
                segments = segments.split(".")
        else:
            segments = modname.split(".")

        for segment in segments:
            if pathname:
                pathname = [pathname]
            file_handle, pathname, desc = imp.find_module(segment, pathname)
        if modname in sys.modules:
            m = sys.modules[modname]
        else:
            m = imp.load_module(modname, file_handle, pathname, desc)

    except Exception as load_ex:
        return False, load_handler_failed_handler(load_ex, modname)
    finally:
        if file_handle is not None:
            file_handle.close()

    try:
        request_handler = getattr(m, fname)
    except AttributeError as e:
        return False, make_fault_handler(status_err, FcException.HandlerNotFound(
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
        resp = get_error_response(user_ex, exc_info[2], True)
        return False, resp

    ret = result
    if not isinstance(result, (str, bytes)):
        try:
            ret = to_json(result)
        except TypeError as json_ex:
            exc_info = sys.exc_info()
            resp = get_error_response(json_ex, exc_info[2])
            return False, resp
    return True, ret


class FCHandler():
    def __init__(self, request, fc_runtime_client):
        self.request = request
        self.request_id = request.request_id
        self.function_type = request.function_type
        if self.function_type is None \
            or int(self.function_type) > Constant.PREFREEZE_FUNCTION \
            or int(self.function_type) < Constant.HANDLE_FUNCTION:
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

        if global_concurrent_limit == 1:
            logger = None
        else:
            logger = init_context_logger(requestId)

        ctx = FCContext(
            account_id,
            requestId,
            Credentials(accessKeyId, accessKeySecret, securityToken),
            FunctionMeta(name, handler, int(memory), timeout),
            ServiceMeta(service_name, service_log_project,
                        service_log_store, qualifier, version_id),
            service_region,
            Tracing(span_context, base64_baggages, jaeger_endpoint),
            logger,
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
        msg = Constant.LOG_TAIL_START_PREFIX_INVOKE + self.request_id
        if self.function_type == Constant.INIT_FUNCTION:
            msg = Constant.LOG_TAIL_START_PREFIX_INITIALIZE + self.request_id
        if self.function_type == Constant.PRESTOP_FUNCTION:
            msg = Constant.LOG_TAIL_START_PREFIX_PRE_STOP + self.request_id
        if self.function_type == Constant.PREFREEZE_FUNCTION:
            msg = Constant.LOG_TAIL_START_PREFIX_PRE_FREEZE + self.request_id
        print(msg)
        get_fc_sys_logger().info(msg)

    def print_end_log(self, is_handled):
        suffix = ""
        if not is_handled:
            suffix = ", Error: Unhandled function error"
        if self.request_id is None:
            self.request_id = "None"
        msg = Constant.LOG_TAIL_END_PREFIX_INVOKE + self.request_id + suffix
        if self.function_type == Constant.INIT_FUNCTION:
            msg = Constant.LOG_TAIL_END_PREFIX_INITIALIZE + self.request_id + suffix
        if self.function_type == Constant.PRESTOP_FUNCTION:
            msg = Constant.LOG_TAIL_END_PREFIX_PRE_STOP + self.request_id + suffix
        if self.function_type == Constant.PREFREEZE_FUNCTION:
            msg = Constant.LOG_TAIL_END_PREFIX_PRE_FREEZE + self.request_id + suffix
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
            valid_handler, request_handler = _concurrent_get_handler(handler)
            # execute handler
            if not valid_handler:
                print_traceback()
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
            if self.request.http_params:
                get_fc_sys_logger().error(
                    '[{}]request http params: {}'.format(self.request_id, self.request.http_params))
            else:
                get_fc_sys_logger().error('[{}]request event: {}'.format(self.request_id, event))
            self.send_error(self.request_id, resp)

        except Exception as ex:
            exc_info = sys.exc_info()
            ret = get_error_response(ex, exc_info[2])
            logger.error(ret)
            get_fc_sys_logger().error("{} {}".format(self.request_id, ret))
            self.send_error(self.request_id, ret)


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
        if log_len == 0:
            return
        if log_len > Constant.MAX_LOG_BYTES:
            log_len = Constant.MAX_LOG_BYTES
            data = data[:log_len]
        self.writer.write(data)
        try:
            self.writer.flush()
        except IOError as ex:
            # TODO, it is ugly here, find better way to avoid sleep
            time.sleep(0.01)
            global global_log_retry_count
            global_log_retry_count = global_log_retry_count + 1
            # self.writer.write(data)
            self.writer.flush()

    def writelines(self, lines):
        self.writer.writelines(lines)
        try:
            self.writer.flush()
        except IOError as ex:
            time.sleep(0.01)
            global global_log_retry_count
            global_log_retry_count = global_log_retry_count + 1
            # self.writer.writelines(lines)
            self.writer.flush()


# logging filter for adding request id to the content
class FCLoggerFilter(logging.Filter):
    # inherit from super
    def filter(self, record):
        record.fc_request_id = global_request_id or ""
        return True


# logging filter for adding request id to the content
class FCContextLoggerFilter(logging.Filter):
    def __init__(self, request_id):
        self.request_id = request_id

    # inherit from super
    def filter(self, record):
        record.fc_request_id = self.request_id
        return True


class FcSysLogSink(object):
    def __init__(self, out):
        self.filename = "syslog"
        if out is None:
            fd = os.environ['_FC_LOG_FD']
            self.file = os.fdopen(int(fd), "wb", closefd=False)
        else:
            self.file = out
        self.frame_type = (Constant.FC_LOG_FRAME_TYPE).to_bytes(4, byteorder='big')

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
        log_msg = self.frame_type + (len(encoded_msg)).to_bytes(4, byteorder='big') + encoded_msg
        self.file.write(log_msg)
        self.file.flush()

    def log_error(self, msg_lines):
        error_message = '\n'.join(msg_lines)
        self.log(error_message)


class FcUserLogSink(object):
    def __init__(self, out):
        self.filename = "userlog"
        self.frame_type = (Constant.FC_LOG_FRAME_TYPE).to_bytes(4, byteorder='big')
        if out is None:
            out = sys.stdout

        try:
            self.file = os.fdopen(out.fileno(), "wb", closefd=False)
            self.file = AsFlushWriter(self.file)
        except:
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
        log_msg = self.frame_type + (len(encoded_msg)).to_bytes(4, byteorder='big') + encoded_msg
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


def init_context_logger(request_id):
    user_log_sink = FcUserLogSink(None)
    user_log_handler = FcLoggerHandler(user_log_sink)
    user_log_handler.setFormatter(logging.Formatter(
        '%(asctime)s.%(msecs)03dZ %(fc_request_id)s [%(levelname)s] %(message)s\n', '%Y-%m-%dT%H:%M:%S'))
    user_log_handler.addFilter(FCContextLoggerFilter(request_id))
    logging.Formatter.converter = time.gmtime
    logger = logging.Logger(request_id)
    logger.handlers = []
    logger.addHandler(user_log_handler)
    log_level = os.getenv(Constant.SERVER_LOG_LEVEL) or "INFO"
    logger.setLevel(log_level)
    return logger


# for test
def _set_global_request_id(request_id):
    global global_request_id
    global_request_id = request_id


def _set_global_concurrent_params():
    global global_concurrent_limit
    global global_concurrent_lock
    try:
        concurrent_limit = os.environ[Constant.FC_CONCURRENT_LIMIT] or ""
        concurrent_limit = int(concurrent_limit)
        import threading
        from threading import Lock
        global_concurrent_lock = Lock()
    except Exception:
        concurrent_limit = 1

    global_concurrent_limit = concurrent_limit


def _init_log(syslog_out=None):
    init_logging(syslog_out=syslog_out)


def _sanitize_envs():
    envs = [k for k in os.environ if ((k.startswith("FC_") or k.startswith("_FC_")) and k not in Constant.SAFE_ENV)]
    for k in envs:
        del os.environ[k]


def wsgi_wrapper(request_id, request_handler, event, context, request_http_params):
    from fc_rapis_wsgi import StartResponseWrapper, get_environ
    start_response = StartResponseWrapper()
    environ = get_environ(event, context, request_http_params)
    logger = logging.getLogger()
    try:
        body = request_handler(environ, start_response)
        from collections.abc import Iterable
        if isinstance(body, Iterable) and not isinstance(body, bytes):
            body = list(body)
        return True, start_response.response(body)
    except Exception as user_ex:
        rid_msg = 'exception on handling request {}:'.format(request_id)
        trace_msg = traceback.format_exc()
        logger.error(trace_msg)
        get_fc_sys_logger().error(rid_msg + " " + trace_msg)
        exc_info = sys.exc_info()
        return False, get_error_response(user_ex, exc_info[2])


# for test
def _set_test_param(request_id):
    global global_request_id
    global_request_id = request_id


def _reset_test_param():
    global global_request_id
    global_request_id = None

###############################################
def main():
    reload(sys)
    sys.stdout = AsFlushWriter(sys.stdout)
    sys.stderr = AsFlushWriter(sys.stderr)
    register_exit_dump_stacks()
    sys.path.insert(0, "/opt/python")
    sys.path.insert(0,
                    '/opt/python/lib/python{}.{}/site-packages'.format(sys.version_info.major, sys.version_info.minor))
    sys.path.insert(0, os.environ[Constant.FUNC_CODE_PATH])

    fc_runtime_client = None

    global global_default_workdir
    global global_fc_workdir_redirect

    try:
        _init_log()
        init_msg = 'FunctionCompute python3 runtime inited.'
        get_fc_sys_logger().info(init_msg)
        print(init_msg)
        _set_global_concurrent_params()
        fc_api_addr = os.environ['FC_RUNTIME_API']
        fc_runtime_client = FcRuntimeClient(fc_api_addr)
        _sanitize_envs()
        # reserved the workspace path and change to rootfs for checkpoint/restore
        global_fc_workdir_redirect = os.getenv("FC_SNAPSHOT_FLAG") == '1'
        global_default_workdir = os.getcwd()
        if global_fc_workdir_redirect:
            get_fc_sys_logger().info("During snapshot creating")
            os.chdir(root_fs_path)

    except Exception as e:
        exc_info = sys.exc_info()
        msg = get_error_response(e, exc_info[2])
        get_fc_sys_logger().info("failed to start due to" + msg)
        if fc_runtime_client:
            fc_runtime_client.return_init_error("start_runtime", msg)
        return
    # loop to wait for invoke
    get_fc_sys_logger().info("concurrent limit: " + str(global_concurrent_limit))
    while True:
        try:
            if global_concurrent_limit == 1:
                request = fc_runtime_client.wait_for_invocation()
            else:
                request = fc_runtime_client.wait_for_invocation_unblock()

            _set_global_request_id(request.request_id)
            global global_log_retry_count
            global_log_retry_count = 0

            # restore the workspace to default before invoke user function
            if global_fc_workdir_redirect:
                get_fc_sys_logger().info("Restore from snapshot")
                os.chdir(global_default_workdir)
                global_fc_workdir_redirect = False
                # restore the temporary environment
                if request.access_key_id != '':
                    if 'ALIBABA_CLOUD_ACCESS_KEY_ID' in os.environ:
                        os.environ['ALIBABA_CLOUD_ACCESS_KEY_ID'] = request.access_key_id
                    if 'accessKeyID' in os.environ:
                        os.environ['accessKeyID'] = request.access_key_id
                if request.access_key_secret != '':
                    if 'ALIBABA_CLOUD_ACCESS_KEY_SECRET' in os.environ:
                        os.environ['ALIBABA_CLOUD_ACCESS_KEY_SECRET'] = request.access_key_secret
                    if 'accessKeySecret' in os.environ:
                        os.environ['accessKeySecret'] = request.access_key_secret
                if request.security_token != '':
                    if 'ALIBABA_CLOUD_SECURITY_TOKEN' in os.environ:
                        os.environ['ALIBABA_CLOUD_SECURITY_TOKEN'] = request.security_token
                    if 'securityToken' in os.environ:
                        os.environ['securityToken'] = request.security_token
                if request.instance_id != '':
                    if "FC_INSTANCE_ID" in os.environ:
                        os.environ['FC_INSTANCE_ID'] = request.instance_id
                del os.environ['FC_SNAPSHOT_FLAG']

            handler = FCHandler(request, fc_runtime_client)
            if global_concurrent_limit == 1:
                handler.handle_request()
            else:
                from threading import Thread
                Thread(target=handler.handle_request).start()

        except Exception as e:
            exc_info = sys.exc_info()
            msg = get_error_response(e, exc_info[2])
            get_fc_sys_logger().info("failed to invoke function due to" + msg)


if __name__ == '__main__':
    main()

 #-*- coding: utf-8 -*-

'''
 JSON serializer for objects not serializable by default json code
 like bytes, datetime, decimal.Decimal....
'''

import sys, traceback, logging
import json
import decimal
from datetime import date, datetime
import fc_rapis_exception as FcException

def default_serializer(obj):
    if isinstance(obj, decimal.Decimal):
        return float(obj)

    elif isinstance(obj, (datetime, date)):
        return obj.isoformat()

    # custom class, if it has a to_json function
    # support user custom class can reponse as json
    elif hasattr(obj, "to_json"):
        return obj.to_json()

    raise TypeError(repr(obj) + " is not JSON serializable")


def to_json(obj):
    return json.dumps(obj, default=default_serializer, indent=4, sort_keys=True)


'''
ca reponse error function
'''
def make_fault_handler(status, ex):
    exc_type, exc_value, exc_traceback = sys.exc_info()
    tb= traceback.format_exception_only(exc_type, exc_value)
    trace_list = []
    for idx, trace_line in enumerate(tb):
        trace_line_lst = trace_line.split('\n')
        for item in trace_line_lst:
            r_item = item.replace('^','').strip()
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

def make_error(ex, trace_list): #stackTrace is an array
    result = {}
    err_msg = str(ex)
    result['errorMessage'] = err_msg
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

    for index in range(len(trace_list)):
        trace_list[index] = format_trace_item(trace_list[index])
    return trace_list

def load_handler_failed_handler(e, modname):
    if isinstance(e, ImportError):
        return make_fault_handler(404, FcException.ImportModuleError("Unable to import module '{}'".format(modname)))
    elif isinstance(e, SyntaxError):
        return make_fault_handler(404, FcException.UserCodeSyntaxError("Syntax error in module '{}'".format(modname)))
    elif isinstance(e, ValueError):
        return make_fault_handler(404, FcException.InvalidHandleName("Invalid handler '{}'".format(modname)))
    else:
        return make_fault_handler(404, FcException.UserCodeError("Module initialization error: '%s'" % str(e)))


def get_error_response(ex, tb, rm_first_trace = False):
    '''
    param rm_first_trace: remove first trace in trace stack
    '''
    trace = traceback.format_tb(tb)
    if rm_first_trace:
        trace = trace[1:]
    trace_list = format_trace_list(trace)
    return to_json(make_error(ex, trace_list))
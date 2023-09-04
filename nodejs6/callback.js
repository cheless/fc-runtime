'use strict';
var constant = require('./constant.js');
var conxole = require('./console.js');
const maxResponsePayloadSize = '16MB';

exports.createInitCallback = function(client) {
    return function(err){
        client.postInitError(err, _exitProcessDuringInit());
    }
}

// Callback object provides a callback method for returning data back to client.
// Function will be terminated once the callback is invoked.
exports.createInvokeCallback = function(headers, client, callback) {
    var isConsumed = false;
    var consumedErr = null;
    var consumedData = null;
    var isDigested = false;
    var requestId =  headers[constant.HEADERS.REQUEST_ID];
    var functionType =  headers[constant.HEADERS.FUNCTION_TYPE];
    var isHttpMode = typeof headers[constant.HEADERS.HTTP_PARAMS] == 'string';
    // Digest the consumed error and data. Digested error or data will send
    // back to the client along the response. Only the first call to digest
    // will succeed.
    var digest = function(httpParams) {
        if (isDigested) {
            return;
        }
        isDigested = true;
        _invokeDone(functionType, isHttpMode, httpParams, requestId, consumedErr, consumedData, client, callback);
    };

    // Consume the error, data. Only the first call to consume will succeed.
    var consume = function(err, data, httpParams) {
        if (isConsumed) {
            return;
        }
        isConsumed = true;
        consumedErr = err;
        consumedData = data;
        process.nextTick(digest, httpParams);
        //process.nextTick(digest);
    };


    // Warp the consume inside a function so that its implementation is
    // not visible to user function.
    if (isHttpMode) {
        return function(err, data, responseHeaders) {
            consume(err, data, responseHeaders);
        };
    }
    return function(err, data) {
        consume(err, data);
    };
};


function _isUnhandledErr (err) {
    return (err instanceof Error) && (typeof err.name === 'string' || err.name instanceof String)
         && err.name.startsWith('FunctionUnhandledError: ')
}

function _getLogPrefix(functionType) {
    var logPrefix = null;
    switch (functionType){
        case constant.INIT_FUNCTION: 
            logPrefix = constant.LOG_TAIL_END_PREFIX_INITIALIZE;
            break;
        case constant.PRESTOP_FUNCTION:
            logPrefix = constant.LOG_TAIL_END_PREFIX_PRE_STOP;
            break;
        case constant.PREFREEZE_FUNCTION:
            logPrefix = constant.LOG_TAIL_END_PREFIX_PRE_FREEZE;
            break;
        default:
            logPrefix = constant.LOG_TAIL_END_PREFIX_INVOKE;
    }
    return logPrefix
}

function _invokeDone (functionType, isHttpMode, httpParams, requestID, err, rawBody, client, callback) {
    let logPrefix = _getLogPrefix(functionType);
    
    if (err != null) {
        // Send back a response indicates there is a handled error.
        let output = _formatErr(err);
        conxole.errorCap(output.toString());
        let msg = logPrefix + requestID + (_isUnhandledErr(err)?
            ', Error: Unhandled function error' : ', Error: Handled function error');
        process.stdout.write(msg+"\n");
        client.postInvocationError(output, requestID, callback);
        return;
    } 

    let msg = logPrefix + requestID;
    process.stdout.write(msg + '\n');

    // for event function
    if (!isHttpMode) {
        let output = _formatData(rawBody);
        client.postInvocationResult("", output, requestID, callback);
        return;
    }
    
    // for http function
    var stream = require('stream');
    // for stream.Readable
    if (rawBody instanceof stream.Readable) {
        var getRawBody = require('raw-body');
        getRawBody(rawBody, {limit:maxResponsePayloadSize}, function(err, body){
            if (err) {
                err.name = "FunctionUnhandledError: " + err.name;
                let output = _formatErr(err);
                conxole.errorCap(output.toString());
                client.postInvocationError(output, requestID, callback);
                return;
            }
            client.postInvocationResult(httpParams, body, requestID, callback);
            return;
        })
        return;
    }

    client.postInvocationResult(httpParams, rawBody, requestID, callback);
}

function _exitProcessDuringInit(){
    process.exit(constant.INIT_ERROR);
}



// Translate error into a pretty object.
function _formatErr(err) {
    var output = {};
    if (err instanceof Error) {
        output = {
            errorMessage: err.message,
            errorType: err.name,
            stackTrace: err.stack.split("\n")
        };
    } else {
        var errMsg = err
        if(err instanceof Buffer) {
            errMsg = err.toString()
        }
        output = {
            errorMessage: _formatData(errMsg),
            errorType: "FunctionHandledError"
        };
    }
    return JSON.stringify(output);
}

// Translate data into a byte buffer.
function _formatData(data) {
    // data is null or undefined.
    if (data == null) {
        return "";
    }

    // Buffer
    if (data instanceof Buffer) {
        return data;
    }
    // Convert other data type to buffer.
    var output = data.toString();
    switch (typeof(data)) {
        case 'function':
            output = data.constructor.toString();
            break;
        case 'object':
            output = JSON.stringify(data);
            break;
        case 'string':
            output = data;
            break;
        case 'number':
        case 'boolean':
            output = data.toString();
            break;
    }
    return output;
}

'use strict';
var logger = require('./logger.js');
var constant = require('./constant.js');
var context = require('./context.js');
var httpparam = require('./httpparam.js');
var prepare_code = require('./prepare_code.js');
var error = require('./error.js');


// Handle initialize/invoke/preFreeze/preStop request
module.exports = exports = function(headers, body, codePath, callback) {
    // Store request id in process. This id will be added to log entry.
    // TODO: Support multiple rid in reserve mode.
    const requestID = headers[constant.HEADERS.REQUEST_ID];
    const functionType = headers[constant.HEADERS.FUNCTION_TYPE];
    process._fc = {
        requestId: requestID
    };
    //print start invoke log
    var logPrefix = null;
    switch (functionType){
        case constant.INIT_FUNCTION: 
            logPrefix = constant.LOG_TAIL_START_PREFIX_INIITALIZE;
            break;
        case constant.PRESTOP_FUNCTION:
            logPrefix = constant.LOG_TAIL_START_PREFIX_PRE_STOP;
            break;
        case constant.PREFREEZE_FUNCTION:
            logPrefix = constant.LOG_TAIL_START_PREFIX_PRE_FREEZE;
            break;
        default:
            logPrefix = constant.LOG_TAIL_START_PREFIX_INVOKE;
    }
    var msg = logPrefix + requestID ;
    process.stdout.write(msg+"\n");
    logger.getLogger().info(msg);
    // Setup function handler.
    var func = null;

    var handler = headers[constant.HEADERS.FUNCTION_HANDLER];
    func = prepare_code.loadFunction(codePath, handler);

    var httpParams = headers[constant.HEADERS.HTTP_PARAMS];
    var isHttpMode = typeof httpParams == 'string';

    var ctx = context.create(headers);
    let result = null;
    if (isHttpMode) {
        var req = httpparam.parseRequest(headers, body, httpParams);
        var resp = new httpparam.Response(callback);
        return func(req, resp, ctx);
    }else if ((functionType === constant.INIT_FUNCTION) || (functionType === constant.PREFREEZE_FUNCTION) ||
        (functionType === constant.PRESTOP_FUNCTION)){
        return func(ctx, callback);
    }
    return func(body, ctx, callback);
};
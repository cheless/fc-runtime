/*
 * Logger provides logging methods for application to log debugging messages
 * into a log file. Default logging level is error. You can overwrite logging
 * level by setting the environment variables.
 *
 * Environment variables
 *   - FC_SERVER_LOG_PATH:  Log directory of the application log.
 *   - FC_SERVER_LOG_LEVEL: Logging level.
 */
'use strict';
var util = require('util');



function ContextLog(requestId){
    this.requestId = requestId;
    this.logLevel = 'silly';
}

ContextLog.prototype.setLogLevel = function (lv) {
    /*
const levels = {
error: 0,
warn: 1,
info: 2,
verbose: 3,
debug: 4,
silly: 5
}
*/
    var formatLv = lv.toLowerCase();
    var lvs = ['error', 'warn', 'info', 'verbose', 'debug', 'silly'];
    if (lvs.indexOf(formatLv) == -1) {
        return;
    }
    this.logLevel = formatLv;
}

ContextLog.prototype.log = function() {
    log(this.requestId, this.logLevel, arguments);
};

ContextLog.prototype.debug = function(){
    log(this.requestId, 'debug', arguments);
}

ContextLog.prototype.info = function(){
    log(this.requestId, 'info', arguments);
}

ContextLog.prototype.warn = function(){
    log(this.requestId, 'warn', arguments);
}

ContextLog.prototype.error = function(){
    log(this.requestId, 'error', arguments);
}

exports.ContextLog=ContextLog;

var log = function(requestId, level, data) {
    var msg = util.format.apply(this, data);
    _writeToStdoutWithRequestID(requestId, level, msg);
};

function _writeToStdoutWithRequestID(requestID, level, msg){
    let timeStr = new Date().toISOString();
    let logMsg = `${timeStr} ${requestID} [${level}] ${msg}`;
    logMsg = logMsg.replace(/\n/g, "\r");
    process.stdout.write(logMsg + "\n");
}

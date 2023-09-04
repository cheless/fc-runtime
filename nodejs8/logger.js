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
var fs = require('fs');
var client = require("./_native_rapis_client.node");

function _createFramedLog() {
    return (level, message) => {
        let timeStr = new Date().toISOString().replace(/T/, ' ').replace(/Z/, '');
        let requestID = process._fc && process._fc.requestId ? process._fc.requestId : '';
        let logMsg = `${timeStr} ${requestID} [${level}] ${message}\n`;
        client.WriteSysFramedLog(logMsg);
    };
};

function _createDebugLog(){
    const debugLogFile = "/tmp/debug.log";
    var debugLogFd = fs.openSync(debugLogFile, 'a');

    return (level, message) => {
        let timeStr = new Date().toISOString().replace(/T/, ' ').replace(/Z/, '');
        let requestID = process._fc && process._fc.requestId ? process._fc.requestId : '';
        let logMsg = `${timeStr} ${requestID} [${level}] ${message}\n`;
        fs.writeSync(debugLogFd, logMsg);
    };
}



class SysLog{
    constructor(logSink) {
        this.logSink = logSink;
    }
    debug(msg, ...params){
        this.logSink("debug", util.format(msg, ...params));
    }
    info(msg, ...params){
        this.logSink("info", util.format(msg, ...params));
    }
    warn(msg, ...params){
        this.logSink("warn", util.format(msg, ...params));
    }
    error(msg, ...params){
        this.logSink("error", util.format(msg, ...params));
    }

}

var createSysLog = () => {
    var logSink = null;
    //logSink = _createDebugLog();
    if (process.env["_FC_LOG_FD"] != null && process.env["_FC_LOG_FD"] != undefined) {
        logSink = _createFramedLog();
    }else{
        logSink = _createDebugLog();
    }
    return new SysLog(logSink);
}

var syslog = createSysLog(); 

exports.updateSysLog = function(){
    syslog = createSysLog();
}

exports.getLogger = function(){
    return syslog;
}

function _writeToStdoutWithRequestID(requestID, level, msg){
    let timeStr = new Date().toISOString();
    let logMsg = `${timeStr} ${requestID} [${level}] ${msg}`;
    logMsg = logMsg.replace(/\n/g, "\r");
    process.stdout.write(logMsg + "\n");
}

class ContextLog{
    constructor(requestId) {
        this.requestId = requestId;
        this.logLevel = "silly";
    }
    setLogLevel (lv) {
        var formatLv = lv.toLowerCase();
        var lvs = ['error', 'warn', 'info', 'verbose', 'debug', 'silly'];
        if (lvs.indexOf(formatLv) == -1) {
            return;
        }
        this.logLevel = formatLv;
    }
    log(msg, ...params){
        _writeToStdoutWithRequestID(this.requestId, this.logLevel, util.format(msg, ...params));
    }
    debug(msg, ...params){
        _writeToStdoutWithRequestID(this.requestId, "debug", util.format(msg, ...params));
    }
    info(msg, ...params){
        _writeToStdoutWithRequestID(this.requestId, "info", util.format(msg, ...params));
    }
    warn(msg, ...params){
        _writeToStdoutWithRequestID(this.requestId, "warn", util.format(msg, ...params));
    }
    error(msg, ...params){
        _writeToStdoutWithRequestID(this.requestId, "error", util.format(msg, ...params));
    }
}

exports.createContextLog=function(requestID){
    return new ContextLog(requestID);
}


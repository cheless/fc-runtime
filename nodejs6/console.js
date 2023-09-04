/*
 * This file exports a redirect() to redirect console logging data to log
 * file. By calling redirect(), following console logging method will be
 * overwritten.
 *
 * Overwritten methods
 *   - console.log
 *   - console.info
 *   - console.warn
 *   - console.error
 *
 * Once console logging data is redirected. Logging data will be saved in
 * a local log file. You can change the log file location by setting the
 * environment variable.
 *
 * Environment variables
 *   - FC_FUNC_LOG_PATH:    The directory of the log file.
 */
'use strict';
var util = require('util');
var constant = require('./constant.js');


// The max length of an auto log error message.
var maxErrLen = constant.LIMIT.ERROR_LOG_LENGTH;
var logLevel = 'silly';


console.setLogLevel = function (lv) {
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
    logLevel = formatLv;
    consoleRedirect();
};

function _writeToStdout(level, msg){
    let timeStr = new Date().toISOString();
    let requestID = process._fc && process._fc.requestId ? process._fc.requestId : '';
    let logMsg = `${timeStr} ${requestID} [${level}] ${msg}`;
    logMsg = logMsg.replace(/\n/g, "\r");
    process.stdout.write(logMsg + "\n");
}


var consoleRedirect = function(){
    var log = function(level, data) {
        var logMsg = util.format.apply(this, data);
        _writeToStdout(level, logMsg);
    };
    console.log = function() {
        log('verbose', arguments);
    };
    console.info = function() {
        log('info', arguments);
    };
    console.warn = function() {
        log('warn', arguments);
    };
    console.error = function() {
        log('error', arguments);
    };
    console.debug = function() {
        log('debug', arguments);
    };
}

// Log an error message and cap at max lenght.
exports.errorCap = function (msg) {
    var newMsg = msg.substring(0, maxErrLen) + (msg.length > maxErrLen ? '... Truncated by FunctionCompute' : '');
    console.error(newMsg);
};

// Configurate logger.
exports.config = function(config) {
    if (config && config.func) {
        if (config.func.logLevel) {
            logLevel = config.func.logLevel;
        }
    }
    // Overwrite console methods to redirect data to logger.
    exports.redirect = function() {
        consoleRedirect();
    };
};
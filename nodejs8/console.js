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
var client = require("./_native_rapis_client.node");
var constant = require('./constant.js');
var slogger = require('./logger.js');

var logLevel = 'silly';


var StdoutCapture = function () {
    var stream = process.stdout;
    // Replace stdout
    var _intercept = function () {
        var original_stdout_write = stream.write;

        stream.write = (function () {
            return function (msg, encoding, fd) {
                if (Buffer.isBuffer(msg)) {
                    msg = msg.toString();
                }
                client.WriteUserFramedLog(msg);
            };
        })();

        return function _revert() {
            stream.write = original_stdout_write;
        };
    };

    // Revert to the original stdout
    var _release;


    /**
     * [Capture writes sent to stdout]
     * @param  {[type]} interceptFn [run each time a write is intercepted]
     */
    this.capture = function () {
        // Save private `release` method for use later.
        _release = _intercept();
    };

    /**
     * Stop capturing writes to stdout
     */
    this.release = function () {
        _release();
    };
};

var StderrCapture = function () {
    var stream = process.stderr;
    // Replace stderr
    var _intercept = function () {
        var original_stderr_write = stream.write;

        stream.write = (function () {
            return function (msg, encoding, fd) {
                if (Buffer.isBuffer(msg)) {
                    msg = msg.toString();
                }
                client.WriteUserFramedLog(msg);
            };
        })();

        return function _revert() {
            stream.write = original_stderr_write;
        };
    };

    // Revert to the original stderr
    var _release;


    /**
     * [Capture writes sent to stderr]
     * @param  {[type]} interceptFn [run each time a write is intercepted]
     */
    this.capture = function () {
        // Save private `release` method for use later.
        _release = _intercept();
    };

    /**
     * Stop capturing writes to stderr
     */
    this.release = function () {
        _release();
    };
};

var stdoutCapture = new StdoutCapture();
var stderrCapture = new StderrCapture();

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

function _writeToStdout(level, msg) {
    let timeStr = new Date().toISOString();
    let requestID = process._fc && process._fc.requestId ? process._fc.requestId : '';
    let logMsg = `${timeStr} ${requestID} [${level}] ${msg}`;
    logMsg = logMsg.replace(/\n/g, "\r");
    process.stdout.write(logMsg + "\n");
}

//default to verbose
var consoleRedirect = function () {
    // Logger for user function.
    var log = function (level, msg, ...params) {
        var logMsg = util.format(msg, ...params);
        _writeToStdout(level, logMsg);
    };
    console.log = function (msg, ...params) {
        log('verbose', msg, ...params);
    };
    console.info = function (msg, ...params) {
        log('info', msg, ...params);
    };
    console.warn = function (msg, ...params) {
        log('warn', msg, ...params);
    };
    console.error = function (msg, ...params) {
        log('error', msg, ...params);
    };
    console.debug = function (msg, ...params) {
        log('debug', msg, ...params);
    };
}

// Configurate logger.
exports.config = function (config) {
    if (config && config.func) {
        if (config.func.logLevel) {
            logLevel = config.func.logLevel;
        }
    }
    // Overwrite console methods to redirect data to logger.
    exports.redirect = function () {
        stdoutCapture.capture();
        stderrCapture.capture();
        consoleRedirect();
    };
};

// The max length of an auto log error message.
var maxErrLen = constant.LIMIT.ERROR_LOG_LENGTH;

// Log an error message and cap at max lenght.
exports.errorCap = function (msg) {
    var newMsg = msg.substring(0, maxErrLen) + (msg.length > maxErrLen ? '... Truncated by FunctionCompute' : '');
    console.error(newMsg);
    slogger.getLogger().error(newMsg);
};

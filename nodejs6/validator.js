/*
 * Validator valides the input request.
 */
'use strict';
var util = require('util');
var constant = require('./constant.js');

// Headers defines a list of headers that need to be validated.
var invokeHeaders = [
    constant.HEADERS.CONTENT_TYPE,
    constant.HEADERS.REQUEST_ID,
    constant.HEADERS.FUNCTION_NAME,
    constant.HEADERS.FUNCTION_HANDLER,
    constant.HEADERS.FUNCTION_MEMORY,
    constant.HEADERS.FUNCTION_TIMEOUT
];


exports.validateReqHeader = function(headers, cb, next) {
    try {
        validateHeaders(headers, invokeHeaders);
        // Execute next handler.
        next();
    } catch (err) {
        // Reject request.
        cb(err);
        return
    }
    
}



var validateHeaders = function(headers, expectedHeaders) {
    // Validate headers.
    // never use for...in loop to loop array, see: https://developer.mozilla.org/zh-CN/docs/Web/JavaScript/Reference/Statements/for...in
    // for...in is used for loop objects
    // and use Object.keys to generate array index or get Object keys
    if (expectedHeaders) {
        for (var header of expectedHeaders) {
            validateHeader(headers, header);
        }
    }
};

var validateHeader = function(headers, headerName, headerValue) {
    var err;
    var val = headers[headerName];
    if (!val) {
        err = new Error(util.format('Missing header %s', headerName));
        err.shouldSanitize = true;
        throw err;
    }
    if (val === '') {
        err = new Error(util.format('Invalid %s header value %s', headerName, val));
        err.shouldSanitize = true;
        throw err;
    }
};

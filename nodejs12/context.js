/*
 * Context is the object that user function can access to get function
 * metadata.
 */
'use strict';
var constant = require('./constant.js');
var logger = require('./logger.js');


//Context object provide function medata information.
exports.create = function (headers) {
    let reqId = headers[constant.HEADERS.REQUEST_ID];
    if (reqId == undefined) {
        throw new Error('the request id is undefined');
    }
    let ctx = {
        'requestId': reqId,
        'credentials': {
            accessKeyId: headers[constant.HEADERS.ACCESS_KEY_ID],
            accessKeySecret: headers[constant.HEADERS.ACCESS_KEY_SECRET],
            securityToken: headers[constant.HEADERS.SECURITY_TOKEN]
        },
        'function': {
            name: headers[constant.HEADERS.FUNCTION_NAME],
            handler: headers[constant.HEADERS.FUNCTION_HANDLER],
            memory: parseInt(headers[constant.HEADERS.FUNCTION_MEMORY]),
            timeout: parseInt(headers[constant.HEADERS.FUNCTION_TIMEOUT]),
        },
        'service': {
            name: headers[constant.HEADERS.SERVICE_NAME],
            logProject: headers[constant.HEADERS.SERVICE_LOG_PROJECT],
            logStore: headers[constant.HEADERS.SERVICE_LOG_STORE],
            qualifier: headers[constant.HEADERS.QUALIFIER],
            versionId: headers[constant.HEADERS.VERSION_ID]
        },
        'region': headers[constant.HEADERS.REGION],
        'accountId': headers[constant.HEADERS.ACCOUNT_ID],
        'logger': logger.createContextLog(reqId), 
        'retryCount': 0,
        'tracing': {
            'openTracingSpanContext': headers[constant.HEADERS.OPENTRACING_SPAN_CONTEXT],
            'openTracingSpanBaggages': parseOpenTracingBaggages(headers),
            'jaegerEndpoint': headers[constant.HEADERS.JAEGER_ENDPOINT]
        },
        get waitsForEmptyEventLoopBeforeCallback() {
            return global.FCWaitForEmptyEventLoop;
        },
        set waitsForEmptyEventLoopBeforeCallback(value) {
            global.FCWaitForEmptyEventLoop = value;
        },
    };

    if (headers[constant.HEADERS.RETRY_COUNT]) {
        ctx['retryCount'] = parseInt(headers[constant.HEADERS.RETRY_COUNT]);
    }

    return ctx;
};

function parseOpenTracingBaggages (headers) {
    let base64Baggages = headers[constant.HEADERS.OPENTRACING_SPAN_BAGGAGES];
    let baggages = {};
    if (base64Baggages != undefined && base64Baggages != '') {
        try {
            // import when used
            baggages = JSON.parse(Buffer.from(base64Baggages, 'base64').toString());
        } catch (e) {
            logger.getLogger().error('Failed to parse base64 opentracing baggages', e);
        }
    }
    return baggages;
}


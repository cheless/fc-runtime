/* jshint node: true */
"use strict";
var constant = require('./constant.js');
var http = require("http");
//get invocation from runtime, or post response to runtime

function convertHeaders(headersKV) {
    let h = {};
    for (let k in headersKV) {
        let v = headersKV[k];

        if (Array.isArray(v)) {
            h[k] = v;
            continue;
        }
        switch(k){
            case constant.HEADERS.FUNCTION_TYPE:
            case constant.HEADERS.FUNCTION_TIMEOUT:
            case constant.HEADERS.RETRY_COUNT:
            case "x-fc-function-deadline":
                v = parseInt(v);
                break;
        }
        h[k] = v;
    }
    return h
}

module.exports = class FcRuntimeClient {
    constructor(runtimeApiAddr, client) {
        this.client = client;
        let runtimeAPIServer = runtimeApiAddr.split(":");
        this.runtimeAPIIP = runtimeAPIServer[0];
        this.runtimeAPIPort = parseInt(runtimeAPIServer[1], 10);
        this.agent = new http.Agent({
            keepAlive: true
            //must bigger than 1, or the response maybe block
           // maxSockets: 5
        });

    }

    //return invocation request
    waitForInvocation(){
        if (this.client === undefined) {
            const options = {
                hostname: this.runtimeAPIIP,
                port: this.runtimeAPIPort,
                path: constant.GET_REQUEST_PATH,
                method: "GET",
                agent: this.agent
            };
            return new Promise((resolve, reject) => {
                let request = http.request(options, response => {
                    let buffers = [];
                    response
                        .on("data", chunk => {
                            buffers.push(Buffer.from(chunk));
                        })
                        .on("end", () => {
                            let headers = convertHeaders(response.headers);
                            resolve({
                                body: Buffer.concat(buffers),
                                headers: headers,
                            });
                        });
                    });
                request
                    .on("error", e => {
                        reject(e);
                    })
                    .end();
            });
          }else {
            //for ut
            this.client.WaitForInvocation();
        }
    }
    
    //post invocation result
    postInvocationResult(httpParams, response, id, callback){
        if (this.client === undefined) {
            let path = constant.POST_RESPONSE_PATH + id + "/response"
            this.postResult(httpParams, path, response, callback)
        }else {
            this.client.PostInvocationResult(id, response);
            callback();
        }
    }

    //post invocation error
    postInvocationError(error, id, callback){
        if (this.client === undefined) {
            let path = constant.POST_RESPONSE_PATH + id + "/error"
            this.postResult("", path, error, callback)
        }else {
            this.client.PostInvocationError(id, error, "");
            callback();
        }
    }

    //post invocation error
    postInitError(error, id, callback){
        if (this.client === undefined) {
            this.postResult("", constant.GET_INIT_ERROR_PATH, error, callback)
        }else {
            this.client.PostInitError(id, error, "");
            callback();
        }
    }

    postResult(httpParams, path, data, callback) {
        //body is buffer or string
        let contextLength = Buffer.from(data).length;
        const options = {
            hostname: this.runtimeAPIIP,
            port: this.runtimeAPIPort,
            path: path,
            method: "POST",
            headers: {
                    "Content-Type": "application/octet-stream",
                    "Content-Length": contextLength,
                    "x-fc-http-params": httpParams
            },
            agent: this.agent,
        };

        let request = http.request(options, response => {
            response.on("end", () => {
                    callback();
                }).on("error", e => {
                    throw e;
                }).on("data", () => {});
        });

        request.on("error", e => {
                throw e;
        });
        request.write(data);
        request.end();
    }

};

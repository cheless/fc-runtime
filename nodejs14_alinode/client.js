/* jshint node: true */
"use strict";
const logger = require('./logger.js');
const constant = require('./constant.js');
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
        this.client = client || require("./_native_rapis_client.node");
        this.useUnblockClient = process.env["FC_USE_UNBLOCK_CLIENT"] === "true";
        if (this.useUnblockClient) {
            logger.getLogger().info("use unblock http client");
            let [runtimeAPIIP, runtimeAPIPort] = runtimeApiAddr.split(":");
            this.runtimeAPIIP = runtimeAPIIP;
            this.runtimeAPIPort = parseInt(runtimeAPIPort, 10);
            this.http = require("http");
            this.agent = new this.http.Agent({
                keepAlive: true,
            });
      }
    }

    //return invocation request
    async waitForInvocation(){
        if (this.useUnblockClient) {
            const options = {
                hostname: this.runtimeAPIIP,
                port: this.runtimeAPIPort,
                path: constant.GET_REQUEST_PATH,
                method: "GET",
                agent: this.agent
            };
            return new Promise((resolve, reject) => {
                let request = this.http.request(options, response => {
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
          }
        return this.client.WaitForInvocation();
    }
    
    //post invocation result
    postInvocationResult(httpParams, response, id, callback){
        this.client.PostInvocationResult(id, response, httpParams);
        callback();
    }

    //post invocation error
    postInvocationError(error, id, callback){
        this.client.PostInvocationError(id, error, "");
        callback();
    }

    //post invocation error
    postInitError(error, id, callback){
        this.client.PostInitError(id, error, "");
        callback();
    }
};

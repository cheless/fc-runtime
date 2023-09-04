/*
 * Agent runs inside the container to execute user function code. Once a response
 * is sent back to client. Container should be frozen, which means all activities,
 * including agent itself, will be frozen as well. Container will be defrosted by
 * client (EA) when the next invocaiton is ready.
 *
 * Status code
 *   - 200: Agent will always return 200 once function is executed. Execution
 *          result will be saved in the response body.
 *   - 400: Indicate the request body is invalid.
 *   - 404: Function handler is invalid.
 *   - 417: Handled function error.
 *   - 429: Too many requests.
 *   - 500: Agent is unable to process the request.
 *
 * Environment variables
 *   - FC_SERVER_PORT: Server port number.
 *   - FC_FUNC_CODE_PATH: The location of the function code.
 */
'use strict';


// Utils
const EnableConcurrentInvoke = process.env["FC_ENABLE_CONCURRENT_INVOKE"] === "true";
// Configuration.
const configx = require('./config.js');
const config = configx.load();
const userCodePath = config.func.codePath;

var conxole = require('./console.js');
// Redirect console log to a log file.
conxole.config(config);
conxole.redirect();

// Init client
const fcRuntimeClient = require("./client.js");
const client = new fcRuntimeClient(process.env.FC_RUNTIME_API);

// Callback
const callback = require('./callback.js');
const invoke = require('./invoke.js');


// Enable sanitizer to wash sensitive information.
const sanitizer = require('./sanitizer.js');
sanitizer.config(config);
sanitizer.washErrorStack();
sanitizer.washEnv();


if (!config.func.codePath) {
    return;
}

// global before exit handler, init to blank function
const CALLBACK = Symbol.for("fcRuntimeCallback");
global[CALLBACK] = callback.createInitCallback(client);


global.FCWaitForEmptyEventLoop = false;

const BEFORE_EXIT_HANDLER = Symbol.for("fcRuntimeBeforeExitHandler");
global[BEFORE_EXIT_HANDLER] = () => {
    console.log('runtime exit before init.');
    new InvocationHandler(client).invokeIteration();
};


// catch user code error
process.on('uncaughtException', function (err) {
    console.error(err.stack);
    if (err == null) {
        err.name = "FunctionUnhandledError: " + err.name;
        global[CALLBACK](err);
    } else {
        let msg = "FunctionUnhandledError: uncaughtException";
        global[CALLBACK](msg);
    }
});

// before exit handler
process.on("beforeExit", () => {
    global[BEFORE_EXIT_HANDLER]();
});


// start loop
class InvocationHandler {
    constructor(client) {
        this.client = client;
        this.callback = () => {
        };
    }

    invokeIteration() {
        let that = this;
        setImmediate(() => {
            if (EnableConcurrentInvoke) {
                that.handleConcurrentInvocation();
            } else {
                that.handleInvocation();
            }
        });
    }

    handleInvocation() {
        this.client.waitForInvocation()
            .then(
                data => {
                    this.success(data);
                },
                err => {
                    console.error("wait for invocation error, due to:", err);
                })
            .catch(err => {
                console.error("catch error:", err);
            });
    }

    handleConcurrentInvocation() {
        this.client.waitForInvocation()
            .then(
                data => {
                    if(EnableConcurrentInvoke === true){
                        this.invokeIteration();
                    }
                    this.success(data);
                },
                err => {
                    console.error("wait for invocation error, due to:", err);
                })
            .catch(err => {
                console.error("catch error:", err);
            });

    }

    success(data){
        let body = data.body;
        let headers = data.headers;
        //create callback
        let invokeCallback = callback.createInvokeCallback(headers, this.client, () => {
            if (FCWaitForEmptyEventLoop === false && EnableConcurrentInvoke === false) {
                this.invokeIteration();
            }
        });
        this.callback = invokeCallback;
        global[CALLBACK] = invokeCallback;
        global[BEFORE_EXIT_HANDLER] = () => this.invokeIteration();
        try {
            let result = invoke(headers, Buffer.from(body), userCodePath, invokeCallback);
            if (_isAsyncFunction(result)) {
                //when promise end, it will goto next iteration by callback in invocation, no need callback here
                result
                    .then(
                        data => {
                        },
                        err => {
                            _handlerError(err, invokeCallback);
                        })
                    .catch(err => {
                        _handlerError(err, invokeCallback);
                    });
            }
        } catch (err) {
            _handlerError(err, invokeCallback);
        }
    }

}

function _handlerError(err, callback){
    if (err instanceof SyntaxError) {
        err.name  = "UserCodeSyntaxError";
    }
    if (err.code === "MODULE_NOT_FOUND") {
        err.name  = "ImportModuleError";
    }
    err.name = "FunctionUnhandledError: " + err.name;
    callback(err);
}

new InvocationHandler(client).invokeIteration();

function _isAsyncFunction(obj) {
    return obj && obj.then && typeof obj.then === "function";
}




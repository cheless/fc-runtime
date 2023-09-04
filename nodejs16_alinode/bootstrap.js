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
const logger = require('./logger.js');
const EnableConcurrentInvoke = process.env["FC_ENABLE_CONCURRENT_INVOKE"] === "true";
// Configuration.
const configx = require('./config.js');
const config = configx.load();
const userCodePath = config.func.codePath;
const rootfsPath = "/";


// Init client
const fcRuntimeClient = require("./client.js");
const client = new fcRuntimeClient(process.env.FC_RUNTIME_API);


// Redirect console log to a log file.
const conxole = require('./console.js');
conxole.config(config);
conxole.redirect();


// Callback
const callback = require('./callback.js');
const invoke = require('./invoke.js');


// Enable sanitizer to wash sensitive information.
const sanitizer = require('./sanitizer.js');
const constant = require("./constant.js");

sanitizer.config(config);
sanitizer.washErrorStack();
sanitizer.washEnv();


if (!config.func.codePath) {
    logger.getLogger().error('Function code path is not set.');
    return;
}
logger.getLogger().info('FunctionCompute nodejs runtime inited.');
logger.getLogger().info('Function code path is set on: %s.', config.func.codePath);

// global before exit handler, init to blank function
const CALLBACK = Symbol.for("fcRuntimeCallback");
global[CALLBACK] = callback.createInitCallback(client);

global.FCWaitForEmptyEventLoop = false;


// reserved the workspace path and change to rootfs for checkpoint/restore
global.CreateFromSnapshot  = process.env["FC_SNAPSHOT_FLAG"] === "1";
const defaultWorkDir = process.cwd()
if (CreateFromSnapshot === true) {
    logger.getLogger().info('During snapshot creating');
    process.chdir(rootfsPath);
}

const BEFORE_EXIT_HANDLER = Symbol.for("fcRuntimeBeforeExitHandler");
global[BEFORE_EXIT_HANDLER] = () => {
    if (EnableConcurrentInvoke) {
        new InvocationHandler(client).invokeConcurrentIteration();
    } else {
        new InvocationHandler(client).invokeIteration();
    }
};

// catch user code error
process.on('uncaughtException', function (err) {
    logger.getLogger().error('got uncaughtException in bootstrap');
    console.error(err.stack);
    logger.getLogger().error(err.stack);
    if (!err) {
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
            that.handleInvocation().then().catch(err => {
                logger.getLogger().error(`Uncaught Runtime Error: ${err.toString()}, ${err.stack.split("\n")}`);
                this.callback(err);
            });
        });
    }

    async handleInvocation() {
        global[BEFORE_EXIT_HANDLER] = () => this.invokeIteration();
        let {headers, body} = await this.client.waitForInvocation();
        if(this.client.useUnblockClient === true){
            this.invokeFunction(headers, body);
        }else {
            let that = this;
            setImmediate(() => {
                that.invokeFunction(headers, body);
            });
        }
    }

    invokeConcurrentIteration() {
        let that = this;
        setImmediate(() => {
            that.handleConcurrentInvocation().then().catch(err => {
                logger.getLogger().error(`Uncaught Runtime Error: ${err.toString()}, ${err.stack.split("\n")}`);
                this.callback(err);
            });
        });
    }

    async handleConcurrentInvocation() {
        global[BEFORE_EXIT_HANDLER] = () => this.invokeConcurrentIteration();
        this.client.waitForInvocation()
            .then(
                data => {
                    if (EnableConcurrentInvoke === true) {
                        this.invokeConcurrentIteration();
                    }
                    this.invokeFunction(data.headers, data.body);
                })
            .catch(err => {
                console.error("wait for invocation error, due to:", err, );
            });
    }

    invokeFunction(headers, body) {
        let invokeCallback = callback.createInvokeCallback(headers, this.client, () => {
            if (!FCWaitForEmptyEventLoop && EnableConcurrentInvoke === false) {
                this.invokeIteration();
            }
        });
        this.callback = invokeCallback;
        global[CALLBACK] = invokeCallback;
        try {
            // Restore the workspace to default before invoke user function
            if (global.CreateFromSnapshot) {
                logger.getLogger().info('Restore from snapshot');
                process.chdir(defaultWorkDir);
                //restore the temporary environment
                if (typeof headers[constant.HEADERS.ACCESS_KEY_ID] !== 'undefined') {
                    if ("ALIBABA_CLOUD_ACCESS_KEY_ID" in process.env) {
                        process.env['ALIBABA_CLOUD_ACCESS_KEY_ID'] = headers[constant.HEADERS.ACCESS_KEY_ID];
                    }
                    if ("accessKeyID" in process.env) {
                        process.env['accessKeyID'] = headers[constant.HEADERS.ACCESS_KEY_ID];
                    }
                }
                if (typeof headers[constant.HEADERS.ACCESS_KEY_SECRET] !== 'undefined') {
                    if ("ALIBABA_CLOUD_ACCESS_KEY_SECRET" in process.env) {
                        process.env['ALIBABA_CLOUD_ACCESS_KEY_SECRET'] = headers[constant.HEADERS.ACCESS_KEY_SECRET];
                    }
                    if ("accessKeySecret" in process.env) {
                        process.env['accessKeySecret'] = headers[constant.HEADERS.ACCESS_KEY_SECRET];
                    }
                }
                if (typeof headers[constant.HEADERS.SECURITY_TOKEN] !== 'undefined') {
                    if ("ALIBABA_CLOUD_SECURITY_TOKEN" in process.env) {
                        process.env['ALIBABA_CLOUD_SECURITY_TOKEN'] = headers[constant.HEADERS.SECURITY_TOKEN];
                    }
                    if ("securityToken" in process.env) {
                        process.env['securityToken'] = headers[constant.HEADERS.SECURITY_TOKEN];
                    }
                }
                if (typeof headers[constant.HEADERS.INSTANCE_ID] !== 'undefined') {
                    if ("FC_INSTANCE_ID" in process.env) {
                        process.env['FC_INSTANCE_ID'] = headers[constant.HEADERS.INSTANCE_ID];
                    }
                }
                global.CreateFromSnapshot = false;
                delete process.env['FC_SNAPSHOT_FLAG'];
            }
            let result = invoke(headers, Buffer.from(body), userCodePath, invokeCallback);
            if (_isAsyncFunction(result)) {
                result
                    .then()
                    .catch(err => {
                        _handlerError(err, invokeCallback);
                    });
            }
        } catch (err) {
            logger.getLogger().error('FunctionCompute nodejs runtime exception:', err);
            _handlerError(err, invokeCallback);
        }
    }
}


if (EnableConcurrentInvoke) {
    new InvocationHandler(client).invokeConcurrentIteration();
} else {
    new InvocationHandler(client).invokeIteration();
}

function _handlerError(err, callback) {
    if (err instanceof SyntaxError) {
        err.name = "UserCodeSyntaxError";
    }
    if (err.code === "MODULE_NOT_FOUND") {
        err.name = "ImportModuleError";
    }
    try {
        err.name = "FunctionUnhandledError: " + err.name;
    }catch (err){
        //ignore
    }
    callback(err);
}

function _isAsyncFunction(obj) {
    return obj && obj.then && typeof obj.then === "function";
}




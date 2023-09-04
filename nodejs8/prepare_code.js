'use strict';
var fs = require('fs');
var util = require('util');
var path = require('path');
var error = require('./error.js');
var logger = require('./logger.js');

var globalModule = {}; // use this map to cache loaded functions

/*
 * Reload the function definition into memory if handler is changed.
 * handle is in the format of '<file name>.<method name>'.
 * Throw: throw error if handler is invalid.
 */
exports.loadFunction = function(codePath, handler) {
    // if this handler is already loaded, just return it
    if ( globalModule[handler] != null && typeof(globalModule[handler]) === 'function') {
        return globalModule[handler];
    }

    process.stdout.write("load code for handler:" + handler + '\n');
    logger.getLogger().info("load code for handler:" + handler + '\n');

    var err;
    var index = handler.lastIndexOf('.');
    if (index === -1) {
        err = new error.InvalidHandlerName(util.format('Invalid handler \'%s\'', handler));
        err.shouldSanitize = true;
        throw err;
    }
    var moduleName = handler.slice(0, index);
    var handlerName = handler.slice(index + 1);
    var modulePath = path.join(codePath, moduleName + '.js');
    if (!fs.existsSync(modulePath)) {
      err = new error.ImportModuleError(util.format('Module \'%s\' is missing.', modulePath));
      err.shouldSanitize = true;
      throw err;
    }
    var module = require(modulePath);

    if (typeof(module[handlerName]) === 'function') {
        globalModule[handler] = module[handlerName] // cache loaded function to globalModule
        return module[handlerName];
    }
    err = new error.HandlerNotFound(util.format('Handler \'%s\' is missing on module \'%s\'', handlerName, moduleName));
    err.shouldSanitize = true;
    throw err;
};

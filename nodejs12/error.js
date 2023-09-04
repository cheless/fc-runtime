//errors maybe throw by the bootstrap


//the function handler name is invalid
class InvalidHandlerName extends Error {
}
InvalidHandlerName.prototype.name = "InvalidHandlerName";

//the function handler name is not exsits
class HandlerNotFound extends Error {
}
HandlerNotFound.prototype.name = "HandlerNotFound";

//the user code have syntax error
class UserCodeSyntaxError extends Error {
}
UserCodeSyntaxError.prototype.name = "UserCodeSyntaxError";

//user code error
class UserCodeError extends Error {
}
UserCodeError.prototype.name = "UserCodeError";

//user code import module error
class ImportModuleError extends Error {
}
ImportModuleError.prototype.name = "ImportModuleError";

//user code import module error
class FunctionHandledError extends Error {
}
FunctionHandledError.prototype.name = "FunctionHandledError";

module.exports = { InvalidHandlerName, HandlerNotFound, UserCodeSyntaxError,UserCodeError, ImportModuleError, FunctionHandledError};



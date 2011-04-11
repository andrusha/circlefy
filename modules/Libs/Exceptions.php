<?php
/*
    You should place here all Exceptions,
    to make them easily load by __autoload.

    Exception recognized by last word Exception
    in it's name
*/

class DataException extends OutOfBoundsException {};
class InitializeException extends LogicException {};

//Comet server workaround exceptions
//Comet.php
abstract class CometException extends Exception {};
class UnknownServerException extends CometException {};
class PacketFormatException extends CometException {};
class SocketException extends CometException {};

//Database exceptions
//DB.php
abstract class DBException extends Exception {};
class QueryParamException extends DBException {};
class NotImplementedException extends DBException {};
class TransactionException extends DBException {};
class SQLException extends DBException {};
class AssertionException extends DBException {};

//Facebook class exceptions
//Facebook.php
abstract class FacebookException extends Exception {};
class FacebookDataException extends FacebookException {};

//User exceptions
//User.php
abstract class UserException extends Exception {};
class UserInfoException extends UserException {};
class AuthException extends UserException {};

//Actions exceptions
//Actions.php
abstract class ActionsExceptions extends Exception {};
class ActionDataException extends ActionsExceptions {};
class ActionTypeException extends ActionsExceptions {};

// Curl.php
class NetworkException extends Exception {};

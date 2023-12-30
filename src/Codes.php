<?php 

namespace MemSess;

class Codes {
    public const PREFIX_LOCK = '.lock';

    public const OK = 1;
    public const E_WRONG_COMMAND = 2;
    public const E_WRONG_PARAMS = 3;
    public const E_SESSION_NONE = 4;
    public const E_KEY_NONE = 5;
    public const E_LIMIT_EXCEEDED = 6;
    public const E_LIFETIME_EXCEEDED = 7;
    public const E_DUPCLICATE_KEY = 8;
    public const E_RECORD_BEEN_CHANGED = 9;
    public const E_LIMIT_PER_SEC_EXCEEDED = 10;
    public const E_DUPLICATE_SESSION = 11;

    public const E_WRONG_LIFETIME = 1001;
    public const E_WRONG_TIMEOUT = 1002;
    public const E_WRONG_ATTEMPTS = 1003;
    public const E_WRONG_KEY = 1004;
    public const E_WRONG_UUID = 1005;
    public const E_WRONG_LIMIT = 1006;

    public const E_UNKNOWN = 1101;
    public const E_SEND = 1102;
    public const E_SAVE_BEFORE_LOAD = 1103;
};

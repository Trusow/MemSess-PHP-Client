<?php 

namespace MemSess;

class Codes {
    public const OK = 1;
    public const E_WRONG_COMMAND = 2;
    public const E_WRONG_PARAMS = 3;
    public const E_SESSION_NONE = 4;
    public const E_KEY_NONE = 5;
    public const E_LIMIT = 6;
    public const E_LIFETIME_EXCEEDED = 7;
    public const E_DUPCLICATE_KEY = 8;
    public const E_RECORD_BEEN_CHANGED = 9;
    public const E_LIMIT_PER_SEC = 10;
    public const E_DUPLICATE_SESSION = 11;
    public const E_UNKNOWN = 12;
    public const E_SEND = 13;
    public const E_SAVE_BEFORE_LOAD = 14;
};

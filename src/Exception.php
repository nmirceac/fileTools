<?php namespace ColorTools;

class Exception extends \Exception{
    const STORE_EXCEPTION_HASH_EMPTY = 1;
    const STORE_EXCEPTION_HASH_NOT_32 = 2;
    const STORE_EXCEPTION_HASH_NOT_FOUND = 3;
}

<?php

namespace IPP\Interpreter\Classes;

use IPP\Interpreter\Classes\Common\FalseEntity;
use IPP\Interpreter\Classes\Common\StringEntity;
use IPP\Interpreter\Classes\Common\TrueEntity;

class ObjectEntity
{
    public function __construct() {}
    public function identicalTo(object $myself, object $target) : TrueEntity | FalseEntity{
        if($myself instanceof $target) {
            return new TrueEntity();
        }
        return new FalseEntity();
    }
//    public function equalTo(object $myself, object $target) : bool{
//        if
//    }
    public function asString() : object{
        return new StringEntity('');
    }
    public function isNumber() : bool{
        return false;
    }
    public function isString() : bool{
        return false;
    }
    public function isBlock() : bool{
        return false;
    }
    public function isNil() : bool{
        return false;
    }
    public function isBoolean() : bool{
        return false;
    }
}
<?php

namespace IPP\Interpreter\Classes;

use IPP\Interpreter\Classes\Common\StringEntity;

class ObjectEntity
{
    public function identicalTo(object $myself, object $target) : bool{
        if($myself instanceof $target) {
            return true;
        }
        return false;
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
<?php

namespace IPP\Interpreter\Classes\Common;

use IPP\Interpreter\Classes\ObjectEntity;

class FalseEntity extends ObjectEntity
{
    public bool $value = false;

    public function asString() : StringEntity {
        return new StringEntity((string)$this->value);
    }
    public function not(TrueEntity $cilovyObjekt) : bool{
        return !$cilovyObjekt->value;
    }

//    public function and(TrueEntity $cilovyObjekt) : bool{
//        if($cilovyObjekt->value == false){
//            return false;
//        }else{
//
//        }
//    }

//    public function or(IntegerEntity $cilovyObjekt){}

//    public function ifTrue(IntegerEntity $cilovyObjekt){}

    public function isBoolean() : bool{
        return true;
    }
}
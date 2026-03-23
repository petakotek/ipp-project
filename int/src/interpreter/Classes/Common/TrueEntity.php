<?php

namespace IPP\Interpreter\Classes\Common;

use IPP\Interpreter\Classes\ObjectEntity;


final class TrueEntity extends ObjectEntity
{
    /** @var array<TrueEntity> */
    private static array $instances = [];

    public bool $value = true;

    protected function __construct(){
        parent::__construct();
    }

    protected function __clone()
    {
    }

    public static function getInstance(): TrueEntity
    {
        $cls = TrueEntity::class;
        if (!isset(self::$instances[$cls])) {
            self::$instances[$cls] = new TrueEntity();
        }

        return self::$instances[$cls];
    }

    public function asString() : object {
        return new StringEntity('true');
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

    /// KONSTRUKTORY
    public static function new(): static
    {
        return new self();
    }
}


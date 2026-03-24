<?php

namespace IPP\Interpreter\Classes\Common;

use IPP\Interpreter\Classes\ObjectEntity;

final class FalseEntity extends ObjectEntity
{
    /** @var array<FalseEntity> */
    private static array $instances = [];
    public bool $value = false;

    protected function __construct()
    {
        parent::__construct();
    }

    protected function __clone()
    {
    }
    public static function getInstance(): FalseEntity
    {
        $cls = FalseEntity::class;
        if (!isset(self::$instances[$cls])) {
            self::$instances[$cls] = new FalseEntity();
        }

        return self::$instances[$cls];
    }
    public function asString(): StringEntity
    {
        return new StringEntity('false');
    }
    public function not(): TrueEntity
    {
        return TrueEntity::getInstance();
    }


//    public function and(TrueEntity $cilovyObjekt) : bool{
//    }

//    public function or(IntegerEntity $cilovyObjekt){}

//    public function ifTrue(IntegerEntity $cilovyObjekt){}

    public function isBoolean(): TrueEntity
    {
        return TrueEntity::getInstance();
    }

    /// KONSTRUKTORY
    public static function new(): FalseEntity
    {
        return new self();
    }
}

<?php

namespace IPP\Interpreter\Classes;

use IPP\Interpreter\Classes\Common\FalseEntity;
use IPP\Interpreter\Classes\Common\StringEntity;
use IPP\Interpreter\Classes\Common\TrueEntity;

class ObjectEntity
{
    public function __construct()
    {
    }
    public function identicalTo(object $target): TrueEntity | FalseEntity
    {
        if (get_class($this) === get_class($target)) {
            return new TrueEntity();
        }
        return new FalseEntity();
    }
    public function equalTo(object $target): TrueEntity | FalseEntity
    {
        if ($target instanceof $this) {
            return TrueEntity::getInstance();
        }
        return FalseEntity::getInstance();
    }
    public function asString(): object
    {
        return new StringEntity('');
    }
    public function isNumber(): FalseEntity | TrueEntity
    {
        return FalseEntity::getInstance();
    }
    public function isString(): FalseEntity | TrueEntity
    {
        return FalseEntity::getInstance();
    }
    public function isBlock(): FalseEntity | TrueEntity
    {
        return FalseEntity::getInstance();
    }
    public function isNil(): FalseEntity | TrueEntity
    {
        return FalseEntity::getInstance();
    }
    public function isBoolean(): FalseEntity | TrueEntity
    {
        return FalseEntity::getInstance();
    }
}

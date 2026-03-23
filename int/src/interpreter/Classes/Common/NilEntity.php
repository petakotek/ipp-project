<?php

namespace IPP\Interpreter\Classes\Common;

use IPP\Interpreter\Classes\ObjectEntity;

final class NilEntity extends ObjectEntity
{
    public function __construct()
    {
        parent::__construct();
    }
    public function asString(): StringEntity
    {
        return new StringEntity('nil');
    }

    public function isNil(): TrueEntity
    {
        return TrueEntity::getInstance();
    }

    public static function new(): NilEntity
    {
        return new NilEntity();
    }

    public static function from(): NilEntity
    {
        return new NilEntity();
    }
}

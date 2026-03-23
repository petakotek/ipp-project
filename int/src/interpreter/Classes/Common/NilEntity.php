<?php

namespace IPP\Interpreter\Classes\Common;

use IPP\Interpreter\Classes\ObjectEntity;

class NilEntity extends ObjectEntity
{
    public function __construct(){

    }
    public function asString() : StringEntity{
        return new StringEntity('nil');
    }
}
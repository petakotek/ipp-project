<?php

namespace IPP\Interpreter\Classes;

use IPP\Interpreter\Classes\Common\FalseEntity;
use IPP\Interpreter\Classes\Common\TrueEntity;
use IPP\Interpreter\Classes\ObjectEntity;

class BlockEntity extends ObjectEntity
{
    // localni promenne instance bloku
    private array $promenne = [];

    public function isBlock(): TrueEntity
    {
        return TrueEntity::getInstance();
    }
}
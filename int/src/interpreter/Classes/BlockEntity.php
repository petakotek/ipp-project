<?php

namespace IPP\Interpreter\Classes;

use IPP\Interpreter\Classes\Common\TrueEntity;
use IPP\Interpreter\InputModel\Block;

class BlockEntity extends ObjectEntity
{
    // localni promenne instance bloku
    public array $locals = [];
    public array $assigns = [];
    public int $arity = 0;
    public function __construct(Block $block)
    {
        parent::__construct();
        $this->locals = $block->parameters;
        $this->assigns = $block->assigns;
        $this->arity = $block->arity;
    }
    public function isBlock(): TrueEntity
    {
        return TrueEntity::getInstance();
    }
}

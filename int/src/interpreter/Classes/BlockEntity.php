<?php

namespace IPP\Interpreter\Classes;

use IPP\Interpreter\Classes\Common\TrueEntity;
use IPP\Interpreter\InputModel\Block;

class BlockEntity extends ObjectEntity
{
    // localni promenne instance bloku
    public array $locals = [];
    public array $assigns = [];
    public array $upperLocals = [];
    public int $arity = 0;
    public function __construct(Block $block, array &$lcs)
    {
        parent::__construct();
        $this->locals = $block->parameters;
        $this->assigns = $block->assigns;
        $this->arity = $block->arity;
        $this->upperLocals = &$lcs;
    }
    public function isBlock(): TrueEntity
    {
        return TrueEntity::getInstance();
    }
}

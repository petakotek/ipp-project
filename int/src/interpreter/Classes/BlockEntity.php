<?php

namespace IPP\Interpreter\Classes;

use IPP\Interpreter\Classes\Common\TrueEntity;
use IPP\Interpreter\InputModel\Block;

class BlockEntity extends ObjectEntity
{
    // localni promenne instance bloku
    /** @var array<mixed> $locals */
    public array $locals = [];
    /** @var array<mixed> $assigns */
    public array $assigns = [];
    /** @var array<mixed> $upperLocals */
    public array $upperLocals = [];
    public int $arity = 0;
    /// TODO: potřeba implementovat nějaký rozsah platnosti pro bloky
    /// nejspíš asi vytvořit nějaké rozhraní a následně v tomto
    /// rozhraní mít pole scope, gettery settery
    ///
    ///
    /**
     * @param Block $block
     * @param array<mixed> $lcs
     */
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

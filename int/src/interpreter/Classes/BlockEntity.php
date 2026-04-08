<?php

namespace IPP\Interpreter\Classes;

use IPP\Interpreter\Classes\Common\TrueEntity;
use IPP\Interpreter\InputModel\Block;

class BlockEntity extends ObjectEntity
{
    public ProgramInterface $interface;
    /** @var array<mixed> $assigns */
    public array $assigns = [];

    public int $arity = 0;

    /**
     * @var array|\IPP\Interpreter\InputModel\Parameter[]
     */
    public array $parameters = [];

    /**
     * @param Block $block
     * @param ProgramInterface $iface - rozhrani programu
     */
    public function __construct(Block $block, ProgramInterface $iface)
    {
        parent::__construct();
        $this->interface = $iface;
        $this->assigns = $block->assigns;
        $this->arity = $block->arity;
        $this->parameters = $block->parameters;
    }
    public function isBlock(): TrueEntity
    {
        return TrueEntity::getInstance();
    }
}

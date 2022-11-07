<?php

namespace A17\PhpTorch\Parser;

use A17\PhpTorch\DocParser;
use PhpParser\Node\Stmt\ClassMethod;

class MethodStatement extends PositionalStatement
{
    public function __construct(public ClassMethod $node, DocParser $parser)
    {
        parent::__construct($parser);
    }

    public function getName(): string
    {
        return $this->node->name->toString();
    }
}

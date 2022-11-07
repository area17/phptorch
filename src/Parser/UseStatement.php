<?php

namespace A17\PhpTorch\Parser;

use A17\PhpTorch\DocParser;
use PhpParser\Node\Stmt\UseUse;

class UseStatement extends PositionalStatement
{
    public function __construct(public UseUse $node, DocParser $parser)
    {
        parent::__construct($parser);
    }

    public function getName(): string
    {
        return $this->node->name->toString();
    }

    public function matches(string $use): bool
    {
        return $this->getName() === $use || $this->getName() === ltrim($use, '\\');
    }
}

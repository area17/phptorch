<?php

namespace A17\PhpTorch\Parser;

use A17\PhpTorch\DocParser;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\PropertyProperty;

class PropertyStatement extends PositionalStatement
{
    public function __construct(public Property $node, DocParser $parser)
    {
        parent::__construct($parser);
    }

    public function getName(): string
    {
        foreach ($this->node->props as $node) {
            if ($node instanceof PropertyProperty) {
                return $node->name->toString();
            }
        }

        throw new \Exception('Unknown case.');
    }
}

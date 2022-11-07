<?php

namespace A17\PhpTorch\Parser;

use A17\PhpTorch\DocParser;

abstract class PositionalStatement
{
    public function __construct(protected DocParser $parser)
    {
    }

    abstract public function getName(): string;

    public function getStartLine(): int
    {
        return $this->node->getStartLine();
    }

    public function getEndLine(): int
    {
        return $this->node->getEndLine();
    }

    public function isSingleLine(): bool
    {
        return $this->getStartLine() === $this->getEndLine();
    }
}

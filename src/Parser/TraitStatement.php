<?php

namespace A17\PhpTorch\Parser;

use A17\PhpTorch\DocParser;
use PhpParser\Node\Name;

class TraitStatement extends PositionalStatement
{
    public function __construct(public Name $node, DocParser $parser)
    {
        parent::__construct($parser);
    }

    public function getName(): string
    {
        if ($use = $this->getUseForTrait()) {
            return $use;
        }

        if ($namespace = $this->getNamespaceForTrait()) {
            return $namespace . '\\' . $this->getBaseName();
        }

        return $this->getBaseName();
    }

    protected function getBaseName(): string
    {
        return $this->node->toString();
    }

    private function getUseForTrait(): ?string
    {
        foreach ($this->parser->getUseStatements() as $useStatement) {
            if (str_ends_with($useStatement->getName(), '\\' . $this->getBaseName())) {
                return $useStatement->getName();
            }
        }

        return null;
    }

    private function getNamespaceForTrait(): ?string
    {
        if (!str_contains($this->getBaseName(), '\\')) {
            return $this->parser->getNamespace();
        }

        return null;
    }

    public function matches(string $trait): bool
    {
        return $this->getName() === $trait || $this->getName() === ltrim($trait, '\\');
    }
}

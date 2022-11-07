<?php

namespace A17\PhpTorch;

use A17\PhpTorch\Parser\MethodStatement;
use A17\PhpTorch\Parser\PropertyStatement;
use A17\PhpTorch\Parser\TraitStatement;
use A17\PhpTorch\Parser\UseStatement;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\TraitUse;
use PhpParser\Node\Stmt\Use_;
use PhpParser\ParserFactory;

class DocParser
{
    /** @var \PhpParser\Node[] */
    private ?array $ast = null;

    public function __construct(private ?string $content = null)
    {
    }

    public static function forContent(string $content): self
    {
        return new self(content: $content);
    }

    public function getCode(): string
    {
        return $this->content;
    }

    private function parsed(): array
    {
        if ($this->ast === null) {
            $parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);
            $this->ast = $parser->parse($this->getCode());
        }

        return $this->ast;
    }

    public function getNamespaceLine(): ?int
    {
        return $this->getNamespaceNode()?->getStartLine();
    }

    public function getClassStartLine(): ?int
    {
        return $this->getClass()?->getStartLine();
    }

    public function getClassEndLine(): ?int
    {
        return $this->getClass()?->getEndLine();
    }

    /**
     * @return \A17\PhpTorch\Parser\TraitStatement[]
     */
    public function getTraits(): array
    {
        $list = [];

        if ($class = $this->getClass()) {
            foreach ($class->stmts as $node) {
                if ($node instanceof TraitUse) {
                    foreach ($node->traits as $trait) {
                        $list[] = new TraitStatement($trait, $this);
                    }
                }
            }
        }

        return $list;
    }

    /**
     * @return \A17\PhpTorch\Parser\MethodStatement[]
     */
    public function getMethods(): array
    {
        $list = [];

        if ($class = $this->getClass()) {
            foreach ($class->stmts as $node) {
                if ($node instanceof ClassMethod) {
                    $list[] = new MethodStatement($node, $this);
                }
            }
        }

        return $list;
    }

    /**
     * @return \A17\PhpTorch\Parser\PropertyStatement[]
     */
    public function getProperties(): array
    {
        $list = [];
        if ($class = $this->getClass()) {
            foreach ($class->stmts as $node) {
                if ($node instanceof Property) {
                    $list[] = new PropertyStatement($node, $this);
                }
            }
        }

        return $list;
    }

    /**
     * @return \A17\PhpTorch\Parser\UseStatement[]
     */
    public function getUseStatements(): array
    {
        $list = [];
        if ($namespace = $this->getNamespaceNode()) {
            foreach ($namespace->stmts as $node) {
                if ($node instanceof Use_) {
                    foreach ($node->uses as $use) {
                        $list[] = new UseStatement($use, $this);
                    }
                }
            }
        }

        return $list;
    }

    public function getNamespace(): ?string
    {
        return $this->getNamespaceNode()?->name->toString();
    }

    private function getClass(): ?Class_
    {
        foreach ($this->parsed() as $node) {
            if ($node instanceof Class_) {
                return $node;
            }
            if ($node instanceof Namespace_) {
                foreach ($node->stmts as $subNode) {
                    if ($subNode instanceof Class_) {
                        return $subNode;
                    }
                }
            }
        }

        return null;
    }

    private function getNamespaceNode(): ?Namespace_
    {
        foreach ($this->parsed() as $node) {
            if ($node instanceof Namespace_) {
                return $node;
            }
        }

        return null;
    }
}

<?php

namespace A17\PhpTorch\Tests\Unit;

use A17\PhpTorch\DocParser;
use PHPUnit\Framework\TestCase;

class ParserTest extends TestCase
{
    public function testParseBasicPhpClass(): void
    {
        $class = <<<PHP
<?php
class test {

}
PHP;

        $parser = DocParser::forContent($class);
        $this->assertEmpty($parser->getUseStatements());
        $this->assertEquals(2, $parser->getClassStartLine());
        $this->assertEquals(4, $parser->getClassEndLine());
        $this->assertEquals(null, $parser->getNamespaceLine());
    }

    public function testParseBasicPhpClassWithNamespace(): void
    {
        $class = <<<PHP
<?php

namespace Bar;

class test {

}
PHP;

        $parser = DocParser::forContent($class);
        $this->assertEmpty($parser->getUseStatements());
        $this->assertEquals(5, $parser->getClassStartLine());
        $this->assertEquals(7, $parser->getClassEndLine());
        $this->assertEquals(3, $parser->getNamespaceLine());
    }

    public function testParseBasicPhpClassWithImports(): void
    {
        $class = <<<PHP
<?php

namespace Bar;

use Some\Class;
use Some\Other\Class;

class test {

}
PHP;

        $parser = DocParser::forContent($class);

        $this->assertCount(2, $parser->getUseStatements());

        $this->assertEquals('Some\Class', $parser->getUseStatements()[0]->getName());
        $this->assertEquals(true, $parser->getUseStatements()[0]->isSingleLine());
        $this->assertEquals(5, $parser->getUseStatements()[0]->getStartLine());
        $this->assertEquals(5, $parser->getUseStatements()[0]->getEndLine());

        $this->assertEquals('Some\Other\Class', $parser->getUseStatements()[1]->getName());
        $this->assertEquals(true, $parser->getUseStatements()[1]->isSingleLine());
        $this->assertEquals(6, $parser->getUseStatements()[1]->getStartLine());
        $this->assertEquals(6, $parser->getUseStatements()[1]->getEndLine());

        $this->assertEquals(8, $parser->getClassStartLine());
        $this->assertEquals(10, $parser->getClassEndLine());
        $this->assertEquals(3, $parser->getNamespaceLine());
    }

    public function testClassWithTraits(): void
    {
        $class = <<<'PHP'
<?php

class test {

use SomeClass;
use Some\Other\Trait;

}
PHP;

        $parser = DocParser::forContent($class);

        $this->assertCount(2, $parser->getTraits());

        $this->assertEquals('SomeClass', $parser->getTraits()[0]->getName());
        $this->assertEquals(true, $parser->getTraits()[0]->isSingleLine());
        $this->assertEquals(5, $parser->getTraits()[0]->getStartLine());
        $this->assertEquals(5, $parser->getTraits()[0]->getEndLine());

        $this->assertEquals('Some\Other\Trait', $parser->getTraits()[1]->getName());
        $this->assertEquals(true, $parser->getTraits()[1]->isSingleLine());
        $this->assertEquals(6, $parser->getTraits()[1]->getStartLine());
        $this->assertEquals(6, $parser->getTraits()[1]->getEndLine());
    }

    public function testClassWithTraitsFromImportWhenNamespaced(): void
    {
        $class = <<<'PHP'
<?php

namespace App\Models;

use App\Models\Traits\SomeTrait;

class Model {

use SomeTrait;
use RelativeTrait;

}
PHP;

        $parser = DocParser::forContent($class);

        $this->assertCount(2, $parser->getTraits());

        $this->assertEquals('App\Models\Traits\SomeTrait', $parser->getTraits()[0]->getName());
        $this->assertEquals(true, $parser->getTraits()[0]->isSingleLine());
        $this->assertEquals(9, $parser->getTraits()[0]->getStartLine());
        $this->assertEquals(9, $parser->getTraits()[0]->getEndLine());

        $this->assertEquals('App\Models\RelativeTrait', $parser->getTraits()[1]->getName());
        $this->assertEquals(true, $parser->getTraits()[1]->isSingleLine());
        $this->assertEquals(10, $parser->getTraits()[1]->getStartLine());
        $this->assertEquals(10, $parser->getTraits()[1]->getEndLine());
    }

    public function testClassWithProperties(): void
    {
        $class = <<<'PHP'
<?php

class test {

public string $demoString = 'f';

public array $demoArray = [
    'bar' => 'foo'
];

}
PHP;

        $parser = DocParser::forContent($class);

        $this->assertCount(2, $parser->getProperties());

        $this->assertEquals('demoString', $parser->getProperties()[0]->getName());
        $this->assertEquals(true, $parser->getProperties()[0]->isSingleLine());
        $this->assertEquals(5, $parser->getProperties()[0]->getStartLine());
        $this->assertEquals(5, $parser->getProperties()[0]->getEndLine());

        $this->assertEquals('demoArray', $parser->getProperties()[1]->getName());
        $this->assertEquals(false, $parser->getProperties()[1]->isSingleLine());
        $this->assertEquals(7, $parser->getProperties()[1]->getStartLine());
        $this->assertEquals(9, $parser->getProperties()[1]->getEndLine());
    }

    public function testClassWithMethods(): void
    {
        $class = <<<'PHP'
<?php

class test {

abstract public function abstractFunc(): void;

public function simpleInline(): void { return true; }

public function simpleMultiLine(): void { 
    return true; 
}

}
PHP;

        $parser = DocParser::forContent($class);

        $this->assertCount(3, $parser->getMethods());

        $this->assertEquals('abstractFunc', $parser->getMethods()[0]->getName());
        $this->assertEquals(true, $parser->getMethods()[0]->isSingleLine());
        $this->assertEquals(5, $parser->getMethods()[0]->getStartLine());
        $this->assertEquals(5, $parser->getMethods()[0]->getEndLine());

        $this->assertEquals('simpleInline', $parser->getMethods()[1]->getName());
        $this->assertEquals(true, $parser->getMethods()[1]->isSingleLine());
        $this->assertEquals(7, $parser->getMethods()[1]->getStartLine());
        $this->assertEquals(7, $parser->getMethods()[1]->getEndLine());

        $this->assertEquals('simpleMultiLine', $parser->getMethods()[2]->getName());
        $this->assertEquals(false, $parser->getMethods()[2]->isSingleLine());
        $this->assertEquals(9, $parser->getMethods()[2]->getStartLine());
        $this->assertEquals(11, $parser->getMethods()[2]->getEndLine());
    }
}

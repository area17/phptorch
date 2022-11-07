<?php

namespace A17\PhpTorch\Tests\Unit;

use A17\PhpTorch\Highlight;
use PHPUnit\Framework\TestCase;

class HighlighterTest extends TestCase
{
    protected string $class = <<<'php'
<?php

namespace App\Models;

use App\Models\SomeOtherModel;
use Package\PackageCachableTrait;

class Blog extends SomeOtherModel {
    use CachableTrait;
    use PackageCachableTrait;

    public $fillable = [
        'bar',
        'foo',
        'baz'
    ];
    
    public $timestamps = false;
    
    public function save(): bool {
      $this->timestamps = true;
      parent::save();
    }
    
    public function someOtherMethod(): string { return 'true'; }
}
php;

    public function testNoManipulation(): void
    {
        $outcome = (string)$this->getFresh();

        $this->assertEquals($this->class, $outcome);
    }

    /**
     * @dataProvider getPropertyCompatibleStatements
     */
    public function testHighlightPropertiesStatements(string $method, string $string): void
    {
        $outcome = (string)$this->getFresh()->$method('fillable');
        $this->assertStringContainsString('public $fillable = [//[tl! ' . $string . ':start]', $outcome);
        $this->assertStringContainsString('];//[tl! ' . $string . ':end]', $outcome);

        $outcome = (string)$this->getFresh()->$method([
            'fillable',
            'timestamps',
        ]);

        $this->assertStringContainsString('public $fillable = [//[tl! ' . $string . ':start]', $outcome);
        $this->assertStringContainsString('];//[tl! ' . $string . ':end]', $outcome);

        $this->assertStringContainsString('public $timestamps = false;//[tl! ' . $string . ']', $outcome);

        // ALL ==================
        $outcome = (string)$this->getFresh()->$method();

        $this->assertStringContainsString('public $fillable = [//[tl! ' . $string . ':start]', $outcome);
        $this->assertStringContainsString('];//[tl! ' . $string . ':end]', $outcome);

        $this->assertStringContainsString('public $timestamps = false;//[tl! ' . $string . ']', $outcome);
    }

    /**
     * @dataProvider getMethodCompatibleStatements
     */
    public function testHighlightMethodStatements(string $method, string $string): void
    {
        $outcome = (string)$this->getFresh()->$method('save');
        $this->assertStringContainsString('public function save(): bool {//[tl! ' . $string . ':start]', $outcome);
        $this->assertStringContainsString('}//[tl! ' . $string . ':end]', $outcome);

        $outcome = (string)$this->getFresh()->$method([
            'save',
            'someOtherMethod',
        ]);

        // Multiline save.
        $this->assertStringContainsString('public function save(): bool {//[tl! ' . $string . ':start]', $outcome);
        $this->assertStringContainsString('}//[tl! ' . $string . ':end]', $outcome);

        // Singleline
        $this->assertStringContainsString('public function someOtherMethod(): string { return \'true\'; }//[tl! ' . $string . ']',
            $outcome);

        // ALL ==================
        $outcome = (string)$this->getFresh()->$method();

        $this->assertStringContainsString('public function save(): bool {//[tl! ' . $string . ':start]', $outcome);
        $this->assertStringContainsString('}//[tl! ' . $string . ':end]', $outcome);

        // Singleline
        $this->assertStringContainsString('public function someOtherMethod(): string { return \'true\'; }//[tl! ' . $string . ']',
            $outcome);
    }

    /**
     * @dataProvider getTraitCompatibleStatements
     */
    public function testHighlightTraitStatements(string $method, string $string): void
    {
        $outcome = (string)$this->getFresh()->$method('App\Models\CachableTrait');
        $this->assertStringContainsString('use CachableTrait;//[tl! ' . $string . ']', $outcome);

        $outcome = (string)$this->getFresh()->$method([
            'App\Models\CachableTrait',
            'Package\PackageCachableTrait',
        ]);
        $this->assertStringContainsString('use CachableTrait;//[tl! ' . $string . ']', $outcome);
        $this->assertStringContainsString('use PackageCachableTrait;//[tl! ' . $string . ']', $outcome);

        // ALL ==================
        $outcome = (string)$this->getFresh()->$method();
        $this->assertStringContainsString('use CachableTrait;//[tl! ' . $string . ']', $outcome);
        $this->assertStringContainsString('use PackageCachableTrait;//[tl! ' . $string . ']', $outcome);
    }

    public function testHighlightTraitSpecial(): void
    {
        $outcome = (string)$this->getFresh()->collapseAllTraits();
        $this->assertStringContainsString('use CachableTrait;//[tl! collapse:start]', $outcome);
        $this->assertStringContainsString('use PackageCachableTrait;//[tl! collapse:end]', $outcome);
    }

    /**
     * @dataProvider getUseCompatibleStatements
     */
    public function testHighlightUseStatements(string $method, string $string): void
    {
        $outcome = (string)$this->getFresh()->$method('App\Models\SomeOtherModel');
        $this->assertStringContainsString('use App\Models\SomeOtherModel;//[tl! ' . $string . ']', $outcome);

        $outcome = (string)$this->getFresh()->$method([
            'App\Models\SomeOtherModel',
            'Package\PackageCachableTrait',
        ]);
        $this->assertStringContainsString('use App\Models\SomeOtherModel;//[tl! ' . $string . ']', $outcome);
        $this->assertStringContainsString('use Package\PackageCachableTrait;//[tl! ' . $string . ']', $outcome);

        // ALL ==================
        $outcome = (string)$this->getFresh()->$method();
        $this->assertStringContainsString('use App\Models\SomeOtherModel;//[tl! ' . $string . ']', $outcome);
        $this->assertStringContainsString('use Package\PackageCachableTrait;//[tl! ' . $string . ']', $outcome);
    }

    public function testHighlightUseSpecial(): void
    {
        $outcome = (string)$this->getFresh()->collapseImports();
        $this->assertStringContainsString('use App\Models\SomeOtherModel;//[tl! collapse:start]', $outcome);
        $this->assertStringContainsString('use Package\PackageCachableTrait;//[tl! collapse:end]', $outcome);
    }

    public function testCombination(): void
    {
        $outcome = (string)$this->getFresh()->focusImports()->diffMethods()->highlightTraits()->collapseProperties();

        $expected = <<<'php'
<?php

namespace App\Models;

use App\Models\SomeOtherModel;//[tl! focus]
use Package\PackageCachableTrait;//[tl! focus]

class Blog extends SomeOtherModel {
    use CachableTrait;//[tl! highlight]
    use PackageCachableTrait;//[tl! highlight]

    public $fillable = [//[tl! collapse:start]
        'bar',
        'foo',
        'baz'
    ];//[tl! collapse:end]
    
    public $timestamps = false;//[tl! collapse]
    
    public function save(): bool {//[tl! add:start]
      $this->timestamps = true;
      parent::save();
    }//[tl! add:end]
    
    public function someOtherMethod(): string { return 'true'; }//[tl! add]
}
php;

        $this->assertEquals($expected, $outcome);
    }

    public function testDiffInMethod(): void
    {
        $class = <<<'php'
<?php

class test {
    public function demo(): void {
        $line1 = 1;
        $line2 = 2;
        $line3 = 3;
        $line4 = 4;
    }
}
php;

        $hl = Highlight::fromCode($class)->diffInMethod('demo', 2, 3, Highlight::TYPE_DIFF_ADD);

        $this->assertEquals(<<<'php'
<?php

class test {
    public function demo(): void {
        $line1 = 1;
        $line2 = 2;//[tl! add:start]
        $line3 = 3;//[tl! add:end]
        $line4 = 4;
    }
}
php,
            (string)$hl);
    }

    public function testFocusInMethod(): void
    {
        $class = <<<'php'
<?php

class test {
    public function demo(): void {
        $line1 = 1;
        $line2 = 2;
        $line3 = 3;
        $line4 = 4;
    }
}
php;

        $hl = Highlight::fromCode($class)->focusInMethod('demo', 1, 4);

        $this->assertEquals(<<<'php'
<?php

class test {
    public function demo(): void {
        $line1 = 1;//[tl! focus:start]
        $line2 = 2;
        $line3 = 3;
        $line4 = 4;//[tl! focus:end]
    }
}
php,
            (string)$hl);
    }

    private function getFresh(): Highlight
    {
        return Highlight::fromCode($this->class);
    }

    public function getUseCompatibleStatements(): array
    {
        return [
            'focus' => [
                'method' => 'focusImports',
                'string' => 'focus',
            ],
            'add' => [
                'method' => 'diffImports',
                'string' => 'add',
            ],
            'highlight' => [
                'method' => 'highlightImports',
                'string' => 'highlight',
            ],
        ];
    }

    public function getTraitCompatibleStatements(): array
    {
        return [
            'focus' => [
                'method' => 'focusTraits',
                'string' => 'focus',
            ],
            'add' => [
                'method' => 'diffTraits',
                'string' => 'add',
            ],
            'highlight' => [
                'method' => 'highlightTraits',
                'string' => 'highlight',
            ],
        ];
    }

    public function getMethodCompatibleStatements(): array
    {
        return [
            'focus' => [
                'method' => 'focusMethods',
                'string' => 'focus',
            ],
            'add' => [
                'method' => 'diffMethods',
                'string' => 'add',
            ],
            'highlight' => [
                'method' => 'highlightMethods',
                'string' => 'highlight',
            ],
            'collapse' => [
                'method' => 'collapseMethods',
                'string' => 'collapse',
            ],
        ];
    }

    public function getPropertyCompatibleStatements(): array
    {
        return [
            'focus' => [
                'method' => 'focusProperties',
                'string' => 'focus',
            ],
            'add' => [
                'method' => 'diffProperties',
                'string' => 'add',
            ],
            'highlight' => [
                'method' => 'highlightProperties',
                'string' => 'highlight',
            ],
        ];
    }
}

<?php

namespace A17\PhpTorch;

use Closure;
use Illuminate\Support\Str;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpFile;

/**
 * @todo: This currently only works for classes.
 */
class Highlight
{
    private PhpFile $file;
    private ClassType $class;

    private const TYPE_FOCUS = 'focus';
    private const TYPE_HIGHLIGHT = 'highlight';
    private const TYPE_COLLAPSE = 'collapse';
    private const TYPE_DIFF_ADD = 'add';
    private const TYPE_DIFF_REMOVE = 'remove';

    private array $methodActions = [];
    private array $propertyActions = [];
    private array $traitActions = [];
    private array $diffInMethod = [];
    private array $uses = [];

    private bool $collapseImports = false;
    private bool $keepCollapseWhenMultiple = false;

    public static function fromCode(string $code): self
    {
        return new self(code: $code);
    }

    public static function new(string $classFile): self
    {
        return new self(classFile: $classFile);
    }

    public function __construct(?string $classFile = null, ?string $code = null)
    {
        if ($classFile) {
            $code = file_get_contents($classFile);
        }

        $this->file = PhpFile::fromCode($code);

        if ($this->file->getClasses()) {
            $this->class = $this->file->getClasses()[array_key_first($this->file->getClasses())];
        }
    }

    public function collapseAll(): self
    {
        $this->collapseProperties();
        $this->collapseAllTraits();
        $this->collapseMethods();
        $this->collapseImports();

        return $this;
    }

    public function diffImports(array|string|null $imports = null, string $addRemove = self::TYPE_DIFF_ADD): self
    {
        $this->handleFile($imports, 'uses', $addRemove);

        return $this;
    }

    public function focusImports(array|string|null $imports = null): self
    {
        $this->handleFile($imports, 'uses', self::TYPE_FOCUS);

        return $this;
    }

    public function diffTraits(array|string|null $traits = null, string $addRemove = self::TYPE_DIFF_ADD): self
    {
        $this->handleClass($traits, 'getTraits', 'traitActions', $addRemove);

        return $this;
    }

    public function collapseAllTraits(): self
    {
        $traits = $this->class->getTraits();

        $traitCount = count($traits);
        if ($traitCount > 0) {
            $firstTrait = $traits[array_key_first($traits)]->getName();

            $this->traitActions[$firstTrait] = ['collapse:' . $traitCount];
        }

        return $this;
    }

    /**
     * Will collapse all unless there is a modification on a use statement.
     */
    public function collapseImports(): self
    {
        $this->collapseImports = true;

        return $this;
    }

    /**
     * Highlight::TYPE_DIFF_ADD or Highlight::TYPE_DIFF_REMOVE
     */
    public function diffProperties(array|string|null $properties = null, string $addRemove = self::TYPE_DIFF_ADD): self
    {
        $this->handleClass($properties, 'getProperties', 'propertyActions', $addRemove);

        return $this;
    }

    public function collapseProperties(array|string|null $properties = null): self
    {
        $this->handleClass($properties, 'getProperties', 'propertyActions', self::TYPE_COLLAPSE);

        return $this;
    }

    public function focusProperties(array|string|null $properties = null, bool $highlight = false): self
    {
        $this->handleClass(
            $properties,
            'getProperties',
            'propertyActions',
            $highlight ? self::TYPE_HIGHLIGHT : self::TYPE_FOCUS
        );

        return $this;
    }

    public function diffMethods(array|string|null $methods = null, string $addRemove = self::TYPE_DIFF_ADD): self
    {
        $this->handleClass($methods, 'getMethods', 'methodActions', $addRemove);

        return $this;
    }

    public function collapseMethods(array|string|null $methods = null): self
    {
        $this->handleClass($methods, 'getMethods', 'methodActions', self::TYPE_COLLAPSE);

        return $this;
    }

    public function focusMethods(array|string|null $methods = null, bool $highlight = false): self
    {
        $this->handleClass(
            $methods,
            'getMethods',
            'methodActions',
            $highlight ? self::TYPE_HIGHLIGHT : self::TYPE_FOCUS
        );

        return $this;
    }

    public function diffInMethod(string $method, int $start, int $end, string $addRemove = self::TYPE_DIFF_ADD): self
    {
        if ($start === 0) {
            $start = 1;
            $end++;
        }

        $this->diffInMethod[$method] = [
            'type' => $addRemove,
            'start' => $start,
            'end' => $end,
        ];

        $body = $this->class->getMethod($method)->getBody();

        $bodyArray = explode(PHP_EOL, $body);

        if ($end > count($bodyArray)) {
            $end = count($bodyArray);
        }

        if ($start - $end === 0) {
            $bodyArray[$start - 1] .= '// ' . $this->getSingleComment([$addRemove]);
        } else {
            $bodyArray[$start - 1] .= '//' . $this->buildStartComment([$addRemove]);
            $bodyArray[$end - 1] .= '//' . $this->buildEndComment([$addRemove]);
        }

        $this->class->getMethod($method)->setBody(implode(PHP_EOL, $bodyArray));

        return $this;
    }

    /**
     * By default when you collapse all and apply other actions. We will remove the collapse as the others are more
     * important. You can disable this behavior with this method.
     */
    public function keepCollapse(bool $keepCollapse = true): self
    {
        $this->keepCollapseWhenMultiple = $keepCollapse;

        return $this;
    }

    public function manipulateClass(\Closure $manipulation): self
    {
        $manipulation($this->class);

        return $this;
    }

    private function handleFile(array|string|null $list, string $target, string $action): void
    {
        foreach ($this->getList($list) as $item) {
            $this->{$target}[$item][] = $action;
        }
    }

    private function handleClass(array|string|null $list, string $getter, string $target, string $action): void
    {
        foreach (
            $this->getList($list, function () use ($getter) {
                return $this->getNames($this->class->{$getter}());
            }) as $item
        ) {
            $this->{$target}[$item][] = $action;
        }
    }

    private function getNames(array $nameAwares): array
    {
        $names = [];

        /** @var \Nette\PhpGenerator\Traits\NameAware $nameAware */
        foreach ($nameAwares as $nameAware) {
            $name = $nameAware->getName();
            $names[$name] = $name;
        }

        return $names;
    }

    private function getList(array|string|null $list = null, ?Closure $all = null): array
    {
        if (isset($list[0]) && is_array($list[0])) {
            $list = $list[0];
        } elseif ($list === null && $all) {
            return $all();
        } elseif (is_string($list)) {
            return [$list];
        }

        return $list;
    }

    private function wrapMethods(array $methods): void
    {
        foreach ($methods as $method => $actions) {
            $actions = $this->removeCollapseIfMultiple($actions);

            $this->class->getMethod($method)->setPrefixComment($this->buildStartComment($actions), true);
            $this->class->getMethod($method)->setSuffixComment($this->buildEndComment($actions), true);
        }
    }

    private function wrapProperties(array $methods): void
    {
        foreach ($methods as $method => $actions) {
            $actions = $this->removeCollapseIfMultiple($actions);

            $value = $this->class->getProperty($method)->getValue();

            if (Str::contains($value, "\n")) {
                $this->class->getProperty($method)->setPrefixComment($this->buildStartComment($actions), true);
                $this->class->getProperty($method)->setSuffixComment($this->buildEndComment($actions), true);
            } else {
                $this->class->getProperty($method)->setPrefixComment($this->getSingleComment($actions), true);
            }
        }
    }

    private function suffixTraits(array $traitsToProcess): void
    {
        $newTraits = [];

        foreach ($this->class->getTraits() as $trait) {
            if (isset($traitsToProcess[$trait->getName()])) {
                $trait->setSuffixComment($this->getSingleComment($traitsToProcess[$trait->getName()]), true);
            }
            $newTraits[] = $trait;
        }

        $this->class->setTraits($newTraits);
    }

    private function removeCollapseIfMultiple(array $actions): array
    {
        if ($this->keepCollapseWhenMultiple) {
            return $actions;
        }

        if ((count($actions) > 1) && ($key = array_search(self::TYPE_COLLAPSE, $actions, true)) !== false) {
            unset($actions[$key]);
        }

        return $actions;
    }

    private function getSingleComment(array $actions): string
    {
        $comment = '';
        foreach ($actions as $type) {
            $comment .= '[tl! ' . $type . ']';
        }

        return $comment;
    }

    private function buildStartComment(array $actions): string
    {
        $comment = '';
        foreach ($actions as $type) {
            $comment .= '[tl! ' . $type . ':start]';
        }

        return $comment;
    }

    private function buildEndComment($actions): string
    {
        $comment = '';
        foreach (array_reverse($actions) as $type) {
            $comment .= '[tl! ' . $type . ':end]';
        }

        return $comment;
    }

    public function process(): void
    {
        $this->wrapMethods($this->methodActions);
        $this->wrapProperties($this->propertyActions);
        $this->suffixTraits($this->traitActions);
    }

    public function copy(): self
    {
        return unserialize(serialize($this));
    }

    public function __toString(): string
    {
        $this->process();
        $file = (string)$this->file;

        foreach ($this->uses as $name => $use) {
            $comment = $this->getSingleComment($use);
            $file = str_replace($name . ';', $name . '; //' . $comment, $file);
        }

        if ($this->uses === [] && $this->collapseImports) {
            $beforeClass = Str::before($file, '{');
            $matches = [];
            preg_match_all('/use (.*)\n/', $beforeClass, $matches);

            $amount = count($matches[1]);

            $first = reset($matches[1]);

            $file = Str::replaceFirst($first, $first . '// [tl! collapse:' . $amount . ']', $file);
        }

        return $file;
    }
}

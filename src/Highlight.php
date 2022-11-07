<?php

namespace A17\PhpTorch;

use Closure;

/**
 * @todo: This currently only works for classes.
 */
class Highlight
{
    private DocParser $parsed;

    private const TYPE_FOCUS = 'focus';
    private const TYPE_HIGHLIGHT = 'highlight';
    private const TYPE_COLLAPSE = 'collapse';
    public const TYPE_DIFF_ADD = 'add';
    public const TYPE_DIFF_REMOVE = 'remove';

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

        $this->parsed = DocParser::forContent($code);
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
        $this->handleClass($imports, 'allUses', 'uses', $addRemove);

        return $this;
    }

    public function focusImports(array|string|null $imports = null): self
    {
        $this->handleClass($imports, 'allUses', 'uses', self::TYPE_FOCUS);

        return $this;
    }

    public function highlightImports(array|string|null $imports = null): self
    {
        $this->handleClass($imports, 'allUses', 'uses', self::TYPE_HIGHLIGHT);

        return $this;
    }

    public function highlightTraits(array|string|null $traits = null): self
    {
        $this->handleClass($traits, 'getTraits', 'traitActions', self::TYPE_HIGHLIGHT);

        return $this;
    }

    public function focusTraits(array|string|null $traits = null): self
    {
        $this->handleClass($traits, 'getTraits', 'traitActions', self::TYPE_FOCUS);

        return $this;
    }

    public function diffTraits(array|string|null $traits = null, string $addRemove = self::TYPE_DIFF_ADD): self
    {
        $this->handleClass($traits, 'getTraits', 'traitActions', $addRemove);

        return $this;
    }

    public function collapseAllTraits(): self
    {
        $traits = $this->parsed->getTraits();

        $traitCount = count($traits);
        if ($traitCount > 0) {
            $firstTrait = $traits[array_key_first($traits)]->getName();
            $lastTrait = $traits[array_key_last($traits)]->getName();

            $this->traitActions[$firstTrait] = ['collapse:start'];
            $this->traitActions[$lastTrait] = ['collapse:end'];
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

    public function focusProperties(array|string|null $properties = null): self
    {
        $this->handleClass(
            $properties,
            'getProperties',
            'propertyActions',
            self::TYPE_FOCUS
        );

        return $this;
    }

    public function highlightProperties(array|string|null $properties = null): self
    {
        $this->handleClass(
            $properties,
            'getProperties',
            'propertyActions',
            self::TYPE_HIGHLIGHT
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

    public function focusMethods(array|string|null $methods = null): self
    {
        $this->handleClass(
            $methods,
            'getMethods',
            'methodActions',
            self::TYPE_FOCUS
        );

        return $this;
    }

    public function highlightMethods(array|string|null $methods = null): self
    {
        $this->handleClass(
            $methods,
            'getMethods',
            'methodActions',
            self::TYPE_HIGHLIGHT
        );

        return $this;
    }

    public function focusInMethod(string $method, int $start, int $end): self
    {
        $this->diffInMethod[$method] = [
            'type' => self::TYPE_FOCUS,
            'start' => $start,
            'end' => $end,
        ];

        return $this;
    }

    public function diffInMethod(string $method, int $start, int $end, string $addRemove = self::TYPE_DIFF_ADD): self
    {
        $this->diffInMethod[$method] = [
            'type' => $addRemove,
            'start' => $start,
            'end' => $end,
        ];

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

    private function handleClass(array|string|null $list, string $getter, string $target, string $action): void
    {
        foreach (
            $this->getList($list, function () use ($getter) {
                return $this->getNames($getter);
            }) as $item
        ) {
            $this->{$target}[$item][] = $action;
        }
    }

    private function getNames(string $getter): array
    {
        $items = match ($getter) {
            'allUses' => $this->parsed->getUseStatements(),
            'getTraits' => $this->parsed->getTraits(),
            'getMethods' => $this->parsed->getMethods(),
            'getProperties' => $this->parsed->getProperties(),
        };

        $list = [];
        foreach ($items as $item) {
            $list[] = $item->getName();
        }

        return $list;
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

    private function handleMethodBodyDiff(string $code): string
    {
        foreach ($this->diffInMethod as $methodName => $options) {
            foreach ($this->parsed->getMethods() as $method) {
                if ($method->getName() === $methodName) {
                    if ($method->isSingleLine()) {
                        $code = $this->suffixLine(
                            $method->getStartLine(),
                            $this->getSingleComment($options['type']),
                            $code,
                            true
                        );
                    } else {
                        $start = $method->getStartLine() + $options['start'];
                        $end = $method->getStartLine() + $options['end'];

                        $code = $this->suffixLine(
                            $start,
                            $this->buildStartComment([$options['type']]),
                            $code,
                            true
                        );
                        $code = $this->suffixLine(
                            $end,
                            $this->buildEndComment([$options['type']]),
                            $code,
                            true
                        );
                    }
                }
            }
        }

        return $code;
    }

    private function wrapMethods(string $code): string
    {
        foreach ($this->methodActions as $methodName => $actions) {
            $actions = $this->removeCollapseIfMultiple($actions);

            foreach ($this->parsed->getMethods() as $method) {
                if ($method->getName() === $methodName) {
                    if ($method->isSingleLine()) {
                        $code = $this->suffixLine(
                            $method->getStartLine(),
                            $this->getSingleComment($actions),
                            $code,
                            true
                        );
                    } else {
                        $code = $this->suffixLine(
                            $method->getStartLine(),
                            $this->buildStartComment($actions),
                            $code,
                            true
                        );
                        $code = $this->suffixLine(
                            $method->getEndLine(),
                            $this->buildEndComment($actions),
                            $code,
                            true
                        );
                    }
                }
            }
        }

        return $code;
    }

    private function wrapProperties(string $code): string
    {
        foreach ($this->propertyActions as $propertyName => $actions) {
            $actions = $this->removeCollapseIfMultiple($actions);

            foreach ($this->parsed->getProperties() as $method) {
                if ($method->getName() === $propertyName) {
                    if ($method->isSingleLine()) {
                        $code = $this->suffixLine(
                            $method->getStartLine(),
                            $this->getSingleComment($actions),
                            $code,
                            true
                        );
                    } else {
                        $code = $this->suffixLine(
                            $method->getStartLine(),
                            $this->buildStartComment($actions),
                            $code,
                            true
                        );
                        $code = $this->suffixLine(
                            $method->getEndLine(),
                            $this->buildEndComment($actions),
                            $code,
                            true
                        );
                    }
                }
            }
        }

        return $code;
    }

    private function suffixTraits(string $code): string
    {
        foreach ($this->traitActions as $name => $actions) {
            foreach ($this->parsed->getTraits() as $trait) {
                if ($trait->matches($name)) {
                    $code = $this->suffixLine(
                        $trait->getStartLine(),
                        $this->getSingleComment($actions),
                        $code,
                        true
                    );
                }
            }
        }

        return $code;
    }

    public function suffixUses(string $code): string
    {
        if ($this->uses !== []) {
            foreach ($this->uses as $name => $use) {
                foreach ($this->parsed->getUseStatements() as $useStatement) {
                    if ($useStatement->matches($name)) {
                        $code = $this->suffixLine(
                            $useStatement->getStartLine(),
                            $this->getSingleComment($use),
                            $code,
                            true
                        );
                    }
                }
            }
        } elseif ($this->collapseImports) {
            if ($this->parsed->getUseStatements() !== []) {
                $first = $this->parsed->getUseStatements()[0];
                $last = $this->parsed->getUseStatements()[count($this->parsed->getUseStatements()) - 1];

                $code = $this->suffixLine($first->getStartLine(), '[tl! collapse:start]', $code, true);
                $code = $this->suffixLine($last->getEndLine(), '[tl! collapse:end]', $code, true);
            }
        }

        return $code;
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

    public function process(string $code): string
    {
        $code = $this->suffixUses($code);
        $code = $this->suffixTraits($code);
        $code = $this->wrapMethods($code);
        $code = $this->wrapProperties($code);
        $code = $this->handleMethodBodyDiff($code);
        return $code;
    }

    public function copy(): self
    {
        return unserialize(serialize($this));
    }

    public function __toString(): string
    {
        $code = $this->parsed->getCode();

        return $this->process($code);
    }

    private function suffixLine(int $line, string $content, string $code, bool $withCommentString = false): string
    {
        $exploded = explode(PHP_EOL, $code);
        $exploded[$line - 1] .= ($withCommentString ? '//' : '') . $content;

        return implode(PHP_EOL, $exploded);
    }
}

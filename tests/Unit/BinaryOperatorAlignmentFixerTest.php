<?php

namespace ChiefTools\PhpCsFixer\Tests\Unit;

use SplFileInfo;
use PHPUnit\Framework\TestCase;
use PhpCsFixer\Tokenizer\Tokens;
use ChiefTools\PhpCsFixer\Config;
use PhpCsFixer\Fixer\Operator\BinaryOperatorSpacesFixer;
use ChiefTools\PhpCsFixer\Fixer\BinaryOperatorAlignmentFixer;

class BinaryOperatorAlignmentFixerTest extends TestCase
{
    public function testItAlignsAssignmentsAcrossContinuationLinesUntilABlankLine(): void
    {
        $source = <<<'PHP'
<?php

function values($value): void
{
    $a = [];
    $bb = $value
        ->call();
    $ccc = 1;
}

PHP;

        $expected = <<<'PHP'
<?php

function values($value): void
{
    $a   = [];
    $bb  = $value
        ->call();
    $ccc = 1;
}

PHP;

        $this->assertSame($expected, $this->fix($source));
    }

    public function testItDoesNotAlignAssignmentsAcrossBlankLines(): void
    {
        $source = <<<'PHP'
<?php

function values($value): void
{
    $a = [];
    $bb = $value
        ->call();

    $ccc = 1;
}

PHP;

        $expected = <<<'PHP'
<?php

function values($value): void
{
    $a  = [];
    $bb = $value
        ->call();

    $ccc = 1;
}

PHP;

        $this->assertSame($expected, $this->fix($source));
    }

    public function testItAlignsPropertyAssignmentsAcrossContinuationLines(): void
    {
        $source = <<<'PHP'
<?php

class Values
{
    protected $short = [
        'a',
    ];
    protected $longer = [
        'b',
    ];
}

PHP;

        $expected = <<<'PHP'
<?php

class Values
{
    protected $short  = [
        'a',
    ];
    protected $longer = [
        'b',
    ];
}

PHP;

        $this->assertSame($expected, $this->fix($source));
    }

    public function testItDoesNotAlignPromotedParameterDefaults(): void
    {
        $source = <<<'PHP'
<?php

class Values
{
    public function __construct(
        private ?string $a,
        private int $bb = 1,
        private int $ccc = 2,
    ) {}
}

PHP;

        $this->assertSame($source, $this->fix($source));
    }

    public function testItAlignsClassConstantAssignments(): void
    {
        $source = <<<'PHP'
<?php

class Values
{
    public const SHORT = 'short';
    public const LONGER = 'longer';
}

PHP;

        $expected = <<<'PHP'
<?php

class Values
{
    public const SHORT  = 'short';
    public const LONGER = 'longer';
}

PHP;

        $this->assertSame($expected, $this->fix($source));
    }

    public function testItAlignsEnumCaseAssignments(): void
    {
        $source = <<<'PHP'
<?php

enum Values: string
{
    case A = 'a';
    case BB = 'bb';
}

PHP;

        $expected = <<<'PHP'
<?php

enum Values: string
{
    case A  = 'a';
    case BB = 'bb';
}

PHP;

        $this->assertSame($expected, $this->fix($source));
    }

    public function testItAlignsObjectPropertyAssignments(): void
    {
        $source = <<<'PHP'
<?php

class Values
{
    public function update(): void
    {
        $this->longName = 1;
        $this->short = 2;
    }
}

PHP;

        $expected = <<<'PHP'
<?php

class Values
{
    public function update(): void
    {
        $this->longName = 1;
        $this->short    = 2;
    }
}

PHP;

        $this->assertSame($expected, $this->fix($source));
    }

    public function testItAlignsDynamicObjectPropertyAssignments(): void
    {
        $source = <<<'PHP'
<?php

function update($item, string $name): void
{
    $item->{$name} = 1;
    $item->flag = 2;
}

PHP;

        $expected = <<<'PHP'
<?php

function update($item, string $name): void
{
    $item->{$name} = 1;
    $item->flag    = 2;
}

PHP;

        $this->assertSame($expected, $this->fix($source));
    }

    public function testItAlignsNestedObjectPropertyAssignments(): void
    {
        $source = <<<'PHP'
<?php

function update($item): void
{
    $item->child->a = 1;
    $item->child->long = 2;
}

PHP;

        $expected = <<<'PHP'
<?php

function update($item): void
{
    $item->child->a    = 1;
    $item->child->long = 2;
}

PHP;

        $this->assertSame($expected, $this->fix($source));
    }

    public function testItDoesNotPadSingleObjectPropertyAssignments(): void
    {
        $source = <<<'PHP'
<?php

class Values
{
    public function update(): void
    {
        $this->short = 1;
    }
}

PHP;

        $this->assertSame($source, $this->fix($source));
    }

    public function testItAlignsArrayOffsetAssignments(): void
    {
        $source = <<<'PHP'
<?php

function values(array $items): void
{
    $items['long'] = 1;
    $items['x'] = 2;
}

PHP;

        $expected = <<<'PHP'
<?php

function values(array $items): void
{
    $items['long'] = 1;
    $items['x']    = 2;
}

PHP;

        $this->assertSame($expected, $this->fix($source));
    }

    public function testItDoesNotAlignArrayOffsetAssignmentsAcrossDifferentControlStructureBlocks(): void
    {
        $source = <<<'PHP'
<?php

function values(array $items, string $key, bool $flag): void
{
    if ($flag) {
        $items[$key][] = 1;
    } else {
        $items[$key] = [2];
    }
}

PHP;

        $this->assertSame($source, $this->fix($source));
    }

    public function testItDoesNotAlignAssignmentsAcrossDifferentControlStructureBlocks(): void
    {
        $source = <<<'PHP'
<?php

function values(bool $flag): void
{
    if ($flag) {
        $short = 1;
    } else {
        $longer = 2;
    }
}

PHP;

        $this->assertSame($source, $this->fix($source));
    }

    public function testItMovesSingleLineDestructuringAssignmentValuesOntoTheAssignmentLine(): void
    {
        $source = <<<'PHP'
<?php

function values(): void
{
    [
        $a,
    ] =
        value();
}

PHP;

        $expected = <<<'PHP'
<?php

function values(): void
{
    [
        $a,
    ] = value();
}

PHP;

        $this->assertSame($expected, $this->fix($source));
    }

    public function testItUsesMinimalArrayPairAlignment(): void
    {
        $source = <<<'PHP'
<?php

function values(): array
{
    return [
        'foo'       => 'bar',
        'long_key'  => 'long_value',
    ];
}

PHP;

        $expected = <<<'PHP'
<?php

function values(): array
{
    return [
        'foo'      => 'bar',
        'long_key' => 'long_value',
    ];
}

PHP;

        $this->assertSame($expected, $this->fix($source));
    }

    public function testItDoesNotAlignArrayPairsAcrossBlankLines(): void
    {
        $source = <<<'PHP'
<?php

function values(): array
{
    return [
        'foo'      => 'bar',

        'long_key' => 'long_value',
    ];
}

PHP;

        $expected = <<<'PHP'
<?php

function values(): array
{
    return [
        'foo' => 'bar',

        'long_key' => 'long_value',
    ];
}

PHP;

        $this->assertSame($expected, $this->fix($source));
    }

    public function testItAlignsFnArrayKeysWithoutTouchingArrowFunctionArrows(): void
    {
        $source = <<<'PHP'
<?php

function values(): array
{
    return [
        'fn' => 'name',
        'long_key' => 'value',
        'callback' => fn ($value) => $value,
    ];
}

PHP;

        $expected = <<<'PHP'
<?php

function values(): array
{
    return [
        'fn'       => 'name',
        'long_key' => 'value',
        'callback' => fn ($value) => $value,
    ];
}

PHP;

        $this->assertSame($expected, $this->fix($source));
    }

    private function fix(string $source): string
    {
        $tokens              = Tokens::fromCode($source);
        $binaryOperatorFixer = new BinaryOperatorSpacesFixer;

        $binaryOperatorConfig = Config::rules()['binary_operator_spaces'];

        $this->assertIsArray($binaryOperatorConfig);
        $binaryOperatorFixer->configure($binaryOperatorConfig);
        $binaryOperatorFixer->fix(new SplFileInfo(__FILE__), $tokens);
        (new BinaryOperatorAlignmentFixer)->fix(new SplFileInfo(__FILE__), $tokens);

        return $tokens->generateCode();
    }
}

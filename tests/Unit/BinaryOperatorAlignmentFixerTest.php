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

function values($date): void
{
    $data      = [];
    $otherDate = $date
        ->addDays(5);
    $commonDate = now();
}

PHP;

        $expected = <<<'PHP'
<?php

function values($date): void
{
    $data       = [];
    $otherDate  = $date
        ->addDays(5);
    $commonDate = now();
}

PHP;

        $this->assertSame($expected, $this->fix($source));
    }

    public function testItDoesNotAlignAssignmentsAcrossBlankLines(): void
    {
        $source = <<<'PHP'
<?php

function values($date): void
{
    $data      = [];
    $otherDate = $date
        ->addDays(5);

    $commonDate = now();
}

PHP;

        $expected = <<<'PHP'
<?php

function values($date): void
{
    $data      = [];
    $otherDate = $date
        ->addDays(5);

    $commonDate = now();
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

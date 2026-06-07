<?php

namespace ChiefTools\PhpCsFixer\Tests\Unit;

use SplFileInfo;
use PHPUnit\Framework\TestCase;
use PhpCsFixer\Tokenizer\Tokens;
use PhpCsFixer\Fixer\Whitespace\MethodChainingIndentationFixer;
use ChiefTools\PhpCsFixer\Fixer\NestedMethodChainingIndentationFixer;

class NestedMethodChainingIndentationFixerTest extends TestCase
{
    public function testItAlignsNestedArgumentChainsWithTheFirstOperatorOnTheLine(): void
    {
        $source = <<<'PHP'
<?php

function build($item)
{
    return wrap(
        $item->one()
            ->two(),
    );
}

PHP;

        $expected = <<<'PHP'
<?php

function build($item)
{
    return wrap(
        $item->one()
             ->two(),
    );
}

PHP;

        $this->assertSame($expected, $this->fix($source));
    }

    public function testItKeepsStatementLevelChainsIndentedByTheNativeFixer(): void
    {
        $source = <<<'PHP'
<?php

function find($item)
{
    return $item->one()
                ->two()
                ->three();
}

PHP;

        $expected = <<<'PHP'
<?php

function find($item)
{
    return $item->one()
        ->two()
        ->three();
}

PHP;

        $this->assertSame($expected, $this->fix($source));
    }

    public function testItKeepsNestedChainsAlignedAfterMultilineArguments(): void
    {
        $source = <<<'PHP'
<?php

function build($item)
{
    wrap(
        $item->one()
            ->two(
                1,
            )
            ->three(),
    );
}

PHP;

        $expected = <<<'PHP'
<?php

function build($item)
{
    wrap(
        $item->one()
             ->two(
                 1,
             )
             ->three(),
    );
}

PHP;

        $this->assertSame($expected, $this->fix($source));
    }

    public function testItKeepsStatementLevelChainsWithMultilineArgumentsIndentedByTheNativeFixer(): void
    {
        $source = <<<'PHP'
<?php

function build($item)
{
    $item->one()
         ->two(
             1,
         )
         ->three();
}

PHP;

        $expected = <<<'PHP'
<?php

function build($item)
{
    $item->one()
        ->two(
            1,
        )
        ->three();
}

PHP;

        $this->assertSame($expected, $this->fix($source));
    }

    public function testItKeepsAssignmentChainsInsideNestedArgumentsIndentedByTheNativeFixer(): void
    {
        $source = <<<'PHP'
<?php

function build($item)
{
    wrap(function () use ($item) {
        $result = $item->one
            ->two()
            ->three();
    });
}

PHP;

        $this->assertSame($source, $this->fix($source));
    }

    public function testItIgnoresObjectAccessInsidePreviousLineArguments(): void
    {
        $source = <<<'PHP'
<?php

function build($item)
{
    wrap([$item->value])
        ->done();
}

PHP;

        $this->assertSame($source, $this->fix($source));
    }

    private function fix(string $source): string
    {
        $tokens = Tokens::fromCode($source);

        (new MethodChainingIndentationFixer)->fix(new SplFileInfo(__FILE__), $tokens);
        (new NestedMethodChainingIndentationFixer)->fix(new SplFileInfo(__FILE__), $tokens);

        return $tokens->generateCode();
    }
}

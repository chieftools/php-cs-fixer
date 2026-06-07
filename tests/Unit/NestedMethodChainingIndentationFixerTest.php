<?php

namespace ChiefTools\PhpCsFixer\Tests\Unit;

use SplFileInfo;
use PHPUnit\Framework\TestCase;
use PhpCsFixer\Tokenizer\Tokens;
use PhpCsFixer\Fixer\Whitespace\MethodChainingIndentationFixer;
use ChiefTools\PhpCsFixer\Fixer\NestedMethodChainingIndentationFixer;

class NestedMethodChainingIndentationFixerTest extends TestCase
{
    public function testItAlignsNestedArgumentChainsWithTheFirstOperatorOnTheExpressionLine(): void
    {
        $source = <<<'PHP'
<?php

function build($service, $handler)
{
    return wrap(
        $service->items()
            ->filter(function ($item) {
                $item->query()
                    ->where('active', true);
            }),
        $handler,
    );
}

PHP;

        $expected = <<<'PHP'
<?php

function build($service, $handler)
{
    return wrap(
        $service->items()
                ->filter(function ($item) {
                    $item->query()
                         ->where('active', true);
                }),
        $handler,
    );
}

PHP;

        $this->assertSame($expected, $this->fix($source));
    }

    public function testItKeepsStatementLevelChainsIndentedByTheNativeFixer(): void
    {
        $source = <<<'PHP'
<?php

function find($value)
{
    return Query::make()
               ->where('name', $value)
               ->first();
}

PHP;

        $expected = <<<'PHP'
<?php

function find($value)
{
    return Query::make()
        ->where('name', $value)
        ->first();
}

PHP;

        $this->assertSame($expected, $this->fix($source));
    }

    public function testItIgnoresObjectAccessInsidePreviousLineArguments(): void
    {
        $source = <<<'PHP'
<?php

function checkResponse($context)
{
    request(resolve([$context->identifier]))
        ->assertValid();
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

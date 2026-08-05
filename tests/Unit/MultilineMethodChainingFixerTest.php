<?php

namespace ChiefTools\PhpCsFixer\Tests\Unit;

use SplFileInfo;
use PHPUnit\Framework\TestCase;
use PhpCsFixer\Tokenizer\Tokens;
use ChiefTools\PhpCsFixer\Fixer\MultilineMethodChainingFixer;
use PhpCsFixer\Fixer\Whitespace\MethodChainingIndentationFixer;

class MultilineMethodChainingFixerTest extends TestCase
{
    public function testItMovesSubsequentChainSegmentsToNewLines(): void
    {
        $source = <<<'PHP'
<?php

$items = $collection
    ->map(
        static fn ($item) => $item->value,
    )
    ->filter(
        static fn ($item) => $item->active,
    )->values();

PHP;

        $expected = <<<'PHP'
<?php

$items = $collection
    ->map(
        static fn ($item) => $item->value,
    )
    ->filter(
        static fn ($item) => $item->active,
    )
    ->values();

PHP;

        $this->assertSame($expected, $this->fix($source));
    }

    public function testItMovesEveryRemainingSegmentToANewLine(): void
    {
        $source = <<<'PHP'
<?php

$items = $service->items()
    ->filter()->values()?->all();

PHP;

        $expected = <<<'PHP'
<?php

$items = $service->items()
    ->filter()
    ->values()
    ?->all();

PHP;

        $this->assertSame($expected, $this->fix($source));
    }

    public function testItLeavesSingleLineChainsAlone(): void
    {
        $source = <<<'PHP'
<?php

$items = $service->items()->filter()->values();

PHP;

        $this->assertSame($source, $this->fix($source));
    }

    public function testNestedArgumentChainsDoNotMakeTheOuterChainMultiline(): void
    {
        $source = <<<'PHP'
<?php

$items = $service->items($other
    ->filter())->values();

PHP;

        $this->assertSame($source, $this->fix($source));
    }

    private function fix(string $source): string
    {
        $tokens = Tokens::fromCode($source);
        $file   = new SplFileInfo(__FILE__);

        (new MultilineMethodChainingFixer)->fix($file, $tokens);
        (new MethodChainingIndentationFixer)->fix($file, $tokens);

        return $tokens->generateCode();
    }
}

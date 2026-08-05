<?php

namespace ChiefTools\PhpCsFixer\Fixer;

use SplFileInfo;
use PhpCsFixer\AbstractFixer;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\Fixer\WhitespacesAwareFixerInterface;
use PhpCsFixer\FixerDefinition\FixerDefinitionInterface;

class MultilineMethodChainingFixer extends AbstractFixer implements WhitespacesAwareFixerInterface
{
    public function getName(): string
    {
        return 'ChiefTools/multiline_method_chaining';
    }

    public function getDefinition(): FixerDefinitionInterface
    {
        return new FixerDefinition(
            'Every object operator after the first multiline chain segment should start on a new line.',
            [
                new CodeSample(
                    <<<'PHP'
<?php

$items = $service->items()
    ->filter()->values();

PHP,
                ),
            ],
        );
    }

    /** Must run before MethodChainingIndentationFixer. */
    public function getPriority(): int
    {
        return 1;
    }

    public function isCandidate(Tokens $tokens): bool
    {
        return $tokens->isAnyTokenKindsFound(Token::getObjectOperatorKinds());
    }

    protected function applyFix(SplFileInfo $file, Tokens $tokens): void
    {
        $lineEnding = $this->whitespacesConfig->getLineEnding();
        $isTestFile = str_ends_with($file->getFilename(), 'Test.php');

        for ($index = 1, $count = count($tokens); $index < $count; $index++) {
            if (!$tokens[$index]->isObjectOperator() || $this->startsOnNewLine($tokens, $index)) {
                continue;
            }

            $previousOperatorIndex = $this->previousChainObjectOperator($tokens, $index);

            if (
                $previousOperatorIndex === null
                || !$this->startsOnNewLine($tokens, $previousOperatorIndex)
                || $isTestFile && $this->isPestExpectationSegment($tokens, $previousOperatorIndex)
            ) {
                continue;
            }

            $newline = new Token([T_WHITESPACE, $lineEnding]);

            if ($tokens[$index - 1]->isWhitespace()) {
                $tokens[$index - 1] = $newline;

                continue;
            }

            $tokens->insertAt($index, $newline);
            $index++;
            $count++;
        }
    }

    private function isPestExpectationSegment(Tokens $tokens, int $operatorIndex): bool
    {
        $methodNameIndex = $tokens->getNextMeaningfulToken($operatorIndex);

        if ($methodNameIndex === null || !$tokens[$methodNameIndex]->isGivenKind(T_STRING)) {
            return false;
        }

        if (strtolower($tokens[$methodNameIndex]->getContent()) === 'and') {
            return true;
        }

        $nextMeaningfulIndex = $tokens->getNextMeaningfulToken($methodNameIndex);

        return $nextMeaningfulIndex !== null && !$tokens[$nextMeaningfulIndex]->equals('(');
    }

    private function previousChainObjectOperator(Tokens $tokens, int $index): ?int
    {
        $segmentEndIndex = $tokens->getPrevMeaningfulToken($index);

        while ($segmentEndIndex !== null && $this->closesBlockOfType($tokens[$segmentEndIndex], Tokens::BLOCK_TYPE_INDEX_SQUARE_BRACE)) {
            $openingBracketIndex = $tokens->findBlockStart(Tokens::BLOCK_TYPE_INDEX_SQUARE_BRACE, $segmentEndIndex);
            $segmentEndIndex     = $tokens->getPrevMeaningfulToken($openingBracketIndex);
        }

        if ($segmentEndIndex === null) {
            return null;
        }

        if ($tokens[$segmentEndIndex]->equals(')')) {
            $openingParenthesisIndex = $tokens->findBlockStart(Tokens::BLOCK_TYPE_PARENTHESIS_BRACE, $segmentEndIndex);
            $memberIndex             = $tokens->getPrevMeaningfulToken($openingParenthesisIndex);

            if ($memberIndex === null) {
                return null;
            }

            $segmentEndIndex = $memberIndex;
        } elseif ($this->closesBlockOfType($tokens[$segmentEndIndex], Tokens::BLOCK_TYPE_DYNAMIC_PROP_BRACE)) {
            $openingBraceIndex = $tokens->findBlockStart(Tokens::BLOCK_TYPE_DYNAMIC_PROP_BRACE, $segmentEndIndex);
            $segmentEndIndex   = $openingBraceIndex;
        }

        $operatorIndex = $tokens->getPrevMeaningfulToken($segmentEndIndex);

        return $operatorIndex !== null && $tokens[$operatorIndex]->isObjectOperator()
            ? $operatorIndex
            : null;
    }

    private function closesBlockOfType(Token $token, int $type): bool
    {
        $block = Tokens::detectBlockType($token);

        return $block !== null && !$block['isStart'] && $block['type'] === $type;
    }

    private function startsOnNewLine(Tokens $tokens, int $index): bool
    {
        $previousMeaningfulIndex = $tokens->getPrevMeaningfulToken($index);

        if ($previousMeaningfulIndex === null) {
            return false;
        }

        for ($i = $previousMeaningfulIndex + 1; $i < $index; $i++) {
            if (preg_match('/\R/', $tokens[$i]->getContent())) {
                return true;
            }
        }

        return false;
    }
}

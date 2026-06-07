<?php

namespace ChiefTools\PhpCsFixer\Fixer;

use SplFileInfo;
use PhpCsFixer\AbstractFixer;
use PhpCsFixer\Tokenizer\Tokens;
use PhpCsFixer\Tokenizer\TokensAnalyzer;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\FixerDefinition\FixerDefinitionInterface;

class BinaryOperatorAlignmentFixer extends AbstractFixer
{
    public function getName(): string
    {
        return 'ChiefTools/binary_operator_alignment';
    }

    public function getDefinition(): FixerDefinitionInterface
    {
        return new FixerDefinition(
            'Consecutive assignment and array-pair operators should be minimally aligned until a blank line separates the group.',
            [
                new CodeSample(
                    <<<'PHP'
<?php

$data = [];
$otherDate = $date
    ->addDays(5);
$commonDate = now();

PHP,
                ),
            ],
        );
    }

    public function getPriority(): int
    {
        return -33;
    }

    public function isCandidate(Tokens $tokens): bool
    {
        return true;
    }

    protected function applyFix(SplFileInfo $file, Tokens $tokens): void
    {
        $code                       = $tokens->generateCode();
        $lines                      = $this->lines($code);
        $records                    = $this->operatorRecords($tokens);
        $nonTargetAssignmentRecords = $this->nonTargetAssignmentRecords($tokens);

        $lines = $this->alignOperator($lines, $records, '=');
        $lines = $this->alignOperator($lines, $records, '=>');
        $lines = $this->normalizeAssignmentOperatorSpacing($lines, $nonTargetAssignmentRecords);

        $fixedCode = implode('', array_map(
            static fn (array $line): string => $line['content'] . $line['ending'],
            $lines,
        ));

        if ($fixedCode !== $code) {
            $tokens->setCode($fixedCode);
        }
    }

    /**
     * @return list<array{content: string, ending: string}>
     */
    private function lines(string $code): array
    {
        $parts = preg_split('/(\R)/', $code, -1, PREG_SPLIT_DELIM_CAPTURE);

        if ($parts === false) {
            return [['content' => $code, 'ending' => '']];
        }

        $lines = [];

        for ($i = 0, $count = count($parts); $i < $count; $i += 2) {
            $lines[] = [
                'content' => $parts[$i],
                'ending'  => $parts[$i + 1] ?? '',
            ];
        }

        return $lines;
    }

    /**
     * @return array<int, array<string, array{column: int, group: string}>>
     */
    private function operatorRecords(Tokens $tokens): array
    {
        $analyzer = new TokensAnalyzer($tokens);
        $records  = [];
        $line     = 0;
        $column   = 0;

        foreach ($tokens as $index => $token) {
            $content = $token->getContent();

            if ($this->isTargetOperator($tokens, $analyzer, $index)) {
                $operator = $token->isGivenKind(T_DOUBLE_ARROW) ? '=>' : '=';

                $records[$line][$operator] ??= [
                    'column' => $column,
                    'group'  => $this->operatorGroup($tokens, $index, $operator),
                ];
            }

            $parts = preg_split('/\R/', $content);

            if ($parts === false || count($parts) === 1) {
                $column += strlen($content);

                continue;
            }

            $line += count($parts) - 1;
            $column = strlen($parts[array_key_last($parts)]);
        }

        return $records;
    }

    /**
     * @return array<int, list<int>>
     */
    private function nonTargetAssignmentRecords(Tokens $tokens): array
    {
        $analyzer = new TokensAnalyzer($tokens);
        $records  = [];
        $line     = 0;
        $column   = 0;

        foreach ($tokens as $index => $token) {
            $content = $token->getContent();

            if (
                $tokens[$index]->equals('=')
                && $analyzer->isBinaryOperator($index)
                && !$this->isAssignmentTargetLinePrefix($this->linePrefixBeforeToken($tokens, $index))
            ) {
                $records[$line][] = $column;
            }

            $parts = preg_split('/\R/', $content);

            if ($parts === false || count($parts) === 1) {
                $column += strlen($content);

                continue;
            }

            $line += count($parts) - 1;
            $column = strlen($parts[array_key_last($parts)]);
        }

        return $records;
    }

    private function operatorGroup(Tokens $tokens, int $index, string $operator): string
    {
        if ($operator === '=') {
            $blockStartIndex = $this->nearestContainingBlockStart($tokens, $index);

            return '=' . ($blockStartIndex ?? $this->lineIndentAt($tokens->generateCode(), $this->lineNumberAt($tokens, $index)));
        }

        $blockStartIndex = $this->nearestContainingBlockStart($tokens, $index);

        return '=>' . ($blockStartIndex ?? $this->lineIndentAt($tokens->generateCode(), $this->lineNumberAt($tokens, $index)));
    }

    private function lineNumberAt(Tokens $tokens, int $index): int
    {
        $line = 0;

        for ($i = 0; $i < $index; $i++) {
            $line += preg_match_all('/\R/', $tokens[$i]->getContent()) ?: 0;
        }

        return $line;
    }

    private function nearestContainingBlockStart(Tokens $tokens, int $index): ?int
    {
        for ($i = $index - 1; $i >= 0; $i--) {
            $block = Tokens::detectBlockType($tokens[$i]);

            if ($block === null) {
                continue;
            }

            if (!$block['isStart']) {
                $i = $tokens->findBlockStart($block['type'], $i);

                continue;
            }

            return $i;
        }

        return null;
    }

    private function isTargetOperator(Tokens $tokens, TokensAnalyzer $analyzer, int $index): bool
    {
        if ($tokens[$index]->isGivenKind(T_DOUBLE_ARROW)) {
            return !$this->isArrowFunctionArrow($tokens, $index);
        }

        if (!$tokens[$index]->equals('=')) {
            return false;
        }

        if (!$analyzer->isBinaryOperator($index)) {
            return false;
        }

        return $this->isAssignmentTargetLinePrefix($this->linePrefixBeforeToken($tokens, $index));
    }

    private function isAssignmentTargetLinePrefix(string $linePrefix): bool
    {
        if (preg_match('/^\h*\$[A-Za-z_]\w*\h*$/', $linePrefix) === 1) {
            return true;
        }

        return preg_match(
            '/^\h*(?:(?:public|protected|private|static|readonly|var)\h+)+(?:[?\\\\\w|&<>,\[\]\h]+\h+)?\$[A-Za-z_]\w*\h*$/',
            $linePrefix,
        ) === 1 || preg_match(
            '/^\h*(?:(?:public|protected|private|final)\h+)*const\h+(?:[?\\\\\w|&<>,\[\]\h]+\h+)?[A-Za-z_]\w*\h*$/',
            $linePrefix,
        ) === 1;
    }

    private function isArrowFunctionArrow(Tokens $tokens, int $index): bool
    {
        for ($i = $index - 1; $i >= 0; $i--) {
            if (preg_match('/\R/', $tokens[$i]->getContent())) {
                return false;
            }

            if ($tokens[$i]->isGivenKind(T_FN)) {
                return true;
            }
        }

        return false;
    }

    private function linePrefixBeforeToken(Tokens $tokens, int $index): string
    {
        $prefix = '';

        for ($i = $index - 1; $i >= 0; $i--) {
            $content = $tokens[$i]->getContent();

            if (preg_match('/\R([^\r\n]*)$/', $content, $matches)) {
                return $matches[1] . $prefix;
            }

            $prefix = $content . $prefix;
        }

        return $prefix;
    }

    private function lineIndentAt(string $code, int $line): string
    {
        $lines = preg_split('/\R/', $code);

        if ($lines === false || !isset($lines[$line])) {
            return '';
        }

        preg_match('/^\h*/', $lines[$line], $matches);

        return $matches[0] ?? '';
    }

    /**
     * @param list<array{content: string, ending: string}>                 $lines
     * @param array<int, array<string, array{column: int, group: string}>> $records
     *
     * @return list<array{content: string, ending: string}>
     */
    private function alignOperator(array $lines, array $records, string $operator): array
    {
        $groups = [];

        foreach ($records as $line => $operators) {
            if (!isset($operators[$operator])) {
                continue;
            }

            $record  = $operators[$operator];
            $segment = $this->segmentAt($lines, $line);
            $groups[$segment][$record['group']][] = [
                'line'   => $line,
                'column' => $record['column'],
            ];
        }

        foreach ($groups as $segmentGroups) {
            foreach ($segmentGroups as $group) {
                $lines = $this->alignGroup($lines, $group);
            }
        }

        return $lines;
    }

    /**
     * @param list<array{content: string, ending: string}> $lines
     * @param array<int, list<int>>                        $records
     *
     * @return list<array{content: string, ending: string}>
     */
    private function normalizeAssignmentOperatorSpacing(array $lines, array $records): array
    {
        foreach ($records as $line => $columns) {
            rsort($columns);

            foreach ($columns as $column) {
                $content = $lines[$line]['content'];

                if (($content[$column] ?? null) !== '=') {
                    continue;
                }

                $before = substr($content, 0, $column);
                $after  = substr($content, $column + 1);

                $lines[$line]['content'] = rtrim($before) . ' = ' . ltrim($after);
            }
        }

        return $lines;
    }

    /**
     * @param list<array{content: string, ending: string}> $lines
     */
    private function segmentAt(array $lines, int $line): int
    {
        $segment = 0;

        for ($i = 0; $i < $line; $i++) {
            if (trim($lines[$i]['content']) === '') {
                $segment++;
            }
        }

        return $segment;
    }

    /**
     * @param list<array{content: string, ending: string}>  $lines
     * @param non-empty-list<array{line: int, column: int}> $group
     *
     * @return list<array{content: string, ending: string}>
     */
    private function alignGroup(array $lines, array $group): array
    {
        $targetColumn = 0;

        foreach ($group as $record) {
            $before = substr($lines[$record['line']]['content'], 0, $record['column']);

            $targetColumn = max($targetColumn, strlen(rtrim($before)) + 1);
        }

        foreach ($group as $record) {
            $line   = $lines[$record['line']]['content'];
            $before = substr($line, 0, $record['column']);
            $after  = substr($line, $record['column']);
            $left   = rtrim($before);

            $lines[$record['line']]['content'] = $left . str_repeat(' ', max(1, $targetColumn - strlen($left))) . $after;
        }

        return $lines;
    }
}

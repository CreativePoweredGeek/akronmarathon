<?php

declare (strict_types=1);
namespace BoldMinded\DataGrab\Dependency\Termwind\Helpers;

use BoldMinded\DataGrab\Dependency\Symfony\Component\Console\Formatter\OutputFormatter;
use BoldMinded\DataGrab\Dependency\Symfony\Component\Console\Helper\SymfonyQuestionHelper;
use BoldMinded\DataGrab\Dependency\Symfony\Component\Console\Output\OutputInterface;
use BoldMinded\DataGrab\Dependency\Symfony\Component\Console\Question\Question;
/**
 * @internal
 */
final class QuestionHelper extends SymfonyQuestionHelper
{
    /**
     * {@inheritdoc}
     */
    protected function writePrompt(OutputInterface $output, Question $question) : void
    {
        $text = OutputFormatter::escapeTrailingBackslash($question->getQuestion());
        $output->write($text);
    }
}

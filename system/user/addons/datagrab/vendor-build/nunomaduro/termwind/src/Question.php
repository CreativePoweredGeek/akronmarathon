<?php

declare (strict_types=1);
namespace BoldMinded\DataGrab\Dependency\Termwind;

use ReflectionClass;
use BoldMinded\DataGrab\Dependency\Symfony\Component\Console\Helper\SymfonyQuestionHelper;
use BoldMinded\DataGrab\Dependency\Symfony\Component\Console\Input\ArgvInput;
use BoldMinded\DataGrab\Dependency\Symfony\Component\Console\Input\StreamableInputInterface;
use BoldMinded\DataGrab\Dependency\Symfony\Component\Console\Question\Question as SymfonyQuestion;
use BoldMinded\DataGrab\Dependency\Symfony\Component\Console\Style\SymfonyStyle;
use BoldMinded\DataGrab\Dependency\Termwind\Helpers\QuestionHelper;
/**
 * @internal
 */
final class Question
{
    /**
     * The streamable input to receive the input from the user.
     */
    private static ?StreamableInputInterface $streamableInput;
    /**
     * An instance of Symfony's question helper.
     */
    private SymfonyQuestionHelper $helper;
    public function __construct(?SymfonyQuestionHelper $helper = null)
    {
        $this->helper = $helper ?? new QuestionHelper();
    }
    /**
     * Sets the streamable input implementation.
     */
    public static function setStreamableInput(?StreamableInputInterface $streamableInput) : void
    {
        self::$streamableInput = $streamableInput ?? new ArgvInput();
    }
    /**
     * Gets the streamable input implementation.
     */
    public static function getStreamableInput() : StreamableInputInterface
    {
        return self::$streamableInput ??= new ArgvInput();
    }
    /**
     * Renders a prompt to the user.
     *
     * @param  iterable<array-key, string>|null  $autocomplete
     */
    public function ask(string $question, ?iterable $autocomplete = null) : mixed
    {
        $html = (new HtmlRenderer())->parse($question)->toString();
        $question = new SymfonyQuestion($html);
        if ($autocomplete !== null) {
            $question->setAutocompleterValues($autocomplete);
        }
        $output = Termwind::getRenderer();
        if ($output instanceof SymfonyStyle) {
            $property = (new ReflectionClass(SymfonyStyle::class))->getProperty('questionHelper');
            $property->setAccessible(\true);
            $currentHelper = $property->isInitialized($output) ? $property->getValue($output) : new SymfonyQuestionHelper();
            $property->setValue($output, new QuestionHelper());
            try {
                return $output->askQuestion($question);
            } finally {
                $property->setValue($output, $currentHelper);
            }
        }
        return $this->helper->ask(self::getStreamableInput(), Termwind::getRenderer(), $question);
    }
}

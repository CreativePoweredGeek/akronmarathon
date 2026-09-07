<?php

namespace BoldMinded\DataGrab\Dependency\Laravel\Prompts\Concerns;

use InvalidArgumentException;
use BoldMinded\DataGrab\Dependency\Laravel\Prompts\Clear;
use BoldMinded\DataGrab\Dependency\Laravel\Prompts\ConfirmPrompt;
use BoldMinded\DataGrab\Dependency\Laravel\Prompts\Grid;
use BoldMinded\DataGrab\Dependency\Laravel\Prompts\MultiSearchPrompt;
use BoldMinded\DataGrab\Dependency\Laravel\Prompts\MultiSelectPrompt;
use BoldMinded\DataGrab\Dependency\Laravel\Prompts\Note;
use BoldMinded\DataGrab\Dependency\Laravel\Prompts\NumberPrompt;
use BoldMinded\DataGrab\Dependency\Laravel\Prompts\PasswordPrompt;
use BoldMinded\DataGrab\Dependency\Laravel\Prompts\PausePrompt;
use BoldMinded\DataGrab\Dependency\Laravel\Prompts\Progress;
use BoldMinded\DataGrab\Dependency\Laravel\Prompts\SearchPrompt;
use BoldMinded\DataGrab\Dependency\Laravel\Prompts\SelectPrompt;
use BoldMinded\DataGrab\Dependency\Laravel\Prompts\Spinner;
use BoldMinded\DataGrab\Dependency\Laravel\Prompts\SuggestPrompt;
use BoldMinded\DataGrab\Dependency\Laravel\Prompts\Table;
use BoldMinded\DataGrab\Dependency\Laravel\Prompts\TextareaPrompt;
use BoldMinded\DataGrab\Dependency\Laravel\Prompts\TextPrompt;
use BoldMinded\DataGrab\Dependency\Laravel\Prompts\Themes\Default\ClearRenderer;
use BoldMinded\DataGrab\Dependency\Laravel\Prompts\Themes\Default\ConfirmPromptRenderer;
use BoldMinded\DataGrab\Dependency\Laravel\Prompts\Themes\Default\GridRenderer;
use BoldMinded\DataGrab\Dependency\Laravel\Prompts\Themes\Default\MultiSearchPromptRenderer;
use BoldMinded\DataGrab\Dependency\Laravel\Prompts\Themes\Default\MultiSelectPromptRenderer;
use BoldMinded\DataGrab\Dependency\Laravel\Prompts\Themes\Default\NoteRenderer;
use BoldMinded\DataGrab\Dependency\Laravel\Prompts\Themes\Default\NumberPromptRenderer;
use BoldMinded\DataGrab\Dependency\Laravel\Prompts\Themes\Default\PasswordPromptRenderer;
use BoldMinded\DataGrab\Dependency\Laravel\Prompts\Themes\Default\PausePromptRenderer;
use BoldMinded\DataGrab\Dependency\Laravel\Prompts\Themes\Default\ProgressRenderer;
use BoldMinded\DataGrab\Dependency\Laravel\Prompts\Themes\Default\SearchPromptRenderer;
use BoldMinded\DataGrab\Dependency\Laravel\Prompts\Themes\Default\SelectPromptRenderer;
use BoldMinded\DataGrab\Dependency\Laravel\Prompts\Themes\Default\SpinnerRenderer;
use BoldMinded\DataGrab\Dependency\Laravel\Prompts\Themes\Default\SuggestPromptRenderer;
use BoldMinded\DataGrab\Dependency\Laravel\Prompts\Themes\Default\TableRenderer;
use BoldMinded\DataGrab\Dependency\Laravel\Prompts\Themes\Default\TextareaPromptRenderer;
use BoldMinded\DataGrab\Dependency\Laravel\Prompts\Themes\Default\TextPromptRenderer;
trait Themes
{
    /**
     * The name of the active theme.
     */
    protected static string $theme = 'default';
    /**
     * The available themes.
     *
     * @var array<string, array<class-string<\Laravel\Prompts\Prompt>, class-string<object&callable>>>
     */
    protected static array $themes = ['default' => [TextPrompt::class => TextPromptRenderer::class, NumberPrompt::class => NumberPromptRenderer::class, TextareaPrompt::class => TextareaPromptRenderer::class, PasswordPrompt::class => PasswordPromptRenderer::class, SelectPrompt::class => SelectPromptRenderer::class, MultiSelectPrompt::class => MultiSelectPromptRenderer::class, ConfirmPrompt::class => ConfirmPromptRenderer::class, PausePrompt::class => PausePromptRenderer::class, SearchPrompt::class => SearchPromptRenderer::class, MultiSearchPrompt::class => MultiSearchPromptRenderer::class, SuggestPrompt::class => SuggestPromptRenderer::class, Spinner::class => SpinnerRenderer::class, Note::class => NoteRenderer::class, Table::class => TableRenderer::class, Progress::class => ProgressRenderer::class, Clear::class => ClearRenderer::class, Grid::class => GridRenderer::class]];
    /**
     * Get or set the active theme.
     *
     * @throws \InvalidArgumentException
     */
    public static function theme(?string $name = null) : string
    {
        if ($name === null) {
            return static::$theme;
        }
        if (!isset(static::$themes[$name])) {
            throw new InvalidArgumentException("Prompt theme [{$name}] not found.");
        }
        return static::$theme = $name;
    }
    /**
     * Add a new theme.
     *
     * @param  array<class-string<\Laravel\Prompts\Prompt>, class-string<object&callable>>  $renderers
     */
    public static function addTheme(string $name, array $renderers) : void
    {
        if ($name === 'default') {
            throw new InvalidArgumentException('The default theme cannot be overridden.');
        }
        static::$themes[$name] = $renderers;
    }
    /**
     * Get the renderer for the current prompt.
     */
    protected function getRenderer() : callable
    {
        $class = \get_class($this);
        return new (static::$themes[static::$theme][$class] ?? static::$themes['default'][$class])($this);
    }
    /**
     * Render the prompt using the active theme.
     */
    protected function renderTheme() : string
    {
        $renderer = $this->getRenderer();
        return $renderer($this);
    }
}

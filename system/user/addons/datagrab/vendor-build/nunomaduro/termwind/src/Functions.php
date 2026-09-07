<?php

declare (strict_types=1);
namespace BoldMinded\DataGrab\Dependency\Termwind;

use Closure;
use BoldMinded\DataGrab\Dependency\Symfony\Component\Console\Output\OutputInterface;
use BoldMinded\DataGrab\Dependency\Termwind\Repositories\Styles as StyleRepository;
use BoldMinded\DataGrab\Dependency\Termwind\ValueObjects\Style;
use BoldMinded\DataGrab\Dependency\Termwind\ValueObjects\Styles;
if (!\function_exists('BoldMinded\\DataGrab\\Dependency\\Termwind\\renderUsing')) {
    /**
     * Sets the renderer implementation.
     */
    function renderUsing(?OutputInterface $renderer) : void
    {
        Termwind::renderUsing($renderer);
    }
}
if (!\function_exists('BoldMinded\\DataGrab\\Dependency\\Termwind\\style')) {
    /**
     * Creates a new style.
     *
     * @param  (Closure(Styles $renderable, string|int ...$arguments): Styles)|null  $callback
     */
    function style(string $name, ?Closure $callback = null) : Style
    {
        return StyleRepository::create($name, $callback);
    }
}
if (!\function_exists('BoldMinded\\DataGrab\\Dependency\\Termwind\\render')) {
    /**
     * Render HTML to the terminal.
     */
    function render(string $html, int $options = OutputInterface::OUTPUT_NORMAL) : void
    {
        (new HtmlRenderer())->render($html, $options);
    }
}
if (!\function_exists('BoldMinded\\DataGrab\\Dependency\\Termwind\\parse')) {
    /**
     * Parse HTML to a string that can be rendered in the terminal.
     */
    function parse(string $html) : string
    {
        return (new HtmlRenderer())->parse($html)->toString();
    }
}
if (!\function_exists('BoldMinded\\DataGrab\\Dependency\\Termwind\\terminal')) {
    /**
     * Returns a Terminal instance.
     */
    function terminal() : Terminal
    {
        return new Terminal();
    }
}
if (!\function_exists('BoldMinded\\DataGrab\\Dependency\\Termwind\\ask')) {
    /**
     * Renders a prompt to the user.
     *
     * @param  iterable<array-key, string>|null  $autocomplete
     */
    function ask(string $question, ?iterable $autocomplete = null) : mixed
    {
        return (new Question())->ask($question, $autocomplete);
    }
}

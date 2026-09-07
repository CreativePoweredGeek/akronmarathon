<?php

namespace BoldMinded\DataGrab\Dependency\Illuminate\Console\View\Components;

use BoldMinded\DataGrab\Dependency\Symfony\Component\Console\Output\OutputInterface;
class Alert extends Component
{
    /**
     * Renders the component using the given arguments.
     *
     * @param  string  $string
     * @param  int  $verbosity
     * @return void
     */
    public function render($string, $verbosity = OutputInterface::VERBOSITY_NORMAL)
    {
        $string = $this->mutate($string, [Mutators\EnsureDynamicContentIsHighlighted::class, Mutators\EnsurePunctuation::class, Mutators\EnsureRelativePaths::class]);
        $this->renderView('alert', ['content' => $string], $verbosity);
    }
}

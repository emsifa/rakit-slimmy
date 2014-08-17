<?php namespace Rakit\Slimmy;

/**
 * Slimmy - extending Slim Framework with simple modular system
 * ------------------------------------------------------------
 * @author      Muhammad Syifa <emsifa@gmail.com>
 * @copyright   2014 Muhammad Syifa
 * @package     Slimmy
 * ------------------------------------------------------------
 */

use Slim\Route as SlimRoute;

class Route extends SlimRoute {

	protected $defined_callable, $parsed_callable;

	public function __construct($pattern, $defined_callable, $callable, $caseSensitive = true)
    {
    	$this->defined_callable = $defined_callable;
    	parent::__construct($pattern, $callable, $caseSensitive);
    }

    public function getDefinedCallable()
    {
    	return $this->defined_callable;
    }

}
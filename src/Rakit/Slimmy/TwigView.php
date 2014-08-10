<?php namespace Rakit\Slimmy;

/**
 * Slimmy - extending Slim Framework with simple modular system
 * ------------------------------------------------------------
 * @author      Muhammad Syifa <emsifa@gmail.com>
 * @copyright   2014 Muhammad Syifa
 * @package     Slimmy
 * ------------------------------------------------------------
 */

use Twig_Autoloader;
use Twig_Loader_Filesystem;
use Twig_Environment;
use Slim\View;

/**
 * TwigView - Custom Slim View for Twig template engine
 */
class TwigView extends View
{

    /**
     * @var Twig_Loader_Filesystem twig_loader for adding a view path
     */
    protected $twig_loader = null;

    /**
     * @var string base_view_path
     */
    public $base_view_path = null;

    /**
     * @var array loader_configs
     */
    public $loader_configs = array();

    /**
     * @var Twig_Environment twig
     */
    protected $twig = null;

    /**
     * Custom render method
     * @param string template filename to render
     * @param array data
     */
    public function render($template, $data = null)
    {
        $twig = $this->getTwig();
        $parser = $twig->loadTemplate($template);

        return $parser->render($this->all(), $data);
    }

    /**
     * Getting Twig_Environment
     */
    public function getTwig()
    {
        if (!$this->twig) {
            Twig_Autoloader::register();
            $this->twig_loader = new Twig_Loader_Filesystem($this->base_view_path);

            $this->twig = new Twig_Environment(
                $this->twig_loader,
                $this->loader_configs
            );
        }

        return $this->twig;
    }

    /**
     * Adding a new view path
     * @param string path view directory
     * @param string namespace alias for this view path
     */
    public function addPath($path, $namespace)
    {
        $this->twig_loader->addPath($path, $namespace);
    }

}

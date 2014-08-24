<?php namespace Rakit\Slimmy;

/**
 * Slimmy - extending Slim Framework with simple modular system
 * ------------------------------------------------------------
 * @author		Muhammad Syifa <emsifa@gmail.com>
 * @copyright	2014 Muhammad Syifa
 * @package		Slimmy
 * ------------------------------------------------------------
 */

use Illuminate\Database\Capsule\Manager as IlluminateCapsule;
use Slim\Slim;

class Slimmy extends Slim {

	public $twig;

	protected $registered_modules = array();

	protected $routes = array();

	public function __construct(array $configs = array())
	{
		// merge configs to default configs
		$configs = array_merge(array(
			'path.app'					=> 'app',
			'module.namespace' 		=> 'App\Modules',
			'module.path' 			=> 'app/modules',
			'view.base_path' 		=> 'views',
			'module.auto_register'	=> true,

			'database.default_connection' => 'default'
		), $configs);

		$configs['view'] = new TwigView();
		
		parent::__construct($configs);

		//$this->setupDatabase($configs);
		$this->setupTwig();

		$connections = $this->config('database.connections');
		if( ! empty($connections) ) {
			$this->setupDatabaseConnections();
		}
	}

	/**
	 * Setup Twig template engine
	 */
	protected function setupTwig()
	{
		$view_path = $this->config('path.app').'/'.$this->config('view.base_path');
		
		$this->view()->base_view_path = array($view_path);
		$this->twig = $this->view()->getTwig();

		// add some global variables
		$this->twig->addGlobal('app', $this);
		$this->twig->addGlobal('req', $this->request);
		
		// registering native functions
		$this->twig->registerUndefinedFunctionCallback(function ($name) {
			if (function_exists($name)) {
				return new \Twig_Function_Function($name);
			}
		
			return false;
		});
	}

	/**
	 * Setup illuminate database connections
	 */
	protected function setupDatabaseConnections()
	{
		// initialize Capsule
		$this->db = new IlluminateCapsule;
		$connections = $this->config('database.connections');
		$default_connection = $this->config('database.default_connection');

		// create conenctions
		foreach($connections as $name => $settings) {
			$this->addDatabaseConnection($name, $settings);
		}

		// set as global, so we can access it everywhere
		$this->db->setAsGlobal();
		$this->db->bootEloquent();

		$this->setDefaultConnection($default_connection);
	}
	
	/**
	 * extending basic mapRoute
	 * with extra method for access module controller
	 * @param array args route parameters
	 */
	protected function mapRoute($_args)
	{
		$pattern = array_shift($_args);
		
		$args = $this->checkAndBuildModuleCallables($_args);	

		$callable = array_pop($args);
		$defined_callable = array_pop($_args);
		$route = new Route($pattern, $defined_callable, $callable, $this->settings['routes.case_sensitive']);
		$this->router->map($route);
		
		$this->routes[] = $route;

		if (count($args) > 0) {
			$route->setMiddleware($args);
		}

		return $route;
	}

	public function getRoutes()
	{
		return $this->routes;
	}
	
	/**
	 * Registering a module
	 * @param string module_name name of module to register
	 */
	public function registerModule($module_name)
	{
		// if module has registered, do not register it again
		if($this->hasRegister($module_name)) return;

		// check if module is exists
		if( ! $this->hasModule($module_name)) {
			throw new \Exception("Module ".$module_name." not found");
		}

		$module_path = $this->config('module.path').'/'.$module_name;

		// adding viewpath namespace for this registered module
		$this->view()->addPath($module_path.'/views', $module_name);

		$app = $this;

		$common_files = array('helpers.php', 'hooks.php', 'routes.php');

		foreach($common_files as $_file) {
			if(file_exists($module_path.'/'.$file)) {
				include($module_path.'/'.$_file);
			}
		}

		$this->registered_modules[$module_name] = $module_path;
	}
	
	/**
	 * Check if module is exists in module path
	 * @param string module_name
	 */
	public function hasModule($module_name)
	{
		$module_path = $this->config('module.path').'/'.$module_name;

		return (file_exists($module_path) AND is_dir($module_path));
	}

	/**
	 * Check if module has registered in app
	 * @param string module_name
	 */
	public function hasRegister($module_name)
	{
		return array_key_exists($module_name, $this->registered_modules);
	}

	/**
	 * Adding illuminate database connection
	 * @param string name connection name
	 * @param array setting connection settings
	 */
	public function addDatabaseConnection($name, array $settings)
	{
		$this->db->addConnection($settings, $name);
	}

	/**
	 * Set illuminate database default connection
	 * @param string connection_name default connection name
	 */
	public function setDefaultConnection($connection_name)
	{
		$container = $this->db->getContainer();
		$container['config']['database.default'] = $connection_name;
	}

	/**
	 * Check and build module controller route access
	 * @param array args slim route parameters
	 */
	protected function checkAndBuildModuleCallables($args)
	{
		if(!is_array($args)) {
			return array();
		}

		foreach($args as $i => $callable) {
			if(is_string($callable)) {
				$args[$i] = $this->getModuleControllerMethodAccess($callable);
			}
		}

		return $args;	
	}

	public function getFlash($key, $default = null)
	{
		if($this->hasFlash($key)) {
			return $_SESSION['slim.flash'][$key];
		} else {
			return $default;
		}
	}

	public function hasFlash($key)
	{
		if(isset($_SESSION['slim.flash']) and is_array($_SESSION['slim.flash'])) {
			return array_key_exists($key, $_SESSION['slim.flash']);
		}
	}

	/**
	 * Getting module controller namespace for access it via route
	 * @param string callable route access declaration
	 */
	protected function getModuleControllerMethodAccess($callable) {
		$module_namespace = $this->config('module.namespace');

		// replacing slash with backslash, so we can (also) access it via slash for namespacing
		$callable = str_replace('/', "\\", $callable);

		// checking module name declaration
		preg_match("/^\@(?<module>[a-zA-Z0-9_]+)(?<ctrl_access>.*)/", $callable, $match);
		
		// if it has module declaration
		if($match) {
			// build namespace and controller method access		
			$callable = "\\".$module_namespace
				."\\".$match['module']
				."\\Controllers\\"
				.$match['ctrl_access'];
			
			$callable = preg_replace("/\\\+/", "\\", $callable);

			// register this module if module.auto_register is enabled
			if( TRUE === $this->config('module.auto_register') ) {
				$this->registerModule($match['module']);
			}
		}
		
		return $callable;
	}

}
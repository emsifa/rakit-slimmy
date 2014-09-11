<?php namespace Rakit\Slimmy;

use Illuminate\Database\ConnectionResolverInterface as Resolver;
use Illuminate\Database\Migrations\Migrator as LaravelMigrator;
use Illuminate\Database\Migrations\DatabaseMigrationRepository as Repository;
use Illuminate\Database\Eloquent\Model as Eloquent;
use Illuminate\Database\Capsule\Manager as Capsule;

class Migrator extends LaravelMigrator {

	protected $app;
	protected $paths = array();

	public function __construct(Slimmy $app, array $paths = array())
	{
		$resolver = Eloquent::getConnectionResolver();
		$repository = new Repository($resolver, $app->config('migration.table'));

		$this->app = $app;
		$this->paths = $paths;
		$this->repository = $repository;
		$this->resolver = $resolver;
	}

	public function addPath($path_name = 'default', $path)
	{
		if( ! $this->hasPath($path_name)) {
			$this->setPath($path_name, $path);
		}
	}

	public function setPath($path_name, $path)
	{
		if(!is_string($path_name)) {
			throw new \InvalidArgumentException("Migration path name must be string");
		}

		$this->paths[$path_name] = $path;
	}

	public function getPath($path_name)
	{
		return $this->hasPath($path_name)? $this->paths[$path_name] : NULL;
	}

	public function getPaths()
	{
		return $this->paths;
	}

	public function hasPath($path_name)
	{
		return array_key_exists($path_name, $this->paths);
	}

	public function run($path_name = null, $pretend = false)
	{
		$this->createMigrationTableIfNotExists();

		$this->notes = array();

		$fullpath_files = $this->getMigrationFiles($path_name);

		$this->requireFiles($fullpath_files);

		$files = array_map(function($file) {
			return str_replace('.php', '', basename($file));
		}, $fullpath_files);

		$ran = $this->repository->getRan();

		$migrations = array_diff($files, $ran);

		$this->runMigrationList($migrations, $pretend);
	}

	public function rollback($pretend = false)
	{
		$fullpath_files = $this->getMigrationFiles();
		$this->requireFiles($fullpath_files);

		parent::rollback($pretend);
	}

	public function getMigrationFiles($path_name = null)
	{
		if(!empty($path_name)) {
			$path = $this->getPath($path_name);

			if(!$path) return array();

			$files = $this->scanPath($path, 0, function($filepath) {
				if(pathinfo($filepath, PATHINFO_EXTENSION) == 'php') return $filepath;
			});
		} else {
			$files = array();

			foreach($this->paths as $path) {
				$scanned_files = $this->scanPath($path, 0, function($filepath) {
					if(pathinfo($filepath, PATHINFO_EXTENSION) == 'php') return $filepath;
				});

				$files = array_merge(
					$files,
					$scanned_files	
				);
			}
		}

		sort($files);

		return $files;
	}

	public function requireFiles(array $files)
	{
		foreach($files as $file) require_once($file);
	}
	
	public function resolve($file)
	{
		$file = implode('_', array_slice(explode('_', $file), 4));

		$class = studly_case($file);

		return new $class;
	}

	protected function scanPath($path, $max_depth = 0, \Closure $filter = null)
	{
		$files = $this->scanFiles($path, 0, $max_depth, $filter);
		return $files;
	}

	protected function scanFiles($path, $current_depth, $max_depth = 0, \Closure $filter = null)
	{
		if($current_depth > 0 AND $current_depth == $max_depth) {
			return array();
		}

		$files = array_diff(scandir($path), array('.', '..'));

		$scanned_files = array();

		foreach($files as $file) {
			$filepath = $path.'/'.$file;			

			if(is_dir($filepath)) {
				$scanned_files = array_merge($scanned_files, $this->scanFiles($filepath, $current_depth+1, $max_depth, $filter));
			} else {
				if(!is_null($filter)) {	
					$filtered = $filter($filepath, $scanned_files);
					if(!is_null($filtered)) $scanned_files[] = $filtered;	
				} else {
					$scanned_files[] = $filepath;
				}	
			}
		}

		return $scanned_files;
	}

	protected function createMigrationTableIfNotExists()
	{
		$schema = Capsule::schema();
		$migration_table = $this->app->config('migration.table');
		
		if(! $schema->hasTable($migration_table)) {

			$schema->create($migration_table, function($table) {
				$table->string("migration", 255);
				$table->integer("batch");
			});

		}
	}

}

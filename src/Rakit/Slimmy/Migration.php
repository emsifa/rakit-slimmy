<?php namespace Rakit\Slimmy;

use Illuminate\Database\Capsule\Manager as Capsule;

abstract Class Migration {

	protected $app, $schema, $db;

	public function __construct()
	{
		$this->app = Slimmy::getInstance();

		$this->schema = Capsule::schema();
		$this->db = $this->app->db;
	}

}
<?php

namespace Grapesc\GrapeFluid;


class BaseParametersRepository
{
	
	/** @var array */
	protected $params = [];
	
	
	public function __construct($appDir, array $directoriesParamsForCheck = [])
	{
		$this->setDefaultParams($appDir);
		$this->loadParamsFromConfig($this->params['configDir'] . "config.php");
		$this->loadParamsFromConfig($this->params['configDir'] . "config.local.php");

		if (array_key_exists('pluginDirs', $this->params)) {
			$this->params['pluginDirs'] = array_merge((array) $this->params['pluginDirs'], [$this->params["appDir"] . "plugins" . DIRECTORY_SEPARATOR]);
		} else {
			$this->params['pluginDirs'] = [$this->params["appDir"] . "plugins" . DIRECTORY_SEPARATOR];
		}

		$this->checkDirectories($directoriesParamsForCheck);
	}

	
	/**
	 * @param string $var
	 * @param mixed $defaultValue
	 * @return mixed
	 */
	public function getParam($var, $defaultValue = null)
	{
		return key_exists($var, $this->params) ? $this->params[$var] : $defaultValue;
	}


	public function getAllParams()
	{
		return $this->params;
	}

	
	/**
	 * @param string $var
	 * @param mixed $val
	 */
	public function setParam($var, $val)
	{
		$this->params[$var] = $val;
	}

	
	/**
	 * @param array $params
	 */
	public function setParams(array $params)
	{
		$this->params = array_merge($this->params, $params);
	}
	
	
	/**
	 * Checking a recursive creating directories required for app
	 * @param array $dirs
	 */
	private function checkDirectories($dirs)
	{
		foreach ($dirs AS $dir) {
			if (!file_exists($this->getParam($dir))) {
				mkdir($this->getParam($dir), $this->getParam("dirPerm"), true);
			}
		}
	}


	/**
	 * @param string $appDir
	 * @return array
	 */
	private function setDefaultParams($appDir)
	{
		$defaultParams                  = [];
		$defaultParams["appDir"]        = $appDir . DIRECTORY_SEPARATOR;
		$defaultParams["moduleDir"]     = $defaultParams["appDir"] . "modules" . DIRECTORY_SEPARATOR;
		$defaultParams["vendorDir"]     = $defaultParams["appDir"] . "vendor" . DIRECTORY_SEPARATOR;
		$defaultParams["grapeFluidDir"] = $defaultParams["vendorDir"] . "grape-fluid" . DIRECTORY_SEPARATOR;
		$defaultParams["configDir"]     = $defaultParams["appDir"] . "config" . DIRECTORY_SEPARATOR;
		$defaultParams["varDir"]        = $defaultParams["appDir"] . "var" . DIRECTORY_SEPARATOR;
		$defaultParams["tempDir"]       = $defaultParams["varDir"] . "temp" . DIRECTORY_SEPARATOR;
		$defaultParams["logDir"]        = $defaultParams["varDir"] . "log" . DIRECTORY_SEPARATOR;
		$defaultParams["wwwDir"]	    = $defaultParams["appDir"] . "www" . DIRECTORY_SEPARATOR;
		$defaultParams["assetsDir"]	    = "components";
		$defaultParams["dirPerm"]       = 0777;
		$defaultParams["debug"]         = false;

		$this->params = $defaultParams;
	}


	/**
	 * @param string $file
	 * @return array
	 */
	private function loadParamsFromConfig($file)
	{
		$params = [];
		if (file_exists($file)) {
			require $file;
		}

		$this->params = array_merge($this->params, $params);
	}
}
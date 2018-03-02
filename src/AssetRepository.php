<?php

namespace Grapesc\GrapeFluid;

use Nette\Caching\Cache;
use Nette\Caching\IStorage;
use Nette\Utils\Finder;
use Symfony\Component\Console\Output\OutputInterface;


/**
 * @author Kulíšek Patrik <kulisek@grapesc.cz>
 * @author Mira Jakes <jakes@grapesc.cz>
 */
class AssetRepository
{

	/** @var array */
	private $assets = [];

	/** @var BaseParametersRepository */
	private $params;

	/** @var Cache */
	private $cache;

	/** @var bool */
	private $deployed = false;


	public function __construct(BaseParametersRepository $params, array $assets = [], IStorage $IStorage)
	{
		$this->assets = $assets;
		$this->params = $params;
		$this->cache  = new Cache($IStorage, "Fluid.Assets");
	}


	/**
	 * Zajišťuje prvotní přenos assetů do produkční složky (www/assets)
	 * Sestavený seznam (dle config.neon) ukládá do cache a v případě debug modu kontroluje změny v jednotlivých souborech
	 *
	 * Pozor:
	 * V případě vyplého debug modu je potřeba vždy pro změnu assetů smazat cache
	 *
	 *
	 * @param bool $force
	 * @return array
	 */
	public function deployAssets($force = false)
	{
		if ($this->deployed) {
			return [];
		}

		$files = ($force ? null : $this->cache->load("assets"));

		if ($this->params->getParam("debug") == false && $files !== null) {
			return [];
		}

		$list = $this->getFileList();

		$deployed = [];
		$tempCache = [];
		$tempLive = [];
		$individual = false;

		if ($files !== null) {
			foreach($files as $limit => $paths) {
				foreach ($paths as $path => $desc) {
					if ($desc['type'] == 'copy') {
						continue;
					}
					$tempCache[$path] = $desc['time'];
				}
			}

			foreach($list as $limit => $paths) {
				foreach ($paths as $path => $desc) {
					if ($desc['type'] == 'copy') {
						continue;
					}
					$tempLive[$path] = $desc['time'];
				}
			}
		}

		if ($files === null) {
			$process = $list;
		} else {
			$process = array_diff_assoc($tempCache, $tempLive);
			$individual = true;
		}

		if (!empty($process)) {
			$wwwAssetDir = $this->getAssetsPublicDirectory();

			if (!file_exists($wwwAssetDir)) {
				mkdir($wwwAssetDir, $this->params->getParam('dirPerm'), true);
			}

			if (!$individual) {
				foreach ($process as $limit => $files)
				{
					foreach ($files as $path => $info) {
						$this->deployFile($path, ($info['asset'] == "upload" && $info['type'] == "copy"), (array_key_exists("destination", $info) ? $info['destination'] : null));
						$deployed[] = $path;
					}
				}
			} else {
				foreach ($process as $path => $info) {
					$this->deployFile($path);
				}
			}

			$this->cache->save("assets", $list);
		}

		$this->deployed = true;
		return $deployed;
	}


	/**
	 * Zkopíruje veškeré soubory z určené upload složky
	 * Neovlivňuje stav cache
	 */
	public function forceDeployUpload()
	{
		$deployed = [];

		foreach ($this->getFileList() as $limit => $files)
		{
			foreach ($files as $path => $info) {
				if (($info['asset'] == "upload" && $info['type'] == "copy")) {
					$this->deployFile($path, true);
					$deployed[] = $path;
				}
			}
		}

		return $deployed;
	}


	/**
	 * Zkopíruje soubor ($path) do produkční složky
	 *
	 * @param $path - Cesta soubory ke kopirovani
	 * @param $upload - Jedna se o soubor z upload slozky?
	 * @param $destination - Cilova slozka, kam soubor zkopirujeme (pokud neni param null)
	 */
	private function deployFile($path, $upload = false, $destination = null)
	{
		if (file_exists($path)) {
			if ($destination == null) {
				if ($upload) {
					$deploy = $this->getAssetsPublicDirectory() . str_replace($this->getEnvironmentPath($this->getUploadPath()), "", $path);
				} else {
					$deploy = $this->getAssetsPublicDirectory() . str_replace($this->params->getParam("appDir"), "", $path);
				}
			} else {
				$deploy = $destination;
			}

			if (!file_exists(dirname($deploy))) {
				mkdir(dirname($deploy), $this->params->getParam('dirPerm'), true);
			}
			copy($path, $deploy);
		}
	}


	/**
	 * Vrací seznamy souborů dle config.neon rozdělených dle limitů
	 * Jednotlivé soubory poté obsahují datum změny, typ a název assetu
	 *
	 * @return array
	 */
	public function getFileList()
	{
		$list = [];

		foreach ($this->assets as $asset => $types) {
			if ($asset == "config") {
				continue;
			}
			$limits = [];
			$files = [];
			foreach ($types as $type => $records) {
				if ($type == "limit") {
					foreach ($records as $limit) {
						// TODO: Rework - toto je temporary support pro nové limity
						if (!is_array($limit)) {
							$limit = ["link" => $limit];
						}
						$limits[] = json_encode($limit);
					}
				} else {
					foreach ($records as $file) {
						if (is_array($file) && array_key_exists(0, $file) && array_key_exists(1, $file)) {
							$file[0] = $this->getEnvironmentPath($file[0]);
							$file[1] = $this->getEnvironmentPath($file[1]);
							if (file_exists($file[0])) {
								$files[$file[0]] = [ "time" => filemtime($file[0]), "type" => $type, "asset" => $asset, "destination" => $file[1]];
								continue;
							} elseif (basename($file[0]) == "*") {
								if (!is_dir(dirname($file[0]))) {
									continue;
								}
								# TODO: Podpora pro kopírování 1:1 podsložky
								$found = Finder::findFiles(basename($file[0]))->from(dirname($file[0]));
								/**
								 * @var string $path
								 * @var \SplFileInfo $info
								 */
								foreach ($found as $path => $info) {
									$files[$path] = [
										"time" => filemtime($path),
										"type" => $type,
										"asset" => $asset,
										"destination" => str_replace("*", $info->getFilename(), $file[1])
									];
								}
								continue;
							}
						} elseif (filter_var($file, FILTER_VALIDATE_URL) !== false) {
							$files[$file] = [ "time" => 0, "type" => $type, "asset" => $asset ];
							continue;
						} elseif (!file_exists(dirname($this->getEnvironmentPath($file)))) {
							continue;
						} else {
							$file = $this->getEnvironmentPath($file);
							if (!is_dir(dirname($file))) {
								continue;
							}
						}

						$found = Finder::findFiles(basename($file))->from(dirname($file));
						foreach ($found as $path => $info) {
							if (pathinfo($path, PATHINFO_EXTENSION) != $type && $type != "copy") {
								continue;
							}
							$files[$path] = [ "time" => filemtime($path), "type" => $type, "asset" => $asset ];
						}
					}
				}
			}
			if (empty($limits)) {
				$everywhere = json_encode(["link" => "*"]);
				if (array_key_exists($everywhere, $list)) {
					$list[$everywhere] = array_merge($list[$everywhere], $files);
				} else {
					$list[$everywhere] = $files;
				}
			} else {
				foreach ($limits as $limit) {
					if (array_key_exists($limit, $list)) {
						$list[$limit] = array_merge($list[$limit], $files);
					} else {
						$list[$limit] = $files;
					}
				}
			}
		}
		return $list;
	}


	/**
	 * Vrací seznam assetů pro aktuální odkaz / akci ($link) a použití ($type) = css, js
	 * Prvotně se snaží najít záznam v cache, pokud selže, sestaví si nový seznam a ten do cache uloží
	 *
	 * @param string $type (css / js)
	 * @param $actualLink
	 * @return mixed|NULL
	 * @throws \Exception
	 * @throws \Throwable
	 */
	public function getForCurrentLink($type, $actualLink)
	{
		return $this->cache->load($actualLink . "_" . $type, function() use ($type, $actualLink) {
			$assets = [];
			foreach ($this->getFileList() as $limit => $files) {
				// TODO: Rework - toto je temporary support pro nové limity
				$limit = json_decode($limit, true);

				// Limit * na link se nevztahuje na administraci, assety pro Admin modul je treba vzdy explicitne uvest
				// TODO: Seznam modulu, u kterych se musi explicitne udavat limity pro linky
				if (($limit['link'] == "*" || preg_match("/^" . $limit['link'] . "/", $actualLink)) AND !($limit['link'] == "*" AND preg_match("/^:Admin:.*/", $actualLink))) {
					foreach ($files as $path => $desc) {
						$isUrl = filter_var($path, FILTER_VALIDATE_URL);
						if ((pathinfo($path, PATHINFO_EXTENSION) == $type && $desc['type'] != "copy") || ($isUrl && $desc['type'] == $type)) {
							if (isset($limit['auth'])) {
								$desc += ["auth" => $limit['auth']];
							}
							if (isset($limit['option'])) {
								$desc += ["option" => $limit['option']];
							}
							if (isset($desc['destination'])) {
								$path = str_replace($this->params->getParam("assetsDir"), "", $desc['destination']);
							}

							if ($isUrl) {
								$assets[$path] = $desc + ["url" => true];
							} else {
								$assets[str_replace(DIRECTORY_SEPARATOR, "/", str_replace($this->params->getParam("appDir"), "", $path))] = $desc;
							}
						}
					}
				}
			}
			return $assets;
		});
	}


	/**
	 * Odstrani assety z public slozky
	 * @param null|string $relativePath
	 * @param OutputInterface|null $output
	 */
	public function clean($relativePath = null, OutputInterface $output = null)
	{
		$directory = realpath($this->getAssetsPublicDirectory() . $relativePath);

		if (!is_dir($directory)) {
			return;
		}

		foreach (Finder::find('*')->in($directory) AS $item) {
			/* @var $item \SplFileInfo */
			if ($item->isDir() AND $item->getRealPath() != $this->getUploadPath()) {
				$relativeDirectoryPath = ltrim(str_replace(realpath($this->getAssetsPublicDirectory()), "", $item->getPathname()), DIRECTORY_SEPARATOR);
				if (!$relativePath AND $output) {
					$output->writeln("<comment>Deleting {$item->getPathname()}</comment>");
				}
				$this->clean($relativeDirectoryPath, $output);
				if (!@rmdir($item->getPathname()) AND $output) {
					$output->writeln("<error>Can`t delete directory {$item->getPathname()}</error>");
				}
			} elseif ($item->isFile()) {
				if (!@unlink($item->getPathname()) AND $output) {
					$output->writeln("<error>Can`t delete file {$item->getPathname()}</error>");
				}
			} elseif ($item->getRealPath() == $this->getUploadPath()) {
				if ($output) {
					$output->writeln("<info>Skipping {$item->getPathname()}</info>");
				}
			}
		}

		if (!$relativePath) {
			$this->cache->clean([Cache::NAMESPACES => ["Fluid.Assets"]]);
		}
	}


	/**
	 * @return bool|string
	 */
	public function getUploadPath()
	{
		return realpath(substr($this->assets['upload']['copy'][0][1], 0, -1));
	}


	/**
	 * @return mixed
	 */
	private function getConfig()
	{
		return $this->assets['config'];
	}


	/**
	 * Převede lomítka v zadané cestě dle platných lomítek na daném prostředí
	 *
	 * @param $path
	 * @return mixed
	 */
	private function getEnvironmentPath($path)
	{
		return str_replace(["\\\\", "//"], ["\\", "/"], str_replace(["/", "\\"], DIRECTORY_SEPARATOR, $path));
	}


	/**
	 * @return string
	 */
	private function getAssetsPublicDirectory()
	{
		return $this->params->getParam("assetsDir");
	}

}
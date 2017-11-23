<?php

namespace Grapesc\GrapeFluid;

use Nette\DI\Container;
use Nette\Http\FileUpload;
use Nette\Utils\Image;
use Nette\Utils\Random;


class ImageStorage
{
	/** @var Container */
	private $container;

	/** @var BaseParametersRepository */
	private $parameters;

	private $configuration = null;
	private $lastState = null;


	public function __construct(Container $container, BaseParametersRepository $parameters)
	{
		$this->container = $container;
		$this->parameters = $parameters;

		if (strpos($this->getEnvironmentPath($this->getFolderPath("upload")), $this->getEnvironmentPath($this->parameters->getParam("wwwDir"))) === false) {
			throw new \LogicException("Image Storage - Upload path is wrongly configured, it must point to www directory");
		}
	}


	/**
	 * Zpracuje obrázek do určené upload složky
	 * Pokud je povoleno, obrázek navíc zazálohuje do určené backup složky
	 *
	 * @param FileUpload $file - soubor (obrázek) ke zpracování
	 * @param int $maxW - nepovinný (aplikuje se nastavení z config.neon)
	 * @param int $maxH - nepovinný (aplikuje se nastavení z config.neon)
	 * @param bool $realPath - true - vrací celou cestu, false - vrací cestu pro html dokument
	 *
	 * @return bool|string - string s cestou k souboru v případě úspěchu, jinak false v případě chyby (možno vyžádat přes getLastState())
	 */
	public function processImage(FileUpload $file, $maxW = null, $maxH = null, $realPath = false)
	{
		/** @var FileUpload $file */
		if ($this->isImage($file)) {
			$uploadPath = $this->saveImage($file, $maxW, $maxH);
			return $realPath ? $uploadPath : str_replace($this->getHtmlPath($this->parameters->getParam("wwwDir")), "", $this->getHtmlPath($uploadPath));
		} else {
			$this->setLastState('storage.message.badfile');
			return false;
		}
	}


	/**
	 * Zpracuje obrázek na první pozici z aktuálního POST requestu do určené upload složky
	 * Pokud je povoleno, obrázek navíc zazálohuje do určené backup složky
	 *
	 * @param int $maxW - nepovinný (aplikuje se nastavení z config.neon)
	 * @param int $maxH - nepovinný (aplikuje se nastavení z config.neon)
	 * @param bool $realPath - true - vrací celou cestu, false - vrací cestu pro html dokument
	 *
	 * @return bool|string - string s cestou k souboru v případě úspěchu, jinak false v případě chyby (možno vyžádat přes getLastState())
	 */
	public function processImageFromRequest($maxW = null, $maxH = null, $realPath = false)
	{
		/** @var FileUpload $file */
		foreach ($this->container->getService("httpRequest")->getFiles() as $file) {
			if ($this->isImage($file)) {
				$uploadPath = $this->saveImage($file, $maxW, $maxH);
				return $realPath ? $uploadPath : str_replace($this->getHtmlPath($this->parameters->getParam("wwwDir")), "", $this->getHtmlPath($uploadPath));
			} else {
				$this->setLastState('storage.message.badfile');
				return false;
			}
		}
		$this->setLastState('storage.message.nothing');
		return false;
	}


	/**
	 * Zpracuje obrázky z aktuální POST requestu do určené upload složky
	 * Pokud je povoleno, obrázek navíc zazálohuje do určené backup složky
	 *
	 * @param int $maxW - nepovinný (aplikuje se nastavení z config.neon)
	 * @param int $maxH - nepovinný (aplikuje se nastavení z config.neon)
	 * @param bool $realPath - true - vrací celou cestu, false - vrací cestu pro html dokument
	 *
	 * @return array - s odpovedi ve tvaru ["stav" => "cesta / nazev"]
	 */
	public function processImagesFromRequest($maxW = null, $maxH = null, $realPath = false)
	{
		$response = [];

		/** @var FileUpload $file */
		foreach ($this->container->getService("httpRequest")->getFiles() as $file) {
			if ($this->isImage($file)) {
				$uploadPath = $this->saveImage($file, $maxW, $maxH);
				$response[$this->getLastState()] = $realPath ? $uploadPath : str_replace($this->getHtmlPath($this->parameters->getParam("wwwDir")), "", $this->getHtmlPath($uploadPath));
			} else {
				$response[$this->getLastState()] = $file->getSanitizedName();
			}
		}
		return $response;
	}


	/**
	 * Zjisti, zda-li je uploadovany obrazek platny obrazek
	 *
	 * @param FileUpload $image
	 * @return bool
	 */
	private function isImage(FileUpload $image)
	{
		return $image->isOk() && $image->isImage();
	}


	/**
	 * Uloží obrázek do produkční složky pro náhlé použití
	 * Pokud je povoleno v config.neon, obrázek taktéž zazálohuje
	 * V případě invalidace cache (_Fluid.Assets) nebo použití příkazu 'asset:deploy' dojde k opětovému nahrání
	 *
	 * @param FileUpload $file
	 * @param int $maxW - nepovinný (aplikuje se nastavení z config.neon)
	 * @param int $maxH - nepovinný (aplikuje se nastavení z config.neon)
	 * @return string
	 */
	private function saveImage(FileUpload $file, $maxW = null, $maxH = null)
	{
		$fileName = $this->getUniqueFileName($file);
		$uploadPath = $this->getFolderPath("upload") . DIRECTORY_SEPARATOR . $fileName;

		$live = $file->toImage();
		$this->resizeImage($live, $maxW, $maxH);
		$live->save($uploadPath, 100);

		if ($this->isBackupEnabled()) {
			$backup = $file->toImage();
			$this->resizeImage($backup, $maxW, $maxH);
			// TODO: Configurable image quality
			$backup->save($this->getFolderPath("backup") . DIRECTORY_SEPARATOR . $fileName, 100);
			$this->setLastState('storage.message.upload-backup');
		} else {
			$this->setLastState('storage.message.upload');
		}

		return $uploadPath;
	}


	/**
	 * Uloží předpřipravený obrázek do určené upload složky (popř. backup složky)
	 * Vygeneruje název souboru, přiřadý datový typ
	 *
	 * Vrátí cestu k souboru
	 *
	 * @param Image $image
	 * @param int $type - výchozí Image::JPEG
	 * @param bool $realPath - vrátit reálnou cestu? nebo pro použití v HTML?
	 * @return string - cesta k souboru
	 */
	public function savePreparedImage(Image $image, $type = Image::JPEG, $realPath = false)
	{
		$fileName = Random::generate(30) . "." . ($type == Image::JPEG ? "jpg" : ($type == Image::PNG ? 'png' : 'gif'));
		$uploadPath = $this->getFolderPath("upload") . DIRECTORY_SEPARATOR . $fileName;

		$image->save($uploadPath, 100, $type);

		if ($this->isBackupEnabled()) {
			$image->save($this->getFolderPath("backup") . DIRECTORY_SEPARATOR . $fileName, 100, $type);
			$this->setLastState('storage.message.upload-backup');
		} else {
			$this->setLastState('storage.message.upload');
		}

		return $realPath ? $uploadPath : str_replace($this->getHtmlPath($this->parameters->getParam("wwwDir")), "", $this->getHtmlPath($uploadPath));
	}


	/**
	 * Smaže soubor ze storage
	 *
	 * Očekává přesně předanou cestu, jakou vrací metody processImage()
	 * nebo savePreparedImage()
	 *
	 * Možno získat výsledek mazání pomocí getLastState() metody
	 *
	 * @param $path
	 * @return bool - true pokud byl soubor smazan, false pokud ne
	 */
	public function deleteImage($path)
	{
		if (strpos($path, '..') === false || strpos($path, '*') === false) {
			$file = basename($path);
			$uploadRemoved = @unlink($this->getFolderPath("upload") . DIRECTORY_SEPARATOR . $file);
			$backupRemoved = false;

			if ($this->isBackupEnabled()) {
				$backupRemoved = @unlink($this->getFolderPath("backup") . DIRECTORY_SEPARATOR . $file);
			}

			if ($uploadRemoved) {
				$this->setLastState("storage.message.delete-upload" . ($backupRemoved ? "-backup" : "-not-backup"));
				return true;
			} else {
				if ($backupRemoved) {
					$this->setLastState("storage.message.delete-backup-not-upload");
				} else {
					$this->setLastState("storage.message.delete-nothing");
				}
				return false;
			}
		} else {
			return false;
		}
	}


	/**
	 * Nastavení stavu aktuálně zpracovávaného obrázku
	 *
	 * @param string $state
	 */
	private function setLastState($state = "")
	{
		$this->lastState = $state;
	}


	/**
	 * Zjištění posledního stavu zpracovávaného obrázku
	 *
	 * @return null|string
	 */
	public function getLastState()
	{
		if (!$this->lastState) {
			$this->lastState = 'storage.message.notfound';
		}
		return $this->lastState;
	}


	/**
	 * Je povolený backup mode?
	 *
	 * @return bool
	 */
	private function isBackupEnabled()
	{
		return array_key_exists("backup", $this->getConfiguration()) ? $this->getConfiguration()['backup'] : false;
	}


	/**
	 * Zjistí, zdali je uvedený typ cesty nastaven v configu a zda-li složka existuje
	 * Pokud neexistuje, vytvoří ji
	 * Pokud není cesta nastavena, vyhodí exception
	 *
	 * @throws \RuntimeException
	 *
	 * @param string $type - typ složky - backup / upload
	 * @return mixed
	 */
	private function getFolderPath($type = "upload")
	{
		if (!array_key_exists($type . 'Folder', $this->getConfiguration())) {
			throw new \RuntimeException("Image Storage - $type folder is not configured!");
		} else {
			$path = $this->getConfiguration()[$type . 'Folder'];
			if (!file_exists($path)) {
				mkdir($path, $this->parameters->getParam("dirPerm"), true);
			}
			return $path;
		}
	}


	/**
	 * @return array - s aktuální konfigurací ImageStorage
	 */
	private function getConfiguration()
	{
		if (!$this->configuration) {
			$this->configuration = $this->container->getParameters()['storage'];
		}
		return $this->configuration;
	}


	/**
	 * Vrátí maximální možný rozměr obrázku na šířku
	 *
	 * @return int
	 */
	private function getMaxWidth()
	{
		if (is_int($width = $this->getConfiguration()['maxsize']['width'])) {
			return $width;
		} else {
			return 1024;
		}
	}


	/**
	 * Vrátí maximální možný rozměr obrázku na výšku
	 *
	 * @return int
	 */
	private function getMaxHeight()
	{
		if (is_int($height = $this->getConfiguration()['maxsize']['height'])) {
			return $height;
		} else {
			return 768;
		}
	}


	/**
	 * Ošetří rozměr obrázku:
	 * Podle parametrů $maxW a $maxH pokud jsou uvedeny
	 * Jinak dle config.neon (%storage.maxsize.width%, %storage.maxsize.height%)
	 *
	 * @param Image $image
	 * @param int $maxW
	 * @param int $maxH
	 */
	private function resizeImage(Image &$image, $maxW = null, $maxH = null)
	{
		$maxWidth = ($maxW !== null ? $maxW : $this->getMaxWidth());
		$maxHeight = ($maxH !== null ? $maxH : $this->getMaxHeight());

		$image->resize($maxWidth, $maxHeight, Image::SHRINK_ONLY);
	}


	/**
	 * Vygeneruje náhodné jméno obrázku
	 * Respektuje datový typ
	 *
	 * @param FileUpload $file
	 * @return string
	 */
	private function getUniqueFileName(FileUpload $file)
	{
		return Random::generate(30) . "." . pathinfo($file->getName(), PATHINFO_EXTENSION);
	}


	/**
	 * Převede lomítka v zadané cestě dle platných lomítek na daném prostředí
	 *
	 * @param $path
	 * @return mixed
	 */
	private function getEnvironmentPath($path)
	{
		return str_replace(["/", "\\"], DIRECTORY_SEPARATOR, $path);
	}


	/**
	 * Převede lomítka v zadané cestě dle lomítek použitelných v HTML dokumentu
	 * Taktéž ošetří případný výskyt dvou lomítek zasebou
	 *
	 * @param $path
	 * @return mixed
	 */
	private function getHtmlPath($path)
	{
		return str_replace(["\\\\", "\\", "//"], "/", $path);
	}

}
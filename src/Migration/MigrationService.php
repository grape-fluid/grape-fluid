<?php

namespace Grapesc\GrapeFluid;

use Grapesc\GrapeFluid\Model\MigrationModel;
use Nette\Utils\DateTime;
use Nette\Utils\Finder;
use RuntimeException;
use Symfony\Component\Console\Output\OutputInterface;


/**
 * @author Mira Jakes <jakes@grapesc.cz>
 */
class MigrationService
{
	
	/** @var MigrationModel */
	protected $migrationModel;

	/** @var ModuleRepository */
	protected $moduleRepository;

	/** @var BaseParametersRepository */
	protected $fluidParameters;

	
	public function __construct(MigrationModel $model, ModuleRepository $moduleRepository, BaseParametersRepository $fluidParameters)
	{
		$this->migrationModel   = $model;
		$this->moduleRepository = $moduleRepository;
		$this->fluidParameters = $fluidParameters;
	}


	/**
	 * @param OutputInterface|null $output
	 * @param bool $reinstall
	 * @return bool
	 */
	public function up(OutputInterface $output = null, $reinstall = false)
	{
		if (!$this->checkConnection($output)) {
			return false;
		}

		$this->checkStructure($output);
		$this->updateMigrations($output, $reinstall);
		
		return true;
	}


	/**
	 * Vyprazdni veskere tabulky v databazi krome tech v parametru $skip
	 *
	 * @param OutputInterface $output
	 * @param array $skip - pole tabulek, ktere nevyprazdnovat
	 */
	public function truncate(OutputInterface $output, $skip = [])
	{
		$context = $this->migrationModel->getContext();
		$tables = $context->getStructure()->getTables();

		$s = $c = 0;
		if (is_array($tables) && !empty($tables)) {
			foreach ($tables as $table) {
				$output->write("<info>Clearing '" . $table['name'] . "' table... </info>");
				if (in_array($table['name'], $skip)) {
					$s++;
					$output->writeln("<info>SKIPPED</info>");
					continue;
				}
				$c++;
				$context->table($table['name'])->delete();
				$output->writeln("<info>DONE</info>");
			}
			$output->writeln("Cleared: $c tables, skipped $s table(s)");
		}
	}


	/**
	 * Vytvori prazdny migracni soubor pro project nebo dle zadaneho nazvu modulu
	 * Nazev prijima ve tvaru <Name>Module nebo 'project'
	 *
	 * @param $name
	 * @return bool
	 */
	public function createMigrationFile($name)
	{
		if (strpos($name, "-") !== false) {
			$newName = "";
			foreach (explode("-", $name) as $part) {
				$newName .= ucfirst(strtolower($part));
			}
			$name = $newName;
		} else {
			$name = ucfirst(strtolower($name));
		}

		$availableModules = $this->moduleRepository->getModules();
		$migrationName = (new DateTime())->format("Y-m-d-Hi") . ".sql";
		$migrationsDirectory = $this->fluidParameters->getParam("appDir") . DIRECTORY_SEPARATOR . "migrations";

		if (array_key_exists($name . "Module", $availableModules)) {
			$moduleClass = $availableModules[$name . "Module"];
			$migrationsDirectory = $moduleClass->getModuleDir() . DIRECTORY_SEPARATOR . "migrations";
		} elseif ($name != "Project") {
			return false;
		}

		if (!file_exists($migrationsDirectory)) {
			mkdir($migrationsDirectory, $this->fluidParameters->getParam("dirPerm"));
		}

		$migrationFile = fopen($migrationsDirectory . DIRECTORY_SEPARATOR . $migrationName, "w");
		fwrite($migrationFile, "# Paste your migration script here");
		fclose($migrationFile);

		return $name;
	}


	/**
	 * @param OutputInterface|null $output
	 * @param bool $reinstall
	 */
	private function updateMigrations(OutputInterface $output = null, $reinstall = false)
	{
		$migratedFiles = $this->getMigratedFiles();

		foreach ($this->moduleRepository->getModules() AS $module) {
			$migrationsDir = $module->getModuleDir() . DIRECTORY_SEPARATOR . "migrations";

			if ($output) {
				$output->writeln("<info>Migration for {$module->getModuleName()}:</info>");
			}

			$migratedFilesCount = 0;

			if (is_dir($migrationsDir)) {
				$files = iterator_to_array(Finder::findFiles(['*.sql', '.php'])->in($migrationsDir));
				usort ($files, function ($a, $b) {
					return strcasecmp($a->getFilename(), $b->getFilename());
				});

				/* @var $file \SplFileInfo */
				foreach ($files AS $file) {

					if (isset($migratedFiles[$module->getModuleName()][$file->getFilename()])) {
						$migratedFile = $migratedFiles[$module->getModuleName()][$file->getFilename()];

						if ($migratedFile['processed'] && !$reinstall) {
							continue;
						}
					}

					if ($output) {
						$output->write("<info>    Execute {$file->getFilename()} migration file ... </info>");
					}

					try {
						$count = $this->importFile($module->getModuleName(), $file);
						if ($output) {
							$output->writeln("<info>OK, $count queries</info>");
						}

					} catch (MigrationException $e) {
						if ($output) {
							$output->writeln("<error>ERROR</error>");
						}

						throw $e;
					}

					$migratedFilesCount++;
				}
			}

			if (!$migratedFilesCount AND $output) {
				$output->writeln("<info>    No migrations for this module.</info>");
			} elseif ($output) {
				$output->writeln("<info>    Migrated $migratedFilesCount file(s) for this module.</info>");
			}
		}

		$projectMigrationsDir = $this->fluidParameters->getParam("appDir") . DIRECTORY_SEPARATOR . "migrations";

		$migratedFilesCount = 0;

		if (is_dir($projectMigrationsDir)) {

			if ($output) {
				$output->writeln("<info>Project migrations:</info>");
			}

			$files = iterator_to_array(Finder::findFiles(['*.sql', '.php'])->in($projectMigrationsDir));
			usort ($files, function ($a, $b) {
				return strcasecmp($a->getFilename(), $b->getFilename());
			});

			foreach ($files as $file) {
				if (isset($migratedFiles["project"][$file->getFilename()])) {
					$migratedFile = $migratedFiles["project"][$file->getFilename()];

					if ($migratedFile['processed'] && !$reinstall) {
						continue;
					}
				}

				if ($output) {
					$output->write("<info>Execute {$file->getFilename()} migration file ...</info>");
				}

				$this->importFile("project", $file);

				if ($output) {
					$output->writeln("<info>OK</info>");
				}

				$migratedFilesCount++;
			}

			if (!$migratedFilesCount AND $output) {
				$output->writeln("<info>    No migrations for this project.</info>");
			} elseif ($output) {
				$output->writeln("<info>Migrated $migratedFilesCount project file(s).</info>");
			}
		} else {
			if ($output) {
				$output->writeln("<info>Project migrations skipped</info>");
			}
		}
	}


	/**
	 * @param string $moduleName
	 * @param \SplFileInfo $file
	 * @return int
	 */
	private function importFile($moduleName, \SplFileInfo $file)
	{
		if (!$migration = $this->migrationModel->getItemBy([$moduleName, $file->getFilename()], "module = ? AND name = ?")) {
			$migration = $this->migrationModel->insert(['module' => $moduleName, 'name' => $file->getFilename(), 'last_update' => new DateTime]);
		}

		$count = 0;
		
		if ($file->getExtension() == 'sql') {
			try {
				$this->migrationModel->getConnection()->getPdo()->beginTransaction();
				$count = $this->executeSqlFormFile($file);
				$this->migrationModel->getConnection()->getPdo()->commit();
			} catch (\Exception $e) {
				$this->migrationModel->getConnection()->getPdo()->rollBack();
				throw new MigrationException($e->getMessage());
			}

		} elseif ($file->getExtension() == 'php') {
			//TODO php migrace - commandy ??
		}

		$this->migrationModel->update(['processed' => 1, 'last_update' => new DateTime], $migration->getPrimary());
		return $count;
	}


	/**
	 * @return array|\Nette\Database\Table\IRow[]|\stdClass
	 */
	private function getMigratedFiles()
	{
		return $this->migrationModel->getAllItems(['module','name']);
	}


	/**
	 * @param OutputInterface|null $output
	 * @return bool
	 */
	private function checkConnection(OutputInterface $output = null)
	{
		if ($output) {
			$output->write("<info>Check db connection ... </info>");
		}

		try {
			$this->migrationModel->checkConnection();
			if ($output) {
				$output->writeln("<info>OK</info>");
			}
			return true;
		} catch (\Exception $e) {
			if ($output) {
				$output->writeln("<error>FALSE ({$e->getMessage()})</error>");
			}
			return false;
		}
	}


	/**
	 * @param OutputInterface|null $output
	 */
	private function checkStructure(OutputInterface $output = null)
	{
		$test = $this->migrationModel->getConnection()->query('SHOW TABLES LIKE ?', $this->migrationModel->getTableName())->fetch();
		if (!$test) {
			if ($output) {
				$output->write("<info>Creating migration table structure ... </info>");
			}

			$this->migrationModel->getConnection()->query(file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'migration.sql'));
			$this->migrationModel->getContext()->getStructure()->rebuild();

			if ($output) {
				$output->writeln("<info>OK</info>");
			}
		}
	}


	/**
	 * @param \SplFileInfo $file
	 * @return int
	 */
	private function executeSqlFormFile(\SplFileInfo $file)
	{
		//Inspired in DIBI
		@set_time_limit(0); // intentionally @

		$handle = @fopen($file->getRealPath(), 'r'); // intentionally @
		if (!$handle) {
			throw new RuntimeException("Cannot open file '$file'.");
		}

		$count     = 0;
		$delimiter = ';';
		$sql       = '';

		$this->migrationModel->getConnection()->getPdo()->query('SET foreign_key_checks = 0;');

		while (!feof($handle)) {
			$s = rtrim(fgets($handle));
			if (substr($s, 0, 10) === 'DELIMITER ') {
				$delimiter = substr($s, 10);
			} elseif (substr($s, -strlen($delimiter)) === $delimiter) {
				$sql .= substr($s, 0, -strlen($delimiter));
				$this->migrationModel->getConnection()->getPdo()->query($sql);
				$sql = '';
				$count++;
			} else {
				$sql .= $s . "\n";
			}
		}
		if (trim($sql) !== '') {
			$this->migrationModel->getConnection()->getPdo()->query($sql);
			$count++;
		}
		fclose($handle);

		$this->migrationModel->getConnection()->getPdo()->query('SET foreign_key_checks = 1;');

		return $count;
	}

}

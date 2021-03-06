<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2013 Xavier Perseguers <xavier@causal.ch>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

namespace Causal\Sphinx\Utility;

/**
 * Sphinx environment setup.
 *
 * @category    Utility
 * @package     TYPO3
 * @subpackage  tx_sphinx
 * @author      Xavier Perseguers <xavier@causal.ch>
 * @copyright   Causal Sàrl
 * @license     http://www.gnu.org/copyleft/gpl.html
 */

class Setup {

	/** @var string */
	protected static $extKey = 'sphinx';

	/** @var array */
	protected static $log = array();

	/**
	 * Initializes the environment by creating directories to hold sphinx and 3rd
	 * party tools.
	 *
	 * @return array Error messages, if any
	 */
	public static function createLibraryDirectories() {
		$errors = array();

		if (!\TYPO3\CMS\Core\Utility\CommandUtility::checkCommand('python')) {
			$errors[] = 'Python interpreter was not found.';
		}
		if (!\TYPO3\CMS\Core\Utility\CommandUtility::checkCommand('unzip')) {
			$errors[] = 'Unzip cannot be executed.';
		}

		$directories = array(
			'Resources/Private/sphinx/',
			'Resources/Private/sphinx/bin/',
			'Resources/Private/sphinx-sources/',
		);
		$basePath = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath(self::$extKey);
		foreach ($directories as $directory) {
			if (!is_dir($basePath . $directory)) {
				\TYPO3\CMS\Core\Utility\GeneralUtility::mkdir_deep($basePath, $directory);
			}
			if (is_dir($basePath . $directory)) {
				if (!is_writable($basePath . $directory)) {
					$errors[] = 'Directory ' . $basePath . $directory . ' is read-only.';
				}
			} else {
				$errors[] = 'Cannot create directory ' . $basePath . $directory . '.';
			}
		}

		return $errors;
	}

	/**
	 * Returns TRUE if the source files of Sphinx are available locally.
	 *
	 * @param string $version Version name (e.g., 1.0.0)
	 * @return boolean
	 */
	public static function hasSphinxSources($version) {
		$sphinxSourcesPath = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath(self::$extKey) . 'Resources/Private/sphinx-sources/';
		$setupFile = $sphinxSourcesPath . $version . '/setup.py';
		return is_file($setupFile);
	}

	/**
	 * Downloads the source files of Sphinx.
	 *
	 * @param string $version Version name (e.g., 1.0.0)
	 * @param string $url Complete URL of the zip file containing the sphinx sources
	 * @param NULL|array $output
	 * @return boolean TRUE if operation succeeded, otherwise FALSE
	 * @throws \Exception
	 * @see https://bitbucket.org/birkenfeld/sphinx/
	 */
	public static function downloadSphinxSources($version, $url, array &$output = NULL) {
		$success = TRUE;
		$tempPath = PATH_site . '/typo3temp/';
		$sphinxSourcesPath = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath(self::$extKey) . 'Resources/Private/sphinx-sources/';

		$zipFilename = $tempPath . $version . '.zip';
		self::$log[] = '[INFO] Fetching ' . $url;
		$zipContent = \TYPO3\CMS\Core\Utility\GeneralUtility::getUrl($url);
		if ($zipContent && \TYPO3\CMS\Core\Utility\GeneralUtility::writeFile($zipFilename, $zipContent)) {
			$output[] = '[INFO] Sphinx ' . $version . ' has been downloaded.';
			$targetPath = $sphinxSourcesPath . $version;
			self::$log[] = '[INFO] Recreating directory ' . $targetPath;
			\TYPO3\CMS\Core\Utility\GeneralUtility::rmdir($targetPath, TRUE);
			\TYPO3\CMS\Core\Utility\GeneralUtility::mkdir($targetPath);

			// Unzip the Sphinx archive
			$unzip = \TYPO3\CMS\Core\Utility\CommandUtility::getCommand('unzip');
			$cmd = $unzip . ' ' . escapeshellarg($zipFilename) . ' -d ' . escapeshellarg($targetPath) . ' 2>&1';
			self::exec($cmd, $_, $ret);
			if ($ret === 0) {
				$output[] = '[INFO] Sphinx ' . $version . ' has been unpacked.';
				// When unzipping the sources, content is located under a directory "birkenfeld-sphinx-<hash>"
				$directories = \TYPO3\CMS\Core\Utility\GeneralUtility::get_dirs($targetPath);
				if (\TYPO3\CMS\Core\Utility\GeneralUtility::isFirstPartOfStr($directories[0], 'birkenfeld-sphinx-')) {
					$fromDirectory = escapeshellarg($targetPath . '/' . $directories[0]);
					$cmd = 'mv ' . $fromDirectory . '/* ' . escapeshellarg($targetPath . '/') . ' 2>&1';
					self::exec($cmd);
					\TYPO3\CMS\Core\Utility\GeneralUtility::rmdir($targetPath . '/' . $directories[0], TRUE);

					// Remove zip file as we don't need it anymore
					@unlink($zipFilename);

					// Patch Sphinx to let us get colored output
					$sourceFilename = $targetPath . '/sphinx/util/console.py';
					if (file_exists($sourceFilename)) {
						self::$log[] = '[INFO] Patching file ' . $sourceFilename;
						$contents = file_get_contents($sourceFilename);
						$contents = str_replace(
							'def color_terminal():',
							"def color_terminal():\n    if 'COLORTERM' in os.environ:\n        return True",
							$contents
						);
						\TYPO3\CMS\Core\Utility\GeneralUtility::writeFile($sourceFilename, $contents);
					}
				} else {
					$success = FALSE;
					$output[] = '[ERROR] Unknown structure in archive ' . $zipFilename;
				}
			} else {
				$success = FALSE;
				$output[] = '[ERROR] Could not extract Sphinx ' . $version . ':' . LF . $cmd;
			}
		} else {
			$success = FALSE;
			$output[] = '[ERROR] Cannot fetch file ' . $url . '.';
		}

		return $success;
	}

	/**
	 * Builds and installs Sphinx locally.
	 *
	 * @param string $version Version name (e.g., 1.0.0)
	 * @param NULL|array $output
	 * @return boolean TRUE if operation succeeded, otherwise FALSE
	 * @throws \Exception
	 */
	public static function buildSphinx($version, array &$output = NULL) {
		$success = TRUE;
		$sphinxSourcesPath = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath(self::$extKey) . 'Resources/Private/sphinx-sources/';
		$sphinxPath = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath(self::$extKey) . 'Resources/Private/sphinx/';

		$pythonHome = NULL;
		$pythonLib = NULL;
		$setupFile = $sphinxSourcesPath . $version . '/setup.py';

		if (is_file($setupFile)) {
			$python = \TYPO3\CMS\Core\Utility\CommandUtility::getCommand('python');
			$cmd = 'cd ' . escapeshellarg(dirname($setupFile)) . ' && ' .
				$python . ' setup.py clean 2>&1 && ' .
				$python . ' setup.py build 2>&1';
			$out = array();
			self::exec($cmd, $out, $ret);
			if ($ret === 0) {
				$pythonHome = $sphinxPath . $version;
				$pythonLib = $pythonHome . '/lib/python';
				self::$log[] = '[INFO] Recreating directory ' . $pythonHome;
				\TYPO3\CMS\Core\Utility\GeneralUtility::rmdir($pythonHome, TRUE);
				\TYPO3\CMS\Core\Utility\GeneralUtility::mkdir_deep($pythonLib . '/');

				$cmd = 'cd ' . escapeshellarg(dirname($setupFile)) . ' && ' .
					'export PYTHONPATH=' . escapeshellarg($pythonLib) . ' && ' .
					$python . ' setup.py install --home=' . escapeshellarg($pythonHome) . ' 2>&1';
				$out = array();
				self::exec($cmd, $out, $ret);
				if ($ret === 0) {
					$output[] = '[INFO] Sphinx ' . $version . ' has been successfully installed.';
				} else {
					$success = FALSE;
					$output[] = '[ERROR] Could not install Sphinx ' . $version . ':' . LF . LF . implode($out, LF);
				}
			} else {
				$success = FALSE;
				$output[] = '[ERROR] Could not build Sphinx ' . $version . ':' . LF . LF . implode($out, LF);
			}
		} else {
			$success = FALSE;
			$output[] = '[ERROR] Setup file ' . $setupFile . ' was not found.';
		}

		if ($success) {
			$shortcutScripts = array(
				'sphinx-build',
				'sphinx-quickstart',
			);
			$pythonPath = escapeshellarg($sphinxPath . $version . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'python');
			foreach ($shortcutScripts as $shortcutScript) {
				$shortcutFilename = $sphinxPath . 'bin' . DIRECTORY_SEPARATOR . $shortcutScript . '-' . $version;
				$scriptFilename = $sphinxPath . $version . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . $shortcutScript;

				$script = <<<EOT
#!/bin/bash

export PYTHONPATH=$pythonPath

$scriptFilename "\$@"
EOT;

				\TYPO3\CMS\Core\Utility\GeneralUtility::writeFile($shortcutFilename, $script);
				chmod($shortcutFilename, 0755);
			}
		}

		return $success;
	}

	/**
	 * Removes a local version of Sphinx (sources + build).
	 *
	 * @param string $version
	 * @param NULL|array $output
	 * @return void
	 */
	public static function removeSphinx($version, array &$output = NULL) {
		$sphinxSourcesPath = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath(self::$extKey) . 'Resources/Private/sphinx-sources/';
		$sphinxPath = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath(self::$extKey) . 'Resources/Private/sphinx/';

		if (is_dir($sphinxSourcesPath . $version)) {
			if (\TYPO3\CMS\Core\Utility\GeneralUtility::rmdir($sphinxSourcesPath . $version, TRUE)) {
				$output[] = '[INFO] Sources of Sphinx ' . $version . ' have been deleted.';
			} else {
				$output[] = '[ERROR] Could not delete sources of Sphinx ' . $version . '.';
			}
		}
		if (is_dir($sphinxPath . $version)) {
			if (\TYPO3\CMS\Core\Utility\GeneralUtility::rmdir($sphinxPath . $version, TRUE)) {
				$output[] = '[INFO] Sphinx ' . $version . ' has been deleted.';
			} else {
				$output[] = '[ERROR] Could not delete Sphinx ' . $version . '.';
			}
		}

		$shortcutScripts = array(
			'sphinx-build-' . $version,
			'sphinx-quickstart-' . $version,
		);
		foreach ($shortcutScripts as $shortcutScript) {
			$shortcutFilename = $sphinxPath . 'bin' . DIRECTORY_SEPARATOR . $shortcutScript;
			if (is_file($shortcutFilename)) {
				@unlink($shortcutFilename);
			}
		}
	}

	/**
	 * Returns TRUE if the source files of the TYPO3 ReST tools are available locally.
	 *
	 * @return boolean
	 */
	public static function hasRestTools() {
		$sphinxSourcesPath = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath(self::$extKey) . 'Resources/Private/sphinx-sources/';
		$setupFile = $sphinxSourcesPath . 'RestTools/ExtendingSphinxForTYPO3/setup.py';
		return is_file($setupFile);
	}

	/**
	 * Downloads the source files of the TYPO3 ReST tools.
	 *
	 * @param NULL|array $output
	 * @return boolean TRUE if operation succeeded, otherwise FALSE
	 * @throws \Exception
	 * @see http://forge.typo3.org/projects/tools-rest
	 */
	public static function downloadRestTools(array &$output = NULL) {
		$success = TRUE;
		$tempPath = PATH_site . '/typo3temp/';
		$sphinxSourcesPath = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath(self::$extKey) . 'Resources/Private/sphinx-sources/';

		if (!\TYPO3\CMS\Core\Utility\CommandUtility::checkCommand('tar')) {
			$success = FALSE;
			$output[] = '[WARNING] Could not find command tar. TYPO3-related commands were not installed.';
		} else {
			$url = 'https://git.typo3.org/Documentation/RestTools.git/tree/HEAD:/ExtendingSphinxForTYPO3';
			/** @var $http \TYPO3\CMS\Core\Http\HttpRequest */
			$http = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
				'\\TYPO3\\CMS\\Core\\Http\HttpRequest',
				$url
			);
			self::$log[] = '[INFO] Fetching ' . $url;
			$body = $http->send()->getBody();
			if (preg_match('#<a .*?href="/Documentation/RestTools\.git/snapshot/([0-9a-f]+)\.tar\.gz">snapshot</a>#', $body, $matches)) {
				$commit = $matches[1];
				$url = 'https://git.typo3.org/Documentation/RestTools.git/snapshot/' . $commit . '.tar.gz';
				$archiveFilename = $tempPath . 'RestTools.tar.gz';
				self::$log[] = '[INFO] Fetching ' . $url;
				$archiveContent = $http->setUrl($url)->send()->getBody();
				if ($archiveContent && \TYPO3\CMS\Core\Utility\GeneralUtility::writeFile($archiveFilename, $archiveContent)) {
					$output[] = '[INFO] TYPO3 ReStructuredText Tools (' . $commit . ') have been downloaded.';

					// Target path is compatible with directory structure of complete git project
					// allowing people to use the official git repository instead, if wanted
					$targetPath = $sphinxSourcesPath . 'RestTools/ExtendingSphinxForTYPO3';
					self::$log[] = '[INFO] Recreating directory ' . $targetPath;
					\TYPO3\CMS\Core\Utility\GeneralUtility::rmdir($targetPath, TRUE);
					\TYPO3\CMS\Core\Utility\GeneralUtility::mkdir_deep($targetPath . '/');

					// Unpack TYPO3 ReST Tools archive
					$tar = \TYPO3\CMS\Core\Utility\CommandUtility::getCommand('tar');
					$cmd = $tar . ' xzvf ' . escapeshellarg($archiveFilename) . ' -C ' . escapeshellarg($targetPath) . ' 2>&1';
					$out = array();
					self::exec($cmd, $out, $ret);
					if ($ret === 0) {
						$output[] = '[INFO] TYPO3 ReStructuredText Tools have been unpacked.';
						// When unpacking the sources, content is located under a directory "RestTools-<shortcommit>"
						$directories = \TYPO3\CMS\Core\Utility\GeneralUtility::get_dirs($targetPath);
						if ($directories[0] === 'RestTools-' . substr($commit, 0, 7)) {
							$fromDirectory = escapeshellarg($targetPath . '/' . $directories[0]);
							$cmd = 'mv ' . $fromDirectory . '/* ' . escapeshellarg($targetPath . '/') . ' 2>&1';
							self::exec($cmd);
							\TYPO3\CMS\Core\Utility\GeneralUtility::rmdir($targetPath . '/' . $directories[0], TRUE);

							// Remove tar.gz archive as we don't need it anymore
							@unlink($archiveFilename);
						}
					} else {
						$success = FALSE;
						$output[] = '[ERROR] Could not extract TYPO3 ReStructuredText Tools:' . LF . LF . implode($out, LF);
					}
				} else {
					$success = FALSE;
					$output[] = '[ERROR] Could not download ' . htmlspecialchars($url);
				}
			} else {
				$success = FALSE;
				$output[] = '[ERROR] Could not download ' . htmlspecialchars('https://git.typo3.org/Documentation/RestTools.git/tree/HEAD:/ExtendingSphinxForTYPO3');
			}
		}

		return $success;
	}

	/**
	 * Builds and installs TYPO3 ReST tools locally.
	 *
	 * @param string $sphinxVersion The Sphinx version to build the ReST tools for
	 * @param NULL|array $output
	 * @return boolean TRUE if operation succeeded, otherwise FALSE
	 * @throws \Exception
	 */
	public static function buildRestTools($sphinxVersion, array &$output = NULL) {
		$success = TRUE;
		$sphinxSourcesPath = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath(self::$extKey) . 'Resources/Private/sphinx-sources/';
		$sphinxPath = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath(self::$extKey) . 'Resources/Private/sphinx/';

		$pythonHome = $sphinxPath . $sphinxVersion;
		$pythonLib = $pythonHome . '/lib/python';

		if (!is_dir($pythonLib)) {
			$success = FALSE;
			$output[] = '[ERROR] Invalid Python library: ' . $pythonLib;
			return $success;
		}

		$setupFile = $sphinxSourcesPath . 'RestTools/ExtendingSphinxForTYPO3/setup.py';
		if (is_file($setupFile)) {
			$python = \TYPO3\CMS\Core\Utility\CommandUtility::getCommand('python');
			$cmd = 'cd ' . escapeshellarg(dirname($setupFile)) . ' && ' .
				$python . ' setup.py clean 2>&1 && ' .
				$python . ' setup.py build 2>&1';
			$out = array();
			self::exec($cmd, $out, $ret);
			if ($ret === 0) {
				$cmd = 'cd ' . escapeshellarg(dirname($setupFile)) . ' && ' .
					'export PYTHONPATH=' . escapeshellarg($pythonLib) . ' && ' .
					$python . ' setup.py install --home=' . escapeshellarg($pythonHome) . ' 2>&1';
				$out = array();
				self::exec($cmd, $out, $ret);
				if ($ret === 0) {
					$output[] = '[INFO] TYPO3 RestructuredText Tools have been successfully installed.';
				} else {
					$success = FALSE;
					$output[] = '[ERROR] Could not install TYPO3 RestructuredText Tools:' . LF . LF . implode($out, LF);
				}
			} else {
				$success = FALSE;
				$output[] = '[ERROR] Could not build TYPO3 RestructuredText Tools:' . LF . LF . implode($out, LF);
			}
		} else {
			$success = FALSE;
			$output[] = '[ERROR] Setup file ' . $setupFile . ' was not found.';
		}

		return $success;
	}

	/**
	 * Returns TRUE if the source files of PyYAML are available locally.
	 *
	 * @return boolean
	 */
	public static function hasPyYaml() {
		$sphinxSourcesPath = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath(self::$extKey) . 'Resources/Private/sphinx-sources/';
		$setupFile = $sphinxSourcesPath . 'PyYAML/setup.py';
		return is_file($setupFile);
	}

	/**
	 * Downloads the source files of PyYAML.
	 *
	 * @param NULL|array $output
	 * @return boolean TRUE if operation succeeded, otherwise FALSE
	 * @throws \Exception
	 * @see http://pyyaml.org/
	 */
	public static function downloadPyYaml(array &$output = NULL) {
		$success = TRUE;
		$tempPath = PATH_site . '/typo3temp/';
		$sphinxSourcesPath = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath(self::$extKey) . 'Resources/Private/sphinx-sources/';

		if (!\TYPO3\CMS\Core\Utility\CommandUtility::checkCommand('tar')) {
			$success = FALSE;
			$output[] = '[WARNING] Could not find command tar. PyYAML was not installed.';
		} else {
			$url = 'http://pyyaml.org/download/pyyaml/PyYAML-3.10.tar.gz';
			$archiveFilename = $tempPath . 'PyYAML-3.10.tar.gz';
			$archiveContent = \TYPO3\CMS\Core\Utility\GeneralUtility::getUrl($url);
			if ($archiveContent && \TYPO3\CMS\Core\Utility\GeneralUtility::writeFile($archiveFilename, $archiveContent)) {
				$output[] = '[INFO] PyYAML 3.10 has been downloaded.';

				$targetPath = $sphinxSourcesPath . 'PyYAML';
				self::$log[] = '[INFO] Recreating directory ' . $targetPath;
				\TYPO3\CMS\Core\Utility\GeneralUtility::rmdir($targetPath, TRUE);
				\TYPO3\CMS\Core\Utility\GeneralUtility::mkdir($targetPath);

				// Unpack PyYAML archive
				$tar = \TYPO3\CMS\Core\Utility\CommandUtility::getCommand('tar');
				$cmd = $tar . ' xzvf ' . escapeshellarg($archiveFilename) . ' -C ' . escapeshellarg($targetPath) . ' 2>&1';
				$out = array();
				self::exec($cmd, $out, $ret);
				if ($ret === 0) {
					$output[] = '[INFO] PyYAML has been unpacked.';
					// When unpacking the sources, content is located under a directory "PyYAML-3.10"
					$directories = \TYPO3\CMS\Core\Utility\GeneralUtility::get_dirs($targetPath);
					if ($directories[0] === 'PyYAML-3.10') {
						$fromDirectory = escapeshellarg($targetPath . '/' . $directories[0]);
						$cmd = 'mv ' . $fromDirectory . '/* ' . escapeshellarg($targetPath . '/');
						self::exec($cmd);
						\TYPO3\CMS\Core\Utility\GeneralUtility::rmdir($targetPath . '/' . $directories[0], TRUE);

						// Remove tar.gz archive as we don't need it anymore
						@unlink($archiveFilename);
					} else {
						$success = FALSE;
						$output[] = '[ERROR] Unknown structure in archive ' . $archiveFilename;
					}
				} else {
					$success = FALSE;
					$output[] = '[ERROR] Could not extract TYPO3 ReStructuredText Tools:' . LF . LF . implode($out, LF);
				}
			} else {
				$success = FALSE;
				$output[] = '[ERROR] Could not download ' . htmlspecialchars($url);
			}
		}

		return $success;
	}

	/**
	 * Builds and installs PyYAML locally.
	 *
	 * @param string $sphinxVersion The Sphinx version to build the ReST tools for
	 * @param NULL|array $output
	 * @return boolean TRUE if operation succeeded, otherwise FALSE
	 * @throws \Exception
	 */
	public static function buildPyYaml($sphinxVersion, array &$output = NULL) {
		$success = TRUE;
		$sphinxSourcesPath = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath(self::$extKey) . 'Resources/Private/sphinx-sources/';
		$sphinxPath = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath(self::$extKey) . 'Resources/Private/sphinx/';

		$pythonHome = $sphinxPath . $sphinxVersion;
		$pythonLib = $pythonHome . '/lib/python';

		if (!is_dir($pythonLib)) {
			$success = FALSE;
			$output[] = '[ERROR] Invalid Python library: ' . $pythonLib;
			return $success;
		}

		$setupFile = $sphinxSourcesPath . 'PyYAML/setup.py';
		if (is_file($setupFile)) {
			$python = \TYPO3\CMS\Core\Utility\CommandUtility::getCommand('python');
			$cmd = 'cd ' . escapeshellarg(dirname($setupFile)) . ' && ' .
				$python . ' setup.py clean 2>&1 && ' .
				$python . ' setup.py build 2>&1';
			$out = array();
			self::exec($cmd, $out, $ret);
			if ($ret === 0) {
				$cmd = 'cd ' . escapeshellarg(dirname($setupFile)) . ' && ' .
					'export PYTHONPATH=' . escapeshellarg($pythonLib) . ' && ' .
					$python . ' setup.py install --home=' . escapeshellarg($pythonHome) . ' 2>&1';
				$out = array();
				self::exec($cmd, $out, $ret);
				if ($ret === 0) {
					$output[] = '[INFO] PyYAML has been successfully installed.';
				} else {
					$success = FALSE;
					$output[] = '[ERROR] Could not install PyYAML:' . LF . LF . implode($out, LF);
				}
			} else {
				$success = FALSE;
				$output[] = '[ERROR] Could not build PyYAML:' . LF . LF . implode($out, LF);
			}
		} else {
			$success = FALSE;
			$output[] = '[ERROR] Setup file ' . $setupFile . ' was not found.';
		}

		return $success;
	}

	/**
	 * Returns a list of online available versions of Sphinx.
	 * Please note: all versions older than 1.0 are automatically discarded
	 * as they are most probably of absolutely no use.
	 *
	 * @return array
	 */
	public static function getSphinxAvailableVersions() {
		$sphinxUrl = 'https://bitbucket.org/birkenfeld/sphinx/downloads';

		$cacheFilename = PATH_site . 'typo3temp/' . self::$extKey . '.' . md5($sphinxUrl) . '.html';
		if (!file_exists($cacheFilename) || filemtime($cacheFilename) < (time() - 86400)) {
			$html = \TYPO3\CMS\Core\Utility\GeneralUtility::getURL($sphinxUrl);
			\TYPO3\CMS\Core\Utility\GeneralUtility::writeFile($cacheFilename, $html);
		} else {
			$html = file_get_contents($cacheFilename);
		}

		$tagsHtml = substr($html, strpos($html, '<section class="tabs-pane" id="tag-downloads">'));
		$tagsHtml = substr($tagsHtml, 0, strpos($tagsHtml, '</section>'));

		$versions = array();
		preg_replace_callback(
			'#<tr class="iterable-item">.*?<td class="name"><a href="[^>]+>([^<]*)</a></td>.*?<a href="([^"]+)">zip</a>#s',
			function($matches) use (&$versions) {
				if ($matches[1] !== 'tip' && version_compare($matches[1], '1.0', '>=')) {
					$versions[$matches[1]] = array(
						'name' => $matches[1],
						'url'  => $matches[2],
					);
				}
			},
			$tagsHtml
		);

		krsort($versions);
		return $versions;
	}

	/**
	 * Returns a list of locally available versions of Sphinx.
	 *
	 * @return array
	 */
	public static function getSphinxLocalVersions() {
		$sphinxPath = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath(self::$extKey) . 'Resources/Private/sphinx';
		$versions = array();
		if (is_dir($sphinxPath)) {
			$versions = \TYPO3\CMS\Core\Utility\GeneralUtility::get_dirs($sphinxPath);
		}
		return $versions;
	}

	/**
	 * Logs and executes a command.
	 *
	 * @param string $cmd
	 * @param NULL|array $output
	 * @param integer $returnValue
	 * @return NULL|array
	 */
	protected static function exec($cmd, &$output = NULL, &$returnValue = 0) {
		self::$log[] = '[CMD] ' . $cmd;
		$lastLine = \TYPO3\CMS\Core\Utility\CommandUtility::exec($cmd, $out, $returnValue);
		self::$log = array_merge(self::$log, $out);
		$output = $out;
		return $lastLine;
	}

	/**
	 * Clears the log of operations.
	 *
	 * @return void
	 */
	public static function clearLog() {
		self::$log = array();
	}

	/**
	 * Dumps the log of operations.
	 *
	 * @param string $filename If empty, will return the complete log of operations instead of writing it to a file
	 * @return void|string
	 */
	public static function dumpLog($filename = '') {
		$content = implode(LF, self::$log);
		if ($filename) {
			$directory = dirname($filename);
			\TYPO3\CMS\Core\Utility\GeneralUtility::mkdir($directory);
			\TYPO3\CMS\Core\Utility\GeneralUtility::writeFile($filename, $content);
		} else {
			return $content;
		}
	}

}

?>
<?php

namespace WapplerSystems\Templatemaker\Controller;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use TYPO3\CMS\Backend\Utility\BackendUtility as BackendUtilityCore;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\FormProtection\FormProtectionFactory;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Mvc\Exception;
use TYPO3\CMS\Extensionmanager\Utility\InstallUtility;


class ManagerController extends ActionController
{


    /**
     * Page uid
     *
     * @var int
     */
    protected $pageUid = 0;


    /**
     * TsConfig configuration
     *
     * @var array
     */
    protected $tsConfiguration = [];


    /**
     * Function will be called before every other action
     *
     * @return void
     */
    public function initializeAction()
    {
        $this->pageUid = (int)GeneralUtility::_GET('id');
        $this->setTsConfig();
        parent::initializeAction();
    }


    public function introAction()
    {

        $assignedValues = [
            'moduleToken' => $this->getToken(true),
            'page' => $this->pageUid,
            'isComposerMode' => Environment::isComposerMode(),
        ];
        $this->view->assignMultiple($assignedValues);
    }


    /**
     */
    public function renameAction()
    {

        try {

            /** @var InstallUtility $service */
            $service = GeneralUtility::makeInstance(InstallUtility::class);

            $title = $this->request->getArgument('title');

            $extKey = trim($this->request->getArgument('key'));

            /* not longer used */
            $camelCase = GeneralUtility::underscoredToUpperCamelCase($extKey);

            $varName = str_replace('_', '', $extKey);

            if (version_compare(TYPO3_version, '11.0.0', '>=')){
                if ($service->isAvailable($extKey)) {
                    throw new Exception('Die Extension ' . $extKey . ' existiert bereits und ist geladen.');
                }
            } else {
                if ($service->isLoaded($extKey)) {
                    throw new Exception('Die Extension ' . $extKey . ' existiert bereits und ist geladen.');
                }
            }

            $extPath = Environment::getPublicPath() . '/typo3conf/ext/' . $extKey . '/';

            GeneralUtility::rmdir($extPath);

            if (!is_dir($extPath)) {
                if (!GeneralUtility::mkdir($extPath)) {
                    throw new Exception('Das Verzeichnis ' . $extPath . ' konnte nicht erstellt werden. Eventuell fehlende Berechtigung?');
                }
                GeneralUtility::copyDirectory(Environment::getPublicPath() . '/typo3conf/ext/demotemplate/', $extPath);
                if (is_dir($extPath.'.git/')) {
                    GeneralUtility::rmdir($extPath . '.git/',true);
                }
            }

            $files = GeneralUtility::getAllFilesAndFoldersInPath([], $extPath);

            foreach ($files as $file) {

                $str = file_get_contents($file);
                $str = str_replace(['tx_demotemplate', "'title' => 'Example Theme',", 'Demo Template'], ['tx_' . $varName, "'title' => '" . $title . "',", $title], $str);

                $str = str_replace(['demotemplate'], [$extKey], $str);

                file_put_contents($file, $str);

                if (strpos($file, 'demotemplate') !== false) {
                    rename($file, str_replace('demotemplate', $varName, $file));
                }
            }


            /* In Datenbank ersetzen ? */
            $changePageLayouts = (int)$this->request->getArgument('pagelayouts');

            /** @var ConnectionPool $connectionPool */
            $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);

            if ($changePageLayouts === 1) {

                /* include_static_file */
                $queryBuilder = $connectionPool->getQueryBuilderForTable('sys_template');
                $statement = $queryBuilder->select('uid', 'include_static_file')
                    ->from('sys_template')
                    ->where(
                        $queryBuilder->expr()->like('include_static_file', $queryBuilder->createNamedParameter('%demotemplate%', \PDO::PARAM_STR))
                    )->execute();

                $connection = $connectionPool->getConnectionForTable('sys_template');
                while ($row = $statement->fetch()) {
                    $connection->update(
                        'sys_template',
                        [
                            'include_static_file' => str_replace('demotemplate', $extKey,
                                $row['include_static_file'])
                        ],
                        ['uid' => (int)$row['uid']]
                    );
                }

                /* tsconfig_includes */
                $queryBuilder = $connectionPool->getQueryBuilderForTable('pages');
                $statement = $queryBuilder->select('uid', 'tsconfig_includes')
                    ->from('pages')
                    ->where(
                        $queryBuilder->expr()->like('tsconfig_includes', $queryBuilder->createNamedParameter('%demotemplate%', \PDO::PARAM_STR))
                    )->execute();

                $connection = $connectionPool->getConnectionForTable('pages');
                while ($row = $statement->fetch()) {
                    $connection->update(
                        'pages',
                        [
                            'tsconfig_includes' => str_replace('demotemplate', $extKey,
                                $row['tsconfig_includes'])
                        ],
                        ['uid' => (int)$row['uid']]
                    );
                }
            }

            $message = 'Die Extension ' . $extKey . ' wurde erfolgreich angelegt. Bitte installieren Sie diese.';

        } catch (\Exception $ex) {
            $message = $ex->getMessage();
        }

        $assignedValues = [
            'message' => $message,
        ];
        $this->view->assignMultiple($assignedValues);
    }


    /**
     * Set the TsConfig configuration for the extension
     *
     * @return void
     */
    protected function setTsConfig()
    {
        $tsConfig = BackendUtilityCore::getPagesTSconfig($this->pageUid);
        if (isset($tsConfig['tx_templatemaker.']['module.']) && is_array($tsConfig['tx_templatemaker.']['module.'])) {
            $this->tsConfiguration = $tsConfig['tx_templatemaker.']['module.'];
        }
    }


    /**
     * Get a CSRF token
     *
     * @param bool $tokenOnly Set it to TRUE to get only the token, otherwise including the &moduleToken= as prefix
     * @return string
     * @throws \InvalidArgumentException
     */
    protected function getToken($tokenOnly = false)
    {
        $token = FormProtectionFactory::get()->generateToken('moduleCall', 'tools_TemplatemakerTxTemplatemakerM1');
        if ($tokenOnly) {
            return $token;
        }
        return '&moduleToken=' . $token;
    }


}

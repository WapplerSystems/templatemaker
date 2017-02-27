<?php
/**
 * Created by PhpStorm.
 * User: svewap
 * Date: 19.02.16
 * Time: 21:32
 */

namespace WapplerSystems\Templatemaker\Controller;


use TYPO3\CMS\Backend\Utility\BackendUtility as BackendUtilityCore;
use TYPO3\CMS\Core\FormProtection\FormProtectionFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Exception;
use TYPO3\CMS\Extensionmanager\Utility\InstallUtility;


class ManagerController extends \TYPO3\CMS\Extbase\Mvc\Controller\ActionController
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



    public function introAction() {


        $assignedValues = [
            'moduleToken' => $this->getToken(true),
            'page' => $this->pageUid,
        ];
        $this->view->assignMultiple($assignedValues);
    }


    /**
     */
    public function renameAction() {



        try {

            /** @var InstallUtility $service */
            $service = $this->objectManager->get(InstallUtility::class);

            $title = $this->request->getArgument('title');

            $extKey = trim($this->request->getArgument('key'));

            $camelCase = GeneralUtility::underscoredToUpperCamelCase($extKey);

            $varName = str_replace('_','',$extKey);


            if ($service->isLoaded($extKey)) {
                throw new Exception('Die Extension ' . $extKey . ' existiert bereits und ist geladen.');
            }

            $extPath = PATH_site . 'typo3conf/ext/' . $extKey . '/';

            GeneralUtility::rmdir($extPath);

            if (!is_dir($extPath)) {
                GeneralUtility::mkdir($extPath);
                GeneralUtility::copyDirectory(PATH_site . 'typo3conf/ext/demotemplate/', $extPath);
            }

            if (!is_dir($extPath)) {
                throw new Exception('Verzeichnis ' . $extPath . ' konnte nicht erstellt werden.');
            }

            $files = GeneralUtility::getAllFilesAndFoldersInPath([], $extPath);

            foreach ($files as $file) {

                $str = file_get_contents($file);
                $str = str_replace(['tx_demotemplate',"'title' => 'Example Theme'," , 'Demo Template'], ['tx_'.$varName, "'title' => '".$title."'," , $title], $str);

                $str = str_replace(['demotemplate'], [$extKey], $str);

                file_put_contents($file, $str);

                if (strpos($file, 'demotemplate') !== false) {
                    rename($file, str_replace('demotemplate', $varName, $file));
                }
            }

            /* neue Ext installieren */
            $service->install($extKey);

            /* In Datenbank ersetzen */
            $changePageLayouts = (int)$this->request->getArgument('pagelayouts');

            if ($changePageLayouts === 1) {
                $rows = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
                    'uid,tx_fed_page_controller_action,tx_fed_page_controller_action_sub',
                    'pages',
                    'tx_fed_page_controller_action LIKE "%Demotemplate%" OR tx_fed_page_controller_action_sub LIKE "%Demotemplate%"'
                );

                foreach ($rows as $row) {
                    $GLOBALS['TYPO3_DB']->exec_UPDATEquery(
                        'pages',
                        'uid=' . (int)$row['uid'],
                        [
                            'tx_fed_page_controller_action' => str_replace('Demotemplate', $camelCase,
                                $row['tx_fed_page_controller_action']),
                            'tx_fed_page_controller_action_sub' => str_replace('Demotemplate', $camelCase,
                                $row['tx_fed_page_controller_action_sub'])
                        ]
                    );
                }

                $rows = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
                    'uid,include_static_file',
                    'sys_template',
                    'include_static_file LIKE "%demotemplate%"'
                );

                foreach ($rows as $row) {
                    $GLOBALS['TYPO3_DB']->exec_UPDATEquery(
                        'sys_template',
                        'uid=' . (int)$row['uid'],
                        [
                            'include_static_file' => str_replace('demotemplate', $extKey,
                                $row['include_static_file'])
                        ]
                    );
                }
            }


            $message = 'Die Extension '.$extKey.' wurde erfolgreich angelegt und aktiviert.';

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
        } else {
            return '&moduleToken=' . $token;
        }
    }


}
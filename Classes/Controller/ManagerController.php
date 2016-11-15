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
use TYPO3\CMS\Core\Package\PackageManager;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;


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
        $this->pageUid = (int)\TYPO3\CMS\Core\Utility\GeneralUtility::_GET('id');
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
     * @param string $name
     */
    public function renameAction() {



        try {

            /** @var \TYPO3\CMS\Extensionmanager\Utility\InstallUtility $service */
            $service = $this->objectManager->get(\TYPO3\CMS\Extensionmanager\Utility\InstallUtility::class);

            $title = $this->request->getArgument('title');

            $name = $this->request->getArgument('key');
            $extName = strtolower(str_replace(array("-", " "), array("", ""), trim($name)));

            $varName = str_replace("_","",$extName);


            if ($service->isLoaded($extName)) {
                throw new \Exception("Die Extension " . $extName . " existiert bereits und ist geladen.");
            }

            $extPath = PATH_site . "typo3conf/ext/" . $extName . "/";

            GeneralUtility::rmdir($extPath);

            if (!is_dir($extPath)) {
                GeneralUtility::mkdir($extPath);
                GeneralUtility::copyDirectory(PATH_site . "typo3conf/ext/demotemplate/", $extPath);
            }

            if (!is_dir($extPath)) {
                throw new \Exception("Verzeichnis " . $extPath . " konnte nicht erstellt werden.");
            }

            $files = GeneralUtility::getAllFilesAndFoldersInPath([], $extPath);

            foreach ($files as $file) {

                $str = file_get_contents($file);
                $str = str_replace("demotemplate", $varName, $str);

                $str = str_replace("'title' => 'Example Theme',","'title' => '".$title."',",$str);

                $str = str_replace("Demo Template",$title,$str);

                file_put_contents($file, $str);

                if (strpos($file, "demotemplate") !== false) {
                    rename($file, str_replace("demotemplate", $varName, $file));
                }
            }

            /* neue Ext installieren */
            $service->install($extName);

            /* In Datenbank ersetzen */
            $changePagelayouts = $this->request->getArgument('pagelayouts');

            if ($changePagelayouts == "1") {
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
                            'tx_fed_page_controller_action' => str_replace("Demotemplate", ucfirst($extName),
                                $row['tx_fed_page_controller_action']),
                            'tx_fed_page_controller_action_sub' => str_replace("Demotemplate", ucfirst($extName),
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
                            'include_static_file' => str_replace("demotemplate", $extName,
                                $row['include_static_file'])
                        ]
                    );
                }
            }


            $message = "Die Extension ".$extName." wurde angelegt und aktiviert.";

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
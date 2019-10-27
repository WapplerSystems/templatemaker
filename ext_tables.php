<?php
if (!defined('TYPO3_MODE')) {
	die('Access denied.');
}

if (TYPO3_MODE === 'BE') {

	\TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerModule(
		'WapplerSystems.Templatemaker',
		'tools',
		'templatemaker',
		'',
		[
			'Manager' => 'intro,rename',
			
		],
		[
			'access' => 'admin',
			'icon'   => 'EXT:templatemaker/Resources/Public/Icons/module-templatemaker.svg',
			'labels' => 'LLL:EXT:templatemaker/Resources/Private/Language/locallang_ba1.xlf',
		]
	);

}

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile('templatemaker', 'Configuration/TypoScript', 'Template Maker');

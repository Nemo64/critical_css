<?php

namespace Nemo64\CriticalCss\Hook;


use Sabberworm\CSS\Property\Import;
use Sabberworm\CSS\Value\CSSString;
use Sabberworm\CSS\Value\URL;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

class MoveCssHook
{
    const MARKER_BELOW_THE_FOLD = '<!-- critical_css: below the fold -->';

    /**
     * @var string
     * @internal used to overwrite the PATH_site constant during testing
     */
    public $pathSite = PATH_site ?? '';

    public function postCssTransform(array &$params, PageRenderer $pageRenderer)
    {
        /** @var TypoScriptFrontendController $tsfe */
        $tsfe = $GLOBALS['TSFE'];
        if ($tsfe->no_cache) {
            return;
        }

        // TODO only run when tx_criticalcss_mode is enabled in rootline

        // if the marker for "below the fold" does not exist, than there is nothing we can do here
        $markerPosition = strrpos($pageRenderer->getBodyContent(), self::MARKER_BELOW_THE_FOLD);
        if ($markerPosition === false) {
            return;
        }

        $files = [];
        foreach (array_intersect(['cssLibs', 'cssFiles'], array_keys($params)) as $category) {
            foreach ($params[$category] as $index => $file) {
                if ($file['rel'] !== 'stylesheet') {
                    continue;
                }

                if (file_exists($this->pathSite . $file['file'])) {
                    unset($params[$category][$index]);
                    $files[] = $file;
                }
            }
        }

        $cObject = GeneralUtility::makeInstance(ContentObjectRenderer::class);

        $inlineStyles = "";
        foreach ($files as $file) {
            $filename = GeneralUtility::createVersionNumberedFilename($file['file']);
            $import = new Import(new URL(new CSSString($filename)), $file['media']);
            $inlineStyles .= "\n" . $cObject->wrap("<style>$import</style>", $file['allWrap'], $file['splitChar']);
        }

        $pageRenderer->setBodyContent(substr_replace(
            $pageRenderer->getBodyContent(),
            $inlineStyles,
            $markerPosition,
            $markerPosition + strlen(self::MARKER_BELOW_THE_FOLD)
        ));
    }
}

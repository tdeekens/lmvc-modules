<?php

namespace Scandio\lmvc\modules\assetpipeline\util;

use Scandio\lmvc\modules\assetpipeline\util;

class FileLocator
{
    private
        $_helper,
        $_cacheDirectory,
        $_assetDirectory,
        $_assetDirectoryFallbacks,
        $_cachedFileName,
        $_cachedFilePath,
        $_cachedFileInfo,
        $_requestedFiles = [];

    function __construct($cacheDirectory = "", $assetDirectory = "") {
        $this->_cacheDirectory = $cacheDirectory;
        $this->_assetDirectory = $assetDirectory;

        $this->_helper = new util\AssetPipelineHelper();
    }

    private function _setCachedFilePath() {
        $this->_cachedFilePath = $this->_helper->path([$this->_assetDirectory, $this->_cacheDirectory]);
    }

    private function _getCachedFileName($assets, $options) {
        $fileName = "";

        #Prefix with options e.g: min.00898888222. (dot in in end!)
        $fileName = ( count($options) > 0 ) ? implode(".", $options) . "." : "";

        #Append file names with + as delimiter and remove extensions from all except last file (e.g. [min.929292.]jquery+my-plugin.js
        $fileName .= implode("+", $this->_helper->stripExtensions($assets, true));

        return $fileName;
    }

    private function _recursiveSearch($asset) {
        $fileLocation = false;

        foreach ($this->_assetDirectoryFallbacks as $assetDirectoryFallback) {
            $iterator = new \RecursiveDirectoryIterator($assetDirectoryFallback);

            foreach(new \RecursiveIteratorIterator($iterator) as $possibleFile) {
                if ($asset == $possibleFile->getFileName()) {
                    $fileLocation = $possibleFile->getPathname();
                    break 2;
                }
            }
        }

        return $fileLocation;
    }

    public function initializeCache($assets, $options = []) {
        $this->_cachedFileName = $this->_getCachedFileName($assets, $options);
        $this->_cachedFileInfo = new \SplFileInfo( $this->_helper->path([$this->_cachedFilePath, $this->_cachedFileName]) );

        foreach ($assets as $asset) {
            # Requesting an non-existent file searches fallback dirs
            $assetFilePath = $this->_helper->path([$this->_assetDirectory, $asset]);

            if ( file_exists( $assetFilePath ) ) {
                $this->_requestedFiles[] = new \SplFileObject($assetFilePath, "r");

            } else if ($assetFilePath = $this->_recursiveSearch($asset)) {
                $this->_requestedFiles[] = new \SplFileObject($assetFilePath, "r");
            }
            else {
                return false;
            }
        }

        return true;
    }

    public function isCached() {
        foreach ($this->_requestedFiles as $requestedFile) {
            if (! $this->_cachedFileInfo->isFile() || ( $requestedFile->getMTime() > $this->_cachedFileInfo->getMTime() )) {
                return false;
            }
        }

        return true;
    }

    public function fromCache() {
        return file_get_contents( $this->_cachedFileInfo->getPathname() );
    }

    public function concat() {
        $fileContent = "";

        foreach ($this->_requestedFiles as $requestedFile) {
            $fileContent .= file_get_contents($requestedFile->getPathname());
        }

        $this->cache($fileContent);

        return $this->_cachedFilePath . DIRECTORY_SEPARATOR . $this->_cachedFileName;
    }

    public function cache($fileContent) {
        $cachedFileObject  = new \SplFileObject($this->_helper->path([$this->_cachedFilePath, $this->_cachedFileName]), "w+");

        $cachedFileObject->fwrite($fileContent);

        return $fileContent;
    }

    public function setCacheDirectory($cacheDirectory) {
        $this->_cacheDirectory = $cacheDirectory;

        $this->_setCachedFilePath();
    }

    public function setAssetDirectory($assetDirectory, $fallbacks = []) {
        $this->_assetDirectory          = $assetDirectory;
        $this->_assetDirectoryFallbacks = $fallbacks;

        $this->_setCachedFilePath();
    }
}
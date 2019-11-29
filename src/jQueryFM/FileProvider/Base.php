<?php

/**
 * Represents a provider used by the file manager to explore and manipulate files and folders
 */
abstract class jQueryFM_FileProvider_Base
{

    /**
     * FileManager that uses this provider
     * @var jQueryFM_FileManager
     */
    public $manager;

    /**
     * Gets the files in the specified folder
     *
     * @param string $folder
     * @param string $filter
     *
     * @return FileManagerItem[]
     * @throws RuntimeException
     */
    public abstract function read($folder = '/', $filter = '*');

    /**
     * Save the uploaded file in the given folder.
     *
     * @param string $folder
     * @param string $name
     * @param string $upload_path
     *
     * @return FileManagerItem|FALSE
     * @throws RuntimeException
     */
    public abstract function create_file($folder, $name, $upload_path);

    /**
     * Create a new folder inside the given folder
     *
     * @param $folder
     * @param $name
     *
     * @return FileManagerItem|FALSE
     */
    public abstract function create_folder($folder, $name);

    /**
     * Rename the indicated file.
     *
     * @param FileManagerItem $file
     * @param                 $new_name
     *
     * @return FileManagerItem
     */
    public abstract function rename(FileManagerItem $file, $new_folder, $new_name);

    /**
     * Delete the given file.
     *
     * @param FileManagerItem $file
     *
     * @return boolean
     */
    public abstract function delete(FileManagerItem $file);

    /**
     * Send the given file to the browser.
     *
     * @param FileManagerItem $file
     * @param FileManagerItem $force Force download
     *
     * @return boolean
     */
    public abstract function download(FileManagerItem $file, $force = true);
}

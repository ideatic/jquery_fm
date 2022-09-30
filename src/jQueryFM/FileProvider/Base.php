<?php

/**
 * Represents a provider used by the file manager to explore and manipulate files and folders
 */
abstract class jQueryFM_FileProvider_Base
{

    /**
     * FileManager that uses this provider
     */
    public jQueryFM_FileManager $manager;

    /**
     * Gets the files in the specified folder
     *
     * @return FileManagerItem[]
     * @throws RuntimeException
     */
    public abstract function read(string $folder = '/', string $filter = '*'): array;

    /**
     * Save the uploaded file in the given folder.
     * @throws RuntimeException
     */
    public abstract function create_file(string $folder, string $name, string $upload_path): ?FileManagerItem;

    /**
     * Create a new folder inside the given folder
     */
    public abstract function create_folder(string $folder, string $name): ?FileManagerItem;

    /**
     * Rename the indicated file.
     */
    public abstract function rename(FileManagerItem $file, string $new_folder, string $new_name): FileManagerItem;

    /**
     * Delete the given file.
     */
    public abstract function delete(FileManagerItem $file): bool;

    /**
     * Send the given file to the browser.
     */
    public abstract function download(FileManagerItem $file, bool $force = true): bool;
}

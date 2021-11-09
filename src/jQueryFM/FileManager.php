<?php

/**
 * jQuery-based file manager
 */
class jQueryFM_FileManager
{

    /**
     * Provider used to list and manipulate files.
     * This is a read only property
     */
    public jQueryFM_FileProvider_Base $provider;

    /**
     * URL to the ajax endpoint
     */
    public string $ajax_endpoint;

    /**
     * URL to the static folder that contains file extension icons
     */
    public string $icons_url = 'static';

    /**
     * Maximum file size allowed to be uploaded (in bytes)
     */
    public int $max_size;

    /**
     * Allow users to upload new files
     */
    public bool $allow_upload = true;

    /**
     * Allow users to delete or rename existing files
     */
    public bool $allow_editing = true;

    /**
     * Allow users to create and explore folders
     */
    public bool $allow_folders = true;

    /**
     * The regular expression for allowed file types, matches against either file mime type or file name.
     * For example, for only allow images, you can use this regular expression: (\.|\/)(gif|jpe?g|png)$
     */
    public string $accept_file_types = '';

    /**
     * Maximum size, in bytes, that an image can have for show it as a preview. Defaults to 500 KB, 0 to disable image previewing, -1 means unlimited (all images will be previewed)
     * @warning This option can consume a lot of bandwidth
     */
    public int $image_preview_limit = 512000;

    /**
     * If TRUE, all files will be forced to be downloaded, if FALSE, the browser will try to show it before download them
     */
    public bool $force_downloads = false;

    /**
     * CSS selector for the DOM element that will be listen to drag events. Defaults to the file manager element.
     */
    public string $drag_selector = '';

    /**
     * Preload files with the initial config, reducing the number of HTTP requests required to initialize the manager
     */
    public bool $preload = true;

    /**
     * Allow file upload through copy and paste.
     * @warning Some desktop apps, like Excel, store copied data as images, causing this to upload that images wheb pasting text on the same page as the file explorer.
     */
    public bool $allow_paste_upload = false;

    public bool $debug = false;

    /**
     * Localizable strings
     */
    public array $strings = [
        'add_file'             => 'Add file',
        'download'             => 'Download',
        'delete'               => 'Delete',
        'rename'               => 'Rename',
        'confirm_delete'       => 'Are you sure you want to delete this file? The operation can not be undone',
        'prompt_newname'       => 'Please type the new file name:',
        'drop_files'           => 'Drop your files here!',
        'max_file_size'        => 'max % each file',
        'accept'               => 'Accept',
        'cancel'               => 'Cancel',
        'create_folder'        => 'Create folder',
        'create_folder_prompt' => 'Please type the new folder name:',
        'number_files'         => '% files',
        'home_folder'          => 'Home',
        'try_again'            => 'Try again',
        'uploading'            => 'Uploading...',
        'cancel_upload'        => 'Cancel upload',
        'unnamed'              => 'Unnamed',
        'error'                => 'Error processing the request',
        'error_filetype'       => 'File type not allowed',
        'error_maxsize'        => 'File is too large',
        'error_rename'         => 'Error renaming the file, please check that the new name is unique for the current folder and try again',
    ];

    /**
     * Initializes a new instance
     *
     * @param jQueryFM_FileProvider_Base|string $provider_or_path File provider used to navigate and perform the actions on the explorer, or string to path manipulated using jQueryFM_FileProvider_FS
     */
    public function __construct($provider_or_path)
    {
        $this->provider = is_string($provider_or_path) ? new jQueryFM_FileProvider_FS($provider_or_path) : $provider_or_path;
        $this->provider->manager = $this;

        $max_upload = $this->_php_unit(ini_get('upload_max_filesize'));
        $max_post = $this->_php_unit(ini_get('post_max_size'));
        $memory_limit = $this->_php_unit(ini_get('memory_limit'));
        $this->max_size = min(array_filter([$max_upload, $max_post, $memory_limit]));
    }

    /**
     * Gets the required settings to initialize the JS file manager
     * @return array
     */
    public function js_config(): array
    {
        $settings = [
            'allow_upload'           => $this->allow_upload,
            'allow_editing'          => $this->allow_editing,
            'allow_folders'          => $this->allow_folders,
            'allow_paste_upload'     => $this->allow_paste_upload,
            'max_file_size'          => $this->max_size,
            'max_file_size_readable' => jQueryFM_Helper::format_size($this->max_size),
            'accept_file_types'      => $this->accept_file_types,
            'ajax_endpoint'          => $this->ajax_endpoint,
            'icons_url'              => $this->icons_url,
            'drag_selector'          => $this->drag_selector,
            'force_downloads'        => $this->force_downloads,
            'strings'                => $this->strings
        ];

        if ($this->preload) {
            $files = [];
            foreach ($this->provider->read() as $f) {
                $files[] = $this->_export_file($f);
            }
            $settings['files'] = $files;
        }

        return $settings;
    }

    /**
     * Render the HTML and JS code necessary to initialize the current instance
     *
     * @param string $id ID of the element where the file manager will be placed
     *
     * @return string
     */
    public function render(string $id): string
    {
        // Initialize JS
        ob_start();
        ?>
        <script>
            jQuery('#<?= $id ?>').jquery_fm(<?= json_encode($this->js_config()) ?>);
        </script>
        <?php

        return ob_get_clean();
    }

    private function _export_file(FileManagerItem $file): array
    {
        $data = [
            'name'      => $file->name,
            'info'      => $file->info,
            'is_folder' => $file->is_folder,
        ];

        if (isset($file->icon)) {
            $data['icon'] = $file->icon;
        }

        if (!$file->allow_edit) {
            $data['allow_edit'] = $file->allow_edit;
        }
        if (isset($file->title)) {
            $data['title'] = $file->title;
        }

        return $data;
    }

    /**
     * Method executed as AJAX and HTTP endpoint
     *
     * @param bool $output_response
     *
     * @return array|bool
     */
    public function process_request(bool $output_response = true)
    {
        $response = ['status' => 'success'];

        try {
            $folder = $this->allow_folders && isset($_REQUEST['folder']) ? $_REQUEST['folder'] : '/';

            // Find file
            $file = null;
            if (isset($_REQUEST['file'])) {
                foreach ($this->provider->read($folder, $_REQUEST['file']) as $f) {
                    if ($f->name == $_REQUEST['file']) {
                        $file = $f;
                        break;
                    }
                }

                if (!$file) {
                    throw new FileManagerException('file_not_found');
                }
            }

            // Apply action
            $action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';
            switch ($action) {
                case 'upload':
                    if (!$this->allow_upload) {
                        throw new FileManagerException('unauthorized');
                    } elseif (empty($_FILES)) {
                        throw new FileManagerException('empty_upload');
                    }

                    foreach ($_FILES as $id => $info) {
                        // Check errors
                        foreach ($info['error'] as $index => $error) {
                            if ($error != UPLOAD_ERR_OK) {
                                throw new FileManagerException($error);
                            }
                        }

                        // Move files
                        foreach ($info['error'] as $index => $error) {
                            $created_file = $this->provider->create_file($folder, basename($info['name'][$index]), $info['tmp_name'][$index]);
                            if ($created_file) {
                                $response['file'] = $this->_export_file($created_file);
                            } else {
                                throw new FileManagerException('create');
                            }
                        }
                    }
                    break;

                case 'show':
                case 'download':
                    if ($this->provider->download($file, $action == 'download')) {
                        return true; // When download is successful, we cannot send more data
                    } else {
                        throw new FileManagerException('download');
                    }

                    break;

                case 'rename':
                    if (!$this->allow_editing || !$file->allow_edit) {
                        throw new FileManagerException('unauthorized');
                    }

                    $item = $this->provider->rename($file, isset($_REQUEST['destFolder']) ? $_REQUEST['destFolder'] : $folder, $_REQUEST['destName']);
                    if ($item) {
                        $response['file'] = $this->_export_file($item);
                    } else {
                        throw new FileManagerException('rename');
                    }

                    break;

                case 'delete':
                    if (!$this->allow_editing || !$file->allow_edit) {
                        throw new FileManagerException('unauthorized');
                    }

                    if (!$this->provider->delete($file)) {
                        throw new FileManagerException('delete');
                    }

                    break;

                case 'read':
                    if (!$this->allow_folders) {
                        throw new FileManagerException('unauthorized');
                    }

                    $response['files'] = [];
                    foreach ($this->provider->read($folder) as $file) {
                        $response['files'][] = $this->_export_file($file);
                    }

                    break;

                case 'create_folder':
                    if (!$this->allow_folders || !$this->allow_editing) {
                        throw new FileManagerException('unauthorized');
                    }

                    $item = $this->provider->create_folder($folder, $_REQUEST['name']);
                    if ($item) {
                        $response['file'] = $this->_export_file($item);
                    } else {
                        throw new FileManagerException('create_folder');
                    }

                    break;

                default:
                    throw new FileManagerException('invalid_action');
                    break;
            }
        } catch (FileManagerException $err) {
            $response['status'] = 'error';
            $response['message'] = 'error_' . $err->getMessage();

            if ($this->debug) {
                $response['error'] = $err->__toString();
            }
        } catch (Exception $err) {
            $response['status'] = 'error';
            $response['message'] = 'error';

            if ($this->debug) {
                $response['error'] = $err->__toString();
            }
        }

        if ($output_response) {
            if ($response['status'] == 'error') {
                header("HTTP/1.1 500");
            }

            header('Content-type: application/json');
            echo json_encode($response);

            return $response['status'] == 'success';
        } else {
            return $response;
        }
    }

    /* Helpers */

    private function _php_unit(string $val): int
    {
        $val = trim($val);
        $unit = '';

        if (!ctype_digit($val)) {
            $unit = strtolower($val[strlen($val) - 1]);
            $val = intval(substr($val, 0, -1));
        }

        switch ($unit) {
            // The 'G' modifier is available since PHP 5.1.0
            case 'g':
                $val *= (1024 * 1024 * 1024);
                break;

            case 'm':
                $val *= (1024 * 1024);
                break;

            case 'k':
                $val *= 1024;
                break;
        }

        return $val;
    }

}

class FileManagerItem
{

    /**
     * File name
     */
    public string $name;

    /**
     * Real file path
     * @var string|bool
     */
    public $path;

    /**
     * Relative folder to this file
     */
    public string $folder;

    /**
     * File size (if applicable)
     */
    public int $size;

    /**
     * File info to show (file size, number of files, etc.)
     * It can be an HTML string
     */
    public string $info;

    /**
     * The current file is a folder
     */
    public bool $is_folder = false;

    /**
     * Base64 version of the icon used to represent this file
     */
    public string $icon;

    /**
     * File title to show on mouse hover
     */
    public string $title;

    /**
     * Allow item editing (renaming, deleting, etc.)
     */
    public bool $allow_edit = true;
}

class FileManagerException extends Exception
{

}

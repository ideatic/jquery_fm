<?php
require 'file_manager.php';
$manager = new FileManager();
$manager->path = dirname(__FILE__) . '/samples/';
$manager->ajax_endpoint = '?isolated=true';

if (isset($_GET['isolated'])) {
    $manager->process_request();
    return;
}
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <title>jQuery File Manager</title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="description" content="">
        <meta name="author" content="">

        <!-- Le styles -->
        <link href="static/bootstrap/css/bootstrap.min.css" rel="stylesheet">
        <link href="static/bootstrap/css/bootstrap-responsive.min.css" rel="stylesheet">
        <link href="static/css/jquery_fm.css" rel="stylesheet">

        <style>
            body {
                padding-top: 60px; /* 60px to make the container go all the way to the bottom of the topbar */
            }
        </style>

    </head>

    <body>

        <div class="navbar navbar-fixed-top">
            <div class="navbar-inner">
                <div class="container">
                    <button type="button" class="btn btn-navbar" data-toggle="collapse" data-target=".nav-collapse">
                        <span class="icon-bar"></span>
                        <span class="icon-bar"></span>
                        <span class="icon-bar"></span>
                    </button>
                    <a class="brand" href="#">jQuery File Manager</a>
                    <div class="nav-collapse collapse">
                        <ul class="nav">
                            <li class="active"><a href="#">Demo</a></li>
                            <li><a href="#about">Download</a></li>
                        </ul>
                    </div><!--/.nav-collapse -->
                </div>
            </div>
        </div>

        <div class="container">

            <div class="page-header">
                <h1>jQuery File Manager <small>Demo</small></h1>
            </div>
            <blockquote>
                File Manager & upload widget with multiple file selection, drag&drop support, progress bars and preview images.
                Allow users to upload new files, and download, rename and delete the existing ones. Responsive design.
                Also works without Javascript (upload and download).
                Works with PHP or any other server-side platform that supports standard HTML form file uploads.
                i18n and l10n enabled.
            </blockquote>
            <h2>Full features</h2>
            <?php
            echo $manager->render();
            ?>
            <h2>Basic file explorer</h2>
            <?php
            $basic = clone $manager;
            $basic->allow_upload = FALSE;
            $basic->allow_folders = FALSE;
            echo $basic->render();
            ?>
        </div> <!-- /container -->

        <!-- Le javascript
        ================================================== -->
        <!-- Placed at the end of the document so the pages load faster -->
        <script src="//ajax.googleapis.com/ajax/libs/jquery/1.9.1/jquery.min.js"></script><script>window.jQuery || document.write('<script src="static/js/jquery.js"><\/script>')</script>
        <script src="static/bootstrap/js/bootstrap.min.js"></script>
        <script src="static/js/jquery_fm.js"></script>
    </body>
</html>

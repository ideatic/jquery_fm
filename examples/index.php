<?php
require '../vendor/autoload.php';

// Full featured file manager
$manager = new jQueryFM_FileManager(dirname(__FILE__) . '/testdir/');
$manager->ajax_endpoint = '?isolated=true';
$manager->icons_url = '../assets/img/fileicons';

// Basic file explorer
$basic = new jQueryFM_FileManager(dirname(__FILE__) . '/testdir/');
$basic->ajax_endpoint = '?isolated=true';
$basic->allow_editing = false;
$basic->allow_upload = false;
$basic->allow_folders = false;
$basic->icons_url = $manager->icons_url;

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

    <link href="../assets/css/jquery_fm.min.css" rel="stylesheet">

    <style>
        body {
            margin: 0;
            font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
            font-size: 14px;
            line-height: 20px;
            color: #333;
            background-color: #fff;
        }

        blockquote {
            padding: 0 0 0 15px;
            margin: 0 0 20px;
            border-left: 5px solid #eee;
        }

        .container {
            max-width: 1170px;
            margin-right: auto;
            margin-left: auto;
        }

        h1, h2, h3, h4, h5, h6 {
            margin: 10px 0;
            font-family: inherit;
            font-weight: bold;
            line-height: 20px;
            color: inherit;
            text-rendering: optimizelegibility;
        }

        h1, h2, h3 {
            line-height: 40px;
        }

        h1 {
            font-size: 38.5px;
        }

        h2 {
            font-size: 31.5px;
        }

        .page-header {
            padding-bottom: 9px;
            margin: 20px 0 30px;
            border-bottom: 1px solid #eee;
        }
    </style>

</head>

<body>

<div class="container">

    <div class="page-header">
        <h1>jQuery File Manager</h1>
    </div>
    <blockquote>
        File Manager & upload widget with multiple file selection, drag&drop support, progress bars and preview images.
        Allow users to upload new files, and download, rename and delete the existing ones. Responsive design.
        Works with PHP or any other server-side platform that supports standard HTML form file uploads.
        i18n and l10n enabled.
    </blockquote>
    <h2>Full featured</h2>

    <div id="full"></div>
    
    <h2>Basic file explorer</h2>

    <div id="basic"></div>
</div>

<script src="//ajax.googleapis.com/ajax/libs/jquery/2.1.0/jquery.min.js"></script>
<script>window.jQuery || document.write('<script src="static/js/jquery.js"><\/script>')</script>
<!-- Only if we want fancy modal windows for prompt and confirm -->
<script src="../assets/js/modal.min.js"></script>
<script src="../assets/js/jquery_fm.min.js"></script>
<?php
echo $manager->render('full');
echo $basic->render('basic');
?>
</body>
</html>

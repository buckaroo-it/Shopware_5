<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="{link file="backend/_resources/css/bootstrap.min.css"}">
    
    {block name="content/header_tags"}{/block}
</head>
<body role="document" style="padding-top: 20px">

<!-- Fixed navbar -->
{*<nav class="navbar navbar-inverse navbar-fixed-top">*}
{*{block name="content/navigation"}{/block}*}
{*</nav>*}
{*<nav class="navbar navbar-inverse navbar-fixed-top">*}
    {*{block name="content/navigation"}{/block}*}
{*</nav>*}

<div class="container theme-showcase" role="main">
    {block name="content/main"}{/block}
</div> <!-- /container -->

<script type="text/javascript" src="{link file="backend/base/frame/postmessage-api.js"}"></script>
<script type="text/javascript" src="{link file="backend/_resources/js/jquery-2.2.4.min.js"}"></script>
<script type="text/javascript" src="{link file="backend/_resources/js/bootstrap.min.js"}"></script>
{block name="content/javascript"}{/block}
</body>
</html>

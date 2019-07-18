<?php
$package->noCache();
 ?>

<ul>
<?php
$actions = $cms->helper('actions')->other('_importexport');
foreach ($actions as $url) {
    if ($url = $cms->helper('urls')->parse($url)) {
        echo "<li>".$url->html()."</li>";
    }
}
 ?>
</ul>

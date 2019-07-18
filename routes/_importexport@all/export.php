<?php
$package->noCache();
$f = $cms->helper('forms');

$nounForm = $f->form('', 'noun');
$nounForm->csrf(false);
$nounForm['noun'] = $f->field('noun', 'Search for a noun to export');
$nounForm['noun']->required();
$nounForm['noun']->default($package['url.args.noun']);
$nounForm['depth'] = $f->field('Formward\\Fields\\Number', 'Include children to depth');
$nounForm['depth']->addTip('Enter "-1" to include all children (may take a long time)');
$nounForm['depth']->addTip('Enter "0" to include no children');
$nounForm['depth']->default(-1);
$nounForm['depth']->required();

ini_set('max_execution_time', 0);

if ($nounForm->handle()) {
    $output = new \Flatrr\FlatArray();
    $output['digraph_export'] = $nounForm->value();
    //build list of all the nouns we need to include
    $nouns = [$cms->read($nounForm['noun']->value())];
    $nouns = $nouns + $cms->helper('graph')->children($nounForm['noun']->value(), null, $nounForm['depth']->value());
    $output['nouns'] = array_values($nouns);
    $output['digraph_export.results'] = count($output['nouns']);
    //see if helpers have hook_export method, and call it if they do
    foreach ($cms->allHelpers() as $name) {
        if (method_exists($cms->helper($name), 'hook_export')) {
            $output['helper.'.$name] = $cms->helper($name)->hook_export($output);
        }
    }
    //output value
    $output['nouns'] = array_map(
        function ($e) {
            return $e->get();
        },
        $output['nouns']
    );
    $package->makeMediaFile($nounForm['noun']->value().'_'.$nounForm['depth']->value().'.json');
    $package->binaryContent(json_encode($output->get()));
    // $package['response.disposition'] = 'attachment';
} else {
    echo $nounForm;
}
